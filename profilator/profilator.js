// Profilator's page: the settings, the preview drawn by profile.js, the code
// and the command for the bot. The code also lives in the address (#code), so
// a link opens the same profile. The pictures picked stay in the browser,
// unless they are put in the code: then they go to the folder Profilator of
// the account's gallery (i/, action profilator, named by their content so one
// sent again is not kept twice) and the code carries their links.
(function () {
  'use strict';

  var root = document.getElementById('profilator');
  var P = window.Profile;
  if (!root || !P)
    return;

  var canvas = document.getElementById('profile-canvas');
  var command = document.getElementById('profile-command');
  var copy = document.getElementById('profile-copy');
  var note = document.getElementById('profile-note');
  var codeInput = document.getElementById('code-input');
  var codeState = document.getElementById('code-state');
  var styleSelect = document.getElementById('style-select');
  var borderSelect = document.getElementById('border-select');
  var styleNote = document.getElementById('style-note');
  var shadowsRange = document.getElementById('shadows-range');
  var shadowsValue = document.getElementById('shadows-value');
  var shadowsNote = document.getElementById('shadows-note');
  var inCode = document.getElementById('images-in-code');
  var login = document.getElementById('profile-login');
  var source = document.getElementById('profile-source');
  var switches = Array.prototype.slice.call(root.querySelectorAll('[data-flag]'));
  var rows = Array.prototype.slice.call(root.querySelectorAll('[data-image]'));
  var special = borderSelect.querySelector('option[value="TurqLeaves"]');

  var levels = {};
  try {
    levels = JSON.parse(root.getAttribute('data-levels') || '{}');
  } catch (e) {
    levels = {};
  }
  var prefix = root.getAttribute('data-prefix') || '';
  var own = root.getAttribute('data-own') === '1';
  var csrf = root.getAttribute('data-csrf') || '';

  var state = { style: 'Stats', border: 'Base', flags: P.DEFAULT_FLAGS, shadows: 30 };
  var pictures = {};
  var assets = { levels: levels, pic: function (name) { return pictures[name] || null; } };

  // the pictures by their place in the code: { img, file, url, status, parts }
  var slots = {};
  var withImages = false;

  var NAMES = { combined: 'tło i styl', background: 'tło', style: 'obrazek stylu', overlay: 'nakładka', premium: 'ultra nakładka' };
  var PRICES = { combined: '10000 SC / 4000 TC', background: '5000 SC / 2500 TC', style: '3000 SC / 1000 TC', overlay: '4000 SC / 1800 TC', premium: '120000 SC / 25000 TC' };

  // made up, apart from what the logged-in account lends
  var data = {
    nick: root.getAttribute('data-nick') || 'Twój nick',
    rank: 'Użytkownik',
    color: root.getAttribute('data-color') || '#546e7a',
    avatar: null,
    level: 42, exp: 14813, top: 17,
    messages: 25000, sc: 150000, tc: 920, ac: 30, ct: 400, pc: 75,
    cards: 2833, limit: 3000, karma: 0,
    rarities: { SSS: 12, SS: 40, S: 95, A: 210, B: 380, C: 600, D: 700, E: 793 },
    scalpels: 18, scissors: 6, ultimates: 3,
    waifu: null, gallery: [],
    anime: { inProgress: 12, completed: 340, skipped: 4, onHold: 9, dropped: 21, inPlan: 118, total: 504, rating: 7.9, count: 4210, time: { years: 0, months: 3, days: 12, hours: 5, minutes: 0 } },
    manga: { inProgress: 5, completed: 61, skipped: 0, onHold: 2, dropped: 3, inPlan: 40, total: 111, rating: 8.2, count: 5320, time: { years: 0, months: 1, days: 4, hours: 0, minutes: 30 } }
  };

  function load(src) {
    return new Promise(function (resolve) {
      if (!src)
        return resolve(null);
      var img = new Image();
      img.onload = function () { resolve(img); };
      img.onerror = function () { resolve(null); };
      img.src = src;
    });
  }

  // the background and the style cut from one picture as the bot cuts it
  // (SplitImageToBackgroundAndStyleAsync()): a bigger one made smaller keeping
  // its shape, a smaller one stretched to 750 x 500; null when the bot could
  // not cut it (a bigger one not shaped 3:2)
  function split(img) {
    var w = img.naturalWidth;
    var h = img.naturalHeight;
    if (w > 750 || h > 500) {
      var k = Math.min(750 / w, 500 / h);
      w = Math.round(w * k);
      h = Math.round(h * k);
    } else if (w < 750 || h < 500) {
      w = 750;
      h = 500;
    }
    if (w < 750 || h < 500)
      return null;
    function part(y, height) {
      var c = document.createElement('canvas');
      c.width = 750;
      c.height = height;
      c.getContext('2d').drawImage(img, 0, -y, w, h);
      return c;
    }
    return { top: part(0, 160), bottom: part(160, 340) };
  }

  function previewImages() {
    var images = {};
    var combined = slots.combined && slots.combined.parts;
    if (combined) {
      images.background = combined.top;
      images.style = combined.bottom;
    } else {
      if (slots.background && slots.background.img) images.background = slots.background.img;
      if (slots.style && slots.style.img) images.style = slots.style.img;
    }
    if (slots.overlay && slots.overlay.img) images.overlay = slots.overlay.img;
    if (slots.premium && slots.premium.img) images.premium = slots.premium.img;
    return images;
  }

  // the links that go into the code: only with the images in it, and those
  // of the style only on a style that has a picture
  function codeImages() {
    var images = {};
    if (!withImages)
      return images;
    // the account's own pictures stay where they are and cost nothing, so they are left out
    P.IMAGES.forEach(function (name) {
      if (slots[name] && slots[name].url && !slots[name].current)
        images[name] = slots[name].url;
    });
    if (!P.styleNeedsImage(state.style)) {
      delete images.style;
      delete images.combined;
    }
    return images;
  }

  function sending() {
    return withImages && P.IMAGES.some(function (name) { return slots[name] && slots[name].uploading; });
  }

  var drawing = false;
  function redraw() {
    if (drawing)
      return;
    drawing = true;
    requestAnimationFrame(function () {
      drawing = false;
      P.render(canvas, assets, state, data, previewImages());
    });
  }

  // --- the controls ---

  function flagName(input) {
    return P.FLAG[input.getAttribute('data-flag')];
  }

  // a switch of the site (css/explorer.css): its box and WŁ / WYŁ beside it
  function setSwitch(input, on) {
    input.checked = on;
    input.parentNode.querySelector('.switch-state').textContent = on ? 'WŁ' : 'WYŁ';
  }

  function showRows() {
    rows.forEach(function (row) {
      var slot = slots[row.getAttribute('data-image')];
      var label = row.querySelector('[data-image-file]');
      var text = 'nie wybrano';
      if (slot)
        text = (slot.file ? slot.file.name : slot.current ? 'twój' : 'z kodu') + (slot.status ? ' · ' + slot.status : '');
      label.textContent = text;
      label.title = slot && slot.url ? slot.url : '';
      row.querySelector('[data-image-clear]').hidden = !slot;
    });
  }

  function update() {
    var images = codeImages();
    var code = P.encode({ style: state.style, border: state.border, flags: state.flags, shadows: state.shadows, images: images });
    styleSelect.value = state.style;
    special.hidden = state.border !== 'TurqLeaves';
    borderSelect.value = state.border;
    shadowsRange.value = String(state.shadows);
    shadowsValue.textContent = state.shadows + '%';
    setSwitch(inCode, withImages);

    switches.forEach(function (input) {
      var flag = flagName(input);
      setSwitch(input, (state.flags & flag) === flag);
      input.parentNode.classList.toggle('idle', !P.settingWorks(flag, state.style));
    });

    var needsImage = P.styleNeedsImage(state.style);
    var styleText = needsImage
      ? 'Ten styl używa obrazka 750 × 340 px. Bez obrazka stylu w kodzie bot zostawi ten, który już masz, albo weźmie nowy z załącznika polecenia.'
      : '';
    var borderText = state.border === 'TurqLeaves' ? 'Ramkę specjalną bot ustawi tylko wtedy, gdy już ją masz.'
      : state.border === 'None' || state.border === 'Base' ? 'Ta ramka jest darmowa.'
      : 'Ta ramka kosztuje 25000 SC albo 8000 TC, jeśli jeszcze jej nie masz.';
    styleText += (styleText ? ' ' : '') + borderText;
    styleNote.textContent = styleText;
    styleNote.hidden = !styleText;

    shadowsNote.textContent = P.configurableStyle(state.style)
      ? 'Cienie są pod panelami stylów na obrazku, a z przełącznikiem Przeźroczysty pasek także pod paskiem.'
      : 'Ten styl nie ma paneli, przeźroczystość działa tylko na pasku z przełącznikiem Przeźroczysty pasek.';

    var waiting = sending();
    command.value = waiting ? 'Wysyłanie obrazków do galerii…' : prefix + 'konp kod ' + code;
    copy.disabled = waiting;

    var notes = [];
    var styleSlot = slots.combined || slots.style;
    if (withImages) {
      var paid = Object.keys(images).map(function (name) { return NAMES[name] + ' ' + PRICES[name]; });
      if (paid.length)
        notes.push('Każdy obrazek z kodu bot liczy jak jego osobną zmianę: ' + paid.join(', ') + '. Kod z obrazkami jest dłuższy, to normalne.');
      if (styleSlot && !needsImage)
        notes.push('Wybrany styl nie używa obrazka, więc obrazek stylu nie trafi do kodu.');
    } else if (styleSlot && styleSlot.file) {
      notes.push(needsImage
        ? 'Włącz obrazki w kodzie albo dołącz obrazek stylu do wiadomości z poleceniem, bez niego bot zostawi obecny.'
        : 'Wybrany styl nie używa obrazka, więc nie dołączaj go do polecenia.');
    }
    note.textContent = notes.join(' ');
    note.hidden = !notes.length;

    showRows();
    if (!waiting && location.hash.slice(1) !== code)
      history.replaceState(null, '', '#' + code);
    // a login comes back to the same profile
    if (login)
      login.href = login.getAttribute('data-login') + encodeURIComponent(location.pathname + location.hash);
    redraw();
  }

  function apply(next) {
    state = { style: next.style, border: next.border, flags: next.flags, shadows: next.shadows };
    update();
  }

  styleSelect.addEventListener('change', function () {
    state.style = styleSelect.value;
    update();
  });

  borderSelect.addEventListener('change', function () {
    state.border = borderSelect.value;
    update();
  });

  shadowsRange.addEventListener('input', function () {
    state.shadows = Number(shadowsRange.value);
    update();
  });

  switches.forEach(function (input) {
    input.addEventListener('change', function () {
      state.flags ^= flagName(input);
      update();
    });
  });

  // --- the pictures ---

  function forget(name) {
    var slot = slots[name];
    if (slot && slot.objectUrl)
      URL.revokeObjectURL(slot.objectUrl);
    delete slots[name];
  }

  // one picture in its place; the background and style together replace the
  // two apart, and either of those replaces it
  function put(name, slot) {
    forget(name);
    if (name === 'combined') {
      forget('background');
      forget('style');
    } else if (name === 'background' || name === 'style') {
      forget('combined');
    }
    slots[name] = slot;
  }

  // the gallery's link of a picture picked here, sent once with a name of its content
  function upload(name) {
    var slot = slots[name];
    if (!slot || !slot.file || slot.url || slot.uploading || slot.failed)
      return;
    if (!own) {
      slot.status = 'do kodu wymaga własnego folderu w galerii';
      slot.failed = true;
      return;
    }
    slot.uploading = true;
    slot.status = 'wysyłanie do galerii…';
    var ext = (slot.file.name.split('.').pop() || 'png').toLowerCase().replace(/[^a-z0-9]/g, '') || 'png';
    slot.file.arrayBuffer().then(function (buffer) {
      return crypto.subtle.digest('SHA-256', buffer);
    }).then(function (hash) {
      var hex = Array.prototype.map.call(new Uint8Array(hash).slice(0, 8), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
      var form = new FormData();
      form.append('action', 'profilator');
      form.append('csrf', csrf);
      form.append('file', slot.file, hex + '.' + ext);
      return fetch('../i/', { method: 'POST', body: form, credentials: 'same-origin' });
    }).then(function (res) {
      return res.json().catch(function () { return null; });
    }).then(function (answer) {
      if (slots[name] !== slot)
        return;
      slot.uploading = false;
      if (answer && answer.ok && answer.url) {
        // the gallery answers with a path of the site, the bot needs the whole address
        slot.url = new URL(answer.url, location.href).href;
        slot.status = 'w galerii';
      } else {
        slot.failed = true;
        slot.status = (answer && answer.message) || 'nie udało się wysłać do galerii';
      }
      update();
    }, function () {
      if (slots[name] !== slot)
        return;
      slot.uploading = false;
      slot.failed = true;
      slot.status = 'nie udało się wysłać do galerii';
      update();
    });
  }

  function uploadAll() {
    if (withImages)
      P.IMAGES.forEach(upload);
  }

  // a picture shown from its file or link; the one of both parts is cut as the bot cuts it
  function show(name, slot, src) {
    load(src).then(function (img) {
      if (slots[name] !== slot)
        return;
      if (!img) {
        slot.status = slot.file ? 'to nie jest obrazek' : 'nie da się go pokazać, link mógł wygasnąć';
        if (slot.file)
          forget(name);
        update();
        return;
      }
      slot.img = img;
      if (name === 'combined') {
        slot.parts = split(img);
        if (!slot.parts) {
          slot.status = 'bot nie podzieli go: większy obrazek musi mieć proporcje 3:2';
          slot.failed = true;
        }
      }
      if (withImages && slot.file && !slot.failed)
        upload(name);
      update();
    });
  }

  rows.forEach(function (row) {
    var name = row.getAttribute('data-image');
    var input = row.querySelector('input[type="file"]');

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      input.value = '';
      if (!file || !/^image\//.test(file.type))
        return;
      var slot = { file: file, objectUrl: URL.createObjectURL(file), status: '' };
      put(name, slot);
      show(name, slot, slot.objectUrl);
      update();
    });

    row.querySelector('[data-image-clear]').addEventListener('click', function () {
      forget(name);
      update();
    });
  });

  inCode.addEventListener('change', function () {
    withImages = inCode.checked;
    uploadAll();
    update();
  });

  // --- the code ---

  // current: the account's own profile, whose pictures it already has
  function loadCode(decoded, current) {
    P.IMAGES.forEach(forget);
    Object.keys(decoded.images).forEach(function (name) {
      var slot = { url: decoded.images[name], status: current ? 'obecny' : '', current: !!current };
      slots[name] = slot;
      show(name, slot, slot.url);
    });
    if (!current && Object.keys(decoded.images).length)
      withImages = true;
    apply(decoded);
  }

  // a code typed or pasted, read as the bot reads it
  codeInput.addEventListener('input', function () {
    var value = codeInput.value.trim();
    if (!value) {
      codeInput.classList.remove('invalid');
      codeState.textContent = '';
      return;
    }
    var decoded = P.decode(value);
    codeInput.classList.toggle('invalid', !decoded);
    codeState.textContent = decoded ? 'wczytany' : 'niepoprawny';
    if (decoded)
      loadCode(decoded);
  });

  copy.addEventListener('click', function () {
    function done() {
      copy.textContent = 'Skopiowano';
      setTimeout(function () { copy.textContent = 'Kopiuj'; }, 1500);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(command.value).then(done, function () { command.select(); });
    } else {
      command.select();
    }
  });

  command.addEventListener('focus', function () { command.select(); });

  // --- the start: the code of the address, the pictures, the fonts ---

  // the account's own profile from the bot (me.php): its numbers and cards
  // instead of the made-up ones, and its look when the address has no code
  var real = false;
  var me = root.getAttribute('data-me');

  function list(stats) {
    return stats ? {
      inProgress: stats.inProgress, completed: stats.completed, skipped: stats.skipped, onHold: stats.onHold,
      dropped: stats.dropped, inPlan: stats.inPlan, total: stats.total, rating: stats.rating, count: stats.count,
      time: { years: stats.years, months: stats.months, days: stats.days, hours: stats.hours, minutes: stats.minutes }
    } : null;
  }

  if (me) {
    fetch(me, { credentials: 'same-origin' }).then(function (res) {
      return res.json().then(function (body) { return { status: res.status, body: body }; }, function () { return { status: res.status, body: null }; });
    }).then(function (answer) {
      var mine = answer.status === 200 && answer.body && !answer.body.error ? answer.body : null;
      if (!mine) {
        source.textContent = 'Układ, grafiki i czcionki są te same co w bocie. ' + (answer.body && answer.body.error === 'none'
          ? 'Nie masz jeszcze profilu w bocie, więc liczby i karty są przykładowe.'
          : 'Bot nie podał teraz twojego profilu, więc liczby i karty są przykładowe; spróbuj za chwilę.');
        return;
      }
      real = true;
      ['level', 'exp', 'top', 'messages', 'sc', 'tc', 'ac', 'ct', 'pc', 'cards', 'limit', 'karma', 'rarities', 'scalpels', 'scissors', 'ultimates', 'rank', 'color']
        .forEach(function (key) { data[key] = mine[key]; });
      data.nick = mine.nick || data.nick;
      data.anime = list(mine.anime);
      data.manga = list(mine.manga);
      data.waifu = null;
      data.gallery = [];
      source.textContent = 'To twój profil z bota, z twoimi liczbami, kartami i statystykami z Shindena; obrazki są te, które masz ustawione.';
      Promise.all([load(mine.avatar), load(mine.waifu)].concat(mine.gallery.map(load))).then(function (images) {
        if (images[0])
          data.avatar = images[0];
        data.waifu = images[1];
        data.gallery = images.slice(2);
        redraw();
      });
      var code = P.decode(mine.code);
      if (code && !fromHash && !codeInput.value.trim()) {
        codeInput.value = mine.code;
        codeState.textContent = 'twój obecny';
        loadCode(code, true);
      } else {
        update();
      }
    }, function () {
      source.textContent = 'Układ, grafiki i czcionki są te same co w bocie. Nie udało się wczytać twojego profilu, więc liczby i karty są przykładowe.';
    });
  }

  var fromHash = P.decode(decodeURIComponent(location.hash.slice(1)));
  if (fromHash) {
    codeInput.value = location.hash.slice(1);
    codeState.textContent = 'wczytany';
    loadCode(fromHash);
  } else {
    update();
  }

  var base = root.getAttribute('data-pw') || '';
  Promise.all(P.pictureNames(levels).map(function (name) {
    return load(base + name + '.png').then(function (img) { pictures[name] = img; });
  })).then(redraw);

  load(root.getAttribute('data-avatar') || root.getAttribute('data-art')).then(function (img) {
    if (real && data.avatar)
      return;
    data.avatar = img;
    redraw();
  });

  // the cards of the made-up gallery and the waifu, put together like a card
  // of Skalpelator: its board, the picture, the frame of the rarity and the dere
  var cards = root.getAttribute('data-cards') || '';
  Promise.all([load(root.getAttribute('data-board')), load(root.getAttribute('data-art'))]).then(function (both) {
    var board = both[0];
    var art = both[1];
    if (!art)
      return;
    var looks = [['SSS', 'Kamidere'], ['SS', 'Deredere'], ['S', 'Tsundere'], ['A', 'Yandere'], ['B', 'Dandere'], ['C', 'Kuudere'],
      ['SSS', 'Mayadere'], ['SS', 'Bodere'], ['S', 'Yami'], ['A', 'Raito'], ['B', 'Yato'], ['E', 'Kamidere']];
    return Promise.all(looks.map(function (look) {
      return Promise.all([load(cards + look[0] + '.png'), load(cards + look[1] + '.png')]).then(function (parts) {
        var card = document.createElement('canvas');
        card.width = 475;
        card.height = 667;
        var ctx = card.getContext('2d');
        if (board)
          ctx.drawImage(board, 13, 13);
        var side = Math.min(art.naturalWidth / 448, art.naturalHeight / 650);
        var w = 448 * side;
        var h = 650 * side;
        ctx.drawImage(art, (art.naturalWidth - w) / 2, (art.naturalHeight - h) / 2, w, h, 13, 13, 448, 650);
        if (parts[0])
          ctx.drawImage(parts[0], 0, 0);
        if (parts[1])
          ctx.drawImage(parts[1], 0, 0);
        return card;
      });
    })).then(function (made) {
      if (real)
        return;
      data.gallery = made;
      data.waifu = made[0];
      redraw();
    });
  });

  if (document.fonts && document.fonts.load) {
    Promise.all([
      document.fonts.load('700 28px Lato', 'Ąą'),
      document.fonts.load('400 16px Lato', 'Ąą'),
      document.fonts.load('700 28px Lato', 'Aa'),
      document.fonts.load('400 16px Lato', 'Aa')
    ]).then(redraw, redraw);
  }
})();
