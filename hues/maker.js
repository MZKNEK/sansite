// The maker of pictures for 0x40's pack (hues/maker.php): any picture, its
// background taken away (the colour touching its edges, a wand, a brush) in
// a mask of its own size, placed in a frame 720 high, then made the pack's
// line art: grey levels, a fill of black or white (a threshold or one of the
// screens), the lines of a difference of blurs, a black outline around the
// figure, alpha either full or none. Seen as it is, or as hues.js colours
// it; maker.php puts it into the pack.
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var H = 720, MAX_SOURCE = 2400, UNDO = 20;
  var stage = $('stage'), g = stage.getContext('2d');

  // the picture opened, no larger than MAX_SOURCE, and the mask over it
  // (opaque where it stays)
  var src = null, srcData = null, mask = null, maskCtx = null;
  var masked = null, tint = null;
  // where it lies in the frame: its scale, its top left corner, mirrored
  var place = { s: 1, x: 0, y: 0, flip: false };
  var W = 1280, tool = 'move', view = 'ink', undo = [];
  var frame = document.createElement('canvas'), frameCtx = frame.getContext('2d', { willReadFrequently: true });
  var result = document.createElement('canvas'), resultCtx = result.getContext('2d');
  var dirty = { mask: true, frame: true, ink: true }, opaque = 0, unsaved = false;

  var inputs = ['tolerance', 'brush', 'shrink', 'brightness', 'contrast', 'smooth', 'lines', 'weight', 'outline'];
  function value(id) { return parseFloat($(id).value); }

  function setState(text) { $('state').textContent = text || ''; }

  // ---- the picture and its mask

  function open(blob, name) {
    var url = URL.createObjectURL(blob), img = new Image();
    img.onload = function () {
      URL.revokeObjectURL(url);
      var k = Math.min(1, MAX_SOURCE / Math.max(img.naturalWidth, img.naturalHeight));
      src = document.createElement('canvas');
      src.width = Math.max(1, Math.round(img.naturalWidth * k));
      src.height = Math.max(1, Math.round(img.naturalHeight * k));
      var c = src.getContext('2d', { willReadFrequently: true });
      c.imageSmoothingQuality = 'high';
      c.drawImage(img, 0, 0, src.width, src.height);
      srcData = c.getImageData(0, 0, src.width, src.height).data;
      mask = document.createElement('canvas');
      mask.width = src.width;
      mask.height = src.height;
      maskCtx = mask.getContext('2d', { willReadFrequently: true });
      maskCtx.fillStyle = '#fff';
      maskCtx.fillRect(0, 0, mask.width, mask.height);
      undo = [];
      place.flip = false;
      $('flip').setAttribute('aria-pressed', 'false');
      $('empty').hidden = true;
      if (name) $('name').value = name.replace(/\.[^.]+$/, '').replace(/[_]+/g, ' ').trim();
      changed('mask');

      var transparent = false;
      for (var i = 3; i < srcData.length; i += 4) if (srcData[i] < 250) { transparent = true; break; }
      var note = src.width + '×' + src.height + (k < 1 ? ' (zmniejszony)' : '') + '. ';
      if (transparent) note += 'Ma przezroczystość, tło już usunięte.';
      else if (removeEdges(true)) note += 'Tło od krawędzi usunięte (Cofnij, jeśli źle).';
      else note += 'Tło niejednolite: użyj różdżki lub gumki.';
      setState(note);
      fit();
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      setState('Nie umiem otworzyć tego pliku.');
    };
    img.src = url;
  }

  function pushUndo() {
    var data = maskCtx.getImageData(0, 0, mask.width, mask.height).data, alpha = new Uint8Array(data.length / 4);
    for (var i = 0; i < alpha.length; i++) alpha[i] = data[i * 4 + 3];
    undo.push(alpha);
    if (undo.length > UNDO) undo.shift();
  }

  function popUndo() {
    if (!undo.length) return;
    writeMask(undo.pop());
  }

  function readMask() {
    var data = maskCtx.getImageData(0, 0, mask.width, mask.height).data, alpha = new Uint8Array(data.length / 4);
    for (var i = 0; i < alpha.length; i++) alpha[i] = data[i * 4 + 3];
    return alpha;
  }

  function writeMask(alpha) {
    var image = maskCtx.createImageData(mask.width, mask.height), data = image.data;
    for (var i = 0; i < alpha.length; i++) {
      data[i * 4] = data[i * 4 + 1] = data[i * 4 + 2] = 255;
      data[i * 4 + 3] = alpha[i];
    }
    maskCtx.putImageData(image, 0, 0);
    changed('mask');
  }

  // the pixels like the colour ref (within the tolerance on every channel,
  // alpha too) reached from the seeds without crossing others
  function flood(seeds, ref, tolerance) {
    var w = src.width, h = src.height, seen = new Uint8Array(w * h), stack = new Int32Array(w * h), top = 0;
    function like(i) {
      var p = i * 4;
      return Math.abs(srcData[p] - ref[0]) <= tolerance && Math.abs(srcData[p + 1] - ref[1]) <= tolerance
        && Math.abs(srcData[p + 2] - ref[2]) <= tolerance && Math.abs(srcData[p + 3] - ref[3]) <= tolerance;
    }
    seeds.forEach(function (i) {
      if (!seen[i] && like(i)) { seen[i] = 1; stack[top++] = i; }
    });
    while (top) {
      var i = stack[--top], x = i % w;
      var next = [x > 0 ? i - 1 : -1, x < w - 1 ? i + 1 : -1, i >= w ? i - w : -1, i < w * (h - 1) ? i + w : -1];
      for (var n = 0; n < 4; n++) {
        var j = next[n];
        if (j >= 0 && !seen[j] && like(j)) { seen[j] = 1; stack[top++] = j; }
      }
    }
    return seen;
  }

  function colourAt(i) {
    var p = i * 4;
    return [srcData[p], srcData[p + 1], srcData[p + 2], srcData[p + 3]];
  }

  // The colour most of the edges have, taken away from them inwards. When
  // asked whether it is worth it (on opening), only when at least half of
  // the edges have it.
  function removeEdges(onlyIfPlain) {
    var w = src.width, h = src.height, border = [], i;
    for (i = 0; i < w; i++) border.push(i, (h - 1) * w + i);
    for (i = 1; i < h - 1; i++) border.push(i * w, i * w + w - 1);
    var counts = {}, best = null;
    border.forEach(function (b) {
      var c = colourAt(b), key = (c[0] >> 4) + ',' + (c[1] >> 4) + ',' + (c[2] >> 4) + ',' + (c[3] >> 4);
      var bin = counts[key] || (counts[key] = { n: 0, sum: [0, 0, 0, 0] });
      bin.n++;
      for (var k = 0; k < 4; k++) bin.sum[k] += c[k];
      if (!best || bin.n > best.n) best = bin;
    });
    var ref = best.sum.map(function (s) { return s / best.n; });
    var tolerance = Math.max(8, value('tolerance'));
    var hits = border.filter(function (b) {
      var c = colourAt(b);
      return Math.max(Math.abs(c[0] - ref[0]), Math.abs(c[1] - ref[1]), Math.abs(c[2] - ref[2])) <= tolerance;
    });
    if (onlyIfPlain && hits.length < border.length / 2) return false;
    var region = flood(hits, ref, tolerance);
    pushUndo();
    var alpha = readMask();
    for (i = 0; i < alpha.length; i++) if (region[i]) alpha[i] = 0;
    writeMask(alpha);
    return true;
  }

  function wand(sx, sy, keep) {
    var i = Math.floor(sy) * src.width + Math.floor(sx);
    var region = flood([i], colourAt(i), value('tolerance'));
    pushUndo();
    var alpha = readMask();
    for (var j = 0; j < alpha.length; j++) if (region[j]) alpha[j] = keep ? 255 : 0;
    writeMask(alpha);
  }

  // the box of what stays, in the picture's pixels
  function figureBounds() {
    var alpha = readMask(), w = src.width, h = src.height, box = { x0: w, y0: h, x1: -1, y1: -1 };
    for (var y = 0; y < h; y += 2) {
      for (var x = 0; x < w; x += 2) {
        var i = y * w + x;
        if (alpha[i] >= 128 && srcData[i * 4 + 3] >= 128) {
          if (x < box.x0) box.x0 = x;
          if (x > box.x1) box.x1 = x;
          if (y < box.y0) box.y0 = y;
          if (y > box.y1) box.y1 = y;
        }
      }
    }
    if (box.x1 < 0) return { x0: 0, y0: 0, x1: w, y1: h };
    box.x1 = Math.min(w, box.x1 + 2);
    box.y1 = Math.min(h, box.y1 + 2);
    return box;
  }

  // ---- the place in the frame

  // a point of the frame in the picture's pixels, and back
  function toSource(fx, fy) {
    var sx = (fx - place.x) / place.s;
    return { x: place.flip ? src.width - sx : sx, y: (fy - place.y) / place.s };
  }
  function toFrameX(sx) {
    return place.x + (place.flip ? src.width - sx : sx) * place.s;
  }

  function placeSource(c) {
    if (place.flip) c.setTransform(-place.s, 0, 0, place.s, place.x + src.width * place.s, place.y);
    else c.setTransform(place.s, 0, 0, place.s, place.x, place.y);
  }

  function baseScale() { return H / src.height; }

  function zoomAt(s, fx, fy) {
    s = Math.max(baseScale() / 8, Math.min(baseScale() * 8, s));
    var p = toSource(fx, fy);
    place.s = s;
    place.x = fx - (place.flip ? src.width - p.x : p.x) * s;
    place.y = fy - p.y * s;
    changed('frame');
  }

  function ground() {
    var box = figureBounds();
    place.y = H - box.y1 * place.s;
    changed('frame');
  }

  function centre() {
    var box = figureBounds(), mid = toFrameX((box.x0 + box.x1) / 2);
    place.x += W / 2 - mid;
    changed('frame');
  }

  // the figure as tall as the frame (or as wide, if wider), in the middle,
  // on the bottom edge
  function fit() {
    if (!src) return;
    var box = figureBounds();
    place.s = Math.min(H / (box.y1 - box.y0), W / (box.x1 - box.x0));
    place.x = 0;
    centre();
    ground();
  }

  // ---- the line art

  function boxesForGauss(sigma) {
    var ideal = Math.sqrt(12 * sigma * sigma / 3 + 1), wl = Math.floor(ideal);
    if (wl % 2 === 0) wl--;
    var m = Math.round((12 * sigma * sigma - 3 * wl * wl - 12 * wl - 9) / (-4 * wl - 4));
    return [0, 1, 2].map(function (i) { return ((i < m ? wl : wl + 2) - 1) / 2; });
  }

  function boxPass(from, to, w, h, r, horizontal) {
    var lines = horizontal ? h : w, len = horizontal ? w : h, step = horizontal ? 1 : w, scale = 1 / (2 * r + 1);
    for (var l = 0; l < lines; l++) {
      var base = horizontal ? l * w : l, acc = 0, k;
      for (k = -r; k <= r; k++) acc += from[base + Math.min(len - 1, Math.max(0, k)) * step];
      for (var p = 0; p < len; p++) {
        to[base + p * step] = acc * scale;
        acc += from[base + Math.min(len - 1, p + r + 1) * step] - from[base + Math.max(0, p - r) * step];
      }
    }
  }

  // a Gaussian blur, as three box blurs each way
  function blur(values, w, h, sigma) {
    var out = new Float32Array(values);
    if (sigma < 0.3) return out;
    var tmp = new Float32Array(values.length);
    boxesForGauss(sigma).forEach(function (r) {
      if (r < 1) return;
      boxPass(out, tmp, w, h, r, true);
      boxPass(tmp, out, w, h, r, false);
    });
    return out;
  }

  // how far each pixel is from the nearest one of the set (8 neighbours,
  // diagonals √2)
  function distanceTo(set, w, h) {
    var d = new Float32Array(w * h), far = 1e9, x, y, i;
    for (i = 0; i < d.length; i++) d[i] = set[i] ? 0 : far;
    for (y = 0; y < h; y++) {
      for (x = 0; x < w; x++) {
        i = y * w + x;
        if (!d[i]) continue;
        var v = d[i];
        if (x > 0) v = Math.min(v, d[i - 1] + 1);
        if (y > 0) {
          v = Math.min(v, d[i - w] + 1);
          if (x > 0) v = Math.min(v, d[i - w - 1] + Math.SQRT2);
          if (x < w - 1) v = Math.min(v, d[i - w + 1] + Math.SQRT2);
        }
        d[i] = v;
      }
    }
    for (y = h - 1; y >= 0; y--) {
      for (x = w - 1; x >= 0; x--) {
        i = y * w + x;
        if (!d[i]) continue;
        var u = d[i];
        if (x < w - 1) u = Math.min(u, d[i + 1] + 1);
        if (y < h - 1) {
          u = Math.min(u, d[i + w] + 1);
          if (x < w - 1) u = Math.min(u, d[i + w + 1] + Math.SQRT2);
          if (x > 0) u = Math.min(u, d[i + w - 1] + Math.SQRT2);
        }
        d[i] = u;
      }
    }
    return d;
  }

  // a lone pixel among the others (at most one of its 8 neighbours like it)
  // takes their colour; only inside the figure
  function clean(black, alpha, w, h) {
    var out = new Uint8Array(black);
    for (var y = 1; y < h - 1; y++) {
      for (var x = 1; x < w - 1; x++) {
        var i = y * w + x;
        if (!alpha[i]) continue;
        var same = 0;
        for (var dy = -1; dy <= 1; dy++) {
          for (var dx = -1; dx <= 1; dx++) if ((dx || dy) && black[i + dy * w + dx] === black[i]) same++;
        }
        if (same <= 1) out[i] = black[i] ? 0 : 1;
      }
    }
    return out;
  }

  function bayer(n) {
    var m = [0];
    for (var size = 1; size < n; size *= 2) {
      var next = [];
      for (var y = 0; y < size * 2; y++) {
        for (var x = 0; x < size * 2; x++) {
          var v = m[(y % size) * size + (x % size)] * 4;
          next.push(v + [0, 2, 3, 1][(y >= size ? 2 : 0) + (x >= size ? 1 : 0)]);
        }
      }
      m = next;
    }
    return m;
  }
  var BAYER = bayer(8);
  // a clustered dot that grows from its middle as it darkens
  var DOTS = [12, 5, 6, 13, 4, 0, 1, 7, 11, 3, 2, 8, 15, 10, 9, 14];

  function compose() {
    if (dirty.mask) {
      masked = document.createElement('canvas');
      masked.width = src.width;
      masked.height = src.height;
      var m = masked.getContext('2d');
      m.drawImage(src, 0, 0);
      m.globalCompositeOperation = 'destination-in';
      m.drawImage(mask, 0, 0);
      tint = document.createElement('canvas');
      tint.width = src.width;
      tint.height = src.height;
      var t = tint.getContext('2d');
      t.fillStyle = 'rgba(255, 0, 90, 0.55)';
      t.fillRect(0, 0, src.width, src.height);
      t.globalCompositeOperation = 'destination-out';
      t.drawImage(mask, 0, 0);
      dirty.mask = false;
    }
    if (frame.width !== W) frame.width = W;
    frame.height = H;
    frameCtx.setTransform(1, 0, 0, 1, 0, 0);
    frameCtx.clearRect(0, 0, W, H);
    frameCtx.imageSmoothingQuality = 'high';
    placeSource(frameCtx);
    frameCtx.drawImage(masked, 0, 0);
    frameCtx.setTransform(1, 0, 0, 1, 0, 0);
    dirty.frame = false;
  }

  function ink() {
    var w = W, h = H, n = w * h, rgba = frameCtx.getImageData(0, 0, w, h).data, i, x, y;
    var alpha = new Uint8Array(n), levelled = new Float32Array(n);
    var bright = value('brightness'), contrast = value('contrast');
    for (i = 0; i < n; i++) {
      var a = rgba[i * 4 + 3] / 255;
      alpha[i] = a >= 0.5 ? 1 : 0;
      // over white, as the stage shows it
      var grey = (0.299 * rgba[i * 4] + 0.587 * rgba[i * 4 + 1] + 0.114 * rgba[i * 4 + 2]) * a + 255 * (1 - a);
      levelled[i] = (grey - 128) * contrast + 128 + bright;
    }
    var shrink = value('shrink');
    if (shrink > 0) {
      var inside = distanceTo(alpha.map(function (v) { return v ? 0 : 1; }), w, h);
      for (i = 0; i < n; i++) if (inside[i] <= shrink) alpha[i] = 0;
    }

    var mode = $('mode').value, tone = blur(levelled, w, h, value('smooth')), fill = new Uint8Array(n);
    if (mode === 'diffuse') {
      var e = new Float32Array(tone);
      for (y = 0; y < h; y++) {
        for (x = 0; x < w; x++) {
          i = y * w + x;
          if (!alpha[i]) continue;
          var old = e[i], black = old < 128, err = old - (black ? 0 : 255);
          fill[i] = black ? 1 : 0;
          if (x < w - 1) e[i + 1] += err * 7 / 16;
          if (y < h - 1) {
            if (x > 0) e[i + w - 1] += err * 3 / 16;
            e[i + w] += err * 5 / 16;
            if (x < w - 1) e[i + w + 1] += err / 16;
          }
        }
      }
    } else {
      for (y = 0; y < h; y++) {
        for (x = 0; x < w; x++) {
          i = y * w + x;
          var limit = mode === 'bayer' ? (BAYER[(y & 7) * 8 + (x & 7)] + 0.5) * 4
            : mode === 'dots' ? (15 - DOTS[(y & 3) * 4 + (x & 3)] + 0.5) * 16 : 128;
          fill[i] = tone[i] < limit ? 1 : 0;
        }
      }
    }
    var tidy = $('clean').checked;
    if (tidy && mode === 'threshold') fill = clean(fill, alpha, w, h);
    if ($('invert').checked) for (i = 0; i < n; i++) fill[i] = fill[i] ? 0 : 1;

    // the lines: where a narrow blur is darker than a wider one
    var strength = value('lines'), line = new Uint8Array(n);
    if (strength > 0) {
      var sigma = value('weight'), narrow = blur(levelled, w, h, sigma), wide = blur(levelled, w, h, sigma * 1.6);
      var limitLine = 0.5 + (100 - strength) / 100 * 14;
      for (i = 0; i < n; i++) line[i] = alpha[i] && narrow[i] - wide[i] < -limitLine ? 1 : 0;
      if (tidy) line = clean(line, alpha, w, h);
    }

    var outline = value('outline'), around = null;
    if (outline > 0) around = distanceTo(alpha, w, h);

    var image = resultCtx.createImageData(w, h), out = image.data;
    opaque = 0;
    for (i = 0; i < n; i++) {
      var p = i * 4;
      if (alpha[i]) {
        var c = fill[i] || line[i] ? 0 : 255;
        out[p] = out[p + 1] = out[p + 2] = c;
        out[p + 3] = 255;
        opaque++;
      } else if (around && around[i] <= outline) {
        out[p + 3] = 255;
        opaque++;
      }
    }
    result.width = w;
    result.height = h;
    resultCtx.putImageData(image, 0, 0);
    dirty.ink = false;
    $('save').disabled = $('download').disabled = !opaque;
  }

  // ---- the stage

  var LEVELS = [0, 0x55, 0xaa, 0xff], hue = 0x2b, busy = false, pointer = null;
  var checker = (function () {
    var c = document.createElement('canvas');
    c.width = c.height = 16;
    var x = c.getContext('2d');
    x.fillStyle = '#c8c8c8';
    x.fillRect(0, 0, 16, 16);
    x.fillStyle = '#9a9a9a';
    x.fillRect(0, 0, 8, 8);
    x.fillRect(8, 8, 8, 8);
    return c;
  })();

  // the stage shows the frame with a margin around it, the picture faint
  // where the frame leaves it out
  var layout = { k: 1, ox: 0, oy: 0 };
  function draw() {
    var box = $('stage-box'), dpr = window.devicePixelRatio || 1;
    var bw = box.clientWidth, bh = box.clientHeight, pad = 0.06;
    var ratio = W * (1 + 2 * pad * H / W) / (H * (1 + 2 * pad));
    var cw = Math.min(bw, bh * ratio), ch = cw / ratio;
    stage.style.width = Math.round(cw) + 'px';
    stage.style.height = Math.round(ch) + 'px';
    stage.width = Math.round(cw * dpr);
    stage.height = Math.round(ch * dpr);
    var k = stage.height / (H * (1 + 2 * pad));
    layout = { k: k, ox: pad * H * k, oy: pad * H * k, dpr: dpr };
    g.setTransform(1, 0, 0, 1, 0, 0);
    g.clearRect(0, 0, stage.width, stage.height);
    if (!src) return;

    if (dirty.mask || dirty.frame) compose();
    var interacting = busy && view !== 'mask';
    if (!interacting && dirty.ink) ink();

    function atFrame() { g.setTransform(k, 0, 0, k, layout.ox, layout.oy); }
    function source(layer) {
      g.setTransform(1, 0, 0, 1, 0, 0);
      g.translate(layout.ox, layout.oy);
      g.scale(k, k);
      if (place.flip) g.transform(-place.s, 0, 0, place.s, place.x + src.width * place.s, place.y);
      else g.transform(place.s, 0, 0, place.s, place.x, place.y);
      g.drawImage(layer, 0, 0);
    }

    // outside the frame, faint
    g.imageSmoothingQuality = 'high';
    g.globalAlpha = 0.25;
    source(view === 'mask' ? src : masked);
    g.globalAlpha = 1;

    atFrame();
    g.save();
    g.beginPath();
    g.rect(0, 0, W, H);
    g.clip();
    if (view === 'hues') {
      g.fillStyle = '#fff';
      g.fillRect(0, 0, W, H);
    } else {
      g.fillStyle = g.createPattern(checker, 'repeat');
      g.fillRect(0, 0, W, H);
    }
    if (view === 'mask') {
      source(src);
      source(tint);
    } else if (interacting) {
      source(masked);
    } else {
      atFrame();
      g.imageSmoothingEnabled = k < 1;
      g.drawImage(result, 0, 0);
      g.imageSmoothingEnabled = true;
      if (view === 'hues') {
        g.globalAlpha = 0.7;
        g.globalCompositeOperation = 'hard-light';
        g.fillStyle = 'rgb(' + LEVELS[hue & 3] + ',' + LEVELS[(hue >> 2) & 3] + ',' + LEVELS[(hue >> 4) & 3] + ')';
        g.fillRect(0, 0, W, H);
        g.globalCompositeOperation = 'source-over';
        g.globalAlpha = 1;
      }
    }
    g.restore();

    // the frame's edge, and the brush where the pointer is
    atFrame();
    g.lineWidth = 1 / k;
    g.strokeStyle = 'rgba(197, 138, 240, 0.9)';
    g.strokeRect(0, 0, W, H);
    if (pointer && (tool === 'erase' || tool === 'restore') && !spaceHeld) {
      g.beginPath();
      g.arc(pointer.x, pointer.y, value('brush') / 2, 0, 2 * Math.PI);
      g.strokeStyle = '#fff';
      g.stroke();
      g.setLineDash([4 / k, 4 / k]);
      g.strokeStyle = '#000';
      g.stroke();
      g.setLineDash([]);
    }
  }

  var queued = false;
  function redraw() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(function () {
      queued = false;
      draw();
    });
  }

  function changed(what) {
    if (what === 'mask') dirty.mask = true;
    if (what === 'mask' || what === 'frame') dirty.frame = true;
    dirty.ink = true;
    unsaved = true;
    paintZoom();
    redraw();
  }

  // the colours of hues.js, one after another while the preview is on
  setInterval(function () {
    if (view !== 'hues' || !src) return;
    var next;
    do next = 1 + Math.floor(Math.random() * 63); while (next === hue);
    hue = next;
    redraw();
  }, 480);

  // ---- the controls

  function paintRange(input) {
    var min = parseFloat(input.min), max = parseFloat(input.max);
    input.style.setProperty('--player-fill', (parseFloat(input.value) - min) / (max - min) * 100 + '%');
    var output = input.parentNode.querySelector('output');
    if (output && input.id !== 'zoom') output.textContent = input.value;
  }

  function paintZoom() {
    if (!src) return;
    var input = $('zoom');
    input.value = Math.log2(place.s / baseScale()).toFixed(2);
    paintRange(input);
    $('zoom-value').textContent = Math.round(place.s * 100) + '%';
  }

  inputs.forEach(function (id) {
    var input = $(id);
    paintRange(input);
    input.addEventListener('input', function () {
      paintRange(input);
      if (id === 'tolerance' || id === 'brush') redraw();
      else changed('ink');
    });
  });
  ['mode', 'clean', 'invert'].forEach(function (id) {
    $(id).addEventListener('change', function () { changed('ink'); });
  });
  $('zoom').addEventListener('input', function () {
    if (src) zoomAt(baseScale() * Math.pow(2, parseFloat(this.value)), W / 2, H);
  });
  $('width').addEventListener('change', function () {
    var next = parseInt(this.value, 10);
    place.x += (next - W) / 2;
    W = next;
    changed('frame');
  });
  $('fit').addEventListener('click', fit);
  $('ground').addEventListener('click', function () { if (src) ground(); });
  $('centre').addEventListener('click', function () { if (src) centre(); });
  $('flip').addEventListener('click', function () {
    if (!src) return;
    var mid = place.x + src.width * place.s / 2;
    place.flip = !place.flip;
    place.x = mid - src.width * place.s / 2;
    this.setAttribute('aria-pressed', String(place.flip));
    changed('frame');
  });
  $('edges').addEventListener('click', function () { if (src) removeEdges(false); });
  $('reset-mask').addEventListener('click', function () {
    if (!src) return;
    pushUndo();
    maskCtx.globalCompositeOperation = 'source-over';
    maskCtx.fillStyle = '#fff';
    maskCtx.fillRect(0, 0, mask.width, mask.height);
    changed('mask');
  });
  $('undo').addEventListener('click', function () { if (src) popUndo(); });

  function setTool(name) {
    tool = name;
    Array.prototype.forEach.call(document.querySelectorAll('#tools [data-tool]'), function (button) {
      button.setAttribute('aria-pressed', String(button.getAttribute('data-tool') === name));
    });
    stage.style.cursor = name === 'move' ? 'grab' : name === 'wand' ? 'crosshair' : 'none';
    redraw();
  }
  function setView(name) {
    view = name;
    Array.prototype.forEach.call(document.querySelectorAll('#views [data-view]'), function (button) {
      button.setAttribute('aria-pressed', String(button.getAttribute('data-view') === name));
    });
    redraw();
  }
  Array.prototype.forEach.call(document.querySelectorAll('#tools [data-tool]'), function (button) {
    button.addEventListener('click', function () { setTool(button.getAttribute('data-tool')); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('#views [data-view]'), function (button) {
    button.addEventListener('click', function () { setView(button.getAttribute('data-view')); });
  });

  // ---- the pointer on the stage

  var spaceHeld = false, drag = null;
  function framePoint(event) {
    var rect = stage.getBoundingClientRect(), dpr = layout.dpr || 1;
    return {
      x: ((event.clientX - rect.left) * dpr - layout.ox) / layout.k,
      y: ((event.clientY - rect.top) * dpr - layout.oy) / layout.k
    };
  }

  function stroke(from, to) {
    var a = toSource(from.x, from.y), b = toSource(to.x, to.y);
    maskCtx.globalCompositeOperation = tool === 'erase' ? 'destination-out' : 'source-over';
    maskCtx.strokeStyle = '#fff';
    maskCtx.lineWidth = value('brush') / place.s;
    maskCtx.lineCap = maskCtx.lineJoin = 'round';
    maskCtx.beginPath();
    maskCtx.moveTo(a.x, a.y);
    maskCtx.lineTo(b.x + 0.01, b.y);
    maskCtx.stroke();
    maskCtx.globalCompositeOperation = 'source-over';
    changed('mask');
  }

  stage.addEventListener('pointerdown', function (event) {
    if (!src || event.button !== 0) return;
    var p = framePoint(event), moving = tool === 'move' || spaceHeld;
    if (tool === 'wand' && !moving) {
      var s = toSource(p.x, p.y);
      if (s.x >= 0 && s.y >= 0 && s.x < src.width && s.y < src.height) wand(s.x, s.y, event.altKey);
      return;
    }
    stage.setPointerCapture(event.pointerId);
    busy = true;
    drag = { moving: moving, last: p, x: place.x, y: place.y };
    if (moving) stage.style.cursor = 'grabbing';
    else {
      pushUndo();
      stroke(p, p);
    }
  });
  stage.addEventListener('pointermove', function (event) {
    if (!src) return;
    var p = framePoint(event);
    pointer = p;
    if (drag && drag.moving) {
      place.x += p.x - drag.last.x;
      place.y += p.y - drag.last.y;
      drag.last = p;
      changed('frame');
    } else if (drag) {
      stroke(drag.last, p);
      drag.last = p;
    } else redraw();
  });
  function endDrag() {
    if (!drag) return;
    drag = null;
    busy = false;
    setTool(tool);
    changed('ink');
  }
  stage.addEventListener('pointerup', endDrag);
  stage.addEventListener('pointercancel', endDrag);
  stage.addEventListener('pointerleave', function () { pointer = null; redraw(); });
  stage.addEventListener('wheel', function (event) {
    if (!src) return;
    event.preventDefault();
    var p = framePoint(event);
    zoomAt(place.s * Math.pow(2, -event.deltaY / 500), p.x, p.y);
  }, { passive: false });

  // ---- opening: the picker, a drop, a paste

  $('open').addEventListener('change', function () {
    if (this.files[0]) open(this.files[0], this.files[0].name);
    this.value = '';
  });
  document.addEventListener('dragover', function (event) { event.preventDefault(); });
  document.addEventListener('drop', function (event) {
    event.preventDefault();
    var file = event.dataTransfer.files[0];
    if (file && /^image\//.test(file.type)) open(file, file.name);
  });
  document.addEventListener('paste', function (event) {
    var items = (event.clipboardData || {}).items || [];
    for (var i = 0; i < items.length; i++) {
      if (items[i].kind === 'file' && /^image\//.test(items[i].type)) {
        event.preventDefault();
        open(items[i].getAsFile(), '');
        return;
      }
    }
  });

  // ---- saving

  function finished(then) {
    if (dirty.mask || dirty.frame) compose();
    if (dirty.ink) ink();
    result.toBlob(then, 'image/png');
  }

  var ERRORS = { name: 'zła nazwa (bez / \\ : * ? " < > | i kropki na początku)', picture: 'brak obrazu', size: 'zły rozmiar', write: 'nie udało się zapisać pliku' };
  function save(overwrite) {
    var name = $('name').value.trim();
    if (!src || !name) {
      $('name').classList.toggle('invalid', !name);
      if (!name) $('name').focus();
      return;
    }
    $('save').disabled = true;
    setState('Zapisuję…');
    finished(function (blob) {
      var form = new FormData();
      form.append('name', name);
      form.append('picture', blob, name + '.png');
      if (overwrite) form.append('overwrite', '1');
      fetch('maker.php?a=save', { method: 'POST', body: form }).then(function (response) {
        return response.json().then(function (answer) { return { status: response.status, answer: answer }; });
      }).then(function (reply) {
        $('save').disabled = false;
        if (reply.status === 409) {
          if (confirm('Obraz „' + name + '” już jest w paczce. Zastąpić?')) save(true);
          else setState('Nie zapisałem.');
          return;
        }
        if (reply.status !== 200) {
          setState('Nie zapisałem: ' + (ERRORS[reply.answer.error] || reply.answer.error));
          return;
        }
        var a = reply.answer;
        unsaved = false;
        setState(a.code === 0 && a.webp
          ? 'Zapisane: Images/' + a.name + '.webp. ' + a.output.split('\n').pop()
          : 'PNG zapisany w Images/, ale respack.php nie zrobił WebP (kod ' + a.code + '): ' + a.output + ' Ustaw CWEBP i uruchom php hues/respack.php.');
      }).catch(function (error) {
        $('save').disabled = false;
        setState('Nie zapisałem: ' + error.message);
      });
    });
  }
  $('save').addEventListener('click', function () { save(false); });
  $('name').addEventListener('input', function () { this.classList.remove('invalid'); });
  $('download').addEventListener('click', function () {
    if (!src) return;
    finished(function (blob) {
      var link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = ($('name').value.trim() || 'obraz') + '.png';
      link.click();
      setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
    });
  });

  // ---- keys

  document.addEventListener('keydown', function (event) {
    var typing = /^(INPUT|SELECT|TEXTAREA)$/.test(event.target.tagName) && event.target.type !== 'range' && event.target.type !== 'checkbox';
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      save(false);
      return;
    }
    if (typing) return;
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
      event.preventDefault();
      popUndo();
      return;
    }
    if (event.ctrlKey || event.metaKey || event.altKey) return;
    if (event.key === ' ') {
      event.preventDefault();
      if (!spaceHeld) { spaceHeld = true; stage.style.cursor = 'grab'; redraw(); }
      return;
    }
    var brush = $('brush');
    var actions = {
      'v': function () { setTool('move'); },
      'w': function () { setTool('wand'); },
      'e': function () { setTool('erase'); },
      'b': function () { setTool('restore'); },
      '1': function () { setView('mask'); },
      '2': function () { setView('ink'); },
      '3': function () { setView('hues'); },
      '[': function () { brush.value = Math.max(2, value('brush') / 1.2); paintRange(brush); redraw(); },
      ']': function () { brush.value = Math.min(200, value('brush') * 1.2); paintRange(brush); redraw(); }
    };
    var action = actions[event.key.toLowerCase()];
    if (action) {
      event.preventDefault();
      action();
    }
  });
  document.addEventListener('keyup', function (event) {
    if (event.key === ' ' && spaceHeld) {
      spaceHeld = false;
      setTool(tool);
    }
  });

  window.addEventListener('resize', redraw);
  window.addEventListener('beforeunload', function (event) {
    if (src && unsaved) event.preventDefault();
  });
  $('save').disabled = $('download').disabled = true;
  setTool('move');
})();
