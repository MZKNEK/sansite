// Easter egg from BLAME!: typing "netsphere" anywhere on the site (or tapping
// the SAFEGUARD tag five times, for phones) asks the Netsphere for access. The
// Safeguard scans for the Net Terminal Gene, never finds it, and the visitor is
// marked an illegal resident. A logged-in account shows its Safeguard level from
// the account menu, which does not help either.
(function () {
  var WORD = 'netsphere';
  var TAPS = 5;
  var TAP_WINDOW = 2000; // ms for the taps on the tag
  var STEP = 520;        // ms between the lines of the scan

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
      }
    ];

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
        // the only thing to focus inside is the button
        e.preventDefault();
        close.focus();
      }
    }

    close.addEventListener('click', shut);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) shut();
    });
    document.addEventListener('keydown', key, true);
    open = shut;
  }
})();
