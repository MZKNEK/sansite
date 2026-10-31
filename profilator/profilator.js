// Profilator's page: the settings, the preview drawn by profile.js, the code
// and the command for the bot. The code also lives in the address (#code), so
// a link opens the same profile. The pictures picked stay in the browser,
// unless they are put in the code: then they go to the folder Profilator of
// the account's gallery (i/, action profilator, named by their content so one
// sent again is not kept twice) and the code carries their links. A picture
// picked from a file is framed to its place in the exact size the bot takes,
// covering it whole, and can be moved and zoomed on the preview ("Ustaw");
// what goes to the gallery is that frame, or the file itself when it already
// is the place's size and was not moved. The overlays can also be placed
// freely ("Tryb dowolny"), smaller than their place, on a transparent frame.
// The frames are resampled as the croppers resample their cards (js/resample.js).
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
  var framer = document.getElementById('frame-bar');
  var frameHint = document.getElementById('frame-hint');
  var frameZoom = document.getElementById('frame-zoom');
  var frameZoomValue = document.getElementById('frame-zoom-value');
  var frameFree = document.getElementById('frame-free');
  var frameFreeRow = frameFree.parentNode;
  var frameBox = canvas.parentNode;

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

  // the pictures by their place in the code: { img, file, url, status, parts,
  // view, out, edited, free }; view and out only for a framed one (its move and
  // zoom and the frame made of them), edited once it differs from the picked
  // file, free for an overlay placed freely
  var slots = {};
  var withImages = false;

  // the place of each picture on the profile, x, y, width and height, which is
  // also the size of the picture the bot takes
  var PLACES = {
    combined: [0, 0, 750, 500], background: [0, 0, 750, 160], style: [0, 160, 750, 340],
    overlay: [0, 98, 750, 402], premium: [0, 0, 750, 500]
  };
  var MAX_ZOOM = 5;
  // the overlays may also be placed freely ("Tryb dowolny"): smaller than
  // their place, anywhere in it, never bigger than their own pixels, the rest
  // of the place left transparent; their zoom then counts their own size
  var FREE = { overlay: true, premium: true };
  var MIN_FREE_ZOOM = 0.02;
  var FREE_KEEP = 16;
  // the one being framed on the preview, null when none
  var editing = null;

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
    // a picture, or a frame made here (a canvas)
    var w = img.naturalWidth || img.width;
    var h = img.naturalHeight || img.height;
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

  // --- framing a picture to its place ---

  // the picture's size at zoom 1, the least that covers the place, keeping its shape
  function coverSize(img, name) {
    var place = PLACES[name];
    var k = Math.max(place[2] / img.naturalWidth, place[3] / img.naturalHeight);
    return [img.naturalWidth * k, img.naturalHeight * k];
  }

  // the picture's size at zoom 1: covering the place, or its own when free
  function baseSize(name, slot) {
    return slot.free ? [slot.img.naturalWidth, slot.img.naturalHeight] : coverSize(slot.img, name);
  }

  // the free one fits whole at the least, or down to MIN_FREE_ZOOM when that is more
  function fitZoom(name, slot) {
    var place = PLACES[name];
    return Math.min(1, place[2] / slot.img.naturalWidth, place[3] / slot.img.naturalHeight);
  }

  function zoomLimits(name, slot) {
    return slot.free ? [Math.min(MIN_FREE_ZOOM, fitZoom(name, slot)), 1] : [1, MAX_ZOOM];
  }

  // centred, covering the place or, free, whole in it and not upscaled
  function startView(name, slot) {
    return { x: 0, y: 0, zoom: slot.free ? fitZoom(name, slot) : 1 };
  }

  // the view kept covering the place (as apps/shared/lib/cover.js does for
  // the croppers), or, free, with FREE_KEEP pixels of it still in the place,
  // so it can go out over any edge but not be lost: x and y move the
  // picture's centre from the place's centre
  function clampView(view, name, slot) {
    var place = PLACES[name];
    var size = baseSize(name, slot);
    var limits = zoomLimits(name, slot);
    var zoom = Math.min(limits[1], Math.max(limits[0], view.zoom));
    var w = size[0] * zoom;
    var h = size[1] * zoom;
    var maxX = slot.free ? (place[2] + w) / 2 - Math.min(FREE_KEEP, w / 2) : (w - place[2]) / 2;
    var maxY = slot.free ? (place[3] + h) / 2 - Math.min(FREE_KEEP, h / 2) : (h - place[3]) / 2;
    return { x: Math.min(maxX, Math.max(-maxX, view.x)), y: Math.min(maxY, Math.max(-maxY, view.y)), zoom: zoom };
  }

  // where the picture lies in its place: its corner and its scale
  function placement(name, slot) {
    var place = PLACES[name];
    var size = baseSize(name, slot);
    var w = size[0] * slot.view.zoom;
    var h = size[1] * slot.view.zoom;
    return { x: (place[2] - w) / 2 + slot.view.x, y: (place[3] - h) / 2 + slot.view.y, w: w, h: h, scale: w / slot.img.naturalWidth };
  }

  // the croppers' resampling (js/resample.js: Lanczos3 without halos, a big
  // reduction averaged first), in a worker (js/resample.worker.js), as a big
  // picture takes up to a second; on the page when there is no worker. Until
  // it is ready, and while a picture is moved, the canvas scales it, and a
  // moment after it stops the frame is made again with it, as it is before
  // it is sent
  var resampleUrl = root.getAttribute('data-resample') ? new URL(root.getAttribute('data-resample'), location.href).href : '';
  var resample = null;
  var worker = null;
  var workerReady = false;
  var asked = {};
  var askedId = 0;
  var pictureKey = 0;
  var REFINE_AFTER = 150;
  var refining = {};

  function refineAll() {
    P.IMAGES.forEach(function (name) {
      if (slots[name] && slots[name].view)
        refine(name, slots[name]).then(function (done) { if (done) redraw(); });
    });
  }

  // no worker: the frames asked for are left as the canvas made them, and the
  // page resamples from now on
  function withoutWorker() {
    if (worker)
      worker.terminate();
    worker = null;
    workerReady = false;
    Object.keys(asked).forEach(function (id) { asked[id](null); });
    asked = {};
    if (resampleUrl)
      import(resampleUrl).then(function (module) {
        resample = module.resample;
        refineAll();
      }, function () {});
  }

  if (resampleUrl && root.getAttribute('data-resample-worker') && window.Worker) {
    try {
      worker = new Worker(root.getAttribute('data-resample-worker'), { type: 'module' });
      worker.onmessage = function (e) {
        var answer = e.data;
        if (answer.type === 'ready') {
          workerReady = true;
          refineAll();
        } else if (answer.type === 'failed') {
          withoutWorker();
        } else if (answer.type === 'frame' && asked[answer.id]) {
          var done = asked[answer.id];
          delete asked[answer.id];
          done(answer.out);
        }
      };
      worker.onerror = withoutWorker;
      worker.postMessage({ type: 'init', url: resampleUrl });
    } catch (e) {
      withoutWorker();
    }
  } else {
    withoutWorker();
  }

  // the frame of the picture in the place's exact size, transparent where it
  // does not reach, the parts of both cut from it
  function frame(name, slot) {
    var place = PLACES[name];
    var at = placement(name, slot);
    var out = slot.out || document.createElement('canvas');
    out.width = place[2];
    out.height = place[3];
    var ctx = out.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(slot.img, at.x, at.y, at.w, at.h);
    slot.out = out;
    slot.fine = false;
    slot.version = (slot.version || 0) + 1;
    if (name === 'combined')
      slot.parts = split(out);
    clearTimeout(refining[name]);
    refining[name] = setTimeout(function () {
      if (slots[name] === slot)
        refine(name, slot).then(function (done) { if (done) redraw(); });
    }, REFINE_AFTER);
  }

  // the frame made again by resampling the picture's own pixels, read once
  // (and given to the worker once); true once it is in, false when there is
  // nothing to resample with or the picture moved meanwhile
  function refine(name, slot) {
    if (!slot.view || slot.fine || !(workerReady || resample))
      return Promise.resolve(false);
    var place = PLACES[name];
    var at = placement(name, slot);
    var area = { x: -at.x / at.scale, y: -at.y / at.scale, width: place[2] / at.scale, height: place[3] / at.scale };
    var version = slot.version;
    var made;
    try {
      if (workerReady ? !slot.key : !slot.pixels) {
        var source = document.createElement('canvas');
        source.width = slot.img.naturalWidth;
        source.height = slot.img.naturalHeight;
        var sctx = source.getContext('2d');
        sctx.drawImage(slot.img, 0, 0);
        var pixels = sctx.getImageData(0, 0, source.width, source.height);
        if (workerReady) {
          slot.key = ++pictureKey;
          worker.postMessage({ type: 'picture', key: slot.key, pixels: pixels }, [pixels.data.buffer]);
        } else {
          slot.pixels = pixels;
        }
      }
      if (workerReady) {
        made = new Promise(function (resolve) {
          var id = ++askedId;
          asked[id] = resolve;
          worker.postMessage({ type: 'frame', id: id, key: slot.key, area: area, width: place[2], height: place[3] });
        });
      } else {
        made = Promise.resolve(resample(slot.pixels, area, place[2], place[3]));
      }
    } catch (e) {
      return Promise.resolve(false);
    }
    return made.then(function (out) {
      if (!out || slots[name] !== slot || slot.version !== version)
        return false;
      slot.out.getContext('2d').putImageData(out, 0, 0);
      slot.fine = true;
      if (name === 'combined')
        slot.parts = split(slot.out);
      return true;
    });
  }

  // the picture as it already is, the place's size and not moved
  function untouched(name, slot) {
    var place = PLACES[name];
    return !slot.edited && slot.img.naturalWidth === place[2] && slot.img.naturalHeight === place[3];
  }

  function previewImages() {
    var images = {};
    var combined = slots.combined && slots.combined.parts;
    if (combined) {
      images.background = combined.top;
      images.style = combined.bottom;
    } else {
      if (slots.background && slots.background.img) images.background = slots.background.out || slots.background.img;
      if (slots.style && slots.style.img) images.style = slots.style.out || slots.style.img;
    }
    if (slots.overlay && slots.overlay.img) images.overlay = slots.overlay.out || slots.overlay.img;
    if (slots.premium && slots.premium.img) images.premium = slots.premium.out || slots.premium.img;
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

  // a picture to be sent once it is framed
  function framing() {
    var slot = editing !== null && slots[editing];
    return withImages && !!slot && !!(slot.file || slot.edited) && !slot.url;
  }

  // what the preview draws while a picture is framed: its overlay switched on,
  // a style with a picture for the style's, so it is in sight
  function shownState() {
    if (editing === null)
      return state;
    var shown = { style: state.style, border: state.border, flags: state.flags, shadows: state.shadows };
    if (editing === 'overlay')
      shown.flags |= P.FLAG.ShowOverlay;
    if (editing === 'premium')
      shown.flags |= P.FLAG.ShowOverlayPro;
    if ((editing === 'style' || editing === 'combined') && !P.styleNeedsImage(state.style))
      shown.style = 'Img';
    return shown;
  }

  // the picture drawn bigger than its own pixels, so it loses sharpness
  function upscaled(name, slot) {
    return baseSize(name, slot)[0] * slot.view.zoom > slot.img.naturalWidth * 1.001;
  }

  // the place being framed outlined, red while the picture is upscaled, the
  // rest of the profile dimmed
  function outline() {
    var place = PLACES[editing];
    var ctx = canvas.getContext('2d');
    ctx.save();
    ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
    ctx.beginPath();
    ctx.rect(0, 0, canvas.width, canvas.height);
    ctx.rect(place[0], place[1], place[2], place[3]);
    ctx.fill('evenodd');
    ctx.setLineDash([8, 6]);
    ctx.lineWidth = 2;
    ctx.strokeStyle = upscaled(editing, slots[editing])
      ? getComputedStyle(document.documentElement).getPropertyValue('--danger').trim() || '#e86262'
      : 'rgba(255, 255, 255, 0.85)';
    ctx.strokeRect(place[0] + 1, place[1] + 1, place[2] - 2, place[3] - 2);
    ctx.restore();
  }

  var drawing = false;
  function redraw() {
    if (drawing)
      return;
    drawing = true;
    requestAnimationFrame(function () {
      drawing = false;
      P.render(canvas, assets, shownState(), data, previewImages());
      if (editing !== null)
        outline();
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
      var name = row.getAttribute('data-image');
      var slot = slots[name];
      var label = row.querySelector('[data-image-file]');
      // the background and the style apart, greyed out while both are in one picture
      var covered = (name === 'background' || name === 'style') && !!slots.combined;
      row.classList.toggle('covered', covered);
      row.querySelector('input[type="file"]').disabled = covered;
      var text = covered ? 'jest w obrazku tło i styl razem' : 'nie wybrano';
      if (slot)
        text = (slot.file ? slot.file.name : slot.edited ? 'ustawiony' : slot.current ? 'twój' : 'z kodu') + (slot.status ? ' · ' + slot.status : '');
      label.textContent = text;
      label.title = slot && slot.url ? slot.url : '';
      row.querySelector('[data-image-clear]').hidden = !slot;
      var edit = row.querySelector('[data-image-edit]');
      edit.hidden = !slot || !slot.img;
      edit.textContent = editing === row.getAttribute('data-image') ? 'Gotowe' : 'Ustaw';
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

    var moving = framing();
    var waiting = moving || sending();
    command.value = moving ? 'Ustawianie obrazka, kliknij Gotowe…' : waiting ? 'Wysyłanie obrazków do galerii…' : prefix + 'konp kod ' + code;
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

    showFramer();
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
    if (slot && slot.key && worker)
      worker.postMessage({ type: 'forget', key: slot.key });
    delete slots[name];
    if (editing === name) {
      editing = null;
      pointers = {};
    }
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

  // the gallery's link of a picture picked or framed here, sent once with a
  // name of its content; not while it is being framed
  function upload(name) {
    var slot = slots[name];
    if (!slot || !slot.img || !(slot.file || slot.edited) || slot.url || slot.uploading || slot.failed || editing === name)
      return;
    if (!own) {
      slot.status = 'do kodu wymaga własnego folderu w galerii';
      slot.failed = true;
      return;
    }
    slot.uploading = true;
    slot.status = 'wysyłanie do galerii…';
    var whole = slot.file && untouched(name, slot);
    var ext = whole ? (slot.file.name.split('.').pop() || 'png').toLowerCase().replace(/[^a-z0-9]/g, '') || 'png' : 'png';
    // a frame goes resampled, as the preview shows it once it is still
    var made = whole ? Promise.resolve(slot.file) : refine(name, slot).then(function (done) {
      if (done)
        redraw();
      return new Promise(function (resolve, reject) {
        slot.out.toBlob(function (blob) { blob ? resolve(blob) : reject(new Error('no blob')); }, 'image/png');
      });
    });
    var blob;
    made.then(function (picture) {
      blob = picture;
      return picture.arrayBuffer();
    }).then(function (buffer) {
      return crypto.subtle.digest('SHA-256', buffer);
    }).then(function (hash) {
      var hex = Array.prototype.map.call(new Uint8Array(hash).slice(0, 8), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
      var form = new FormData();
      form.append('action', 'profilator');
      form.append('csrf', csrf);
      form.append('file', blob, hex + '.' + ext);
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
      if (slot.file) {
        slot.view = { x: 0, y: 0, zoom: 1 };
        frame(name, slot);
      } else if (name === 'combined') {
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

    row.querySelector('[data-image-edit]').addEventListener('click', function () {
      if (editing === name)
        stopFraming();
      else
        startFraming(name);
    });
  });

  // --- moving and zooming a picture on the preview ---

  var pointers = {};

  // a picture of a code is shown as the bot shows it until it is moved; one
  // from another server cannot be read back, so it cannot be framed
  function startFraming(name) {
    var slot = slots[name];
    if (!slot || !slot.img)
      return;
    if (editing !== null)
      stopFraming();
    if (!slot.view) {
      slot.view = { x: 0, y: 0, zoom: 1 };
      frame(name, slot);
      try {
        slot.out.getContext('2d').getImageData(0, 0, 1, 1);
      } catch (e) {
        unframe(name, slot);
        slot.status = 'obrazka z innej strony nie da się ustawić, wybierz go z pliku';
        update();
        return;
      }
      slot.plain = true;
    }
    editing = name;
    update();
  }

  function unframe(name, slot) {
    delete slot.view;
    delete slot.out;
    delete slot.plain;
    if (name === 'combined')
      slot.parts = split(slot.img);
  }

  function stopFraming() {
    var name = editing;
    var slot = slots[name];
    editing = null;
    pointers = {};
    if (slot && slot.plain && !slot.edited)
      unframe(name, slot);
    if (withImages)
      upload(name);
    update();
  }

  // the centre of the place being framed, on the canvas
  function centre() {
    var place = PLACES[editing];
    return [place[0] + place[2] / 2, place[1] + place[3] / 2];
  }

  // zoomed by k around a point of the place (from its centre), which stays put
  function zoomBy(view, k, px, py) {
    var limits = zoomLimits(editing, slots[editing]);
    var zoom = Math.min(limits[1], Math.max(limits[0], view.zoom * k));
    var r = zoom / view.zoom;
    return { x: px - (px - view.x) * r, y: py - (py - view.y) * r, zoom: zoom };
  }

  // the picture's new place; the first change makes it a picture of its own,
  // to be sent again instead of the link or the file it came from
  function moveView(view) {
    var slot = slots[editing];
    var first = !slot.edited || !!slot.url || !!slot.failed;
    slot.view = clampView(view, editing, slot);
    slot.edited = true;
    slot.url = null;
    slot.current = false;
    slot.failed = false;
    frame(editing, slot);
    if (first) {
      slot.status = '';
      update();
    } else {
      showZoom();
      redraw();
    }
  }

  function showZoom() {
    var slot = slots[editing];
    var limits = zoomLimits(editing, slot);
    var zoom = Math.round(slot.view.zoom * 100);
    var up = upscaled(editing, slot);
    frameZoom.min = String(Math.floor(limits[0] * 100));
    frameZoom.max = String(Math.round(limits[1] * 100));
    frameZoom.value = String(zoom);
    frameZoomValue.textContent = zoom + '%' + (slot.free ? ' oryginału' : '') + (up ? ' · ponad rozmiar obrazka, traci ostrość' : '');
    frameZoomValue.classList.toggle('profilator-upscaled', up);
  }

  function showFramer() {
    framer.hidden = editing === null;
    frameBox.classList.toggle('framing', editing !== null);
    if (editing === null)
      return;
    var place = PLACES[editing];
    var slot = slots[editing];
    frameFreeRow.hidden = !FREE[editing];
    setSwitch(frameFree, !!slot.free);
    var text = 'Ustawiasz: ' + NAMES[editing] + ', ' + place[2] + ' × ' + place[3] + ' px. ' + (slot.free
      ? 'Przeciągnij obrazek gdziekolwiek w polu, pomniejsz go kółkiem, dwoma palcami albo suwakiem; nie urośnie ponad swój rozmiar, a reszta pola zostanie przeźroczysta.'
      : 'Przeciągnij obrazek na podglądzie, przybliż go kółkiem, dwoma palcami albo suwakiem; zawsze wypełnia całe pole.');
    if ((editing === 'style' || editing === 'combined') && !P.styleNeedsImage(state.style))
      text += ' Wybrany styl nie używa obrazka, więc podgląd na chwilę pokazuje styl obrazek.';
    if ((editing === 'overlay' && !(state.flags & P.FLAG.ShowOverlay)) || (editing === 'premium' && !(state.flags & P.FLAG.ShowOverlayPro)))
      text += ' Przełącznik nakładki jest wyłączony, podgląd pokazuje ją na czas ustawiania.';
    frameHint.textContent = text;
    showZoom();
  }

  function canvasPoint(e) {
    var rect = canvas.getBoundingClientRect();
    return { x: (e.clientX - rect.left) * canvas.width / rect.width, y: (e.clientY - rect.top) * canvas.height / rect.height };
  }

  // where the fingers or the mouse are together, and how far apart two are
  function gesture() {
    var ids = Object.keys(pointers);
    var x = 0;
    var y = 0;
    ids.forEach(function (id) {
      x += pointers[id].x / ids.length;
      y += pointers[id].y / ids.length;
    });
    var d = ids.length > 1 ? Math.hypot(pointers[ids[0]].x - pointers[ids[1]].x, pointers[ids[0]].y - pointers[ids[1]].y) : 0;
    return { x: x, y: y, d: d };
  }

  canvas.addEventListener('pointerdown', function (e) {
    if (editing === null)
      return;
    e.preventDefault();
    canvas.setPointerCapture(e.pointerId);
    pointers[e.pointerId] = canvasPoint(e);
  });

  canvas.addEventListener('pointermove', function (e) {
    if (editing === null || !pointers[e.pointerId])
      return;
    var before = gesture();
    pointers[e.pointerId] = canvasPoint(e);
    var after = gesture();
    var view = slots[editing].view;
    view = { x: view.x + after.x - before.x, y: view.y + after.y - before.y, zoom: view.zoom };
    if (before.d && after.d) {
      var c = centre();
      view = zoomBy(view, after.d / before.d, after.x - c[0], after.y - c[1]);
    }
    moveView(view);
  });

  function lift(e) {
    delete pointers[e.pointerId];
  }
  canvas.addEventListener('pointerup', lift);
  canvas.addEventListener('pointercancel', lift);

  canvas.addEventListener('wheel', function (e) {
    if (editing === null)
      return;
    e.preventDefault();
    var p = canvasPoint(e);
    var c = centre();
    var delta = e.deltaY * (e.deltaMode === 1 ? 33 : 1);
    moveView(zoomBy(slots[editing].view, Math.exp(-delta * 0.0015), p.x - c[0], p.y - c[1]));
  }, { passive: false });

  frameZoom.addEventListener('input', function () {
    if (editing !== null)
      moveView(zoomBy(slots[editing].view, Number(frameZoom.value) / 100 / slots[editing].view.zoom, 0, 0));
  });

  document.getElementById('frame-reset').addEventListener('click', function () {
    if (editing !== null)
      moveView(startView(editing, slots[editing]));
  });

  frameFree.addEventListener('change', function () {
    if (editing === null)
      return;
    var slot = slots[editing];
    slot.free = frameFree.checked;
    moveView(startView(editing, slot));
    update();
  });

  document.getElementById('frame-done').addEventListener('click', function () {
    if (editing !== null)
      stopFraming();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && editing !== null)
      stopFraming();
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
