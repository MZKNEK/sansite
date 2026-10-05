// The home page: the motto in Morse code, the bot status dot and the Discord
// account in the top right corner. Kept out of index.html, so the security
// policy can allow only the site's own script files (server/nginx).

// shows the motto in Morse code; every symbol lights up in real Morse timing,
// like a signal being sent (dot 1 unit, dash 3, gaps 1 / 3 / 7)
(function () {
  var CODE = {
    A: '.-', B: '-...', C: '-.-.', D: '-..', E: '.', F: '..-.', G: '--.', H: '....', I: '..',
    J: '.---', K: '-.-', L: '.-..', M: '--', N: '-.', O: '---', P: '.--.', Q: '--.-', R: '.-.',
    S: '...', T: '-', U: '..-', V: '...-', W: '.--', X: '-..-', Y: '-.--', Z: '--..', ',': '--..--'
  };
  var UNIT = 60;      // ms
  var START = 1200;   // ms, after the entrance animation
  var PAUSE = 21;     // units of silence before the signal repeats

  var motto = document.getElementById('motto');
  var text = motto.textContent.trim();
  var signal = document.createElement('span');
  signal.setAttribute('aria-hidden', 'true');

  var t = 0;
  text.toUpperCase().split(/\s+/).forEach(function (word, w) {
    var wordEl = document.createElement('span');
    wordEl.className = 'morse-word';

    word.split('').forEach(function (ch, c) {
      var code = CODE[ch];
      if (!code) return;

      var letterEl = document.createElement('span');
      letterEl.className = 'morse-letter';
      code.split('').forEach(function (sym, i) {
        t += i ? 1 : (c ? 3 : (w ? 7 : 0));
        var el = document.createElement('i');
        el.className = sym === '.' ? 'dot' : 'dash';
        el.style.animationDelay = (START + t * UNIT) + 'ms';
        letterEl.appendChild(el);
        t += sym === '.' ? 1 : 3;
      });
      wordEl.appendChild(letterEl);
    });
    signal.appendChild(wordEl);
  });

  var plain = document.createElement('span');
  plain.className = 'sr-only';
  plain.textContent = text;

  motto.style.setProperty('--cycle', (t + PAUSE) * UNIT + 'ms');
  motto.replaceChildren(signal, plain);
})();

// status.php asks the bot API at most once a minute and says online, idle,
// offline or maintenance (a planned break set in the admin panel)
(function () {
  var dot = document.getElementById('bot-status');
  var labels = {
    online: 'Dostępny: bot działa',
    idle: 'Zaraz wracam: bot działa, ale w ciągu ostatnich 24 h odpowiadał tylko w {uptime}% sprawdzeń',
    offline: 'Niedostępny: bot nie odpowiada {down}',
    maintenance: 'Nie przeszkadzać: przerwa techniczna, bot może nie odpowiadać'
  };

  var opts = {};
  if (window.AbortSignal && AbortSignal.timeout) opts.signal = AbortSignal.timeout(8000);

  fetch('./status.php', opts)
    .then(function (res) { return res.ok ? res.json() : null; })
    .then(function (data) {
      if (!data || !labels[data.status]) throw new Error('unknown status');

      var label = labels[data.status]
        .replace('{uptime}', Number(data.uptime).toLocaleString('pl-PL'))
        .replace(' {down}', data.down ? ' ' + data.down : '');
      if (data.status === 'idle' && data.issues) label = 'Zaraz wracam: bot działa, ale ' + data.issues;
      dot.className = 'bot-status ' + data.status;
      dot.title = label + ' (kliknij, żeby zobaczyć dostępność)';
      dot.setAttribute('aria-label', label);
    })
    // without an answer nothing is known about the bot, so no dot at all
    .catch(function () { dot.remove(); });

  // a click on the dot opens the public status page; held for 3 seconds it
  // charges up and fires like Killy's Gravitational Beam Emitter in BLAME!:
  // a thin beam straight through everything, then the blast along it
  var timer, pressed = 0;

  function hold(e) {
    if (e.button > 0) return;
    pressed = Date.now();
    dot.classList.add('charging');
    timer = setTimeout(fire, 3000);
  }

  function release() {
    clearTimeout(timer);
    dot.classList.remove('charging');
    pressed = 0;
  }

  // the beam goes in next to the dot, a layer below it, so it comes out from
  // under the dot and runs to the right edge of the window
  function fire() {
    release();
    var wrap = dot.parentNode;
    var r = dot.getBoundingClientRect(), w = wrap.getBoundingClientRect();
    var beam = document.createElement('div');
    beam.className = 'gbe-beam';
    beam.style.left = (r.left - w.left + r.width / 2) + 'px';
    beam.style.top = (r.top - w.top + r.height / 2) + 'px';
    beam.style.width = Math.max(0, document.documentElement.clientWidth - (r.left + r.width / 2)) + 'px';
    wrap.appendChild(beam);
    document.body.classList.add('gbe-shot');
    dot.classList.add('recoil');
    setTimeout(function () {
      beam.remove();
      document.body.classList.remove('gbe-shot');
      dot.classList.remove('recoil');
    }, 1700);
  }

  dot.addEventListener('pointerdown', hold);
  dot.addEventListener('pointerup', function () {
    var click = pressed && Date.now() - pressed < 400;
    release();
    if (click) location.href = 'state/';
  });
  ['pointerleave', 'pointercancel'].forEach(function (type) {
    dot.addEventListener(type, release);
  });
  // a long touch would open the context menu instead
  dot.addEventListener('contextmenu', function (e) { e.preventDefault(); });
})();

// The Discord account in the top right corner (account.php): a login button,
// or the account menu of every page, with the gallery and the panel when it
// may open them (js/account.js). An account that may read the API gets the
// API button unlocked.
(function () {
  var box = document.getElementById('home-account');
  var api = document.getElementById('api-link');

  fetch('./account.php', { credentials: 'same-origin', cache: 'no-store' })
    .then(function (res) { return res.ok ? res.json() : null; })
    .then(function (me) {
      if (!me) return;
      if (!me.menu) {
        if (!me.login) return;
        var login = document.createElement('a');
        login.href = 'account.php?login';
        login.className = 'home-login hud-corners';
        login.textContent = 'Zaloguj przez Discord';
        box.appendChild(login);
        box.hidden = false;
        return;
      }

      // the menu comes ready from the server, its texts escaped there
      box.innerHTML = me.menu;
      SanakanAccount.bind(box.firstElementChild);
      box.hidden = false;

      if (me.api) {
        api.classList.remove('restricted');
        api.title = 'Dokumentacja API';
        var lock = api.querySelector('.lock');
        lock.setAttribute('aria-label', 'masz dostęp');
        lock.querySelector('path').setAttribute('d', 'M8 11V8a4 4 0 0 1 7.7-1.5');
      }

      // "Zalogowano jako ..." after coming back from Discord
      if (me.flash) {
        var flash = document.createElement('p');
        flash.className = 'home-flash';
        flash.setAttribute('role', 'status');
        flash.textContent = me.flash;
        document.body.appendChild(flash);
        setTimeout(function () { flash.remove(); }, 4500);
      }
    })
    .catch(function () {});
})();
