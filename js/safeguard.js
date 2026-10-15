// The Safeguard's register: the easter eggs of the site a visitor has found
// (inc/auth/finds.php, the card in /account/). A find stays in memory until
// saving is enabled. It then persists in this browser and goes to the account through
// account.php?finds once there is one: at once, or on the next page that shows
// the account. Every find needs a proof from the server (megastructure.php),
// which the browser keeps with it and the account asks for; the other scripts
// ask for one with SanakanSafeguard.witness(key), or note a find whose answer
// carried it with SanakanSafeguard.found(key, proof).
//
// On every page, and finds themselves: the Safeguard's word to whoever opens
// the browser's console, the Konami code that lets a Silicon Creature into the
// page, and the service worker (sw.js) whose offline page is one of the finds
// (as is the network lost and back while a page is open).
// In their time of the year: on 31 October Silicon Creatures scurry along the
// edges of the page, one of them caught a seasonal find; at Christmas and
// through the winter snow falls (unless turned off in the profile); on 31
// December fireworks go off at midnight; at Easter an egg hides on every page,
// five of them a seasonal find; on a Friday the 13th the titles glitch, one
// caught a seasonal find too. Those are apart from the count. js/home.js adds
// the mottos of the holidays, Sanakan's Christmas hat, the New Year's rocket,
// the Japanese home page of 1 April and the cake of the bot's birthday, 13 October,
// each another seasonal find.
window.SanakanSafeguard = (function () {
  // the site's root, from this script's own address (js/safeguard.js)
  var ROOT = new URL('../', document.currentScript.src).pathname;
  var STORE = 'safeguard';
  var storage = window.SanakanDiscoveryStorage;
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
    pingpong: 'Ping pong z Safeguardem',
    idle: 'Czuwanie',
    regen: 'Regeneracja',
    unknown: 'Tożsamość nieznana',
    denied: 'Pod zamkniętymi drzwiami',
    snake: 'Coś pełza w maszynerii',
    builders: 'Godzina Budowniczych',
    floor0: 'Piętro zero',
    maze: 'Labirynt piętra zero',
    paper: 'Ślad na papierze',
    gene: 'Gen terminala sieciowego',
    admin: 'Administrator Netsfery',
    killy: 'Spotkanie z Killym'
  };
  var TOTAL = Object.keys(FINDS).length;
  // the same as SAFEGUARD_SEASONAL: the finds of a time of the year, apart
  // from the count, as the rest of the year nobody can make them
  var SEASONAL = {
    xmas: 'Święta w Megastrukturze',
    newyear: 'Nowy cykl',
    easter: 'Pisanki z Megastruktury',
    aprilfools: 'Zagubiony w tłumaczeniu',
    birthday: 'Sto lat',
    halloween: 'Noc form krzemowych',
    friday13: 'Piątek trzynastego',
    sakura: 'Sakura',
    perseids: 'Perseidy',
    fireflies: 'Światło w Megastrukturze'
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

  // what this browser knows: its finds, key => unix time, which of them the
  // account already has (sent), their first proofs (key => proof), and later
  // proofs waiting in case the first is rejected (retryProofs, key => array).
  // localStorage may be off, then nothing stays
  function load() {
    var state = null;
    try {
      state = JSON.parse(storage.getItem(STORE));
    } catch (err) {
      // private mode or a broken entry: start empty
    }
    if (!state || typeof state !== 'object') state = {};
    if (!state.finds || typeof state.finds !== 'object') state.finds = {};
    if (!state.sent || typeof state.sent !== 'object') state.sent = {};
    if (!state.proofs || typeof state.proofs !== 'object') state.proofs = {};
    if (!state.retryProofs || typeof state.retryProofs !== 'object') state.retryProofs = {};
    return state;
  }

  function save(state) {
    try {
      storage.setItem(STORE, JSON.stringify(state));
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
    return box;
  }

  // "Nowy wpis", a link to the register once there is an account
  function toast(key, state) {
    var count = Object.keys(state.finds).filter(function (found) { return FINDS[found]; }).length;
    var box = notice('Rejestr Safeguarda · nowy wpis', NAMES[key],
      (SEASONAL[key] ? 'wpis sezonowy' : count + ' z ' + TOTAL)
        + (storage.choice() !== 'yes' ? ' · na bieżącej stronie' : loggedIn() ? ' · rejestr w profilu' : ' · zapisane w tej przeglądarce, po zalogowaniu trafi do profilu'),
      loggedIn() ? ROOT + 'account/#rejestr' : null);
    box.classList.add('sg-find-toast');
  }

  // Notes a find with the proof megastructure.php gave; the first time it
  // shows the toast and goes to the account.
  function found(key, proof) {
    if (!NAMES[key] || !proof) return;
    var state = load();
    var fresh = !state.finds[key];
    if (fresh) state.finds[key] = Math.floor(Date.now() / 1000);
    proof = String(proof);
    if (!state.proofs[key]) state.proofs[key] = proof;
    else if (!state.sent[key] && state.proofs[key] !== proof) {
      // Keep the earliest proof: a later one could put the steps after it out
      // of order. A newly collected proof may still replace an old invalid one.
      var retries = state.retryProofs[key];
      if (!Array.isArray(retries)) retries = [];
      if (retries.indexOf(proof) < 0) retries.push(proof);
      state.retryProofs[key] = retries;
    }
    save(state);
    if (fresh) toast(key, state);
    if (!state.sent[key]) sync();
  }

  var syncing = false;
  var again = false;

  function json(res) {
    return res.ok || res.status === 403 || res.status === 503 ? res.json() : null;
  }

  // Asks account.php for the account's finds; with an account, it takes those
  // into the browser, forgets those the account was sent and lost, and sends
  // the ones it lacks. The profile shows them only
  // after a reload, so its register is reloaded once when it gained some.
  function sync() {
    if (storage.choice() !== 'yes') return;
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
        delete state.retryProofs[key];
        if (!state.finds[key] || finds[key] < state.finds[key]) state.finds[key] = finds[key];
      });
    }

    fetch(ROOT + 'account.php?finds', { credentials: 'same-origin', cache: 'no-store' })
      .then(json)
      .then(function (me) {
        if (!me || !me.login || storage.choice() !== 'yes') return null;
        var state = load();
        // what the account had and has no more, an admin took back: gone here too
        Object.keys(state.sent).forEach(function (key) {
          if (me.finds && me.finds[key]) return;
          delete state.sent[key];
          delete state.finds[key];
          delete state.proofs[key];
          delete state.retryProofs[key];
        });
        take(state, me.finds);
        var send = {};
        var proofs = {};
        pending(state).forEach(function (key) {
          send[key] = state.finds[key];
          if (state.proofs[key]) proofs[key] = state.proofs[key];
        });
        save(state);
        if (!Object.keys(send).length) return null;

        var body = new FormData();
        body.append('action', 'finds');
        body.append('csrf', me.csrf);
        body.append('found', JSON.stringify(send));
        body.append('proofs', JSON.stringify(proofs));
        return fetch(ROOT + 'account.php', { method: 'POST', body: body, credentials: 'same-origin' })
          .then(json)
          .then(function (answer) {
            if (!answer || !answer.finds || storage.choice() !== 'yes') return;
            var state = load();
            // Register order puts prerequisites before their following steps.
            var rejected = Object.keys(NAMES).filter(function (key) {
              return (answer.rejected || []).indexOf(key) >= 0
                && Object.prototype.hasOwnProperty.call(send, key) && !state.sent[key] && !answer.finds[key];
            });
            // Try one replacement at a time: a following step's original proof
            // may still be valid once an earlier prerequisite has been recovered.
            var retry = rejected.some(function (key) {
              // Another tab may have replaced the proof after this request.
              // Its rejection says nothing about that replacement.
              if (state.proofs[key] !== proofs[key]) {
                return !!state.proofs[key];
              }
              var retries = state.retryProofs[key];
              if (!Array.isArray(retries) || !retries.length) return false;
              state.proofs[key] = retries.shift();
              if (!retries.length) delete state.retryProofs[key];
              state.finds[key] = Math.floor(Date.now() / 1000);
              return true;
            });
            if (retry) {
              // A valid following step may have been rejected only because its
              // prerequisite used an old proof. Keep it until replacements run out.
              again = true;
            } else rejected.forEach(function (key) {
              if (state.proofs[key] !== proofs[key]) return;
              delete state.finds[key];
              delete state.proofs[key];
              delete state.retryProofs[key];
            });
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
  document.addEventListener('sanakan:discovery-storage', syncWaiting);

  // ---- The console ------------------------------------------------------------

  if (window.console && console.log) {
    console.log('%c SAFEGUARD · LV.9 ', 'font: bold 13px monospace; letter-spacing: 0.3em; color: #fff; background: #9b59b6; padding: 4px 8px;');
    console.log('%cWykryto próbę dostępu do kodu źródłowego.\nGen terminala sieciowego: NIE WYKRYTO.\nKlasyfikacja: nielegalny mieszkaniec.\n\nZgłoś się Safeguardowi: safeguard()',
      'font: 12px monospace; line-height: 1.6; color: #b670d3;');
  }

  // safeguard() reports to it; safeguard('<word>') says a word to it, and the
  // right one, from the terminal of floor zero's maze, gets the gene's third
  // fragment
  window.safeguard = function (word) {
    witness('console');
    if (word == null) {
      console.log('%cZgłoszenie przyjęte. Wpis w rejestrze: Intruz w kodzie.\nSafeguard nie zapomina. Nie ruszaj się.', 'font: 12px monospace; line-height: 1.6; color: #e86262;');
      return;
    }
    mega({ ask: String(word) }).then(function (answer) {
      if (answer && answer.fragment) {
        fragment(3, answer.fragment);
        fragment('word', word);
        found('maze', answer.proof);
        paper();
        console.log('%cSłowo rozpoznane. Fragment sekwencji 3/4: ' + answer.fragment + '\nOstatni fragment Safeguard zostawia tylko na papierze.', 'font: 12px monospace; line-height: 1.6; color: #4fd1c5;');
      } else if (answer && answer.stranger) {
        console.log('%cSafeguard nie rozmawia z obcymi. Zaloguj się.', 'font: 12px monospace; color: #e86262;');
      } else {
        console.log('%cSafeguard nie zna tego słowa.', 'font: 12px monospace; color: #e86262;');
      }
    });
    return 'nadawanie…';
  };

  // ---- The Net Terminal Gene ------------------------------------------------------

  // megastructure.php's answer to a question, or null without one: the way to
  // the gene is kept on the server, as the repository is public; what is too
  // long for an address (a game of the snake) goes in post, as a POST
  var megaCsrfPending = null;

  function mega(query, post) {
    query = query || {};
    function request(params, body) {
      var opts = { cache: 'no-store', credentials: 'same-origin' };
      if (body) {
        opts.method = 'POST';
        opts.body = body;
      }
      if (window.AbortSignal && AbortSignal.timeout) opts.signal = AbortSignal.timeout(8000);
      return fetch(ROOT + 'megastructure.php?' + new URLSearchParams(params), opts)
        .then(function (res) {
          if (res.ok) return res.json();
          var err = new Error('Megastructure request failed');
          err.status = res.status;
          throw err;
        });
    }
    // Games and ticket redemption write server state, even without a login.
    // Obtain a fresh token so a login or logout in another tab is safe too.
    var changes = post || query.snake != null || (query.witness != null && query.ticket != null);
    function token() {
      if (!megaCsrfPending) {
        megaCsrfPending = request({ csrf: 1 }).then(function (auth) {
          megaCsrfPending = null;
          return auth;
        }, function (err) {
          megaCsrfPending = null;
          throw err;
        });
      }
      return megaCsrfPending;
    }
    function send(retry) {
      return token().then(function (auth) {
        if (!auth || !auth.csrf) throw new Error('Missing CSRF token');
        var body = new URLSearchParams(post || query);
        body.set('csrf', auth.csrf);
        return request(post ? query : {}, body);
      }).catch(function (err) {
        if (!retry || (err.status && err.status < 500 && err.status !== 403)) throw err;
        return send(false); // safe: the server returns the original ticket proof
      });
    }
    var answer = changes ? send(true) : request(query);
    return answer
      .catch(function () { return null; });
  }

  // Asks megastructure.php to witness a find made here, with what it needs
  // for it (a ticket of when it began, a word), and notes it with the proof
  // the server gives; the answer is whether there is one. A find this browser
  // has with its proof is not asked for again.
  function witness(key, extra) {
    var state = load();
    if (state.finds[key] && state.proofs[key]) {
      if (!state.sent[key]) sync();
      return Promise.resolve(true);
    }
    var query = { witness: key };
    Object.keys(extra || {}).forEach(function (name) {
      if (extra[name] != null) query[name] = extra[name];
    });
    return mega(query).then(function (answer) {
      if (!answer || !answer.proof) return false;
      found(key, answer.proof);
      return true;
    });
  }

  // a ticket of when something began (idle, gbe, killy), or null
  function ticket(kind) {
    return mega({ ticket: kind }).then(function (answer) { return answer && answer.ticket || null; });
  }

  // the gene's fragments this browser has, number => "7C2E" (localStorage
  // "safeguard-gene"), so the terminal can list them
  var GENE_STORE = 'safeguard-gene';

  function fragments() {
    try {
      var kept = JSON.parse(storage.getItem(GENE_STORE));
      return kept && typeof kept === 'object' ? kept : {};
    } catch (err) {
      return {};
    }
  }

  function fragment(n, value) {
    var kept = fragments();
    kept[n] = String(value).toUpperCase();
    try {
      storage.setItem(GENE_STORE, JSON.stringify(kept));
    } catch (err) {
      // remembered by whoever wrote it down
    }
  }

  // The fourth fragment the Safeguard leaves only on paper. Once the browser
  // has the word of floor zero's maze or the account earned its third fragment,
  // every page asks for it beforehand and keeps it in memory only; a page
  // about to be printed (its preview is enough) gets it as a line on top,
  // gone again after, and then the terminal
  // knows it too. Nothing on the screen ever shows it.
  var paperFragment = null;
  var paperProof = null;
  var paperLine = null;

  function paper() {
    var word = fragments().word;
    if (paperFragment || (!word && !loggedIn())) return;
    // Without a local word the server checks this account's recorded maze.
    mega({ paper: word || '' }).then(function (answer) {
      if (answer && answer.fragment) {
        paperFragment = answer.fragment;
        paperProof = answer.proof;
      }
    });
  }
  // Static pages learn about the session after their account menu arrives.
  document.addEventListener('sanakan:account', paper);

  function onPaper() {
    if (!paperFragment || paperLine) return;
    paperLine = document.createElement('div');
    paperLine.className = 'sg-paper';
    paperLine.setAttribute('aria-hidden', 'true');
    paperLine.textContent = 'SAFEGUARD · LV.9 — ślad na papierze';
    var value = document.createElement('b');
    value.textContent = 'Fragment sekwencji 4/4: ' + paperFragment;
    paperLine.appendChild(value);
    document.body.insertBefore(paperLine, document.body.firstChild);
    fragment(4, paperFragment);
    found('paper', paperProof);
  }

  function offPaper() {
    if (paperLine) paperLine.remove();
    paperLine = null;
  }

  window.addEventListener('beforeprint', onPaper);
  window.addEventListener('afterprint', offPaper);
  // Safari before 13 knows only the print media query
  if (!('onbeforeprint' in window) && window.matchMedia) {
    var printing = matchMedia('print');
    if (printing.addListener) printing.addListener(function (e) { if (e.matches) onPaper(); else offPaper(); });
  }
  paper();

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
        witness('silicon');
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

  // The holidays of a day (today() by default), each with an easter egg of its
  // own; a day may have more than one: "xmas" from 20 December to Epiphany (6
  // January), "newyear" on 31 December and 1 January, "easter" from Palm
  // Sunday to Easter Monday, "aprilfools" on 1 April, "birthday" on the bot's,
  // 13 October, "halloween" on 31 October and "friday13" on every Friday the
  // 13th, "sakura" from 2 to 15 April (apart from April Fools), "perseids" from 1 to 15 August and
  // "fireflies" from 15 to 30 June
  // (the same as safeguardHolidays() in inc/auth/finds.php; backgrounds in
  // js/seasonal-background.js).
  function holidays(d) {
    d = d || today();
    var list = [];
    var date = new Date(d.year, d.month - 1, d.day);
    if ((d.month === 12 && d.day >= 20) || (d.month === 1 && d.day <= 6)) list.push('xmas');
    if ((d.month === 12 && d.day === 31) || (d.month === 1 && d.day === 1)) list.push('newyear');
    var days = Math.round((date - easterSunday(d.year)) / 86400000);
    if (days >= -7 && days <= 1) list.push('easter');
    if (d.month === 4 && d.day === 1) list.push('aprilfools');
    if (d.month === 10 && d.day === 13) list.push('birthday');
    if (d.month === 10 && d.day === 31) list.push('halloween');
    if (d.day === 13 && date.getDay() === 5) list.push('friday13');
    if (d.month === 4 && d.day >= 2 && d.day <= 15) list.push('sakura');
    if (d.month === 8 && d.day <= 15) list.push('perseids');
    if (d.month === 6 && d.day >= 15) list.push('fireflies');
    return list;
  }

  function holiday(key) {
    return holidays().indexOf(key) >= 0;
  }

  // Halloween: now and then a Silicon Creature runs along an edge in view, of
  // a box of the page or of the window itself, stops for a moment halfway and
  // runs on; with reduced motion it only sits there for a while. A click
  // catches it, a seasonal find.
  var CRAWLER = '<svg viewBox="0 0 28 16" aria-hidden="true">'
    + '<g class="legs"><path d="M9 9 4 14M12 9l-2 6M16 9l2 6M19 9l5 5M9 7 3 3M19 7l6-4" /></g>'
    + '<ellipse cx="14" cy="8" rx="7" ry="3.6" /><circle cx="22.5" cy="7.5" r="2.6" />'
    + '<circle class="eye" cx="23.4" cy="6.8" r="0.9" /></svg>';
  // what has edges to run on: the HUD's corners and the cards, the links,
  // fields and rows with a frame
  var CRAWL_ON = '.hud-corners, .links a, .field, .card, .notice-bar, .changes-line, .hunter, .secrets li, article.cmd, .version-group';
  var EDGE = 60;   // px of an edge in view, at least

  // the edges of the boxes in view, each as far as it is in view, walked
  // clockwise: the creature runs on the outside of the box
  function boxEdges(w, h) {
    var list = [];
    Array.prototype.forEach.call(document.querySelectorAll(CRAWL_ON), function (el) {
      if (el.closest('.sg-toast, .ns-overlay, .pl-overlay, .pp-overlay, .account-drop, .to-top')) return;
      var r = el.getBoundingClientRect();
      if (r.width < EDGE || r.height < 24) return;
      var left = Math.max(r.left, 0), right = Math.min(r.right, w);
      var top = Math.max(r.top, 0), bottom = Math.min(r.bottom, h);
      if (right - left >= EDGE) {
        if (r.top > 14 && r.top < h - 2) list.push([left, r.top, right, r.top]);
        if (r.bottom > 14 && r.bottom < h - 2) list.push([right, r.bottom, left, r.bottom]);
      }
      if (bottom - top >= EDGE) {
        if (r.right > 2 && r.right < w - 14) list.push([r.right, top, r.right, bottom]);
        if (r.left > 14 && r.left < w - 2) list.push([r.left, bottom, r.left, top]);
      }
    });
    return list;
  }

  function crawl() {
    var w = document.documentElement.clientWidth, h = innerHeight;
    var boxes = boxEdges(w, h);
    var e;
    if (boxes.length && Math.random() < 0.6) {
      // on the outside of the box, or now and then upside down on its inside
      e = boxes[Math.floor(Math.random() * boxes.length)];
      if (Math.random() < 0.3) e = [e[2], e[3], e[0], e[1]];
    } else {
      // the window's edges, walked the other way round, so it stays in view
      var windowEdges = [[w, 0, 0, 0], [0, 0, 0, h], [0, h, w, h], [w, h, w, 0]];
      e = windowEdges[Math.floor(Math.random() * 4)];
      // its middle stretch on a long one
      var cut = Math.random() * 0.3;
      e = [e[0] + (e[2] - e[0]) * cut, e[1] + (e[3] - e[1]) * cut, e[2] - (e[2] - e[0]) * cut, e[3] - (e[3] - e[1]) * cut];
    }
    var angle = Math.atan2(e[3] - e[1], e[2] - e[0]) * 180 / Math.PI;
    var length = Math.hypot(e[2] - e[0], e[3] - e[1]);
    var mid = [(e[0] + e[2]) / 2, (e[1] + e[3]) / 2];
    function at(x, y) { return 'translate(' + (x - 14) + 'px, ' + (y - 14) + 'px) rotate(' + angle + 'deg)'; }

    // a button, but out of the tab order and hidden from screen readers: it
    // is gone in a few seconds
    var bug = document.createElement('button');
    bug.type = 'button';
    bug.className = 'sg-crawler';
    bug.tabIndex = -1;
    bug.setAttribute('aria-hidden', 'true');
    bug.innerHTML = CRAWLER;
    document.body.appendChild(bug);
    var move = null;
    var gone;
    if (still()) {
      bug.style.transform = at(mid[0], mid[1]);
      gone = setTimeout(function () { bug.remove(); }, 4000);
    } else {
      move = bug.animate([
        { transform: at(e[0], e[1]), opacity: 0 },
        { transform: at(e[0], e[1]), opacity: 1, offset: 0.05 },
        { transform: at(mid[0], mid[1]), offset: 0.4 },
        { transform: at(mid[0], mid[1]), offset: 0.65 },
        { transform: at(e[2], e[3]), opacity: 1, offset: 0.95 },
        { transform: at(e[2], e[3]), opacity: 0 }
      ], { duration: 1600 + length * 4, easing: 'ease-in-out', fill: 'forwards' });
      move.onfinish = function () { bug.remove(); };
    }

    bug.addEventListener('click', function () {
      if (bug.disabled) return;
      bug.disabled = true;
      clearTimeout(gone);
      if (move) {
        move.onfinish = null;
        move.pause();
      }
      bug.classList.add('caught');
      setTimeout(function () { bug.remove(); }, 700);
      caught();
    });
  }

  function caught() {
    if (load().finds.halloween)
      notice('Forma krzemowa · zneutralizowana', 'Kolejna', 'Wpis w rejestrze już jest. Safeguard poluje dalej.', null);
    else
      witness('halloween').then(function (noted) {
        if (!noted) notice('Forma krzemowa · zneutralizowana', 'Safeguard nie odebrał sygnału', 'Złap następną, wpis jeszcze czeka.', null);
      });
  }

  function halloween() {
    if (!document.body.animate && !still()) return;
    (function next() {
      setTimeout(function () {
        if (!document.hidden) crawl();
        next();
      }, 2500 + Math.random() * 5000);
    })();
  }

  // New Year: luminous fireworks, rockets that rise from the bottom of the
  // window (or from a point given) and burst. js/home.js fires them from its
  // rocket; on 31 December they go off by themselves at midnight on any page
  // open, and on one opened in the first minutes of the year.
  var SPARK_COLOURS = ['#b670d3', '#4fd1c5', '#e86262', '#f0a35e', '#5fd38d', '#e8d36a'];

  function spark(kind, colour, layer) {
    var bit = document.createElement('i');
    bit.className = 'sg-spark sg-firework-' + kind;
    bit.setAttribute('aria-hidden', 'true');
    bit.style.color = colour;
    (layer || document.body).appendChild(bit);
    return bit;
  }

  function animateSpark(bit, frames, timing) {
    bit.animate(frames, timing).onfinish = function () { bit.remove(); };
  }

  function burst(x, y, colour, options) {
    var smallBurst = options && options.small;
    var layer = options && options.layer;
    var bright = options && options.colour ? '#fff0bc' : '#fff';
    var count = (smallBurst ? 20 : 34) + Math.floor(Math.random() * (smallBurst ? 4 : 8));
    var flash = spark('flare', bright, layer);
    animateSpark(flash, [
      { transform: 'translate(' + x + 'px, ' + y + 'px) scale(0.15)', opacity: 0.9 },
      { transform: 'translate(' + x + 'px, ' + y + 'px) scale(1.7)', opacity: 0 }
    ], { duration: 500, easing: 'ease-out' });
    for (var i = 0; i < count; i++) {
      var bit = spark('trail', Math.random() < 0.25 ? bright : colour, layer);
      var angle = (i / count) * Math.PI * 2 + Math.random() * 0.12;
      var far = (smallBurst ? 60 : 90) + Math.random() * (smallBurst ? 50 : 100);
      if (i % 4 === 0) far *= 0.6;
      var dx = Math.cos(angle) * far, dy = Math.sin(angle) * far;
      var degrees = angle * 180 / Math.PI;
      var falling = degrees + ((90 - degrees + 540) % 360 - 180) * 0.8;
      var drop = 65 + Math.random() * 65;
      bit.style.setProperty('--spark-tail', (12 + Math.random() * 18).toFixed(1) + 'px');
      animateSpark(bit, [
        { transform: 'translate(' + x + 'px, ' + y + 'px) rotate(' + degrees + 'deg) scale(0.3)', opacity: 1,
          easing: 'cubic-bezier(0.1, 0.75, 0.25, 1)' },
        { transform: 'translate(' + (x + dx) + 'px, ' + (y + dy) + 'px) rotate(' + degrees + 'deg) scale(1)', opacity: 0.85,
          offset: 0.35, easing: 'cubic-bezier(0.45, 0, 0.85, 0.7)' },
        { transform: 'translate(' + (x + dx * 1.15) + 'px, ' + (y + dy + drop) + 'px) rotate(' + falling + 'deg) scale(0.15)', opacity: 0 }
      ], { duration: 1700 + Math.random() * 700 });
    }
    // A few smaller embers remain after the main bloom, falling under gravity.
    for (var j = 0; j < (smallBurst ? 5 : 9); j++) {
      var ember = spark('ember', j % 3 ? colour : bright, layer);
      var ex = x + (Math.random() - 0.5) * (smallBurst ? 100 : 160);
      var ey = y + (Math.random() - 0.5) * 90;
      var drift = (Math.random() - 0.5) * 35;
      animateSpark(ember, [
        { transform: 'translate(' + ex + 'px, ' + ey + 'px)', opacity: 0 },
        { transform: 'translate(' + (ex + drift * 0.25) + 'px, ' + (ey + 20) + 'px)', opacity: 0.75, offset: 0.2 },
        { transform: 'translate(' + (ex + drift) + 'px, ' + (ey + 125) + 'px) scale(0.3)', opacity: 0 }
      ], { delay: 300 + Math.random() * 200, duration: 1400 + Math.random() * 700, fill: 'backwards', easing: 'ease-in' });
    }
  }

  function rocket(from, options) {
    var w = document.documentElement.clientWidth, h = innerHeight;
    var x0 = from ? from.x : w * (0.15 + Math.random() * 0.7), y0 = from ? from.y : h + 10;
    var x = w * (0.18 + Math.random() * 0.64), y = h * (0.18 + Math.random() * 0.25);
    var colour = options && options.colour || SPARK_COLOURS[Math.floor(Math.random() * SPARK_COLOURS.length)];
    var head = spark('rocket', colour, options && options.layer);
    var dx = x - x0, dy = y - y0;
    var firstAngle = Math.atan2(dy * 0.72, dx * 0.8) * 180 / Math.PI;
    var lastAngle = Math.atan2(dy * 0.28, dx * 0.2) * 180 / Math.PI;
    head.animate([
      { transform: 'translate(' + x0 + 'px, ' + y0 + 'px) rotate(' + firstAngle + 'deg)', opacity: 0.65 },
      { transform: 'translate(' + (x0 + dx * 0.8) + 'px, ' + (y0 + dy * 0.72) + 'px) rotate(' + lastAngle + 'deg)', opacity: 1, offset: 0.6 },
      { transform: 'translate(' + x + 'px, ' + y + 'px) rotate(' + lastAngle + 'deg)', opacity: 1 }
    ], { duration: 950 + Math.random() * 350, easing: 'cubic-bezier(0.15, 0.65, 0.25, 1)' }).onfinish = function () {
      head.remove();
      if (!options || !options.layer || (options.layer.isConnected && !document.hidden && !still())) burst(x, y, colour, options);
    };
  }

  function fireworks(rockets, from, options) {
    if (still() || !document.body.animate) return;
    // Skin backgrounds own their timing and layer; the event keeps its usual
    // staggered volley, with the same rocket and burst renderer underneath.
    if (options && options.layer) {
      if (options.layer.isConnected) rocket(from, options);
      return;
    }
    for (var n = 0; n < rockets; n++) setTimeout(rocket, n * 380 + Math.random() * 250, from);
  }

  // the real clock, not a pretended day: midnight is midnight
  function newYearMidnight() {
    var now = new Date();
    if (now.getMonth() === 0 && now.getDate() === 1 && now.getHours() === 0 && now.getMinutes() < 15) {
      setTimeout(function () { fireworks(8); }, 1500);
      return;
    }
    if (now.getMonth() !== 11 || now.getDate() !== 31) return;
    setTimeout(function () { fireworks(14); }, new Date(now.getFullYear() + 1, 0, 1) - now);
  }

  // Friday the 13th: now and then a title of the page in view breaks into
  // glyphs for a moment, a glitch. A click on it while it lasts catches it, a
  // seasonal find, and does nothing else (a link is not followed then).
  var GLITCH_ON = 'h1, h2, h3, .hud-title, .links a';
  var glitching = null;

  function glitch() {
    if (glitching || infected || document.hidden || document.body.classList.contains('ns-open')) return;
    var h = innerHeight;
    var titles = Array.prototype.filter.call(document.querySelectorAll(GLITCH_ON), function (el) {
      var r = el.getBoundingClientRect();
      return el.textContent.trim().length > 2 && r.width > 0 && r.top > 0 && r.bottom < h
        && !el.closest('.sg-toast, .ns-overlay, .pl-overlay, .pp-overlay, .account-drop, .sg-silicon, #motto');
    });
    if (!titles.length) return;
    var el = titles[Math.floor(Math.random() * titles.length)];

    // only its text changes, whatever else it holds stays
    var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    var nodes = [], texts = [];
    while (walker.nextNode()) {
      nodes.push(walker.currentNode);
      texts.push(walker.currentNode.nodeValue);
    }
    function scramble() {
      nodes.forEach(function (node, i) {
        node.nodeValue = texts[i].replace(/\S/g, function (ch) {
          return Math.random() < 0.4 ? GLYPHS[Math.floor(Math.random() * GLYPHS.length)] : ch;
        });
      });
    }
    var calm = still();
    el.classList.add('sg-glitch');
    scramble();
    var tick = calm ? null : setInterval(scramble, 90);
    var stop = setTimeout(restore, calm ? 3500 : 2400);
    function restore() {
      clearInterval(tick);
      clearTimeout(stop);
      nodes.forEach(function (node, i) { node.nodeValue = texts[i]; });
      el.classList.remove('sg-glitch');
      glitching = null;
    }
    glitching = { el: el, restore: restore };
  }

  // ---- A passer-by ---------------------------------------------------------------

  // Now and then, a while after a page opens, Killy walks along the bottom of
  // the window, a shadow with one white eye and his Gravitational Beam Emitter
  // charging in hand; with reduced motion he only stands in a corner for a
  // while. The more finds the browser has, the likelier he comes: one page in
  // KILLY_ODDS without any, each find one more chance in it, at most one page
  // in KILLY_MOST. The browser rolls KILLY_SLOTS times likelier, and he comes
  // only when the server's roll says he walks too (one slot of its time in
  // KILLY_SLOTS, MEGA_KILLY_CHANCE in inc/megastructure.php), with a ticket a
  // click needs for the find. A click stops him, and once met he comes no
  // more. localStorage "safeguard-killy" set to "1" sends him on every page
  // all the same, to try it out (the find only with the server's ticket).
  var KILLY_ODDS = 250;
  var KILLY_MOST = 20;
  var KILLY_SLOTS = 5;
  var KILLY_PACE = 85;   // px a second
  var KILLY = '<svg viewBox="0 0 60 120" aria-hidden="true">'
    + '<g class="leg back"><path d="M27 92v22h-4" /></g><g class="leg front"><path d="M33 92v22h5" /></g>'
    + '<g class="body"><path class="coat" d="M23 26h14l4 5 2 30 5 35H34l-3-12-3 12H13l5-35 1-30Z" />'
    + '<path class="coat" d="M37 28l4 3 1 26-4 1-2-24Z" />'
    + '<path class="coat" d="M37 55h15v5h-7v4h-4v-4h-4Z" /><circle class="charge" cx="51" cy="57.5" r="1.6" />'
    + '<path class="coat" d="M21 19C19 10 24 4 31 4c6 0 10 4 10 10l5 1-4 3 2 4-5-2-1 5-3-4-3 4-1-4-4 3 0-4-5 2 2-4Z" />'
    + '<rect class="eye" x="35" y="15" width="3" height="1.2" /></g></svg>';

  // the chance of a page, from the finds this browser has
  function killyOdds(finds) {
    return Math.min(1 / KILLY_MOST, (1 + Object.keys(finds).length) / KILLY_ODDS);
  }

  function passerBy() {
    var sure = false;
    try {
      sure = localStorage.getItem('safeguard-killy') === '1';
    } catch (err) {
      // no storage, only luck
    }
    var finds = load().finds;
    if (!sure && (finds.killy || Math.random() >= killyOdds(finds) * KILLY_SLOTS)) return;
    setTimeout(function walk() {
      if (document.hidden || infected || document.body.classList.contains('ns-open')) {
        setTimeout(walk, 3000);
        return;
      }
      ticket('killy').then(function (pass) {
        if (pass || sure) killy(pass);
      });
    }, 2000 + Math.random() * 8000);
  }

  function killy(pass) {
    var w = document.documentElement.clientWidth;
    var man = document.createElement('button');
    man.type = 'button';
    man.className = 'sg-killy';
    man.title = 'Killy';
    man.setAttribute('aria-label', 'Killy');
    man.innerHTML = KILLY;
    document.body.appendChild(man);

    var move = null;
    var gone;
    if (still()) {
      man.style.transform = 'translateX(' + (w - 64) + 'px)';
      man.classList.add('stopped');
      gone = setTimeout(function () { man.remove(); }, 8000);
    } else {
      move = man.animate([
        { transform: 'translateX(-50px)' },
        { transform: 'translateX(' + (w + 10) + 'px)' }
      ], { duration: (w + 60) / KILLY_PACE * 1000, easing: 'linear', fill: 'forwards' });
      move.onfinish = function () { man.remove(); };
    }

    man.addEventListener('click', function () {
      if (man.classList.contains('met')) return;
      man.classList.add('met', 'stopped');
      if (move) move.pause();
      clearTimeout(gone);
      var say = document.createElement('span');
      say.className = 'sg-killy-say';
      say.textContent = '…';
      man.appendChild(say);
      if (pass) witness('killy', { ticket: pass });
      // he says nothing more and goes on his way
      setTimeout(function () {
        say.remove();
        man.classList.remove('stopped');
        if (move) {
          move.playbackRate = 1.5;
          move.play();
        } else {
          man.remove();
        }
      }, 2600);
    });
  }

  function fridayThe13th() {
    document.addEventListener('click', function (e) {
      if (!glitching || !glitching.el.contains(e.target)) return;
      e.preventDefault();
      e.stopPropagation();
      glitching.restore();
      if (load().finds.friday13)
        notice('Usterka · namierzona', 'Kolejna', 'Wpis w rejestrze już jest. Safeguard dalej łata Megastrukturę.', null);
      else
        witness('friday13').then(function (noted) {
          if (!noted) notice('Usterka · namierzona', 'Safeguard nie odebrał sygnału', 'Namierz następną, wpis jeszcze czeka.', null);
        });
    }, true);
    (function next(wait) {
      setTimeout(function () {
        glitch();
        next(12000 + Math.random() * 18000);
      }, wait);
    })(4000 + Math.random() * 4000);
  }

  // Snow of glyphs falling over the page, behind its dialogs: at Christmas and
  // through the calendar winter, from 20 December to 20 March, or with a snow
  // skin. Both sources share one layer and the profile's animation preference.
  function snowing() {
    var d = today();
    return (d.month === 12 && d.day >= 20) || d.month <= 2 || (d.month === 3 && d.day <= 20);
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
    layer.classList.toggle('paused', document.hidden);
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

  function updateSnow() {
    var skin = getComputedStyle(document.documentElement).getPropertyValue('--season-background').trim();
    var wanted = snowing() || skin === 'snow';
    var enabled = window.SanakanAppearance ? SanakanAppearance.animations() : !/(?:^|; )snow=off(?:;|$)/.test(document.cookie);
    if (wanted && enabled && !still()) snow();
    else if (snowLayer) snowLayer.hidden = true;
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
      var kept = JSON.parse(storage.getItem(EGG_STORE));
      if (kept && kept.year === year && Array.isArray(kept.pages)) got = kept;
    } catch (err) {
      // no storage: the eggs of this visit only
    }
    var page = location.pathname.replace(/index\.(?:php|html)$/, '');
    // the eggs of five pages and still no find (the server did not answer,
    // or the account did not take it): the Safeguard is asked again, as no
    // egg may be left to try with
    if (got.pages.length >= EGG_PAGES && !load().finds.easter) witness('easter');
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
        try {
          var saved = JSON.parse(storage.getItem(EGG_STORE));
          if (saved && saved.year === year && Array.isArray(saved.pages))
            got.pages = Array.from(new Set(got.pages.concat(saved.pages)));
        } catch (err) {}
        if (got.pages.indexOf(page) < 0) got.pages.push(page);
        try {
          storage.setItem(EGG_STORE, JSON.stringify(got));
        } catch (err) {
          // counted for this visit only
        }
        var count = got.pages.length;
        if (count < EGG_PAGES) notice('Pisanka z Megastruktury', count + ' z ' + EGG_PAGES, 'Następna czeka na innej stronie.', null);
        else if (!load().finds.easter) witness('easter').then(function (noted) {
          if (!noted) notice('Pisanka z Megastruktury', 'Safeguard nie odebrał sygnału', 'Spróbuje jeszcze raz na następnej stronie.', null);
        });
        else notice('Pisanka z Megastruktury', count + ' zebranych', 'Wpis w rejestrze już jest. Safeguard docenia zapał.', null);
      });
    }, 1200);
  }

  if (holiday('halloween')) halloween();
  new MutationObserver(updateSnow).observe(document.documentElement, { attributes: true, attributeFilter: ['data-skin', 'data-skin-animations', 'data-skin-background'] });
  if (window.matchMedia) matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', updateSnow);
  document.addEventListener('visibilitychange', function () {
    if (snowLayer) snowLayer.classList.toggle('paused', document.hidden);
  });
  updateSnow();
  if (holiday('easter')) eggHunt();
  if (holiday('friday13')) fridayThe13th();
  newYearMidnight();
  passerBy();

  // ---- Offline: the service worker and its page --------------------------------

  // A page open while the browser loses the network and gets it back is the
  // find too: a browser set to work offline (Firefox) may answer from its own
  // cache or show its own error, and never ask sw.js for the offline page.
  var cutOff = false;
  window.addEventListener('offline', function () { cutOff = true; });
  window.addEventListener('online', function () {
    if (!cutOff) return;
    cutOff = false;
    witness('offline');
  });

  // sw.js shows offline.html when a page cannot be reached and leaves a cache
  // behind to say so; the next page that loads notes the find
  function offlineFind() {
    if (storage.choice() !== 'yes' || !window.caches || !caches.has) return;
    caches.has(OFFLINE_SEEN).then(function (seen) {
      if (!seen || storage.choice() !== 'yes') return;
      caches.delete(OFFLINE_SEEN);
      witness('offline');
    }).catch(function () {});
  }
  offlineFind();
  document.addEventListener('sanakan:discovery-storage', offlineFind);

  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(ROOT + 'sw.js').catch(function () {
        // no offline page, the site works as before
      });
    });
  }

  // found(key, proof) notes a find with its proof, witness(key, extra) asks
  // the server for one first and ticket(kind) for a ticket, names its names, finds() what this browser found
  // today() the day of the year (maybe a pretended one), holiday(key) whether
  // it is that one's (holidays() above), and fireworks(rockets, from) the New
  // Year's, from a point {x, y} of the window or from its bottom. An optional
  // third argument supplies a background layer, colour and smaller bursts.
  // mega(query)
  // asks megastructure.php (post for a POST), fragment(n, value) keeps a fragment of the gene
  // and fragments() gives those kept
  return {
    notice: notice,
    found: found,
    witness: witness,
    ticket: ticket,
    mega: mega,
    fragment: fragment,
    fragments: fragments,
    names: FINDS,
    finds: function () { return load().finds; },
    today: today,
    holiday: holiday,
    fireworks: fireworks
  };
})();
