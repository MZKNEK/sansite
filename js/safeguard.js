// The Safeguard's register: the easter eggs of the site a visitor has found
// (inc/auth/finds.php, the card in /account/). A find is kept in the browser
// first, so it counts without a login too, and goes to the account through
// account.php?finds once there is one: at once, or on the next page that shows
// the account. The other scripts note a find with SanakanSafeguard.found(key).
//
// On every page, and finds themselves: the Safeguard's word to whoever opens
// the browser's console, the Konami code that lets a Silicon Creature into the
// page, and the service worker (sw.js) whose offline page is one of the finds.
// Not finds, as they come once a year: on 31 October Silicon Creatures scurry
// along the edges of the HUD (and js/home.js sends another motto on 1 April).
window.SanakanSafeguard = (function () {
  // the site's root, from this script's own address (js/safeguard.js)
  var ROOT = new URL('../', document.currentScript.src).pathname;
  var STORE = 'safeguard';
  // the same keys as SAFEGUARD_FINDS in inc/auth/finds.php, with their names
  var FINDS = {
    netsphere: 'Nielegalny mieszkaniec',
    gbe: 'Strzał z Emitera',
    lost: 'Zgubiony w Megastrukturze',
    console: 'Intruz w kodzie',
    silicon: 'Forma krzemowa',
    header: 'Podsłuch',
    lore: 'Kronikarz',
    offline: 'Odcięty od sieci',
    morse: 'Sygnał zwrotny',
    idle: 'Czuwanie',
    regen: 'Regeneracja'
  };
  var TOTAL = Object.keys(FINDS).length;
  // the cache sw.js leaves when it has shown the offline page
  var OFFLINE_SEEN = 'sanakan-offline-seen';

  function still() {
    return window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function typing(target) {
    return target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName));
  }

  // ---- The register ---------------------------------------------------------

  // what this browser knows: its finds, key => unix time, and which of them
  // the account already has (sent); localStorage may be off, then nothing stays
  function load() {
    var state = null;
    try {
      state = JSON.parse(localStorage.getItem(STORE));
    } catch (err) {
      // private mode or a broken entry: start empty
    }
    if (!state || typeof state !== 'object') state = {};
    if (!state.finds || typeof state.finds !== 'object') state.finds = {};
    if (!state.sent || typeof state.sent !== 'object') state.sent = {};
    return state;
  }

  function save(state) {
    try {
      localStorage.setItem(STORE, JSON.stringify(state));
    } catch (err) {
      // nothing kept, the find still counts on the account
    }
  }

  function pending(state) {
    return Object.keys(state.finds).filter(function (key) { return !state.sent[key]; });
  }

  function loggedIn() {
    return !!document.querySelector('.account-menu');
  }

  var toastTimer;

  // "Nowy wpis" in the corner, a link to the register once there is an account
  function toast(key, count) {
    var old = document.querySelector('.sg-toast');
    if (old) old.remove();
    clearTimeout(toastTimer);

    var box = document.createElement(loggedIn() ? 'a' : 'div');
    box.className = 'sg-toast hud-corners';
    box.setAttribute('role', 'status');
    if (box.tagName === 'A') box.href = ROOT + 'account/#rejestr';
    var head = document.createElement('b');
    head.textContent = 'Rejestr Safeguarda · nowy wpis';
    var name = document.createElement('span');
    name.textContent = FINDS[key];
    var note = document.createElement('small');
    note.textContent = count + ' z ' + TOTAL + (loggedIn() ? ' · rejestr w profilu' : ' · zapisane w tej przeglądarce, po zalogowaniu trafi do profilu');
    box.append(head, name, note);
    document.body.appendChild(box);
    toastTimer = setTimeout(function () { box.remove(); }, 6000);
  }

  // Notes a find; the first time it shows the toast and goes to the account.
  function found(key) {
    if (!FINDS[key]) return;
    var state = load();
    if (!state.finds[key]) {
      state.finds[key] = Math.floor(Date.now() / 1000);
      save(state);
      toast(key, Object.keys(state.finds).length);
    }
    if (!state.sent[key]) sync();
  }

  var syncing = false;
  var again = false;

  function json(res) {
    return res.ok || res.status === 403 || res.status === 503 ? res.json() : null;
  }

  // Asks account.php for the account's finds; with an account, it takes those
  // into the browser and sends the ones it lacks. The profile shows them only
  // after a reload, so its register is reloaded once when it gained some.
  function sync() {
    if (syncing) {
      again = true;
      return;
    }
    syncing = true;

    var register = document.querySelector('[data-safeguard-register]');
    var shown = register ? (register.getAttribute('data-found') || '').split(' ') : [];

    function take(state, finds) {
      Object.keys(finds || {}).forEach(function (key) {
        if (!FINDS[key]) return;
        state.sent[key] = 1;
        if (!state.finds[key] || finds[key] < state.finds[key]) state.finds[key] = finds[key];
      });
    }

    fetch(ROOT + 'account.php?finds', { credentials: 'same-origin', cache: 'no-store' })
      .then(json)
      .then(function (me) {
        if (!me || !me.login) return null;
        var state = load();
        take(state, me.finds);
        var send = {};
        pending(state).forEach(function (key) { send[key] = state.finds[key]; });
        save(state);
        if (!Object.keys(send).length) return null;

        var body = new FormData();
        body.append('action', 'finds');
        body.append('csrf', me.csrf);
        body.append('found', JSON.stringify(send));
        return fetch(ROOT + 'account.php', { method: 'POST', body: body, credentials: 'same-origin' })
          .then(json)
          .then(function (answer) {
            if (!answer || !answer.finds) return;
            var state = load();
            take(state, answer.finds);
            save(state);
            if (register && Object.keys(answer.finds).some(function (key) { return shown.indexOf(key) < 0; }))
              location.reload();
          });
      })
      .catch(function () {
        // no answer: the finds wait in the browser for the next page
      })
      .then(function () {
        syncing = false;
        if (again) {
          again = false;
          sync();
        }
      });
  }

  // a page that shows the account sends what waits; the static pages get it
  // later from account.php (js/account.js), which says so with an event
  function syncWaiting() {
    if (pending(load()).length && loggedIn()) sync();
  }
  syncWaiting();
  document.addEventListener('sanakan:account', syncWaiting);

  // ---- The console ------------------------------------------------------------

  if (window.console && console.log) {
    console.log('%c SAFEGUARD · LV.9 ', 'font: bold 13px monospace; letter-spacing: 0.3em; color: #fff; background: #9b59b6; padding: 4px 8px;');
    console.log('%cWykryto próbę dostępu do kodu źródłowego.\nGen terminala sieciowego: NIE WYKRYTO.\nKlasyfikacja: nielegalny mieszkaniec.\n\nZgłoś się Safeguardowi: safeguard()',
      'font: 12px monospace; line-height: 1.6; color: #b670d3;');
  }

  window.safeguard = function () {
    found('console');
    console.log('%cZgłoszenie przyjęte. Wpis w rejestrze: Intruz w kodzie.\nSafeguard nie zapomina. Nie ruszaj się.', 'font: 12px monospace; line-height: 1.6; color: #e86262;');
  };

  // ---- The Konami code: a Silicon Creature gets into the page ------------------

  var KONAMI = 'arrowup arrowup arrowdown arrowdown arrowleft arrowright arrowleft arrowright b a';
  // on a phone: the arrows as swipes, B and A as two taps
  var SWIPES = 'arrowup arrowup arrowdown arrowdown arrowleft arrowright arrowleft arrowright tap tap';
  var SWIPE = 40;   // px a finger moves for a swipe
  var keys = [];
  var touches = [];
  var infected = false;

  document.addEventListener('keydown', function (e) {
    if (!e.key || e.ctrlKey || e.metaKey || e.altKey || typing(e.target)) return;
    keys = keys.concat(e.key.toLowerCase()).slice(-10);
    if (keys.join(' ') === KONAMI) {
      keys = [];
      infect();
    }
  });

  var start = null;
  document.addEventListener('touchstart', function (e) {
    start = e.touches.length === 1 ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null;
  }, { passive: true });
  document.addEventListener('touchend', function (e) {
    if (!start || !e.changedTouches.length) return;
    var dx = e.changedTouches[0].clientX - start.x;
    var dy = e.changedTouches[0].clientY - start.y;
    start = null;
    var move = Math.abs(dx) < SWIPE && Math.abs(dy) < SWIPE ? 'tap'
      : Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'arrowright' : 'arrowleft')
      : (dy > 0 ? 'arrowdown' : 'arrowup');
    // a swipe up moves the finger up, as the arrow
    touches = touches.concat(move).slice(-10);
    if (touches.join(' ') === SWIPES) {
      touches = [];
      infect();
    }
  }, { passive: true });

  var GLYPHS = '░▒▓█▚▞▙▟◢◣◤◥01#%&@$';

  // The page goes green and shakes, its titles crumble into glyphs and the
  // infection climbs to 100%; then the Safeguard sweeps it clean.
  function infect() {
    if (infected || document.body.classList.contains('ns-open')) return;
    infected = true;
    var calm = still();
    var root = document.documentElement;

    var box = document.createElement('div');
    box.className = 'sg-silicon hud-corners';
    box.setAttribute('role', 'alert');
    var title = document.createElement('b');
    title.textContent = 'Wykryto formę krzemową';
    var level = document.createElement('span');
    var bar = document.createElement('i');
    bar.className = 'sg-silicon-bar';
    var fill = document.createElement('i');
    bar.appendChild(fill);
    box.append(title, level, bar);
    document.body.appendChild(box);
    root.classList.add('silicon');

    // the titles of the page, only those that are plain text
    var titles = Array.prototype.filter.call(document.querySelectorAll('.hud-title, h2'), function (el) {
      return !el.children.length && el.textContent.trim() && !box.contains(el);
    }).slice(0, 24);
    var texts = titles.map(function (el) { return el.textContent; });

    function scramble(share) {
      titles.forEach(function (el, i) {
        el.textContent = texts[i].replace(/\S/g, function (ch) {
          return Math.random() < share ? GLYPHS[Math.floor(Math.random() * GLYPHS.length)] : ch;
        });
      });
    }

    var percent = 0;
    var climb = setInterval(function () {
      percent = Math.min(100, percent + 3 + Math.floor(Math.random() * 6));
      level.textContent = 'infekcja strony: ' + percent + '%';
      fill.style.width = percent + '%';
      if (!calm) scramble(percent / 160);
      if (percent >= 100) {
        clearInterval(climb);
        clean();
      }
    }, 110);

    function clean() {
      box.classList.add('clean');
      title.textContent = 'Safeguard · dezynfekcja';
      level.textContent = 'usuwanie formy krzemowej…';
      var sweep = document.createElement('div');
      sweep.className = 'sg-sweep';
      document.body.appendChild(sweep);

      setTimeout(function () {
        root.classList.remove('silicon');
        titles.forEach(function (el, i) { el.textContent = texts[i]; });
        level.textContent = 'Strona oczyszczona. Zagrożenie zneutralizowane.';
        fill.style.width = '0';
      }, calm ? 600 : 1100);

      setTimeout(function () {
        sweep.remove();
        box.remove();
        infected = false;
        found('silicon');
      }, calm ? 2200 : 2800);
    }
  }

  // ---- The days of the year ------------------------------------------------------

  // today's month and day; localStorage "safeguard-date" set to "MM-DD" makes
  // the site pretend another day, to try out the days below
  function today() {
    var fake = null;
    try {
      fake = /^(\d\d)-(\d\d)$/.exec(localStorage.getItem('safeguard-date') || '');
    } catch (err) {
      // no storage, no pretending
    }
    if (fake) return { month: +fake[1], day: +fake[2] };
    var now = new Date();
    return { month: now.getMonth() + 1, day: now.getDate() };
  }

  // Halloween: now and then a Silicon Creature runs along an edge of a HUD box
  // in view (or of the window), stops for a moment halfway and runs on
  var CRAWLER = '<svg viewBox="0 0 28 16" aria-hidden="true">'
    + '<g class="legs"><path d="M9 9 4 14M12 9l-2 6M16 9l2 6M19 9l5 5M9 7 3 3M19 7l6-4" /></g>'
    + '<ellipse cx="14" cy="8" rx="7" ry="3.6" /><circle cx="22.5" cy="7.5" r="2.6" />'
    + '<circle class="eye" cx="23.4" cy="6.8" r="0.9" /></svg>';

  function crawl() {
    var w = document.documentElement.clientWidth, h = innerHeight;
    var boxes = Array.prototype.map.call(document.querySelectorAll('.hud-corners'), function (el) {
      return el.getBoundingClientRect();
    }).filter(function (r) {
      return r.width > 60 && r.height > 24 && r.top > 0 && r.bottom < h && r.left >= 0 && r.right <= w;
    });
    var r = boxes.length && Math.random() < 0.85
      ? boxes[Math.floor(Math.random() * boxes.length)]
      : { left: 2, top: 2, right: w - 2, bottom: h - 2 };

    // an edge, walked one way or the other, with the creature's feet on it
    var edges = [
      [r.left, r.top, r.right, r.top],
      [r.right, r.top, r.right, r.bottom],
      [r.right, r.bottom, r.left, r.bottom],
      [r.left, r.bottom, r.left, r.top]
    ];
    var e = edges[Math.floor(Math.random() * 4)];
    if (Math.random() < 0.5) e = [e[2], e[3], e[0], e[1]];
    var angle = Math.atan2(e[3] - e[1], e[2] - e[0]) * 180 / Math.PI;
    var length = Math.hypot(e[2] - e[0], e[3] - e[1]);
    var mid = [(e[0] + e[2]) / 2, (e[1] + e[3]) / 2];
    function at(x, y) { return 'translate(' + (x - 14) + 'px, ' + (y - 14) + 'px) rotate(' + angle + 'deg)'; }

    var bug = document.createElement('div');
    bug.className = 'sg-crawler';
    bug.innerHTML = CRAWLER;
    document.body.appendChild(bug);
    var move = bug.animate([
      { transform: at(e[0], e[1]), opacity: 0 },
      { transform: at(e[0], e[1]), opacity: 1, offset: 0.05 },
      { transform: at(mid[0], mid[1]), offset: 0.45 },
      { transform: at(mid[0], mid[1]), offset: 0.6 },
      { transform: at(e[2], e[3]), opacity: 1, offset: 0.95 },
      { transform: at(e[2], e[3]), opacity: 0 }
    ], { duration: 1600 + length * 4, easing: 'ease-in-out' });
    move.onfinish = function () { bug.remove(); };
  }

  function halloween() {
    if (still() || !document.body.animate) return;
    (function next() {
      setTimeout(function () {
        if (!document.hidden) crawl();
        next();
      }, 2500 + Math.random() * 5000);
    })();
  }

  var day = today();
  if (day.month === 10 && day.day === 31) halloween();

  // ---- Offline: the service worker and its page --------------------------------

  // sw.js shows offline.html when a page cannot be reached and leaves a cache
  // behind to say so; the next page that loads notes the find
  if (window.caches && caches.has) {
    caches.has(OFFLINE_SEEN).then(function (seen) {
      if (!seen) return;
      caches.delete(OFFLINE_SEEN);
      found('offline');
    }).catch(function () {});
  }

  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(ROOT + 'sw.js').catch(function () {
        // no offline page, the site works as before
      });
    });
  }

  // found(key) notes a find, names its names, finds() what this browser found
  // and today() the day of the year (maybe a pretended one)
  return {
    found: found,
    names: FINDS,
    finds: function () { return load().finds; },
    today: today
  };
})();
