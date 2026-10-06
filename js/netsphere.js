// Easter egg from BLAME!: typing "netsphere" anywhere on the site (or tapping
// the SAFEGUARD tag five times, for phones) asks the Netsphere for access. The
// Safeguard scans for the Net Terminal Gene, never finds it, and the visitor is
// marked an illegal resident. A logged-in account shows its Safeguard level from
// the account menu, which does not help either. After the verdict the Safeguard
// leaves its emergency terminal open: a prompt with a few commands that answer
// with the site's real data (status, ping, whoami, going to a page) and some
// lore of the Megastructure.
(function () {
  var WORD = 'netsphere';
  var TAPS = 5;
  var TAP_WINDOW = 2000; // ms for the taps on the tag
  var STEP = 520;        // ms between the lines of the scan
  // the site's root, from this script's own address (js/netsphere.js)
  var ROOT = new URL('../', document.currentScript.src).pathname;

  var typed = '';
  var taps = [];
  var open = null;

  document.addEventListener('keydown', function (e) {
    if (open || e.ctrlKey || e.metaKey || e.altKey || e.key.length !== 1) return;
    var t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;

    typed = (typed + e.key.toLowerCase()).slice(-WORD.length);
    if (typed === WORD) {
      typed = '';
      scan();
    }
  });

  document.addEventListener('click', function (e) {
    if (open || !e.target.closest || !e.target.closest('.tag')) return;
    var now = Date.now();
    taps = taps.filter(function (time) { return now - time < TAP_WINDOW; });
    taps.push(now);
    if (taps.length >= TAPS) {
      taps = [];
      scan();
    }
  });

  // "LV.9 DEV" from the account menu (accountMenuHtml() in inc/auth.php), or null
  function level() {
    var badge = document.querySelector('.account-scan em');
    return badge ? badge.textContent.trim() : null;
  }

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text != null) node.textContent = text;
    return node;
  }

  // one line of the scan: a label, dots and the result in its colour
  function line(label, result, kind) {
    var row = el('p', 'ns-line');
    row.appendChild(el('span', 'ns-label', '> ' + label));
    if (result) row.appendChild(el('span', 'ns-result' + (kind ? ' ' + kind : ''), result));
    return row;
  }

  function scan() {
    var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var lv = level();
    var dev = lv && /^LV\.9\b/.test(lv);
    var before = document.activeElement;

    var overlay = el('div', 'ns-overlay');
    var box = el('div', 'ns-box hud-corners');
    box.setAttribute('role', 'alertdialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-labelledby', 'ns-title');
    var title = el('h2', 'ns-title', 'NETSPHERE · ŻĄDANIE DOSTĘPU');
    title.id = 'ns-title';
    var log = el('div', 'ns-log');
    log.setAttribute('aria-live', 'polite');
    var bar = el('div', 'ns-bar');
    bar.appendChild(el('i'));
    var close = el('button', 'ns-close', '[ zamknij ]');
    close.type = 'button';
    box.append(title, log, close);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    document.body.classList.add('ns-open');
    close.focus();

    var gene = line('gen terminala sieciowego', 'NIE WYKRYTO', 'bad');
    var steps = [
      function () { log.appendChild(line('łączenie z Netsferą', 'OK', 'ok')); },
      function () {
        var row = line('skanowanie genu terminala sieciowego');
        row.appendChild(bar);
        log.appendChild(row);
        // laid out empty first, or the bar would start full instead of filling up
        void bar.offsetWidth;
        bar.classList.add('run');
      },
      null, null, null,
      function () { bar.classList.add('fail'); log.appendChild(gene); },
      function () { log.appendChild(line('poziom Safeguard', lv || 'NIEZNANY', lv ? '' : 'bad')); },
      function () {
        overlay.classList.add('alarm');
        log.appendChild(el('p', 'ns-verdict', 'NIELEGALNY MIESZKANIEC'));
      },
      function () {
        log.appendChild(el('p', 'ns-note', dev
          ? 'Poziom LV.9 nie zastąpi genu. Nawet Safeguard nie ma wstępu do Netsfery.'
          : 'Dostęp zabroniony. Wezwano Safeguard, nie ruszaj się.'));
      },
      function () { input = terminal(log, box, close, shut, lv); }
    ];
    var input = null;

    var timers = [];
    steps.forEach(function (step, i) {
      if (!step) return;
      if (still) step();
      else timers.push(setTimeout(step, (i + 1) * STEP));
    });

    function shut() {
      timers.forEach(clearTimeout);
      document.removeEventListener('keydown', key, true);
      overlay.remove();
      document.body.classList.remove('ns-open');
      open = null;
      if (before && before.focus) before.focus();
    }

    function key(e) {
      if (e.key === 'Escape') {
        e.stopPropagation();
        shut();
      } else if (e.key === 'Tab') {
        // inside there is only the button, and the terminal's prompt once it opens
        e.preventDefault();
        (input && document.activeElement !== input ? input : close).focus();
      }
    }

    close.addEventListener('click', shut);
    // a click anywhere in the box goes back to the prompt, unless text was picked
    box.addEventListener('click', function (e) {
      if (input && e.target !== close && !String(window.getSelection())) input.focus();
    });
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) shut();
    });
    document.addEventListener('keydown', key, true);
    open = shut;
  }

  // ---- The emergency terminal ----------------------------------------------

  // where "idź" goes: [path from the site's root, or a full address, name]
  var PLACES = {
    start: ['', 'strona główna'],
    polecenia: ['cmd/', 'polecenia bota'],
    zmiany: ['cmd/zmiany/', 'zmiany w poleceniach'],
    status: ['state/', 'status bota'],
    api: ['api/', 'dokumentacja API'],
    galeria: ['i/', 'galeria'],
    profil: ['account/', 'twój profil'],
    panel: ['admin/', 'panel'],
    prywatnosc: ['privacy/', 'prywatność'],
    wiki: ['https://wiki.sanakan.pl', 'wiki']
  };
  var PLACE_ALIASES = {
    '~': 'start', '/': 'start', home: 'start', cmd: 'polecenia', state: 'status', i: 'galeria',
    account: 'profil', admin: 'panel', privacy: 'prywatnosc', 'prywatność': 'prywatnosc'
  };

  // commands not in the help, answered with a few lines of BLAME!
  var LORE = {
    killy: ['Wykryto: Killy. Wyposażenie: Emiter Promienia Grawitacyjnego.', 'Zalecenie Safeguarda: nie stawać na linii strzału.'],
    cibo: ['Cibo, naukowczyni. Szuka genu terminala sieciowego razem z Killym.', 'Poza kontrolą Safeguarda.'],
    sanakan: ['Sanakan: Safeguard poziomu 9 w ludzkiej postaci.', 'Ten bot nosi jej imię. Jest mniej groźny.'],
    sudo: ['Safeguard nie zna słowa sudo.', 'Bez genu terminala sieciowego uprawnień nie da się podnieść.'],
    rm: ['Megastruktury nie da się usunąć.', 'Budowniczowie wciąż budują.']
  };

  var STATES = {
    online: ['DZIAŁA', 'ok'],
    idle: ['DZIAŁA, ALE BYWAŁ NIEDOSTĘPNY', 'warn'],
    offline: ['NIE ODPOWIADA', 'bad'],
    maintenance: ['PRZERWA TECHNICZNA', 'warn']
  };

  // A prompt under the scan's log; every command and its answer go into the log.
  // Returns the prompt's input, for the focus.
  function terminal(log, box, close, shut, lv) {
    var history = [];
    var back = 0; // how many commands back the arrow keys went

    var form = el('form', 'ns-prompt');
    form.appendChild(el('span', 'ns-sign', '>'));
    var input = el('input');
    input.type = 'text';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.setAttribute('autocapitalize', 'off');
    input.setAttribute('enterkeyhint', 'send');
    input.setAttribute('aria-label', 'Polecenie dla terminala Safeguarda');
    form.appendChild(input);
    box.insertBefore(form, close);

    function add(node) {
      log.appendChild(node);
      log.scrollTop = log.scrollHeight;
    }

    // a line of text, or a label with its value on the right
    function say(text, kind) {
      add(el('p', 'ns-out' + (kind ? ' ' + kind : ''), text));
    }

    function row(label, value, kind, cls) {
      var p = el('p', 'ns-out ns-row' + (cls ? ' ' + cls : ''));
      p.append(el('b', null, label), el('span', kind || null, value));
      add(p);
    }

    function ask(url) {
      var opts = { cache: 'no-store' };
      if (window.AbortSignal && AbortSignal.timeout) opts.signal = AbortSignal.timeout(8000);
      return fetch(url, opts).then(function (res) {
        if (!res.ok) throw new Error(res.status);
        return res;
      });
    }

    function go(target) {
      shut();
      location.href = /^https?:/.test(target) ? target : ROOT + target;
    }

    function places() {
      Object.keys(PLACES).forEach(function (key) { row(key, PLACES[key][1], null, 'ns-help'); });
    }

    var commands = {
      pomoc: function () {
        [
          ['pomoc', 'ta lista'],
          ['status', 'stan bota teraz'],
          ['ping', 'czas odpowiedzi strony'],
          ['whoami', 'kim jesteś dla Safeguarda'],
          ['skan', 'jeszcze jeden skan genu'],
          ['idź <miejsce>', 'przejście na stronę (ls: lista)'],
          ['man <polecenie>', 'opis polecenia bota'],
          ['czas', 'czas sieci'],
          ['wyczyść', 'czyści ekran'],
          ['zamknij', 'zamyka terminal (Esc)']
        ].forEach(function (help) { row(help[0], help[1], null, 'ns-help'); });
        say('Strzałki ↑ i ↓ przywołują wcześniejsze polecenia.', 'dim');
      },

      status: function () {
        say('odpytywanie bota…', 'dim');
        ask(ROOT + 'status.php')
          .then(function (res) { return res.json(); })
          .then(function (data) {
            var state = STATES[data.status] || ['NIEZNANY', 'bad'];
            row('bot', state[0], state[1]);
            row('dostępność 24 h', Number(data.uptime).toLocaleString('pl-PL') + '%');
            if (data.issues) row('problemy', data.issues, 'warn');
            if (data.down) row('nie odpowiada', data.down, 'bad');
            say('Szczegóły: idź status', 'dim');
          })
          .catch(function () { say('Strona nie odpowiada.', 'bad'); });
      },

      // three requests one after another, as ping sends its packets
      ping: function () {
        var times = [];
        var host = location.host;
        function next() {
          var start = performance.now();
          ask(ROOT + 'status.php')
            .then(function () {
              var ms = Math.round(performance.now() - start);
              times.push(ms);
              say('odpowiedź z ' + host + ': czas=' + ms + ' ms');
              if (times.length < 3) next();
              else row('średnio', Math.round(times.reduce(function (a, b) { return a + b; }) / times.length) + ' ms', 'ok');
            })
            .catch(function () { say('Brak odpowiedzi z ' + host + '.', 'bad'); });
        }
        next();
      },

      whoami: function () {
        var name = document.querySelector('.account-name');
        row('konto', name ? name.textContent.trim() : 'niezalogowane', name ? null : 'dim');
        row('poziom Safeguard', lv || 'NIEZNANY', lv ? null : 'bad');
        row('gen terminala sieciowego', 'NIE WYKRYTO', 'bad');
        row('klasyfikacja', 'NIELEGALNY MIESZKANIEC', 'bad');
      },

      skan: function () {
        row('gen terminala sieciowego', 'NIE WYKRYTO', 'bad');
        say('Powtarzanie skanu nie wytworzy genu.', 'dim');
      },

      'idź': function (where) {
        if (!where) {
          places();
          return;
        }
        var key = PLACE_ALIASES[where] || where;
        if (!PLACES[key]) {
          say('Nie ma takiego miejsca: ' + where + '. Dostępne:', 'bad');
          places();
          return;
        }
        say('przechodzenie: ' + PLACES[key][1] + '…', 'ok');
        go(PLACES[key][0]);
      },

      man: function (name) {
        if (!name) {
          say('Podaj nazwę polecenia bota, np. man daily.', 'bad');
          return;
        }
        go('cmd/#' + encodeURIComponent(name));
      },

      czas: function () {
        var now = new Date().toLocaleString('pl-PL', {
          timeZone: 'Europe/Warsaw', day: '2-digit', month: '2-digit', year: 'numeric',
          hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
        row('czas sieci', now.replace(',', ''));
        row('czas Megastruktury', 'NIEZNANY', 'dim');
      },

      'wyczyść': function () { log.textContent = ''; },

      zamknij: function () { shut(); }
    };
    var aliases = {
      help: 'pomoc', '?': 'pomoc', scan: 'skan', netsphere: 'skan', idz: 'idź', cd: 'idź', go: 'idź',
      polecenie: 'man', date: 'czas', time: 'czas', wyczysc: 'wyczyść', clear: 'wyczyść', cls: 'wyczyść',
      exit: 'zamknij', quit: 'zamknij', q: 'zamknij'
    };

    function run(line) {
      var words = line.trim().split(/\s+/);
      var name = words[0].toLowerCase();
      if (!name) return;
      if (history[history.length - 1] !== line) history.push(line);

      add(el('p', 'ns-cmd', '> ' + line));
      var arg = (words[1] || '').toLowerCase();
      if (name === 'ls')
        places();
      else if (LORE[name])
        LORE[name].forEach(function (text) { say(text); });
      else if (commands[aliases[name] || name])
        commands[aliases[name] || name](arg);
      else
        say('Nieznane polecenie: ' + name + '. Wpisz pomoc.', 'bad');
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var line = input.value;
      input.value = '';
      back = 0;
      run(line);
    });

    input.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
      e.preventDefault();
      back = Math.max(0, Math.min(history.length, back + (e.key === 'ArrowUp' ? 1 : -1)));
      input.value = back ? history[history.length - back] : '';
      input.setSelectionRange(input.value.length, input.value.length);
    });

    say('Terminal awaryjny Safeguarda. Wpisz pomoc.', 'dim');
    input.focus();

    return input;
  }
})();
