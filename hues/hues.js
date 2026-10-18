// 0x40 (hues/), the site's own take on 0x40 Hues: the pictures of its default
// respack (respack.json, made by respack.php) flash on the stage under one of
// 64 colours, changed on the beats of its songs, a buildup once and then the
// loop. Every half beat has one character of a beat string, which says what
// happens on it (EFFECTS): the classic maps of 0x40 Hues where the pack has
// them (classicMaps(), with the later effects added in the extended mode,
// enrich() of beats.js), else one counted in the browser from the decoded
// song, its tempo and the onsets of its low and high band (analyse() of
// beats.js). A song or pictures of
// the visitor's own are read the same way and never leave the browser.
(function () {
  'use strict';

  // The 64 colours (0x40): every red, green and blue of 00, 55, AA and FF,
  // the index r + 4g + 16b, as in 0x40 Hues' own set, named in Polish
  var NAMES = [
    'Czerń', 'Cegła', 'Karmazyn', 'Czerwień', 'Butelka', 'Muł', 'Brąz', 'Pomarańcz',
    'Zieleń', 'Trawa', 'Kukurydza', 'Bursztyn', 'Limonka', 'Liść', 'Kiwi', 'Żółć',
    'Północ', 'Śliwka', 'Malina', 'Róża', 'Bagno', 'Świt', 'Stara róża', 'Łosoś',
    'Jadeit', 'Szparag', 'Oliwka', 'Brzoskwinia', 'Wiosna', 'Mięta', 'Pistacja', 'Banan',
    'Granat', 'Indygo', 'Purpura', 'Fuksja', 'Ocean', 'Irys', 'Wrzos', 'Goździk',
    'Morski', 'Laguna', 'Srebro', 'Róż', 'Woda', 'Akwamaryna', 'Seledyn', 'Krem',
    'Błękit', 'Elektryk', 'Fiolet', 'Magenta', 'Kobalt', 'Barwinek', 'Lawenda', 'Heliotrop',
    'Lazur', 'Chaber', 'Liliowy', 'Wata cukrowa', 'Cyjan', 'Lód', 'Niebo', 'Biel'
  ];
  var LEVELS = [0, 0x55, 0xaa, 0xff];
  function hueRgb(i) {
    return [LEVELS[i & 3], LEVELS[(i >> 2) & 3], LEVELS[(i >> 4) & 3]];
  }

  // the sets of the settings: the same 64 lifted toward white, the normal a
  // little, the pastel more
  function setRgb(set, i) {
    var lift = set === 'pastel' ? 0.45 : 0.12;
    return hueRgb(i).map(function (c) { return Math.round(c + (255 - c) * lift); });
  }
  function hexByte(n, width) {
    var s = n.toString(16).toUpperCase();
    while (s.length < width) s = '0' + s;
    return s;
  }
  function cssRgb(rgb) {
    return 'rgb(' + Math.round(rgb[0]) + ',' + Math.round(rgb[1]) + ',' + Math.round(rgb[2]) + ')';
  }

  // what each character of a beat string does, and the maps counted from a
  // song or made from a classic one (beats.js)
  var EFFECTS = HuesBeats.EFFECTS, analyse = HuesBeats.analyse, enrich = HuesBeats.enrich;

  // The classic mode keeps to what the first, Flash 0x40 Hues knew (x o X O
  // - : * ~ = | + .); the inversion and the slices came later, and stand in
  // it for their nearest classic character
  var CLASSIC = { 'i': 'x', 's': 'o', 'v': 'x', '#': '-' };
  var classic = false;
  function modeChars(chars) {
    return classic ? chars.replace(/[isv#]/g, function (ch) { return CLASSIC[ch]; }) : chars;
  }

  // ---- the page ---------------------------------------------------------

  var $ = function (id) { return document.getElementById(id); };
  var canvas = $('hues-stage'), g = canvas.getContext('2d');
  var ui = $('hues-ui'), statusLine = $('hues-status'), help = $('hues-help');
  // the pack's files come through pack.php, only for those let in (inc/members.php)
  var PACK = new URL('./', document.currentScript.src).pathname + 'pack.php?f=';
  var VOLUME_KEY = 'sanakan-hues-volume';
  var SPEED_KEY = 'sanakan-hues-animation-speed';
  var MODE_KEY = 'sanakan-hues-transitions';
  var BLUR_KEY = 'sanakan-hues-blur';

  // The blur of the settings, as 0x40 Hues offers it: how far a beat smears
  // the picture (of its height), how fast that fades (the time constant, s)
  // and how many copies draw it at most (each a whole picture a frame, so
  // the extreme stays below what lags)
  var BLUR = {
    amount: { low: 0.025, medium: 0.05, high: 0.12 },
    decay: { slow: 0.18, medium: 0.11, fast: 0.07, faster: 0.045 },
    quality: { low: 5, medium: 9, high: 13, extreme: 19 }
  };
  var blurSettings = { amount: 'medium', decay: 'fast', quality: 'medium' };
  try {
    var keptBlur = JSON.parse(localStorage.getItem(BLUR_KEY) || '{}') || {};
    Object.keys(blurSettings).forEach(function (kind) {
      if (Object.prototype.hasOwnProperty.call(BLUR[kind], keptBlur[kind])) blurSettings[kind] = keptBlur[kind];
    });
  } catch (_) { /* the defaults */ }

  var images = [];  // the pictures read so far: { name, frames (images or bitmaps), key, cx }
  var songs = [];   // { name, loop, build (a file name or a dropped file), and once read their buffers and maps }

  // the songs go to input, which feeds the volume (gain) and the analyser
  var audio = null, input = null, gain = null, analyser = null, sources = [];
  var song = -1, track = null, map = null, loading = 0;
  var volume = 0.2;
  try {
    var kept = parseFloat(localStorage.getItem(VOLUME_KEY));
    if (kept >= 0 && kept <= 1) volume = kept;
  } catch (_) { /* the default volume */ }
  // running: not paused; blocked: the browser holds the sound back until the
  // first click or key, as it does for a page that plays at once
  var muted = false, locked = false, running = true, blocked = false;

  // what is on the stage
  var state = {
    hue: 0, rgb: [255, 255, 255], fade: null,
    image: -1,
    blur: null, slice: null, blackout: null, invert: false,
    part: '', char: -1, time: -1
  };

  function now() {
    return performance.now() / 1000;
  }

  function setStatus(text) {
    statusLine.textContent = text || '';
    statusLine.hidden = !text;
  }

  // ---- pictures

  // Where the figure stands in its picture (0 to 1): the middle of what is
  // not transparent across it, so a narrow stage can follow it, not the
  // frame, and the top of it, so a picture grown over a wide stage keeps it
  function figureBox(src) {
    var w = 128, h = 72, off = document.createElement('canvas');
    off.width = w;
    off.height = h;
    var o = off.getContext('2d', { willReadFrequently: true });
    o.drawImage(src, 0, 0, w, h);
    var data = o.getImageData(0, 0, w, h).data, min = w, max = -1, top = -1;
    for (var y = 0; y < h; y++) {
      for (var x = 0; x < w; x++) {
        if (data[(y * w + x) * 4 + 3] > 32) {
          if (top < 0) top = y;
          if (x < min) min = x;
          if (x > max) max = x;
        }
      }
    }
    return max < 0 ? { cx: 0.5, top: 0 } : { cx: (min + max + 1) / 2 / w, top: top / h };
  }

  // the middle of the figure the pack gives (for a picture with no
  // transparency to find it by) goes before the one found
  function addImage(name, frames, key, cx) {
    var box = { cx: 0.5, top: 0 };
    try { box = figureBox(frames[0]); } catch (_) { /* the middle */ }
    images.push({ name: name, frames: frames, key: key, cx: cx === undefined ? box.cx : cx, top: box.top });
    if (state.image < 0) showImage(images.length - 1);
  }

  // A picture of the pack, or an animation with all its frames, out of the
  // bytes of pictures.bin ([start, end] of each). An image of a blob: address,
  // not an ImageBitmap, which would hold every picture decoded (about 1 GB for
  // the pack) where the browser decodes an image when drawn and lets it go.
  function readFrame(bytes) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.decoding = 'async';
      img.onload = function () { resolve(img); };
      img.onerror = reject;
      img.src = URL.createObjectURL(new Blob([bytes], { type: 'image/webp' }));
    });
  }

  function readPicture(item, bytes) {
    Promise.all(item.parts.map(function (part) {
      return readFrame(bytes.subarray(part[0], part[1]));
    })).then(function (frames) {
      addImage(item.entry.name, frames, 'pack:' + item.entry.name, item.entry.cx);
    }, function () { /* left out */ });
  }

  // The pack's pictures, then the frames of its animations, come in one file
  // (pictures.bin, made by respack.php) in the order of the list, each as
  // long as the list's pictures.sizes says; each is shown once its bytes are
  // in, not after the whole file. The file's version in the address lets
  // the browser keep it until it changes.
  function loadPictures(entries, pack) {
    var sizes = pack.sizes, size = 0, n = 0;
    var items = entries.map(function (entry) {
      var parts = [];
      (entry.frames || [null]).forEach(function () {
        parts.push([size, size += sizes[n++] || 0]);
      });
      return { entry: entry, parts: parts, end: size };
    });
    var bytes = new Uint8Array(size), got = 0, next = 0;
    // the bar shows only when the file is not in at once (not kept by the
    // browser, a slow link)
    var bar = $('hues-loading'), barTimer = setTimeout(function () { bar.hidden = false; }, 400);
    function take(chunk) {
      chunk = chunk.subarray(0, size - got);
      bytes.set(chunk, got);
      got += chunk.length;
      var percent = size ? Math.floor(got / size * 100) : 100;
      bar.setAttribute('aria-valuenow', percent);
      $('hues-loading-text').textContent = 'Wczytuję obrazy… ' + percent + '% (' + (got / 1048576).toFixed(1) + ' z ' + (size / 1048576).toFixed(1) + ' MB)';
      $('hues-loading-fill').style.width = percent + '%';
      while (next < items.length && items[next].end <= got) readPicture(items[next++], bytes);
    }
    function done() {
      clearTimeout(barTimer);
      bar.hidden = true;
    }
    return fetch(PACK + 'pictures.bin&v=' + encodeURIComponent(pack.version)).then(function (response) {
      if (!response.ok) throw new Error(response.status);
      if (!response.body) return response.arrayBuffer().then(function (buffer) { take(new Uint8Array(buffer)); });
      var reader = response.body.getReader();
      return reader.read().then(function step(result) {
        if (result.done) return;
        take(result.value);
        return reader.read().then(step);
      });
    }).then(done, function (error) {
      done();
      throw error;
    });
  }

  function showImage(index) {
    if (!images.length) return;
    index = ((index % images.length) + images.length) % images.length;
    if (index === state.image) return;
    state.image = index;
    $('hues-image').textContent = images[index].name;
    // the whole name, which the bar's fixed width may cut
    $('hues-image').title = images[index].name + ' (szukaj obrazu, O)';
  }

  function imageByHand(step) {
    showImage(state.image + step);
  }

  function randomImage() {
    if (locked || images.length < 2) return;
    if (options.shuffleImages === 'off') { showImage(state.image + 1); return; }
    var next = Math.floor(Math.random() * (images.length - 1));
    showImage(next >= state.image ? next + 1 : next);
  }

  // ---- colours

  function showHue(index, fade) {
    var rgb = setRgb(options.colours, index);
    state.fade = fade ? { from: currentRgb(), to: rgb, t0: now(), dur: fade } : null;
    state.hue = index;
    state.rgb = rgb;
    $('hues-hue').textContent = NAMES[index];
    $('hues-hue-hex').textContent = '#' + hexByte((rgb[0] << 16) | (rgb[1] << 8) | rgb[2], 6);
    $('hues-hue-index').textContent = '$0x' + hexByte(index, 2);
    document.documentElement.style.setProperty('--hue', cssRgb(rgb));
  }

  function randomHue(fade) {
    var next = Math.floor(Math.random() * 63);
    showHue(next >= state.hue ? next + 1 : next, fade);
  }

  function currentRgb() {
    var f = state.fade;
    if (!f) return state.rgb;
    var p = Math.min(1, (now() - f.t0) / f.dur);
    if (p >= 1) { state.fade = null; return state.rgb; }
    return [0, 1, 2].map(function (k) { return f.from[k] + (f.to[k] - f.from[k]) * p; });
  }

  // ---- the beats

  // how long until the next character that does anything, for a fade
  function untilNext(c) {
    var dots = 1;
    while (map && dots < 32 && map.chars.charAt(c + dots) === '.') dots++;
    return Math.min(4, dots * map.step);
  }

  function hit(ch, c) {
    var effect = EFFECTS[ch];
    if (!effect) return;
    var t = now();
    if (state.blackout && state.blackout.hold) state.blackout = { t0: t, release: t, out: 0.08 };
    state.invert = false;
    if (effect.colour) randomHue(effect.colour === 'fade' ? untilNext(c) : 0);
    var before = state.image;
    if (effect.image) randomImage();
    if (effect.blur) state.blur = { axis: effect.blur, t0: t };
    if (effect.slice) state.slice = { axis: effect.slice, t0: t, seed: Math.random() * 1000, from: before };
    if (effect.invert) state.invert = true;
    if (effect.blackout === 'hold') state.blackout = { t0: t, hold: true };
    if (effect.blackout === 'short') state.blackout = { t0: t, release: t + Math.min(0.12, map.step * 0.5), out: map.step * 0.6 };
  }

  // Where the song is as heard, less what the output still holds: in its
  // buildup until the loop starts, then in the loop, round and round
  function position() {
    if (!track) return null;
    var heard = audio.currentTime - (audio.baseLatency || 0) - (audio.outputLatency || 0);
    var maps = trackMaps(), mode = classic ? 'classic ' : '';
    if (track.withBuild && heard < track.loopAt)
      return { part: mode + 'build', map: maps.build || SILENT, t: Math.max(0, heard - track.t0) };
    return { part: mode + 'loop', map: maps.loop, t: Math.max(0, heard - track.loopAt) % track.loopLength };
  }

  // the maps of the song playing in the mode chosen: the classic ones of the
  // pack where the song has them, else the counted ones
  var SILENT = { chars: '', start: 0, step: 1 };
  function trackMaps() {
    var mode = classic ? track.classic : track.extended;
    return mode.loop ? mode : track.counted;
  }

  function beats() {
    var at = position();
    if (!at) return;
    // the loop began, or began again
    if (at.part !== state.part || at.t < state.time) state.char = -1;
    state.part = at.part;
    state.time = at.t;
    map = at.map;
    $('hues-time').textContent = hexByte(Math.floor(at.t * 10) & 0xffff, 4);
    var c = Math.floor((at.t - map.start) / map.step);
    if (c === state.char || c < 0) return;
    // a tab back from the background skips what it missed
    var from = c - state.char > 8 ? c : state.char + 1;
    for (var k = from; k <= c; k++) hit(modeChars(map.chars.charAt(k)), k);
    state.char = c;
    paintBeats(c);
  }

  var BEAT_LENGTH = 64;
  function paintBeats(c) {
    var next = map.chars.substr(c + 1, BEAT_LENGTH);
    // the buildup runs into its loop, the loop into its start again
    if (track) next += trackMaps().loop.chars.substr(0, BEAT_LENGTH);
    next = modeChars(next.substr(0, BEAT_LENGTH));
    while (next.length < BEAT_LENGTH) next += '.';
    $('hues-beat-now').textContent = modeChars(map.chars.charAt(c)) || '.';
    $('hues-beat-next').textContent = next;
    $('hues-beatno').textContent = hexByte(Math.max(0, c) & 0xffff, 4);
  }

  // ---- drawing

  var dpr = 1;
  // the bar's height for the styles of what sits above it (the stage goes
  // on under it)
  var barHeight = 0;
  if (window.ResizeObserver) new ResizeObserver(function () {
    barHeight = ui.offsetHeight;
    document.documentElement.style.setProperty('--bar', ui.offsetHeight + 'px');
  }).observe(ui);
  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    var width = window.innerWidth, height = window.innerHeight;
    // at most about 4 million pixels, a 4K screen at 1
    while (dpr > 1 && width * height * dpr * dpr > 4.2e6) dpr -= 0.25;
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
  }

  // An animation goes round once every four beats (in 2 s before a song)
  // at the speed 1, which the slider of the settings changes (kept in this
  // browser)
  var animationSpeed = 1;
  try {
    var keptSpeed = parseFloat(localStorage.getItem(SPEED_KEY));
    if (keptSpeed > 0 && keptSpeed <= 4) animationSpeed = keptSpeed;
  } catch (_) { /* the default speed */ }
  // the blur of the settings, each of its three (kept in this browser)
  function setBlur(kind, value, quiet) {
    if (!Object.prototype.hasOwnProperty.call(BLUR[kind], value)) return;
    blurSettings[kind] = value;
    var radio = document.querySelector('input[name="hues-blur-' + kind + '"][value="' + value + '"]');
    if (radio) radio.checked = true;
    if (!quiet) try { localStorage.setItem(BLUR_KEY, JSON.stringify(blurSettings)); } catch (_) { /* this visit only */ }
  }

  // The classic or the extended effects (kept in this browser); what is held
  // on the stage of the extended ones goes at once
  function setMode(mode, quiet) {
    classic = mode === 'classic';
    if (classic) { state.slice = null; state.invert = false; }
    $(classic ? 'hues-mode-classic' : 'hues-mode-extended').checked = true;
    if (!quiet) try { localStorage.setItem(MODE_KEY, classic ? 'classic' : 'extended'); } catch (_) { /* this visit only */ }
  }

  function setAnimationSpeed(value, quiet) {
    animationSpeed = value;
    $('hues-speed').value = String(value);
    fillRange($('hues-speed'));
    $('hues-speed-value').textContent = '×' + value.toFixed(2).replace('.', ',');
    if (!quiet) try { localStorage.setItem(SPEED_KEY, String(value)); } catch (_) { /* this visit only */ }
  }

  // a range of the site's (player-range) shows its value as the filled part
  // of its line
  function fillRange(range) {
    var span = range.max - range.min;
    range.style.setProperty('--player-fill', (span ? (range.value - range.min) / span * 100 : 0) + '%');
  }

  function frameOf(image) {
    var n = image.frames.length;
    if (n < 2) return image.frames[0];
    // by the counted beat, whose characters are always half beats
    var cycle = (track ? 8 * track.counted.loop.step : 2) / animationSpeed;
    return image.frames[Math.floor(now() / cycle * n) % n];
  }

  // The picture as tall as the stage, in the middle, the bar over it; on a
  // narrow stage at most twice its width, standing on the bottom edge as the
  // figures of the pack do. With the centring of the settings on, it covers
  // the whole stage instead, with no bands: as tall as it on a narrow one,
  // following its figure, and as wide as it on a wide one, going up only as
  // far as its figure's top needs
  function imageRect(image, src) {
    var W = canvas.width, H = canvas.height;
    var iw = src.naturalWidth || src.width, ih = src.naturalHeight || src.height;
    var smart = options.smartAlign === 'on';
    var h = smart ? Math.max(H, W * ih / iw) : Math.min(H, 2 * W * ih / iw);
    var w = iw * h / ih, y = smart ? Math.max(H - h, -image.top * h) : H - h;
    // the centring of the settings: on its figure, or on the middle of it
    var cx = smart ? image.cx : 0.5;
    var x = w <= W ? (W - w) / 2 : Math.min(0, Math.max(W - w, W / 2 - cx * w));
    return { x: Math.round(x), y: Math.round(y), w: Math.round(w), h: Math.round(h), iw: iw, ih: ih };
  }

  // a picture, smeared along an axis by d pixels: copies averaged over the
  // stroke, each the 1/(i+1) of what is there
  function drawImage(index, alpha) {
    var image = images[index], src = frameOf(image), rect = imageRect(image, src);
    g.imageSmoothingQuality = 'high';
    var t = now();
    var blur = state.blur, slice = state.slice;
    var d = 0;
    if (blur) {
      d = rect.h * BLUR.amount[blurSettings.amount] * Math.exp(-(t - blur.t0) / BLUR.decay[blurSettings.decay]);
      if (d < 0.5) { state.blur = null; d = 0; }
    }
    if (slice) {
      var amount = rect.h * 0.08 * Math.exp(-(t - slice.t0) / 0.12);
      if (amount < 0.5) state.slice = null;
      else {
        // the picture it cuts from, if it changed (not when locked)
        var old = slice.from >= 0 && slice.from !== index && images[slice.from] ? images[slice.from] : null;
        var oldSrc = old ? frameOf(old) : src;
        return drawSlices({ src: src, rect: rect }, { src: oldSrc, rect: old ? imageRect(old, oldSrc) : rect },
          alpha, slice, amount, 1 - amount / (rect.h * 0.08));
      }
    }
    if (!d) {
      g.globalAlpha = alpha;
      g.drawImage(src, rect.x, rect.y, rect.w, rect.h);
      return;
    }
    // fewer as the smear fades: no closer than 3 px, at least 3
    var copies = Math.max(3, Math.min(BLUR.quality[blurSettings.quality], Math.ceil(2 * d / 3)));
    if (copies > 9 || canvas.width * canvas.height > BIG_STAGE) return drawSmearHalf(src, rect, alpha, blur.axis, d, copies);
    for (var i = 0; i < copies; i++) {
      var off = -d + 2 * d * i / (copies - 1);
      g.globalAlpha = alpha / (i + 1);
      g.drawImage(src, rect.x + (blur.axis === 'h' ? off : 0), rect.y + (blur.axis === 'v' ? off : 0), rect.w, rect.h);
    }
  }

  // Many copies (the high and extreme quality), and any on a stage above
  // about 1080p (a full screen of 1440p or 4K, where each copy of the whole
  // picture costs millions of pixels a frame), go to a canvas of half the
  // size, drawn up once: a quarter of the pixels each, which a smear hides.
  // It keeps its size while the stage and the blur amount do, room for the
  // widest smear around the picture.
  var BIG_STAGE = 2.2e6;
  var half = document.createElement('canvas'), halfContext = half.getContext('2d');
  function drawSmearHalf(src, rect, alpha, axis, d, copies) {
    var pad = Math.ceil(rect.h * BLUR.amount[blurSettings.amount]);
    var padX = axis === 'h' ? pad : 0, padY = axis === 'v' ? pad : 0;
    var hw = Math.ceil((rect.w + 2 * padX) / 2), hh = Math.ceil((rect.h + 2 * padY) / 2);
    if (half.width !== hw || half.height !== hh) {
      half.width = hw;
      half.height = hh;
    } else {
      halfContext.clearRect(0, 0, hw, hh);
    }
    halfContext.imageSmoothingQuality = 'high';
    for (var i = 0; i < copies; i++) {
      var off = -d + 2 * d * i / (copies - 1);
      halfContext.globalAlpha = 1 / (i + 1);
      halfContext.drawImage(src, (padX + (axis === 'h' ? off : 0)) / 2, (padY + (axis === 'v' ? off : 0)) / 2, rect.w / 2, rect.h / 2);
    }
    g.globalAlpha = alpha;
    g.drawImage(half, 0, 0, hw, hh, rect.x - padX, rect.y - padY, hw * 2, hh * 2);
  }

  // The spectrum analyser of the settings, as the equalizer of the Teto and
  // Miku skins: bands on a logarithmic scale from 45 Hz to 15 kHz, mirrored
  // from the middle, each with its peak line, standing on the bar, behind the
  // picture. Drawn dark under the colour, it takes the colour and its blend
  // as the picture does.
  var spectrumData = null, levels = null, tops = null, edges = null;
  function drawSpectrum(W, H) {
    if (options.spectrum !== 'on' || !analyser) return;
    var half = W / dpr < 700 ? 12 : 24;
    if (!levels || levels.length !== half) {
      levels = new Float32Array(half);
      tops = new Float32Array(half);
      edges = null;
    }
    if (!edges) {
      edges = [];
      for (var e = 0; e <= half; e++) {
        var bin = Math.round(45 * Math.pow(15000 / 45, e / half) * analyser.fftSize / audio.sampleRate);
        edges.push(Math.min(analyser.frequencyBinCount, e ? Math.max(bin, edges[e - 1] + 1) : bin));
      }
    }
    var live = audio.state === 'running' && track;
    if (live) analyser.getByteFrequencyData(spectrumData);
    var any = false;
    for (var b = 0; b < half; b++) {
      var level = 0;
      if (live) {
        var loudest = 0;
        for (var k = edges[b]; k < edges[b + 1]; k++) if (spectrumData[k] > loudest) loudest = spectrumData[k];
        // above a floor that sinks toward the treble, which gets a lift too
        var floor = 0.42 - 0.22 * b / half;
        level = Math.max(0, (loudest / 255 - floor) / (1 - floor));
        level = Math.min(1, Math.pow(level, 1.3) * (1 + 0.6 * b / half));
      }
      levels[b] = level > levels[b] ? level : levels[b] + (level - levels[b]) * 0.3;
      tops[b] = Math.max(levels[b], tops[b] - 0.01);
      if (tops[b] > 0.005) any = true;
    }
    if (!any) return;
    var bottom = H - barHeight * dpr, height = H * 0.22;
    var gap = 4 * dpr, pad = 12 * dpr, count = half * 2;
    var width = (W - 2 * pad - gap * (count - 1)) / count;
    var fill = g.createLinearGradient(0, bottom, 0, bottom - height);
    fill.addColorStop(0, 'rgba(0, 0, 0, 0.45)');
    fill.addColorStop(1, 'rgba(0, 0, 0, 0.2)');
    g.globalCompositeOperation = 'source-over';
    g.globalAlpha = 1;
    for (var i = 0; i < count; i++) {
      var band = i < half ? half - 1 - i : i - half;
      var x = pad + i * (width + gap);
      var tall = Math.max(0.02, levels[band]) * height;
      g.fillStyle = fill;
      g.fillRect(x, bottom - tall, width, tall);
      g.fillStyle = 'rgba(0, 0, 0, 0.7)';
      g.fillRect(x, bottom - tops[band] * height - 2 * dpr, width, 2 * dpr);
    }
  }

  // the picture cut into strips moved apart, which close up again
  // A slice cuts between the picture there was and the new one: each strip
  // of either, more of the new one as the shifts settle (done: 0 to 1)
  function drawSlices(fresh, old, alpha, slice, amount, done) {
    var strips = 14;
    g.globalAlpha = alpha;
    function noise(i, k, scale) {
      var r = Math.sin(slice.seed + i * k) * scale;
      return r - Math.floor(r);
    }
    for (var i = 0; i < strips; i++) {
      var shift = (noise(i, 12.9898, 43758.5453) - 0.5) * 2 * amount;
      var shift2 = (noise(i, 78.233, 12345.6789) - 0.5) * 2 * amount;
      if (slice.axis !== 'v') {
        var a = noise(i, 39.3468, 24634.6345) < 0.4 + 0.6 * done ? fresh : old, ra = a.rect;
        g.drawImage(a.src, 0, i * ra.ih / strips, ra.iw, ra.ih / strips,
          ra.x + shift, ra.y + i * ra.h / strips, ra.w, ra.h / strips);
      }
      if (slice.axis !== 'h') {
        var b = noise(i, 93.9898, 35791.2468) < 0.4 + 0.6 * done ? fresh : old, rb = b.rect;
        g.drawImage(b.src, i * rb.iw / strips, 0, rb.iw / strips, rb.ih,
          rb.x + i * rb.w / strips, rb.y + shift2, rb.w / strips, rb.h);
      }
    }
  }

  function draw() {
    var W = canvas.width, H = canvas.height, t = now();
    g.globalCompositeOperation = 'source-over';
    g.globalAlpha = 1;
    // the backdrop of the settings; a transparent one lets the page through
    if (options.backdrop === 'transparent') g.clearRect(0, 0, W, H);
    else {
      g.fillStyle = options.backdrop === 'black' ? '#000' : '#fff';
      g.fillRect(0, 0, W, H);
    }

    // the analyser behind the picture, as the equalizer of the skins stands
    // behind the page
    drawSpectrum(W, H);
    if (state.image >= 0) drawImage(state.image, 1);

    // the colour over everything, as 0x40 Hues lays it: hard light at 70%
    g.globalAlpha = 0.7;
    g.globalCompositeOperation = options.blend;
    g.fillStyle = cssRgb(currentRgb());
    g.fillRect(0, 0, W, H);
    g.globalAlpha = 1;

    if (state.invert) {
      g.globalCompositeOperation = 'difference';
      g.fillStyle = '#fff';
      g.fillRect(0, 0, W, H);
    }
    var b = state.blackout;
    if (b) {
      var dark;
      if (b.hold) dark = Math.min(1, (t - b.t0) / 0.05);
      else if (t < b.release) dark = 1;
      else dark = 1 - (t - b.release) / b.out;
      // a held one may draw its first frame at the very time it began (the
      // clock is coarse), so only one going out ends at 0
      if (dark <= 0) { if (!b.hold) state.blackout = null; }
      else {
        g.globalCompositeOperation = 'source-over';
        g.globalAlpha = dark;
        g.fillStyle = '#000';
        g.fillRect(0, 0, W, H);
        g.globalAlpha = 1;
      }
    }
  }

  // Until the sound starts, while a song loads and when paused, the colours
  // drift slowly
  var idleAt = 0;
  function idle() {
    var t = now();
    if (t - idleAt > 2.5) {
      idleAt = t;
      randomHue(2.4);
    }
  }

  function frame() {
    if (track && audio && audio.state === 'running') { beats(); autoSong(); }
    else idle();
    draw();
    requestAnimationFrame(frame);
  }

  // ---- the options of the settings

  // What 0x40 Hues lets one choose, each a few values, kept in this browser;
  // the first of the values is the default
  var OPTIONS_KEY = 'sanakan-hues-settings';
  var OPTIONS = {
    autoSong: ['off', 'time', 'loop'],
    autoShuffle: ['on', 'off'],
    autoFade: ['on', 'off'],
    buildups: ['on', 'off', 'once'],
    smartAlign: ['on', 'off'],
    shuffleImages: ['on', 'off'],
    colours: ['normal', 'pastel'],
    blend: ['hard-light', 'screen', 'multiply'],
    backdrop: ['transparent', 'white', 'black'],
    spectrum: ['off', 'on']
  };
  // only what was picked is kept, so the defaults may change later
  var options = {}, picked = {}, autoAfter = 5;
  (function () {
    var kept = {};
    try { kept = JSON.parse(localStorage.getItem(OPTIONS_KEY) || '{}') || {}; } catch (_) { /* the defaults */ }
    Object.keys(OPTIONS).forEach(function (key) {
      if (OPTIONS[key].indexOf(kept[key]) >= 0) picked[key] = kept[key];
      options[key] = picked[key] || OPTIONS[key][0];
    });
    if (kept.autoAfter >= 1 && kept.autoAfter <= 99) autoAfter = picked.autoAfter = Math.round(kept.autoAfter);
  })();
  function saveOptions() {
    try { localStorage.setItem(OPTIONS_KEY, JSON.stringify(picked)); } catch (_) { /* this visit only */ }
  }
  function setOption(key, value, quiet) {
    if (OPTIONS[key].indexOf(value) < 0) return;
    options[key] = value;
    var radio = document.querySelector('input[name="hues-opt-' + key + '"][value="' + value + '"]');
    if (radio) radio.checked = true;
    if (!quiet) {
      picked[key] = value;
      saveOptions();
    }
    if (key === 'colours') showHue(state.hue);
    if (key === 'backdrop') document.body.setAttribute('data-backdrop', value);
    if (key === 'autoSong') paintAutoAfter();
  }
  // "po 5 minutach", "po 1 pętli"
  function paintAutoAfter() {
    var loops = options.autoSong === 'loop';
    $('hues-auto-unit').textContent = loops ? (autoAfter === 1 ? 'pętli' : 'pętlach') : (autoAfter === 1 ? 'minucie' : 'minutach');
    $('hues-auto-after').disabled = options.autoSong === 'off';
  }

  // The next song by itself, after so many minutes of a song or rounds of its
  // loop: one at random or the next, faded out first if so chosen
  var switching = false;
  function autoSong() {
    if (!track || switching || options.autoSong === 'off' || audio.state !== 'running') return;
    var heard = audio.currentTime;
    var due = options.autoSong === 'time'
      ? heard - track.t0 >= autoAfter * 60
      : heard >= track.loopAt && Math.floor((heard - track.loopAt) / track.loopLength) >= autoAfter;
    if (due) nextSong();
  }
  function nextSong() {
    var next = song + 1;
    if (options.autoShuffle === 'on' && songs.length > 1) {
      next = Math.floor(Math.random() * (songs.length - 1));
      if (next >= song) next++;
    }
    switching = true;
    if (options.autoFade !== 'on' || !gain) { playSong(next); return; }
    var at = audio.currentTime, ticket = loading;
    gain.gain.cancelScheduledValues(at);
    gain.gain.setValueAtTime(gain.gain.value, at);
    gain.gain.linearRampToValueAtTime(0, at + 2);
    // a song picked meanwhile wins
    setTimeout(function () { if (ticket === loading) playSong(next); }, 2000);
  }

  // ---- the songs

  function decode(data) {
    return new Promise(function (resolve, reject) {
      // the old Safari takes callbacks only
      var promise = audio.decodeAudioData(data, resolve, reject);
      if (promise && promise.then) promise.then(resolve, reject);
    });
  }

  // a file of the pack by its name, or one dropped on the page
  function readSong(file) {
    var data = typeof file === 'string'
      ? fetch(PACK + 'Songs/' + encodeURIComponent(file)).then(function (response) {
        if (!response.ok) throw new Error(response.status);
        return response.arrayBuffer();
      })
      : file.arrayBuffer();
    return data.then(decode);
  }

  function stopSong() {
    sources.forEach(function (node) {
      try { node.stop(); } catch (_) { /* never started */ }
      node.disconnect();
    });
    sources = [];
    track = null;
    map = null;
  }

  function play(buffer, at, loop) {
    var node = audio.createBufferSource();
    node.buffer = buffer;
    node.loop = loop;
    node.connect(input);
    node.start(at);
    sources.push(node);
  }

  // A song's classic maps, spread over its loop as 0x40 Hues spreads them;
  // the buildup's at the loop's pace, with dots before it to end with it
  function classicMaps(entry) {
    if (!entry.rhythm) return {};
    var loop = { chars: entry.rhythm, start: 0, step: entry.loopBuffer.duration / entry.rhythm.length };
    var build = null;
    if (entry.buildBuffer && entry.buildupRhythm) {
      var chars = entry.buildupRhythm, length = entry.buildBuffer.duration;
      var wanted = Math.round(length / loop.step);
      if (chars.length < wanted) chars = new Array(wanted - chars.length + 1).join('.') + chars;
      build = { chars: chars, start: 0, step: length / chars.length };
    }
    return { loop: loop, build: build };
  }

  // the extended ones made from them, once a song
  function extendedMaps(entry) {
    var classicOnes = classicMaps(entry);
    if (!classicOnes.loop) return {};
    if (!entry.extended) {
      entry.extended = {
        loop: enrich(entry.loopBuffer, classicOnes.loop.chars),
        build: classicOnes.build ? enrich(entry.buildBuffer, classicOnes.build.chars) : null
      };
    }
    return {
      loop: { chars: entry.extended.loop, start: 0, step: classicOnes.loop.step },
      build: classicOnes.build ? { chars: entry.extended.build, start: 0, step: classicOnes.build.step } : null
    };
  }

  function playSong(index) {
    if (!songs.length) return;
    index = ((index % songs.length) + songs.length) % songs.length;
    var entry = songs[index], ticket = ++loading;
    song = index;
    stopSong();
    // the volume back from a fade out
    switching = false;
    if (gain) {
      gain.gain.cancelScheduledValues(audio.currentTime);
      gain.gain.value = muted ? 0 : volume;
    }
    $('hues-song').textContent = entry.name;
    $('hues-song').title = entry.name + ' (szukaj utworu, U)';
    $('hues-bpm').textContent = '—';
    setStatus('Wczytuję utwór ' + entry.name + '…');
    var ready = entry.loopBuffer ? Promise.resolve() : Promise.all([
      readSong(entry.loop),
      // a song still plays without the buildup it could not read
      entry.build ? readSong(entry.build).catch(function () { return null; }) : null
    ]).then(function (buffers) {
      entry.loopBuffer = buffers[0];
      entry.buildBuffer = buffers[1];
    });
    ready.then(function () {
      if (ticket !== loading || entry.loopMap) return;
      setStatus('Szukam rytmu…');
      // the message first, the counting after it is drawn
      return new Promise(function (resolve) {
        setTimeout(function () {
          entry.loopMap = analyse(entry.loopBuffer);
          if (entry.buildBuffer) {
            var period = 2 * entry.loopMap.step;
            entry.buildMap = analyse(entry.buildBuffer, {
              period: period,
              phase: (entry.buildBuffer.duration + entry.loopMap.start) % period
            });
          }
          resolve();
        }, 30);
      });
    }).then(function () {
      if (ticket !== loading) return;
      // the buildup always, never, or the first time the song plays
      var build = options.buildups === 'on' || options.buildups === 'once' && !entry.builtOnce ? entry.buildBuffer : null;
      if (build) entry.builtOnce = true;
      var t0 = audio.currentTime + 0.05;
      var loopAt = t0 + (build ? build.duration : 0);
      if (build) play(build, t0, false);
      play(entry.loopBuffer, loopAt, true);
      track = {
        t0: t0, loopAt: loopAt, loopLength: entry.loopBuffer.duration, withBuild: !!build,
        counted: { loop: entry.loopMap, build: build ? entry.buildMap : null },
        classic: classicMaps(entry),
        extended: extendedMaps(entry)
      };
      map = (build && trackMaps().build) || trackMaps().loop;
      state.part = '';
      state.char = -1;
      state.time = -1;
      $('hues-bpm').textContent = entry.loopMap.bpm.toFixed(1);
      paintBeats(-1);
      setStatus(blocked ? HOLD : '');
    }).catch(function () {
      if (ticket !== loading) return;
      setStatus('Nie udało się wczytać utworu ' + entry.name + '. Możesz dodać własny plik muzyczny.');
    });
  }

  function setVolume(value) {
    volume = Math.max(0, Math.min(1, value));
    $('hues-volume').value = String(Math.round(volume * 100));
    fillRange($('hues-volume'));
    if (gain) gain.gain.value = muted ? 0 : volume;
    try { localStorage.setItem(VOLUME_KEY, String(volume)); } catch (_) { /* this visit only */ }
  }

  function toggleMute() {
    muted = !muted;
    if (gain) gain.gain.value = muted ? 0 : volume;
    $('hues-mute').setAttribute('aria-pressed', muted ? 'true' : 'false');
  }

  function togglePause() {
    if (!audio) return;
    running = !running;
    if (running) audio.resume();
    else audio.suspend();
    paintPause();
  }

  function paintPause() {
    var button = $('hues-pause');
    button.setAttribute('aria-pressed', running ? 'false' : 'true');
    button.setAttribute('aria-label', running ? 'Pauza' : 'Wznów');
  }

  function setLocked(on) {
    locked = on;
    $('hues-lock').setAttribute('aria-pressed', on ? 'true' : 'false');
  }

  function toggleUi() {
    document.body.classList.toggle('hues-bare');
  }

  // The settings: the speed of the animations and files of the visitor's
  // own; one panel above the bar at a time
  var settings = $('hues-settings');
  function toggleSettings(show) {
    // the focus leaves with it for the gear, or the keys would go to nothing
    if (!settings.hidden && settings.contains(document.activeElement) && show !== true) $('hues-settings-toggle').focus();
    settings.hidden = show === undefined ? !settings.hidden : !show;
    if (!settings.hidden) { toggleHelp(false); closePicker(); }
    $('hues-settings-toggle').setAttribute('aria-expanded', settings.hidden ? 'false' : 'true');
  }

  function toggleHelp(show) {
    if (!help.hidden && help.contains(document.activeElement) && show !== true) $('hues-help-toggle').focus();
    help.hidden = show === undefined ? !help.hidden : !show;
    if (!help.hidden) { closePicker(); toggleSettings(false); }
    $('hues-help-toggle').setAttribute('aria-expanded', help.hidden ? 'false' : 'true');
  }

  function fullscreen() {
    var root = document.documentElement;
    if (document.fullscreenElement) document.exitFullscreen();
    else if (root.requestFullscreen) root.requestFullscreen().catch(function () { /* refused */ });
  }

  // Files of the visitor's own, chosen or dropped: songs go on the list and
  // the last one plays, pictures join the others; read here, sent nowhere
  function addFiles(files) {
    var last = -1;
    Array.prototype.forEach.call(files, function (file) {
      var name = file.name.replace(/\.[^.]+$/, '');
      if (/^audio\//.test(file.type) || /\.(mp3|ogg|oga|opus|webm|wav|flac|m4a|aac)$/i.test(file.name)) {
        songs.push({ name: name, loop: file, build: null });
        last = songs.length - 1;
      } else if (/^image\//.test(file.type) && window.createImageBitmap) {
        createImageBitmap(file).then(function (bitmap) {
          addImage(name, [bitmap], 'file:' + name + ':' + file.size + ':' + file.lastModified);
          showImage(images.length - 1);
        }, function () {
          setStatus('Nie udało się odczytać obrazka ' + file.name + '.');
        });
      }
    });
    if (last >= 0) playSong(last);
  }

  // the pack's list
  var packReady = fetch(PACK + 'respack.json').then(function (response) {
    if (!response.ok) throw new Error(response.status);
    return response.json();
  }).then(function (json) {
    var centres = json.centres || {};
    var pictures = json.images.map(function (name) { return { name: name, cx: centres[name] }; }).concat(json.animations);
    songs = json.songs.map(function (entry) {
      return { name: entry.title || entry.name, loop: entry.loop, build: entry.build, rhythm: entry.rhythm, buildupRhythm: entry.buildupRhythm };
    }).concat(songs);
    if (pictures.length && json.pictures) {
      loadPictures(pictures, json.pictures).catch(function () {
        setStatus('Nie udało się wczytać wszystkich obrazów. Możesz dodać własne pliki.');
      });
    }
  }).catch(function () {
    setStatus('Nie udało się wczytać listy obrazów i utworów. Możesz dodać własne pliki.');
  });

  // The page starts at once. A browser that holds the sound back until the visitor does something is
  // told so on the status line, and the first click or key lets it go.
  function begin() {
    var Context = window.AudioContext || window.webkitAudioContext;
    if (!Context) {
      setStatus('Ta przeglądarka nie odtwarza dźwięku przez Web Audio.');
      return;
    }
    audio = new Context();
    gain = audio.createGain();
    gain.connect(audio.destination);
    gain.gain.value = muted ? 0 : volume;
    // the analyser hears the song before the volume, so muting leaves it be;
    // a silent output keeps every browser running it
    input = audio.createGain();
    input.connect(gain);
    analyser = audio.createAnalyser();
    analyser.fftSize = 2048;
    analyser.smoothingTimeConstant = 0.7;
    input.connect(analyser);
    var silent = audio.createGain();
    silent.gain.value = 0;
    analyser.connect(silent);
    silent.connect(audio.destination);
    spectrumData = new Uint8Array(analyser.frequencyBinCount);
    paintPause();
    if (audio.state !== 'running') {
      blocked = true;
      setStatus(HOLD);
      var unlock = function () {
        if (running) audio.resume();
      };
      document.addEventListener('pointerdown', unlock, true);
      document.addEventListener('keydown', unlock, true);
      audio.addEventListener('statechange', function () {
        if (audio.state !== 'running' || !blocked) return;
        blocked = false;
        document.removeEventListener('pointerdown', unlock, true);
        document.removeEventListener('keydown', unlock, true);
        if (statusLine.textContent === HOLD) setStatus('');
      });
    }
    packReady.then(function () {
      playSong(Math.floor(Math.random() * songs.length));
    });
  }
  var HOLD = 'Kliknij lub naciśnij klawisz, aby włączyć dźwięk.';

  // ---- the search of pictures and songs

  // A click on the picture's or the song's name on the bar (or O, U) opens a
  // list of them all with a search field; the arrows move through it, Enter
  // or a click picks. A picture or animation picked stays (the lock); a song
  // picked plays.
  var picker = $('hues-picker'), pickerSearch = $('hues-picker-search'), pickerList = $('hues-picker-list');
  var picking = null, shown = [], active = 0;

  // lower case without the Polish marks, so "zrodlo" finds "źródło"
  function fold(text) {
    return String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l');
  }

  function pickerItems() {
    return picking === 'image'
      ? images.map(function (image, i) {
        return { name: image.name, note: image.frames.length > 1 ? 'animacja' : '', index: i, current: i === state.image };
      })
      : songs.map(function (entry, i) {
        return { name: entry.name, note: entry.build ? 'z buildupem' : '', index: i, current: i === song };
      });
  }

  function renderPicker() {
    var words = fold(pickerSearch.value).split(/\s+/).filter(Boolean);
    shown = pickerItems().filter(function (item) {
      var name = fold(item.name);
      return words.every(function (word) { return name.indexOf(word) >= 0; });
    }).sort(function (a, b) { return a.name.localeCompare(b.name, 'pl'); });
    // without a query on what is on now, with one on the first match
    active = words.length ? 0 : Math.max(0, shown.findIndex(function (item) { return item.current; }));
    pickerList.textContent = '';
    shown.forEach(function (item, k) {
      var row = document.createElement('li');
      row.id = 'hues-pick-' + k;
      row.setAttribute('role', 'option');
      row.textContent = item.name;
      if (item.note) {
        var note = document.createElement('small');
        note.textContent = item.note;
        row.appendChild(note);
      }
      if (item.current) row.classList.add('hues-current');
      row.addEventListener('click', function () { choose(k); });
      row.addEventListener('mousemove', function () {
        if (active !== k) { active = k; paintActive(false); }
      });
      pickerList.appendChild(row);
    });
    $('hues-picker-empty').hidden = shown.length > 0;
    paintActive(true);
  }

  function paintActive(scroll) {
    Array.prototype.forEach.call(pickerList.children, function (row, k) {
      row.setAttribute('aria-selected', k === active ? 'true' : 'false');
    });
    var row = pickerList.children[active];
    if (row) {
      pickerSearch.setAttribute('aria-activedescendant', row.id);
      if (scroll) row.scrollIntoView({ block: 'nearest' });
    } else {
      pickerSearch.removeAttribute('aria-activedescendant');
    }
  }

  function openPicker(kind) {
    if (picking === kind) { closePicker(); return; }
    toggleHelp(false);
    toggleSettings(false);
    picking = kind;
    $('hues-picker-title').textContent = kind === 'image' ? 'Obraz' : 'Utwór';
    pickerSearch.placeholder = kind === 'image' ? 'Szukaj obrazu…' : 'Szukaj utworu…';
    pickerList.setAttribute('aria-label', kind === 'image' ? 'Obrazy' : 'Utwory');
    pickerSearch.value = '';
    picker.hidden = false;
    $(kind === 'image' ? 'hues-image' : 'hues-song').setAttribute('aria-expanded', 'true');
    renderPicker();
    pickerSearch.focus();
  }

  function closePicker() {
    if (!picking) return;
    var opener = $(picking === 'image' ? 'hues-image' : 'hues-song');
    opener.setAttribute('aria-expanded', 'false');
    picker.hidden = true;
    picking = null;
    if (picker.contains(document.activeElement)) opener.focus();
  }

  function choose(k) {
    var item = shown[k];
    if (!item) return;
    if (picking === 'image') {
      showImage(item.index);
      setLocked(true);
    } else {
      playSong(item.index);
    }
    closePicker();
  }

  pickerSearch.addEventListener('input', renderPicker);
  pickerSearch.addEventListener('keydown', function (event) {
    var moves = { ArrowDown: 1, ArrowUp: -1, PageDown: 10, PageUp: -10 };
    if (moves[event.key] && shown.length) {
      event.preventDefault();
      active = Math.max(0, Math.min(shown.length - 1, active + moves[event.key]));
      paintActive(true);
    } else if (event.key === 'Enter') {
      event.preventDefault();
      choose(active);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      closePicker();
    }
  });
  $('hues-picker-close').addEventListener('click', closePicker);
  // a click elsewhere closes it, but not the click of its own name
  document.addEventListener('pointerdown', function (event) {
    if (!picking || picker.contains(event.target)) return;
    if (event.target.closest && event.target.closest('#hues-image, #hues-song')) return;
    closePicker();
  });

  // ---- controls

  $('hues-pause').addEventListener('click', togglePause);
  $('hues-song').addEventListener('click', function () { openPicker('song'); });
  $('hues-image').addEventListener('click', function () { openPicker('image'); });
  // the arrows beside the names, as the keys N, P and the arrows
  $('hues-song-prev').addEventListener('click', function () { playSong(song - 1); });
  $('hues-song-next').addEventListener('click', function () { playSong(song + 1); });
  $('hues-image-prev').addEventListener('click', function () { imageByHand(-1); });
  $('hues-image-next').addEventListener('click', function () { imageByHand(1); });
  $('hues-lock').addEventListener('click', function () { setLocked(!locked); });
  $('hues-speed').addEventListener('input', function () { setAnimationSpeed(parseFloat(this.value)); });
  $('hues-speed-reset').addEventListener('click', function () { setAnimationSpeed(1); });
  Array.prototype.forEach.call(document.querySelectorAll('input[name="hues-mode"]'), function (radio) {
    radio.addEventListener('change', function () { setMode(this.value); });
  });
  $('hues-settings-toggle').addEventListener('click', function () { toggleSettings(); });
  $('hues-settings-close').addEventListener('click', function () { toggleSettings(false); });
  // a click elsewhere closes the settings, but not the click of the gear
  document.addEventListener('pointerdown', function (event) {
    if (settings.hidden || settings.contains(event.target)) return;
    if (event.target.closest && event.target.closest('#hues-settings-toggle')) return;
    toggleSettings(false);
  });
  $('hues-mute').addEventListener('click', toggleMute);
  $('hues-volume').addEventListener('input', function () { setVolume(this.value / 100); });
  $('hues-full').addEventListener('click', fullscreen);
  $('hues-hide').addEventListener('click', toggleUi);
  // a touch of the stage brings the hidden bar back
  canvas.addEventListener('click', function () {
    if (document.body.classList.contains('hues-bare')) toggleUi();
  });
  $('hues-help-toggle').addEventListener('click', function () { toggleHelp(); });
  $('hues-help-close').addEventListener('click', function () { toggleHelp(false); });
  $('hues-files').addEventListener('change', function () {
    addFiles(this.files);
    this.value = '';
  });
  if (!document.documentElement.requestFullscreen) $('hues-full').hidden = true;

  document.addEventListener('dragover', function (event) {
    if (event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') >= 0) event.preventDefault();
  });
  document.addEventListener('drop', function (event) {
    if (!event.dataTransfer || !event.dataTransfer.files.length) return;
    event.preventDefault();
    addFiles(event.dataTransfer.files);
  });

  document.addEventListener('keydown', function (event) {
    if (event.ctrlKey || event.metaKey || event.altKey) return;
    var target = event.target;
    // what takes typing keeps the keys; a slider, a radio or a box does not
    if (target && (target.isContentEditable || /^(TEXTAREA|SELECT)$/.test(target.tagName)
      || target.tagName === 'INPUT' && !/^(range|radio|checkbox|button)$/.test(target.type))) return;
    if (event.key === 'Escape' && picking) { closePicker(); return; }
    if (event.key === 'Escape' && !help.hidden) { toggleHelp(false); return; }
    if (event.key === 'Escape' && !settings.hidden) { toggleSettings(false); return; }
    var key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
    var actions = {
      ' ': togglePause,
      'ArrowLeft': function () { imageByHand(-1); },
      'ArrowRight': function () { imageByHand(1); },
      'ArrowUp': function () { setVolume(volume + 0.05); },
      'ArrowDown': function () { setVolume(volume - 0.05); },
      'n': function () { playSong(song + 1); },
      'p': function () { playSong(song - 1); },
      'k': function () { setLocked(!locked); },
      'm': toggleMute,
      'h': toggleUi,
      'f': fullscreen,
      'o': function () { openPicker('image'); },
      'u': function () { openPicker('song'); },
      's': function () { toggleSettings(); },
      '?': function () { toggleHelp(); }
    };
    // a focused button, slider or radio keeps its own Space and arrows
    if ((key === ' ' && (target.tagName === 'BUTTON' || /^(radio|checkbox)$/.test(target.type)))
      || (/^Arrow/.test(key) && /^(range|radio)$/.test(target.type))) return;
    if (actions[key]) {
      event.preventDefault();
      actions[key]();
    }
  });

  setVolume(volume);
  Object.keys(OPTIONS).forEach(function (key) {
    setOption(key, options[key], true);
    Array.prototype.forEach.call(document.querySelectorAll('input[name="hues-opt-' + key + '"]'), function (radio) {
      radio.addEventListener('change', function () { setOption(key, this.value); });
    });
  });
  $('hues-auto-after').value = String(autoAfter);
  $('hues-auto-after').addEventListener('change', function () {
    var value = Math.round(parseFloat(this.value));
    autoAfter = value >= 1 && value <= 99 ? value : autoAfter;
    this.value = String(autoAfter);
    picked.autoAfter = autoAfter;
    saveOptions();
    paintAutoAfter();
  });
  setAnimationSpeed(animationSpeed, true);
  try { setMode(localStorage.getItem(MODE_KEY) || 'classic', true); } catch (_) { setMode('classic', true); }
  Object.keys(blurSettings).forEach(function (kind) {
    setBlur(kind, blurSettings[kind], true);
    Array.prototype.forEach.call(document.querySelectorAll('input[name="hues-blur-' + kind + '"]'), function (radio) {
      radio.addEventListener('change', function () { setBlur(kind, this.value); });
    });
  });
  resize();
  window.addEventListener('resize', resize);
  showHue(Math.floor(Math.random() * 64));
  begin();
  requestAnimationFrame(frame);
})();
