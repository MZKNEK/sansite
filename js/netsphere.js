// Easter egg from BLAME!: typing "netsphere" anywhere on the site (or tapping
// the SAFEGUARD tag five times, for phones) asks the Netsphere for access. The
// Safeguard scans for the Net Terminal Gene, does not find it, and the visitor
// is marked an illegal resident; only a browser that has given the terminal
// the gene (inc/megastructure.php) is let in. A logged-in account shows its Safeguard level from
// the account menu, which does not help either. After the verdict the Safeguard
// leaves its emergency terminal open: a prompt with a few commands that answer
// with the site's real data (status, ping, whoami, going to a page) and some
// lore of the Megastructure. The scan, five entries of the lore and the
// password from the X-Safeguard-Haslo-Awaryjne header (sendSafeguardHeader()
// in inc/megastructure.php, "toha-" and four characters of the site's
// secret, typed as a command) are finds of the Safeguard's register
// (js/safeguard.js).
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

    // a browser that has given the terminal the gene (inc/megastructure.php)
    // is let in, the only one
    var legal = hasGene();
    var gene = legal
      ? line('gen terminala sieciowego', 'WYKRYTO', 'ok')
      : line('gen terminala sieciowego', 'NIE WYKRYTO', 'bad');
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
      function () { bar.classList.add(legal ? 'pass' : 'fail'); log.appendChild(gene); },
      function () { log.appendChild(line('poziom Safeguard', lv || 'NIEZNANY', lv ? '' : 'bad')); },
      function () {
        if (legal) {
          overlay.classList.add('granted');
          log.appendChild(el('p', 'ns-verdict', 'DOSTĘP PRZYZNANY'));
        } else {
          overlay.classList.add('alarm');
          log.appendChild(el('p', 'ns-verdict', 'NIELEGALNY MIESZKANIEC'));
        }
        note('netsphere');
      },
      function () {
        log.appendChild(el('p', 'ns-note', legal
          ? 'Uprawniony użytkownik Netsfery. Safeguard opuszcza broń.'
          : dev
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

  // a find for the Safeguard's register (js/safeguard.js), when it is on the
  // page: with the proof of an answer of megastructure.php, or witnessed by it
  function note(key, proof) {
    if (!window.SanakanSafeguard) return;
    if (proof) SanakanSafeguard.found(key, proof);
    else SanakanSafeguard.witness(key);
  }

  // whether this browser has found the Net Terminal Gene
  function hasGene() {
    return !!(window.SanakanSafeguard && SanakanSafeguard.finds().gene);
  }

  // ---- The emergency terminal ----------------------------------------------

  // where "idź" goes: [path from the site's root, or a full address, name]
  var PLACES = {
    start: ['', 'strona główna'],
    polecenia: ['cmd/', 'polecenia bota'],
    zmiany: ['cmd/changes/', 'zmiany w poleceniach'],
    status: ['state/', 'status bota'],
    api: ['api/', 'dokumentacja API'],
    galeria: ['i/', 'galeria'],
    profil: ['account/', 'twój profil'],
    panel: ['admin/', 'panel'],
    prywatnosc: ['privacy/', 'prywatność'],
    rejestr: ['account/#rejestr', 'rejestr Safeguarda w profilu'],
    lowcy: ['hunters/', 'ranking łowców sekretów'],
    wiki: ['https://wiki.sanakan.pl', 'wiki']
  };
  var PLACE_ALIASES = {
    '~': 'start', '/': 'start', home: 'start', cmd: 'polecenia', state: 'status', i: 'galeria',
    account: 'profil', admin: 'panel', privacy: 'prywatnosc', 'prywatność': 'prywatnosc',
    'łowcy': 'lowcy', hunters: 'lowcy', ranking: 'lowcy'
  };

  // commands not in the help, answered with a few lines of BLAME!; five of
  // them read, in any visits, make a find of the register
  var LORE = {
    killy: ['Wykryto: Killy. Wyposażenie: Emiter Promienia Grawitacyjnego.', 'Zalecenie Safeguarda: nie stawać na linii strzału.'],
    cibo: ['Cibo, naukowczyni. Szuka genu terminala sieciowego razem z Killym.', 'Poza kontrolą Safeguarda.'],
    sanakan: ['Sanakan: Safeguard poziomu 9 w ludzkiej postaci.', 'Ten bot nosi jej imię. Jest mniej groźny.'],
    sudo: ['Safeguard nie zna słowa sudo.', 'Bez genu terminala sieciowego uprawnień nie da się podnieść.'],
    rm: ['Megastruktury nie da się usunąć.', 'Nikt jeszcze nie spróbował z całą siłą, od samego korzenia.'],
    budowniczy: ['Budowniczowie: maszyny, które bez końca rozbudowują Megastrukturę.', 'Nikt już nie umie wydać im rozkazu, żeby przestali.'],
    administracja: ['Administracja Netsfery straciła kontrolę nad Safeguardem i Budowniczymi,', 'gdy ludzie stracili gen terminala sieciowego.'],
    silikon: ['Formy krzemowe: wróg ludzi i Safeguarda. Też szukają genu.', 'Podobno wpuszcza je do sieci stary kod z 1986 roku.'],
    dhomochevsky: ['Dhomochevsky: forma krzemowa, która walczyła u boku Killy\'ego.', 'Wróg twojego wroga nie musi być przyjacielem.'],
    emiter: ['Emiter Promienia Grawitacyjnego przebija wszystko na swojej drodze.', 'Na stronie głównej ktoś zostawił jeden przy kropce statusu. Trzeba go przytrzymać.'],
    swieta: ['Safeguard nie obchodzi świąt.', 'Budowniczowie też nie robią przerwy, nawet w Wigilię.'],
    pisanka: ['Kury wyginęły w Megastrukturze kilka tysięcy pięter temu.', 'Na Wielkanoc Safeguard i tak chowa pisanki na stronach. Ma swoje powody.'],
    urodziny: ['Sanakan uruchomiono 13 października 2017 roku.', 'Safeguard nie liczy lat. W ten dzień na stronie głównej i tak stoi tort.'],
    halloween: ['31 października formy krzemowe wychodzą na krawędzie strony.', 'Safeguard odnotowuje każdą złapaną. Cukierków nie rozdaje.'],
    sylwester: ['Megastruktura nie zna lat, tylko cykle Budowniczych.', 'W Sylwestra Safeguard i tak zostawia przy logo rakietę.'],
    pingpong: ['Safeguard odpowiada na PING. Odeślij mu odpowiedź Morse\'em na stronie głównej.', 'Do pięciu punktów. Safeguard przyjmuje wyzwania przez cały rok.'],
    piatek: ['W piątek trzynastego usterek w Megastrukturze jest więcej.', 'Safeguard je łapie. Ty też możesz spróbować.'],
    zero: ['Mówią, że pod najniższym piętrem jest jeszcze jedno, piętro zero.', 'Prowadzi tam tylko korytarz Budowniczych, a ich drzwi otwierają się o trzeciej.']
  };
  var LORE_ALIASES = { 'święta': 'swieta', 'mikołaj': 'swieta', mikolaj: 'swieta', jajko: 'pisanka', pisanki: 'pisanka', tort: 'urodziny', birthday: 'urodziny', dynia: 'halloween', psikus: 'halloween', nowyrok: 'sylwester', fajerwerki: 'sylwester', 'ping-pong': 'pingpong', ping_pong: 'pingpong', prima: 'pingpong', aprilis: 'pingpong', 'piątek': 'piatek', trzynasty: 'piatek', usterka: 'piatek', 'piętro': 'zero', pietro: 'zero', drzwi: 'zero', gbe: 'emiter', budowniczowie: 'budowniczy', 'formy': 'silikon', krzem: 'silikon', zarzad: 'administracja', 'zarząd': 'administracja' };
  var LORE_FIND = 5;
  var LORE_STORE = 'safeguard-lore';

  // the lore read so far, kept in the browser; five of it is a find
  function readLore(key) {
    var read = [];
    try {
      read = JSON.parse(SanakanDiscoveryStorage.getItem(LORE_STORE)) || [];
    } catch (err) {
      // private mode: only this visit counts
    }
    if (!Array.isArray(read)) read = [];
    if (read.indexOf(key) < 0) read.push(key);
    try {
      SanakanDiscoveryStorage.setItem(LORE_STORE, JSON.stringify(read));
    } catch (err) {
      // the same
    }
    if (read.length >= LORE_FIND) note('lore');
  }

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

    // the way to the gene is an account's (inc/megastructure.php): without
    // one, megastructure.php answers only that it does not know who asks
    function stranger(answer) {
      if (!answer || !answer.stranger) return false;
      row('tożsamość', 'NIEZNANA', 'bad');
      say('Safeguard nie rozmawia z obcymi. Zaloguj się.', 'dim');
      return true;
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

    // The Megastructure removed from its root: its machinery fails one part
    // after another, and the failure page (megastructure.php?awaria, the
    // site's 500 page) is what is left, with what it hides (js/500.js)
    function removeAll() {
      var parts = ['/inc', '/js', '/css', '/i', '/cmd', '/state'];
      parts.forEach(function (part, i) {
        setTimeout(function () { row('usuwanie ' + part, 'BŁĄD', 'bad'); }, (i + 1) * 260);
      });
      setTimeout(function () {
        say('Maszyneria Megastruktury przestaje odpowiadać.', 'bad');
      }, (parts.length + 1) * 260);
      setTimeout(function () { go('megastructure.php?awaria'); }, (parts.length + 1) * 260 + 1100);
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
          ['rejestr', 'twoje znaleziska dla Safeguarda'],
          ['czas', 'czas sieci'],
          ['wyczyść', 'czyści ekran'],
          ['zamknij', 'zamyka terminal (Esc)']
        ].concat(hasGene() ? [['netsfera', 'wejście do Netsfery']] : []).forEach(function (help) { row(help[0], help[1], null, 'ns-help'); });
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
        if (hasGene()) {
          row('gen terminala sieciowego', 'WYKRYTO', 'ok');
          row('klasyfikacja', 'UPRAWNIONY UŻYTKOWNIK NETSFERY', 'ok');
        } else {
          row('gen terminala sieciowego', 'NIE WYKRYTO', 'bad');
          row('klasyfikacja', 'NIELEGALNY MIESZKANIEC', 'bad');
        }
      },

      skan: function () {
        if (hasGene()) {
          row('gen terminala sieciowego', 'WYKRYTO', 'ok');
          say('Sekwencja przyjęta wcześniej. Safeguard pamięta.', 'dim');
          return;
        }
        row('gen terminala sieciowego', 'NIE WYKRYTO', 'bad');
        say('Powtarzanie skanu nie wytworzy genu.', 'dim');
      },

      // The Net Terminal Gene (inc/megastructure.php): without a sequence the
      // fragments the account has earned; with one, megastructure.php says whether
      // it is the gene, the one time the Safeguard lets anyone in
      gen: function (arg, sequence) {
        if (!window.SanakanSafeguard) {
          say('Rejestr Safeguarda nie odpowiada.', 'bad');
          return;
        }
        if (!sequence) {
          SanakanSafeguard.mega({ fragments: 1 }).then(function (answer) {
            if (stranger(answer)) return;
            if (!answer || !answer.fragments) {
              say('Rejestr Safeguarda nie odpowiada.', 'bad');
              return;
            }
            var kept = answer.fragments;
            [1, 2, 3, 4].forEach(function (n) {
              row('fragment ' + n + '/4', kept[n] || '????', kept[n] ? 'ok' : 'dim');
            });
            say('Złożoną sekwencję podaj tak: gen XXXX-XXXX-XXXX-XXXX', 'dim');
          });
          return;
        }
        say('weryfikacja sekwencji…', 'dim');
        SanakanSafeguard.mega({ gene: sequence }).then(function (answer) {
          if (stranger(answer)) return;
          if (!answer || !answer.ok) {
            row('sekwencja', 'ODRZUCONA', 'bad');
            row('gen terminala sieciowego', 'NIE WYKRYTO', 'bad');
            return;
          }
          var steps = [
            ['sekwencja', 'PRZYJĘTA', 'ok'],
            ['gen terminala sieciowego', 'WYKRYTO', 'ok'],
            ['klasyfikacja', 'UPRAWNIONY UŻYTKOWNIK NETSFERY', 'ok'],
            ['dostęp do Netsfery', 'PRZYZNANY', 'ok']
          ];
          steps.forEach(function (step, i) {
            setTimeout(function () {
              row(step[0], step[1], step[2]);
              if (i === steps.length - 1) {
                say('Safeguard opuszcza broń. Pierwszy raz od tysięcy lat ktoś ma prawo tu być.');
                SanakanSafeguard.found('gene', answer.proof);
              }
            }, (i + 1) * STEP);
          });
        });
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

      // the finds this browser knows; the dates and the hints in the profile
      rejestr: function () {
        if (!window.SanakanSafeguard) {
          say('Rejestr Safeguarda nie odpowiada.', 'bad');
          return;
        }
        var finds = SanakanSafeguard.finds();
        var names = SanakanSafeguard.names;
        Object.keys(names).forEach(function (key) {
          if (finds[key]) row(names[key], 'ZNALEZIONE', 'ok');
          else row('wpis niezidentyfikowany', '—', 'dim');
        });
        row('razem', Object.keys(finds).length + ' z ' + Object.keys(names).length);
        say('Wskazówki do reszty: idź rejestr', 'dim');
      },

      // the password the server gives in its X-Safeguard-Haslo-Awaryjne
      // header, "toha-" and four characters; megastructure.php checks it
      toha: function (arg, rest, word) {
        if (!window.SanakanSafeguard || word === 'toha') {
          say('Hasło awaryjne Toha Heavy Industries: NIEPEŁNE.', 'bad');
          say('Serwer podaje całe hasło tym, którzy go słuchają.', 'dim');
          return;
        }
        SanakanSafeguard.witness('header', { word: word }).then(function (ok) {
          if (!ok) {
            say('Hasło awaryjne Toha Heavy Industries: ODRZUCONE.', 'bad');
            return;
          }
          say('Hasło awaryjne Toha Heavy Industries: PRZYJĘTE.', 'ok');
          say('Hasło się zgadza. Genu terminala sieciowego nadal brak.');
        });
      },

      // Past the gene (inc/megastructure.php): with the gene the Netsphere
      // opens, an address of its own; with the archive's last record,
      // deciphered, the Netsphere knows its own
      netsfera: function (arg, rest) {
        if (!window.SanakanSafeguard) {
          say('Rejestr Safeguarda nie odpowiada.', 'bad');
          return;
        }
        var line = rest || '';
        var compact = line.replace(/[\s-]/g, '');
        var sequence = /^[0-9a-f]{16}$/i.test(compact) ? compact : null;
        if (!sequence && !line) {
          var kept = SanakanSafeguard.fragments();
          if ([1, 2, 3, 4].every(function (n) { return kept[n]; }))
            sequence = [1, 2, 3, 4].map(function (n) { return kept[n]; }).join('');
        }
        if (!line && !sequence) {
          row('gen terminala sieciowego', hasGene() ? 'PODAJ SEKWENCJĘ' : 'NIE WYKRYTO', 'bad');
          say('Netsfera wpuszcza tylko z genem: netsfera XXXX-XXXX-XXXX-XXXX', 'dim');
          return;
        }
        if (sequence) {
          say('łączenie z Netsferą…', 'dim');
          SanakanSafeguard.mega({ enter: sequence }).then(function (answer) {
            if (stranger(answer)) return;
            if (!answer || !answer.archive) {
              row('dostęp do Netsfery', 'ODMOWA', 'bad');
              return;
            }
            row('dostęp do Netsfery', 'PRZYZNANY', 'ok');
            say('przechodzenie: archiwum Administracji…', 'ok');
            setTimeout(function () { go(answer.archive.replace(/^\//, '')); }, 900);
          });
          return;
        }
        say('weryfikacja zapisu…', 'dim');
        SanakanSafeguard.mega({ record: line }).then(function (answer) {
          if (stranger(answer)) return;
          if (!answer || !answer.ok) {
            row('zapis', 'NIEROZPOZNANY', 'bad');
            return;
          }
          row('zapis', 'ROZPOZNANY', 'ok');
          row('klasyfikacja', 'ADMINISTRATOR NETSFERY', 'ok');
          say('Netsfera rozpoznała swojego. Safeguard melduje się do rozkazów.');
          note('admin', answer.proof);
        });
      },

      'wyczyść': function () { log.textContent = ''; },

      zamknij: function () { shut(); }
    };
    var aliases = {
      help: 'pomoc', '?': 'pomoc', scan: 'skan', netsphere: 'skan', idz: 'idź', cd: 'idź', go: 'idź',
      polecenie: 'man', date: 'czas', time: 'czas', wyczysc: 'wyczyść', clear: 'wyczyść', cls: 'wyczyść',
      exit: 'zamknij', quit: 'zamknij', q: 'zamknij', register: 'rejestr', gene: 'gen', sekwencja: 'gen'
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
      else if (name === 'rm' && /^-(?:rf|fr)$/i.test(words[1] || '') && /^\/\*?$/.test(words[2] || ''))
        removeAll();
      else if (LORE[LORE_ALIASES[name] || name]) {
        var entry = LORE_ALIASES[name] || name;
        LORE[entry].forEach(function (text) { say(text); });
        readLore(entry);
      }
      else if (/^toha(-|$)/.test(name))
        commands.toha(arg, words.slice(1).join(' '), name);
      else if (commands[aliases[name] || name])
        commands[aliases[name] || name](arg, words.slice(1).join(' '));
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
