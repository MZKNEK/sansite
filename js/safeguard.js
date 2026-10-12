// The Safeguard's register: the easter eggs of the site a visitor has found
// (inc/auth/finds.php, the card in /account/). A find is kept in the browser
// first, so it counts without a login too, and goes to the account through
// account.php?finds once there is one: at once, or on the next page that shows
// the account. The other scripts note a find with SanakanSafeguard.found(key).
//
// On every page, and finds themselves: the Safeguard's word to whoever opens
// the browser's console, the Konami code that lets a Silicon Creature into the
// page, and the service worker (sw.js) whose offline page is one of the finds
// (as is the network lost and back while a page is open).
// Once a year: on 31 October Silicon Creatures scurry along the edges of the
// HUD, at Christmas and through the winter snow falls (unless turned off in
// the profile) and at Easter an egg hides on every page, five
// of them a seasonal find, apart from the count (js/home.js adds the mottos
// of the holidays and of 1 April, Sanakan's Christmas hat and the cake of the
// bot's birthday, 13 October, another seasonal find).
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
  // the same as SAFEGUARD_SEASONAL: the finds of a time of the year, apart
  // from the count, as the rest of the year nobody can make them
  var SEASONAL = {
    xmas: 'Święta w Megastrukturze',
    easter: 'Pisanki z Megastruktury',
    birthday: 'Sto lat'
  };
  var NAMES = {};
  [FINDS, SEASONAL].forEach(function (list) {
    Object.keys(list).forEach(function (key) { NAMES[key] = list[key]; });
  });
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

  // a note in the corner, a link when href is given
  function notice(title, text, small, href) {
    var old = document.querySelector('.sg-toast');
    if (old) old.remove();
    clearTimeout(toastTimer);

    var box = document.createElement(href ? 'a' : 'div');
    box.className = 'sg-toast hud-corners';
    box.setAttribute('role', 'status');
    if (href) box.href = href;
    var head = document.createElement('b');
    head.textContent = title;
    var name = document.createElement('span');
    name.textContent = text;
    var note = document.createElement('small');
    note.textContent = small;
    box.append(head, name, note);
    document.body.appendChild(box);
    toastTimer = setTimeout(function () { box.remove(); }, 6000);
  }

  // "Nowy wpis", a link to the register once there is an account
  function toast(key, state) {
    var count = Object.keys(state.finds).filter(function (found) { return FINDS[found]; }).length;
    notice('Rejestr Safeguarda · nowy wpis', NAMES[key],
      (SEASONAL[key] ? 'wpis sezonowy' : count + ' z ' + TOTAL)
        + (loggedIn() ? ' · rejestr w profilu' : ' · zapisane w tej przeglądarce, po zalogowaniu trafi do profilu'),
      loggedIn() ? ROOT + 'account/#rejestr' : null);
  }

  // Notes a find; the first time it shows the toast and goes to the account.
  function found(key) {
    if (!NAMES[key]) return;
    var state = load();
    if (!state.finds[key]) {
      state.finds[key] = Math.floor(Date.now() / 1000);
      save(state);
      toast(key, state);
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
        if (!NAMES[key]) return;
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

  // today's year, month and day; localStorage "safeguard-date" set to "MM-DD"
  // (this year) or "YYYY-MM-DD" makes the site pretend another day, to try
  // out the days below
  function today() {
    var now = new Date();
    var fake = null;
    try {
      fake = /^(?:(\d{4})-)?(\d\d)-(\d\d)$/.exec(localStorage.getItem('safeguard-date') || '');
    } catch (err) {
      // no storage, no pretending
    }
    if (fake) return { year: fake[1] ? +fake[1] : now.getFullYear(), month: +fake[2], day: +fake[3] };
    return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate() };
  }

  // Easter Sunday of a year (the Gregorian computus), as a Date at midnight
  function easterSunday(year) {
    var a = year % 19, b = Math.floor(year / 100), c = year % 100;
    var d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25);
    var g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30;
    var i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7;
    var m = Math.floor((a + 11 * h + 22 * l) / 451);
    var month = Math.floor((h + l - 7 * m + 114) / 31), day = (h + l - 7 * m + 114) % 31 + 1;
    return new Date(year, month - 1, day);
  }

  // The holidays of the moment: "xmas" from 20 December to Epiphany (6
  // January), "easter" from Palm Sunday to Easter Monday, "birthday" on the
  // bot's, 13 October (js/home.js), otherwise null.
  function season() {
    var d = today();
    if (d.month === 10 && d.day === 13) return 'birthday';
    if ((d.month === 12 && d.day >= 20) || (d.month === 1 && d.day <= 6)) return 'xmas';
    var date = new Date(d.year, d.month - 1, d.day);
    var easter = easterSunday(d.year);
    var days = Math.round((date - easter) / 86400000);
    if (days >= -7 && days <= 1) return 'easter';
    return null;
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

  // Snow of glyphs falling over the page, behind its dialogs: at Christmas and
  // through the calendar winter, from 20 December to 20 March, unless the
  // account turned it off in its profile (the cookie "snow", inc/auth/preferences.php)
  function snowing() {
    var d = today();
    return (d.month === 12 && d.day >= 20) || d.month <= 2 || (d.month === 3 && d.day <= 20);
  }

  function snowOff() {
    return /(?:^|; )snow=off(?:;|$)/.test(document.cookie);
  }

  var snowLayer = null;

  function snow() {
    if (still()) return;
    if (snowLayer) {
      snowLayer.hidden = false;
      return;
    }
    var GLYPHS = ['*', '·', '+', '❄', '*', '·'];
    var layer = document.createElement('div');
    layer.className = 'sg-snow';
    layer.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < 28; i++) {
      var flake = document.createElement('i');
      flake.textContent = GLYPHS[i % GLYPHS.length];
      flake.style.left = (Math.random() * 100) + '%';
      flake.style.fontSize = (9 + Math.random() * 9).toFixed(1) + 'px';
      flake.style.opacity = (0.25 + Math.random() * 0.45).toFixed(2);
      // falling and swaying, each at its own pace, already under way
      flake.style.animationDuration = (9 + Math.random() * 10).toFixed(1) + 's, ' + (2.5 + Math.random() * 3).toFixed(1) + 's';
      flake.style.animationDelay = (-Math.random() * 19).toFixed(1) + 's, ' + (-Math.random() * 5).toFixed(1) + 's';
      layer.appendChild(flake);
    }
    document.body.appendChild(layer);
    snowLayer = layer;
  }

  // the switch in the profile shows only while it snows, and shows at once
  // what it does (saved with the form)
  function snowSwitch() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-snow-pref]'), function (field) {
      field.hidden = false;
      var box = field.querySelector('input');
      box.addEventListener('change', function () {
        if (box.checked) snow();
        else if (snowLayer) snowLayer.hidden = true;
      });
    });
  }

  // Easter: an egg sits on the top edge of one of the page's HUD boxes until
  // it is clicked and breaks; eggs from five pages are a find. The pages are
  // kept for the year, so each page has one egg a year. Only the pages anyone
  // may open have one (and the 404 page, once, whatever the address), so a
  // visitor without a login or rights finds them all the same.
  var EGG_PAGES = 5;
  var EGG_PLACES = ['', 'cmd/', 'cmd/changes/', 'state/', 'state/versions/', 'privacy/'];
  var EGG_STORE = 'safeguard-easter';
  var EGG_COLORS = ['#9b59b6', '#4fd1c5', '#e86262', '#f0a35e', '#5fd38d', '#e8d36a'];
  var EGG = '<svg viewBox="0 0 20 26" aria-hidden="true">'
    + '<path class="shell" d="M10 1C5 1 1 9 1 15.5 1 21 5 25 10 25s9-4 9-9.5C19 9 15 1 10 1Z" />'
    + '<path class="band" d="M1.6 13 4.5 10.5 7.5 13 10 10.5 12.5 13 15.5 10.5 18.4 13" />'
    + '<circle class="dot" cx="6" cy="18.5" r="1.3" /><circle class="dot" cx="10" cy="20.3" r="1.3" />'
    + '<circle class="dot" cx="14" cy="18.5" r="1.3" /><circle class="dot" cx="10" cy="6.5" r="1.1" /></svg>';

  function eggHunt() {
    var year = today().year;
    var got = { year: year, pages: [] };
    try {
      var kept = JSON.parse(localStorage.getItem(EGG_STORE));
      if (kept && kept.year === year && Array.isArray(kept.pages)) got = kept;
    } catch (err) {
      // no storage: the eggs of this visit only
    }
    var page = location.pathname.replace(/index\.(?:php|html)$/, '');
    if (document.querySelector('.not-found'))
      page = '404';
    else if (page.indexOf(ROOT) !== 0 || EGG_PLACES.indexOf(page.slice(ROOT.length)) < 0)
      return;
    if (got.pages.indexOf(page) >= 0) return;

    // once the page has settled; the boxes of dialogs and fixed buttons are left out
    setTimeout(function () {
      var boxes = Array.prototype.filter.call(document.querySelectorAll('.hud-corners'), function (el) {
        var r = el.getBoundingClientRect();
        return r.width > 80 && r.height > 20 && !el.closest('.sg-toast, .ns-overlay, .pl-overlay, .account-drop, .to-top');
      });
      if (!boxes.length) return;
      var box = boxes[Math.floor(Math.random() * boxes.length)];
      var along = Math.random();

      var egg = document.createElement('button');
      egg.type = 'button';
      egg.className = 'sg-egg';
      egg.title = 'Pisanka';
      egg.setAttribute('aria-label', 'Pisanka');
      egg.style.setProperty('--egg', EGG_COLORS[Math.floor(Math.random() * EGG_COLORS.length)]);
      egg.innerHTML = EGG;
      document.body.appendChild(egg);

      // on the top edge of its box, where it is now (the page may move)
      function place() {
        var r = box.getBoundingClientRect();
        egg.style.left = (r.left + scrollX + 10 + along * Math.max(0, r.width - 36)) + 'px';
        egg.style.top = (r.top + scrollY - 25) + 'px';
      }
      place();
      window.addEventListener('resize', place);

      egg.addEventListener('click', function () {
        egg.disabled = true;
        egg.classList.add('broken');
        window.removeEventListener('resize', place);
        setTimeout(function () { egg.remove(); }, 900);
        got.pages.push(page);
        try {
          localStorage.setItem(EGG_STORE, JSON.stringify(got));
        } catch (err) {
          // counted for this visit only
        }
        var count = got.pages.length;
        if (count === EGG_PAGES) found('easter');
        else if (count < EGG_PAGES) notice('Pisanka z Megastruktury', count + ' z ' + EGG_PAGES, 'Następna czeka na innej stronie.', null);
        else notice('Pisanka z Megastruktury', count + ' zebranych', 'Wpis w rejestrze już jest. Safeguard docenia zapał.', null);
      });
    }, 1200);
  }

  var day = today();
  if (day.month === 10 && day.day === 31) halloween();
  if (snowing()) {
    snowSwitch();
    if (!snowOff()) snow();
  }
  if (season() === 'easter') eggHunt();

  // ---- Offline: the service worker and its page --------------------------------

  // A page open while the browser loses the network and gets it back is the
  // find too: a browser set to work offline (Firefox) may answer from its own
  // cache or show its own error, and never ask sw.js for the offline page.
  var cutOff = false;
  window.addEventListener('offline', function () { cutOff = true; });
  window.addEventListener('online', function () {
    if (!cutOff) return;
    cutOff = false;
    found('offline');
  });

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
  // today() the day of the year (maybe a pretended one) and season() the
  // holidays of the moment
  return {
    found: found,
    names: FINDS,
    finds: function () { return load().finds; },
    today: today,
    season: season
  };
})();
