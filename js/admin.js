// Admin panel: buttons and forms with data-action send that action to the
// panel and reload the page with the result (messages come from explorer.js)
(function () {
  var csrf = document.body.dataset.csrf;
  var gallery = window.SanakanGallery;

  function send(fields, controls) {
    var form = new FormData();
    form.append('csrf', csrf);
    Object.keys(fields).forEach(function (key) {
      form.append(key, fields[key]);
    });

    controls.forEach(function (control) { control.disabled = true; });

    fetch('index.php', { method: 'POST', body: form }).then(function (res) {
      return res.json();
    }).then(function (result) {
      if (result.ok) {
        gallery.flashAfterReload(result.message);
        keepScrolls();
        location.reload();
      } else {
        gallery.toast(result.message, true);
        controls.forEach(function (control) { control.disabled = false; });
      }
    }).catch(function () {
      gallery.toast('Serwer nie odpowiedział poprawnie.', true);
      controls.forEach(function (control) { control.disabled = false; });
    });
  }

  // A list that scrolls in its card (data-keep-scroll, e.g. the trash) is
  // where it was after the reload of a change; the browser keeps only the page
  var SCROLLS_KEY = 'panel-scrolls';

  function keepScrolls() {
    var tops = {};
    document.querySelectorAll('[data-keep-scroll]').forEach(function (el) {
      tops[el.id] = el.scrollTop;
    });
    try {
      sessionStorage.setItem(SCROLLS_KEY, JSON.stringify(tops));
    } catch (err) {
      // the lists start at the top then
    }
  }

  try {
    var tops = JSON.parse(sessionStorage.getItem(SCROLLS_KEY) || '{}');
    sessionStorage.removeItem(SCROLLS_KEY);
    Object.keys(tops).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.scrollTop = tops[id];
    });
  } catch (err) {
    // nothing kept
  }

  // ---- Date and time in the Polish way -------------------------------------------
  // The browser draws a datetime-local field in its own language, with AM/PM in
  // an English one. Each becomes a text field "05.10.2026 14:23" with a
  // calendar from Monday and a 24-hour clock; the form still sends
  // "2026-10-05T14:23", Polish time, from a hidden field of the same name.

  var MONTHS = ['Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec',
    'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
  var WEEKDAYS = ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So', 'Nd'];

  function pad(n) {
    return (n < 10 ? '0' : '') + n;
  }

  // a Date only holds the parts here (year ... minute), always read as Polish time
  function partsValue(d) {
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function partsText(d) {
    return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function fromValue(value) {
    var m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value);
    return m ? new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5]) : null;
  }

  // "5.10.2026 14:23", "05.10.2026 14.23", or only the date (midnight); null when wrong
  function fromText(text) {
    var m = /^\s*(\d{1,2})\.(\d{1,2})\.(\d{4})(?:[\s,]+(\d{1,2})[:.](\d{2}))?\s*$/.exec(text);
    if (!m) return null;
    var d = new Date(+m[3], m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0));
    var right = d.getDate() === +m[1] && d.getMonth() === m[2] - 1 && +(m[4] || 0) < 24 && +(m[5] || 0) < 60;
    return right ? d : null;
  }

  // now in Poland, whatever the zone of the browser
  function polishNow() {
    var parts = {};
    new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Europe/Warsaw', year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
    }).formatToParts(new Date()).forEach(function (part) { parts[part.type] = +part.value; });
    return new Date(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute);
  }

  function element(tag, className, text) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined) el.textContent = text;
    return el;
  }

  function polishDateField(input) {
    var hidden = element('input');
    hidden.type = 'hidden';
    hidden.name = input.name;
    hidden.value = input.value;

    var field = element('input', 'dt-field');
    field.type = 'text';
    field.autocomplete = 'off';
    field.placeholder = 'dd.mm.rrrr gg:mm';
    field.setAttribute('aria-label', input.getAttribute('aria-label') || input.name);

    var wrap = element('span', 'dt');
    input.parentNode.replaceChild(wrap, input);
    wrap.appendChild(field);
    wrap.appendChild(hidden);

    var current = fromValue(hidden.value);
    var view = current || polishNow();
    var pop = null;
    field.value = current ? partsText(current) : '';

    function set(d) {
      current = d;
      hidden.value = d ? partsValue(d) : '';
      field.value = d ? partsText(d) : '';
      field.classList.remove('invalid');
      if (d) view = new Date(d.getFullYear(), d.getMonth(), 1);
    }

    // what was typed, when the field is left
    field.addEventListener('change', function () {
      var text = field.value.trim();
      if (text === '') {
        set(null);
      } else if (fromText(text)) {
        set(fromText(text));
      } else {
        field.classList.add('invalid');
      }
      if (pop) render();
    });

    function render() {
      pop.textContent = '';

      var head = element('div', 'dt-head');
      var prev = element('button', 'dt-step', '‹');
      var next = element('button', 'dt-step', '›');
      prev.type = next.type = 'button';
      prev.setAttribute('aria-label', 'Poprzedni miesiąc');
      next.setAttribute('aria-label', 'Następny miesiąc');
      prev.addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); render(); });
      next.addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); render(); });
      head.appendChild(prev);
      head.appendChild(element('b', '', MONTHS[view.getMonth()] + ' ' + view.getFullYear()));
      head.appendChild(next);
      pop.appendChild(head);

      var grid = element('div', 'dt-grid');
      WEEKDAYS.forEach(function (day) { grid.appendChild(element('span', 'dt-weekday', day)); });
      // from the Monday of the week the month starts in, six weeks
      var first = new Date(view.getFullYear(), view.getMonth(), 1);
      var start = new Date(first.getFullYear(), first.getMonth(), 1 - (first.getDay() + 6) % 7);
      var today = polishNow();
      for (var i = 0; i < 42; i++) {
        var day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
        var button = element('button', 'dt-day', String(day.getDate()));
        button.type = 'button';
        if (day.getMonth() !== view.getMonth()) button.classList.add('other');
        if (day.getDay() === 0) button.classList.add('sunday');
        if (day.toDateString() === today.toDateString()) button.classList.add('today');
        if (current && day.toDateString() === current.toDateString()) button.classList.add('chosen');
        button.addEventListener('click', pick.bind(null, day));
        grid.appendChild(button);
      }
      pop.appendChild(grid);

      // the time, 0-23 and 0-59
      var time = element('div', 'dt-time');
      var shown = current || polishNow();
      var hour = element('input', 'dt-hour');
      var minute = element('input', 'dt-minute');
      [[hour, 23, shown.getHours(), 'Godzina'], [minute, 59, shown.getMinutes(), 'Minuta']].forEach(function (spec) {
        spec[0].type = 'number';
        spec[0].min = 0;
        spec[0].max = spec[1];
        spec[0].value = pad(spec[2]);
        spec[0].setAttribute('aria-label', spec[3]);
        spec[0].addEventListener('change', function () {
          var value = Math.max(0, Math.min(spec[1], parseInt(spec[0].value, 10) || 0));
          spec[0].value = pad(value);
          var base = current || polishNow();
          set(new Date(base.getFullYear(), base.getMonth(), base.getDate(),
            spec[0] === hour ? value : base.getHours(), spec[0] === minute ? value : base.getMinutes()));
        });
      });
      time.appendChild(element('span', '', 'Godzina'));
      time.appendChild(hour);
      time.appendChild(element('b', '', ':'));
      time.appendChild(minute);
      pop.appendChild(time);

      var actions = element('div', 'dt-actions');
      [['Teraz', function () { set(polishNow()); render(); }],
        ['Wyczyść', function () { set(null); close(); }],
        ['Gotowe', function () { close(); }]].forEach(function (spec, i) {
        var button = element('button', 'admin-btn small' + (i === 2 ? ' primary' : ''), spec[0]);
        button.type = 'button';
        button.addEventListener('click', spec[1]);
        actions.appendChild(button);
      });
      pop.appendChild(actions);

      function pick(day) {
        var base = current || polishNow();
        set(new Date(day.getFullYear(), day.getMonth(), day.getDate(), base.getHours(), base.getMinutes()));
        render();
      }
    }

    function open() {
      if (pop) return;
      pop = element('div', 'dt-pop hud-corners');
      pop.setAttribute('role', 'dialog');
      pop.setAttribute('aria-label', 'Wybór daty i godziny');
      render();
      wrap.appendChild(pop);
      document.addEventListener('mousedown', outside);
    }

    function close() {
      if (!pop) return;
      wrap.removeChild(pop);
      pop = null;
      document.removeEventListener('mousedown', outside);
    }

    function outside(e) {
      if (!wrap.contains(e.target)) close();
    }

    field.addEventListener('focus', open);
    field.addEventListener('click', open);
    field.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        close();
      } else if (e.key === 'Enter') {
        // takes what was typed instead of sending the form
        e.preventDefault();
        field.dispatchEvent(new Event('change'));
        if (!field.classList.contains('invalid')) close();
      }
    });
  }

  document.querySelectorAll('input[type="datetime-local"]').forEach(polishDateField);

  // a form with a wrong date is not sent (before the sending below)
  document.querySelectorAll('form[data-action]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var wrong = form.querySelector('.dt-field.invalid');
      if (!wrong) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      gallery.toast('Popraw datę: ' + wrong.value + ' (dd.mm.rrrr gg:mm).', true);
      wrong.focus();
    });
  });

  // ---- The server's clock ---------------------------------------------------------
  // It ticks on in the bot card, in the site's time zone, and says when it is
  // more than 2 s away from the clock of this computer.
  var clock = document.querySelector('[data-server-ms]');
  if (clock) {
    var navigation = performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
    // when the page started to arrive, by this computer's clock
    var arrived = navigation ? performance.timeOrigin + navigation.responseStart : Date.now();
    var offset = Number(clock.dataset.serverMs) - arrived;
    var clockFormat = new Intl.DateTimeFormat('pl-PL', {
      timeZone: clock.dataset.zone, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
    });
    var clockNow = clock.querySelector('.server-clock-now');
    var tick = function () { clockNow.textContent = clockFormat.format(new Date(Date.now() + offset)); };
    tick();
    setInterval(tick, 1000);

    var seconds = Math.round(offset / 1000);
    var drift = clock.querySelector('.server-clock-drift');
    if (Math.abs(seconds) >= 2) {
      drift.textContent = 'różni się od zegara tego komputera o ' + Math.abs(seconds) + ' s (serwer ' + (seconds > 0 ? 'się spieszy' : 'się spóźnia') + ')';
      drift.classList.add('warn');
    } else {
      drift.textContent = 'zgodny z zegarem tego komputera';
    }
  }

  // ---- The recent logins ----------------------------------------------------------
  // A row opens to where its rights come from and the buttons to give more;
  // the button above opens or closes them all.
  var loginButtons = Array.prototype.slice.call(document.querySelectorAll('.login-more'));
  var openAll = document.getElementById('logins-toggle');

  function openLogin(button, open) {
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.parentNode.querySelector('.login-details').hidden = !open;
  }

  loginButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      openLogin(button, button.getAttribute('aria-expanded') !== 'true');
    });
  });

  if (openAll) {
    openAll.addEventListener('click', function () {
      var open = openAll.getAttribute('aria-expanded') !== 'true';
      loginButtons.forEach(function (button) { openLogin(button, open); });
      openAll.setAttribute('aria-expanded', open ? 'true' : 'false');
      openAll.textContent = open ? 'Zwiń wszystkie' : 'Rozwiń wszystkie';
    });
  }

  // ---- The trash -------------------------------------------------------------------
  // "Podgląd" opens a row to the picture or film, or to those of a folder; they
  // load only the first time it opens, from index.php?kosz=ID.
  function trashUrl(id, path) {
    return 'index.php?kosz=' + encodeURIComponent(id) + (path ? '&plik=' + encodeURIComponent(path) : '');
  }

  function trashMedia(url, video, small) {
    var media = document.createElement(video ? 'video' : 'img');
    media.src = url;
    if (video) {
      media.preload = 'metadata';
      media.muted = small;
      media.controls = !small;
    } else {
      media.alt = '';
      media.loading = 'lazy';
    }
    return media;
  }

  function trashNote(preview, text) {
    var note = document.createElement('p');
    note.className = 'hint';
    note.textContent = text;
    preview.appendChild(note);
  }

  function fillTrashPreview(button, preview) {
    var id = button.dataset.item;
    if (button.dataset.kind !== 'folder') {
      var link = document.createElement('a');
      link.href = trashUrl(id);
      link.target = '_blank';
      link.className = 'trash-full';
      link.appendChild(trashMedia(link.href, button.dataset.kind === 'video', false));
      preview.appendChild(link);
      return;
    }

    trashNote(preview, 'Wczytywanie…');
    fetch(trashUrl(id) + '&pliki').then(function (res) {
      return res.json();
    }).then(function (result) {
      preview.textContent = '';
      if (!result.files.length) {
        trashNote(preview, 'W folderze nie ma obrazków ani filmów.');
        return;
      }
      var grid = document.createElement('div');
      grid.className = 'trash-grid';
      result.files.forEach(function (file) {
        var tile = document.createElement('a');
        tile.href = trashUrl(id, file.path);
        tile.target = '_blank';
        tile.title = file.path + ' · ' + file.size;
        tile.appendChild(trashMedia(tile.href, file.video, true));
        grid.appendChild(tile);
      });
      preview.appendChild(grid);
      if (result.count > result.files.length)
        trashNote(preview, 'Pokazano ' + result.files.length + ' z ' + result.count + ' plików.');
    }).catch(function () {
      preview.textContent = '';
      delete preview.dataset.filled;
      trashNote(preview, 'Nie udało się wczytać zawartości folderu.');
    });
  }

  document.querySelectorAll('.trash-peek').forEach(function (button) {
    button.addEventListener('click', function () {
      var preview = button.closest('.trash-row').querySelector('.trash-preview');
      var open = button.getAttribute('aria-expanded') !== 'true';
      if (open && !preview.dataset.filled) {
        preview.dataset.filled = '1';
        preview.textContent = '';
        fillTrashPreview(button, preview);
      }
      if (!open)
        preview.querySelectorAll('video').forEach(function (video) { video.pause(); });
      preview.hidden = !open;
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      button.textContent = open ? 'Zwiń' : 'Podgląd';
    });
  });

  // buttons: the action is in data-action, its fields in the other data-* attributes
  // (data-confirm asks first)
  document.querySelectorAll('button[data-action]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) return;

      var fields = {};
      Object.keys(button.dataset).forEach(function (key) {
        if (key !== 'confirm') fields[key] = button.dataset[key];
      });
      send(fields, [button]);
    });
  });

  // forms: the action is in data-action, the fields are the inputs
  // (a checkbox sends 1 or 0)
  document.querySelectorAll('form[data-action]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fields = { action: form.dataset.action };
      Array.prototype.forEach.call(form.elements, function (field) {
        if (!field.name) return;
        fields[field.name] = field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value.trim();
      });
      send(fields, Array.prototype.slice.call(form.querySelectorAll('button')));
    });
  });
})();
