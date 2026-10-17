// The middles of the figures of 0x40's pictures (hues/centres.php): a picture
// on a stage of the chosen shape, drawn as hues.js draws it with its centring
// on, the whole picture beside it with the window the stage shows, and a
// slider for the middle, which centres.php keeps (in respack.json when run
// locally, on the site otherwise).
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var stage = $('stage'), g = stage.getContext('2d');
  var whole = $('whole'), w = whole.getContext('2d');
  var slider = $('cx');
  var csrf = document.body.getAttribute('data-csrf') || '';
  var names = [], saved = {}, pending = {}, found = {}, pictures = {};
  var index = 0, shape = 0.5625;

  // the lines in the skin's colours
  function colour(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
  }

  // the middle of what is not transparent across it and its top, as
  // figureBox of hues.js finds them
  function figureBox(src) {
    var bw = 128, bh = 72, off = document.createElement('canvas');
    off.width = bw;
    off.height = bh;
    var o = off.getContext('2d', { willReadFrequently: true });
    o.drawImage(src, 0, 0, bw, bh);
    var data = o.getImageData(0, 0, bw, bh).data, min = bw, max = -1, top = -1;
    for (var y = 0; y < bh; y++) {
      for (var x = 0; x < bw; x++) {
        if (data[(y * bw + x) * 4 + 3] > 32) {
          if (top < 0) top = y;
          if (x < min) min = x;
          if (x > max) max = x;
        }
      }
    }
    return max < 0 ? { cx: 0.5, top: 0 } : { cx: (min + max + 1) / 2 / bw, top: top / bh };
  }

  function load(name) {
    if (pictures[name]) return pictures[name];
    pictures[name] = new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () {
        found[name] = figureBox(img);
        resolve(img);
      };
      img.onerror = reject;
      img.src = 'centres.php?a=image&n=' + encodeURIComponent(name);
    });
    return pictures[name];
  }

  function current() {
    var name = names[index];
    if (name in pending) return pending[name];
    if (name in saved) return saved[name];
    return found[name] ? Math.round(found[name].cx * 100) / 100 : 0.5;
  }

  // imageRect of hues.js with the centring on: covering the stage, as
  // tall as it on a narrow one, following the middle
  function rect(img, cx, top, W, H) {
    var iw = img.naturalWidth, ih = img.naturalHeight;
    var h = Math.max(H, W * ih / iw), wd = iw * h / ih;
    var y = Math.max(H - h, -top * h);
    var x = wd <= W ? (W - wd) / 2 : Math.min(0, Math.max(W - wd, W / 2 - cx * wd));
    return { x: x, y: y, w: wd, h: h };
  }

  var shown = null;
  function draw() {
    var name = names[index], img = shown;
    if (!img) return;
    var accent = colour('--accent-light', '#c58af0'), muted = colour('--text-muted', '#9a9fa4');
    var dpr = window.devicePixelRatio || 1, box = $('stage-box');
    var bw = box.clientWidth, bh = box.clientHeight;
    var ratio = shape || window.innerWidth / window.innerHeight;
    var cw = Math.min(bw, bh * ratio), ch = cw / ratio;
    stage.style.width = Math.round(cw) + 'px';
    stage.style.height = Math.round(ch) + 'px';
    stage.width = Math.round(cw * dpr);
    stage.height = Math.round(ch * dpr);
    var W = stage.width, H = stage.height, cx = current();
    var r = rect(img, cx, found[name].top, W, H);
    g.fillStyle = '#fff';
    g.fillRect(0, 0, W, H);
    g.imageSmoothingQuality = 'high';
    g.drawImage(img, r.x, r.y, r.w, r.h);
    if ($('guide').checked) {
      g.strokeStyle = accent;
      g.lineWidth = dpr;
      g.setLineDash([6 * dpr, 6 * dpr]);
      g.beginPath();
      g.moveTo(W / 2, 0);
      g.lineTo(W / 2, H);
      g.stroke();
      g.setLineDash([]);
    }

    // the whole picture, with the window the stage shows
    var ww = whole.clientWidth * dpr, wh = ww * img.naturalHeight / img.naturalWidth;
    whole.width = Math.round(ww);
    whole.height = Math.round(wh);
    whole.style.height = Math.round(wh / dpr) + 'px';
    w.fillStyle = '#fff';
    w.fillRect(0, 0, ww, wh);
    w.drawImage(img, 0, 0, ww, wh);
    var k = ww / r.w, vx = -r.x * k, vy = -r.y * k, vw = W * k, vh = H * k;
    w.fillStyle = 'rgba(16, 17, 18, 0.6)';
    w.fillRect(0, 0, ww, vy);
    w.fillRect(0, vy + vh, ww, wh - vy - vh);
    w.fillRect(0, vy, vx, vh);
    w.fillRect(vx + vw, vy, ww - vx - vw, vh);
    w.lineWidth = 2 * dpr;
    w.strokeStyle = muted;
    w.setLineDash([4 * dpr, 4 * dpr]);
    w.beginPath();
    w.moveTo(found[name].cx * ww, 0);
    w.lineTo(found[name].cx * ww, wh);
    w.stroke();
    w.setLineDash([]);
    w.strokeStyle = accent;
    w.beginPath();
    w.moveTo(cx * ww, 0);
    w.lineTo(cx * ww, wh);
    w.stroke();
  }

  function paintState() {
    var name = names[index], cx = current();
    slider.value = cx;
    slider.style.setProperty('--player-fill', cx * 100 + '%');
    $('value').textContent = cx.toFixed(2);
    $('count').textContent = (index + 1) + ' / ' + names.length;
    var dirty = name in pending;
    $('state').textContent = dirty ? 'niezapisany' : name in saved ? 'ręczny' : 'automatyczny';
    $('state').classList.toggle('centres-dirty', dirty);
    $('save').disabled = !dirty;
    $('revert').disabled = !dirty;
    $('auto').disabled = !(name in saved) && !dirty;
    Array.prototype.forEach.call($('pick').options, function (option, i) {
      var n = names[i];
      option.textContent = (n in pending ? '* ' : n in saved ? '• ' : '  ') + n;
    });
    $('pick').value = String(index);
  }

  function show(i) {
    index = (i + names.length) % names.length;
    var name = names[index];
    shown = null;
    try { history.replaceState(null, '', '#' + encodeURIComponent(name)); } catch (_) { /* the address stays */ }
    load(name).then(function (img) {
      if (names[index] !== name) return;
      shown = img;
      paintState();
      draw();
    });
    paintState();
    // the neighbours ready for the arrows
    load(names[(index + 1) % names.length]);
    load(names[(index - 1 + names.length) % names.length]);
  }

  function setCx(value) {
    var name = names[index];
    value = Math.round(Math.max(0, Math.min(1, value)) * 100) / 100;
    if (name in saved && value === saved[name]) delete pending[name];
    else pending[name] = value;
    paintState();
    draw();
  }

  // cx null gives the picture back to the middle hues.js finds
  function send(name, cx) {
    var form = new FormData();
    form.append('name', name);
    form.append('cx', cx === null ? '' : String(cx));
    form.append('csrf', csrf);
    return fetch('centres.php?a=save', { method: 'POST', body: form, credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) throw new Error(response.status);
      return response.json();
    }).then(function (answer) {
      if (answer.cx === null) delete saved[name];
      else saved[name] = answer.cx;
      delete pending[name];
      if (names[index] === name) { paintState(); draw(); }
    }, function (error) {
      alert('Nie zapisałem: ' + error.message);
    });
  }

  $('prev').addEventListener('click', function () { show(index - 1); });
  $('next').addEventListener('click', function () { show(index + 1); });
  $('pick').addEventListener('change', function () { show(parseInt(this.value, 10)); });
  slider.addEventListener('input', function () { setCx(parseFloat(this.value)); });
  $('save').addEventListener('click', function () {
    var name = names[index];
    if (name in pending) send(name, pending[name]);
  });
  $('revert').addEventListener('click', function () {
    delete pending[names[index]];
    paintState();
    draw();
  });
  $('auto').addEventListener('click', function () { send(names[index], null); });
  $('guide').addEventListener('change', draw);
  Array.prototype.forEach.call(document.querySelectorAll('#shapes button'), function (button) {
    button.addEventListener('click', function () {
      shape = parseFloat(this.getAttribute('data-shape'));
      Array.prototype.forEach.call(document.querySelectorAll('#shapes button'), function (other) {
        other.setAttribute('aria-pressed', String(other === button));
      });
      draw();
    });
  });
  window.addEventListener('resize', draw);

  document.addEventListener('keydown', function (event) {
    if (event.target.tagName === 'SELECT') return;
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      $('save').click();
      return;
    }
    if (event.ctrlKey || event.metaKey || event.altKey) return;
    var step = event.shiftKey ? 0.05 : 0.01;
    var actions = {
      'ArrowLeft': function () { show(index - 1); },
      'ArrowRight': function () { show(index + 1); },
      ',': function () { setCx(current() - step); },
      '<': function () { setCx(current() - step); },
      '.': function () { setCx(current() + step); },
      '>': function () { setCx(current() + step); }
    };
    if (actions[event.key]) {
      event.preventDefault();
      actions[event.key]();
    }
  });

  window.addEventListener('beforeunload', function (event) {
    if (Object.keys(pending).length) event.preventDefault();
  });

  fetch('centres.php?a=pack', { cache: 'no-store', credentials: 'same-origin' }).then(function (response) {
    if (!response.ok) throw new Error(response.status);
    return response.json();
  }).then(function (pack) {
    names = pack.images;
    saved = pack.centres || {};
    names.forEach(function (name, i) {
      var option = document.createElement('option');
      option.value = String(i);
      $('pick').appendChild(option);
    });
    if (!names.length) return;
    var start = names.indexOf(decodeURIComponent(location.hash.slice(1)));
    show(start < 0 ? 0 : start);
  }, function (error) {
    $('state').textContent = 'Nie wczytałem listy obrazów (' + error.message + ').';
  });
})();
