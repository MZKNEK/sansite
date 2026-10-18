// The maker of 0x40's songs (hues/songs.php): a song, whole, with three
// marks on it, the start of the buildup (B), of the loop (L) and its end (E),
// laid on the grid of its beats; heard as 0x40 plays it (the buildup once,
// then the loop round and round) or only at the loop's join; and its beat
// maps, a character a cell, every cell a half beat of the loop (the buildup's
// spread over it the same way, ending where the loop starts), written by hand
// or counted from the sound (analyse() of beats.js), with a small stage that
// does what they say. songs.php makes the cuts Opus and lists the maps.
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var EFFECTS = HuesBeats.EFFECTS, analyse = HuesBeats.analyse, makeFft = HuesBeats.makeFft;
  // the pack's songs are Opus, always 48 kHz, so the cuts are made at it
  var RATE = 48000, ROW = 32, UNDO = 100;
  var CHARS = 'xoXO-:*~=|+isv#.';
  var LEGEND = [
    ['x', 'pionowe rozmycie, nowy obraz i barwa'],
    ['o', 'poziome rozmycie, nowy obraz i barwa'],
    ['X', 'pionowe rozmycie i barwa'],
    ['O', 'poziome rozmycie i barwa'],
    ['-', 'nowy obraz i barwa'],
    [':', 'nowa barwa'],
    ['*', 'nowy obraz'],
    ['~', 'płynne przejście barwy'],
    ['=', 'przejście barwy i nowy obraz'],
    ['|', 'krótkie wygaszenie'],
    ['+', 'wygaszenie do następnego znaku'],
    ['i', 'odwrócenie kolorów (rozszerzone)'],
    ['s', 'cięcie do nowego obrazu w poziomie (rozszerzone)'],
    ['v', 'cięcie do nowego obrazu w pionie (rozszerzone)'],
    ['#', 'cięcie do nowego obrazu w obu (rozszerzone)'],
    ['.', 'nic']
  ];

  // the songs go through the volume, kept in the browser, at first 20% as
  // on 0x40
  var VOLUME_KEY = 'sanakan-hues-songs-volume', volume = 0.2;
  try {
    var kept = parseFloat(localStorage.getItem(VOLUME_KEY));
    if (kept >= 0 && kept <= 1) volume = kept;
  } catch (_) { /* the default volume */ }
  var ctx = null, gain = null;
  function audio() {
    if (!ctx) {
      ctx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: RATE });
      gain = ctx.createGain();
      gain.gain.value = volume;
      gain.connect(ctx.destination);
    }
    return ctx;
  }
  function setVolume(value, keep) {
    volume = Math.max(0, Math.min(1, value));
    var range = $('volume');
    range.value = String(Math.round(volume * 100));
    range.style.setProperty('--player-fill', volume * 100 + '%');
    if (gain) gain.gain.value = volume;
    if (keep) try { localStorage.setItem(VOLUME_KEY, String(volume)); } catch (_) { /* this visit only */ }
  }
  setVolume(volume, false);
  $('volume').addEventListener('input', function () { setVolume(this.value / 100, true); });

  // the song (a pack's: its buildup and loop one after another), the marks,
  // the beat and the maps
  var source = null, peaks = null;
  var cut = { b: 0, l: 0, e: 0 };
  var beat = { bpm: 120, phase: 0 };
  var maps = { build: '', loop: '' };
  // a song of the pack being changed (its entry), whether its sound changed
  var packSong = null, soundChanged = false, unsaved = false, keyTyped = false;
  // the song as opened, its middle (the vocals, counted once a song) and how
  // much of it is taken away: as heard, and as the pack has it
  var original = null, centre = null, removal = { on: false, strength: 1, saved: 0, busy: false };
  var history = [];

  function setState(text) { $('state').textContent = text || ''; }
  function colour(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
  }
  var COLOURS = {};
  function readColours() {
    COLOURS = {
      accent: colour('--accent-light', '#c58af0'),
      link: colour('--link', '#b48ee0'),
      muted: colour('--text-muted', '#9a9fa4'),
      text: colour('--text', '#e6e6e6'),
      warning: colour('--warning', '#e0a040'),
      danger: colour('--danger', '#e05060')
    };
  }
  readColours();

  // ---- time, beats and cells

  function period() { return 60 / beat.bpm; }
  function snap(t) {
    if (!$('snap').checked) return t;
    var p = period();
    return beat.phase + Math.round((t - beat.phase) / p) * p;
  }
  function loopStep() { return (cut.e - cut.l) / Math.max(1, maps.loop.length); }
  function buildStep() { return maps.build.length ? (cut.l - cut.b) / maps.build.length : 0; }
  function hasBuild() { return cut.l - cut.b > 0.01; }

  // a map made n long: kept from its start (the loop) or its end (the
  // buildup, which ends where the loop starts)
  function fit(chars, n, fromEnd) {
    n = Math.max(0, n);
    if (chars.length >= n) return fromEnd ? chars.slice(chars.length - n) : chars.slice(0, n);
    var dots = new Array(n - chars.length + 1).join('.');
    return fromEnd ? dots + chars : chars + dots;
  }
  // a map spread over another number of cells, each character where its
  // time falls (another tempo)
  function stretch(chars, n) {
    var out = new Array(n + 1).join('.').split('');
    for (var i = 0; i < chars.length; i++) {
      var ch = chars.charAt(i), at = Math.min(n - 1, Math.round(i * n / chars.length));
      if (ch !== '.' && out[at] === '.') out[at] = ch;
    }
    return out.join('');
  }

  // the cells there are for the marks and the tempo
  function reshape(how) {
    var half = period() / 2;
    var n = Math.max(1, Math.round((cut.e - cut.l) / half));
    maps.loop = how === 'stretch' ? stretch(maps.loop, n) : fit(maps.loop, n, false);
    var m = hasBuild() ? Math.max(1, Math.round((cut.l - cut.b) / ((cut.e - cut.l) / n))) : 0;
    maps.build = how === 'stretch' && maps.build ? stretch(maps.build, m) : fit(maps.build, m, true);
  }

  function snapshot() {
    history.push(JSON.stringify({ cut: cut, beat: beat, maps: maps, soundChanged: soundChanged }));
    if (history.length > UNDO) history.shift();
    unsaved = true;
  }
  function undo() {
    if (!history.length) return;
    var s = JSON.parse(history.pop());
    cut = s.cut; beat = s.beat; maps = s.maps; soundChanged = s.soundChanged;
    if (player) { player.node.loopStart = cut.l; player.node.loopEnd = cut.e; }
    paintTempo();
    changed();
  }

  // ---- opening

  function decode(data) {
    return new Promise(function (resolve, reject) {
      audio().decodeAudioData(data, resolve, reject);
    });
  }

  function computePeaks() {
    var n = 4096, out = new Float32Array(n * 2), chans = [];
    for (var c = 0; c < source.numberOfChannels; c++) chans.push(source.getChannelData(c));
    var per = source.length / n;
    for (var i = 0; i < n; i++) {
      var lo = 0, hi = 0, from = Math.floor(i * per), to = Math.min(source.length, Math.floor((i + 1) * per));
      for (var s = from; s < to; s++) {
        var v = 0;
        for (c = 0; c < chans.length; c++) v += chans[c][s];
        v /= chans.length;
        if (v < lo) lo = v;
        if (v > hi) hi = v;
      }
      out[i * 2] = lo;
      out[i * 2 + 1] = hi;
    }
    peaks = out;
  }

  function begin(buffer) {
    stop();
    source = original = buffer;
    centre = null;
    removal.on = false;
    removal.saved = 0;
    removal.busy = false;
    $('vocals').checked = false;
    $('vocals').disabled = buffer.numberOfChannels < 2;
    computePeaks();
    history = [];
    $('empty').hidden = true;
    unsaved = false;
  }

  // a new song: its tempo counted, the loop 8 bars from its first beat
  function openFile(file) {
    setState('Czytam ' + file.name + '…');
    file.arrayBuffer().then(decode).then(function (buffer) {
      begin(buffer);
      packSong = null;
      soundChanged = true;
      $('pick').value = '';
      $('key').readOnly = false;
      keyTyped = false;
      $('title').value = file.name.replace(/\.[^.]+$/, '').replace(/_/g, ' ');
      $('key').value = keyFrom($('title').value);
      setState('Liczę tempo…');
      setTimeout(function () {
        detect();
        var p = period();
        cut.l = firstLoudBeat();
        cut.b = cut.l;
        cut.e = Math.min(cut.l + 32 * p, beat.phase + Math.floor((source.duration - beat.phase) / p) * p);
        maps = { build: '', loop: '' };
        reshape();
        fillFromSound('loop');
        showAround();
        changed();
        findMoments();
        $('remove').hidden = true;
        setState(file.name + ': ' + source.duration.toFixed(1) + ' s, ' + beat.bpm.toFixed(1) + ' BPM. Ustaw znaczniki, posłuchaj szwu, popraw mapę.');
        unsaved = true;
      }, 30);
    }).catch(function () {
      setState('Nie umiem odczytać tego pliku.');
    });
  }

  function fetchSong(name) {
    return fetch('songs.php?a=file&f=' + encodeURIComponent('Songs/' + name)).then(function (response) {
      if (!response.ok) throw new Error(name);
      return response.arrayBuffer();
    }).then(decode);
  }

  // a song of the pack: its buildup and loop one after another, its maps,
  // the tempo they give
  function openPack(entry) {
    setState('Czytam ' + entry.loop + '…');
    Promise.all([fetchSong(entry.loop), entry.build ? fetchSong(entry.build) : null]).then(function (parts) {
      var loop = parts[0], build = parts[1];
      var lb = build ? build.length : 0, chans = Math.max(loop.numberOfChannels, build ? build.numberOfChannels : 1);
      var whole = audio().createBuffer(chans, lb + loop.length, RATE);
      for (var c = 0; c < chans; c++) {
        var out = whole.getChannelData(c);
        if (build) out.set(build.getChannelData(Math.min(c, build.numberOfChannels - 1)), 0);
        out.set(loop.getChannelData(Math.min(c, loop.numberOfChannels - 1)), lb);
      }
      begin(whole);
      packSong = entry;
      soundChanged = false;
      $('key').value = entry.loop.replace(/^loop_|\.ogg$/g, '');
      $('key').readOnly = true;
      $('title').value = entry.title || entry.name;
      cut = { b: 0, l: lb / RATE, e: whole.duration };
      var rhythm = entry.rhythm || '';
      if (rhythm) {
        beat.bpm = 30 * rhythm.length / loop.duration;
        beat.phase = cut.l;
        maps.loop = rhythm;
        // as classicMaps() of hues.js: dots before it, so it ends with the buildup
        var m = build ? Math.round(build.duration / loopStep()) : 0;
        maps.build = build ? fit(entry.buildupRhythm || '', Math.max(m, (entry.buildupRhythm || '').length), true) : '';
      } else {
        detect();
        beat.phase = cut.l;
        maps = { build: '', loop: '' };
        reshape();
        fillFromSound('loop');
        if (build) fillFromSound('build');
      }
      paintTempo();
      showAround();
      changed();
      findMoments();
      $('remove').hidden = false;
      unsaved = false;
      setState((entry.title || entry.name) + ': pętla ' + loop.duration.toFixed(2) + ' s' + (build ? ', buildup ' + build.duration.toFixed(2) + ' s' : '') + ', ' + beat.bpm.toFixed(2) + ' BPM.');
    }).catch(function (error) {
      setState('Nie wczytałem ' + error.message + '.');
    });
  }

  // "Artist - Night Drive (slowed)" as NightDrive, the way the pack's files
  // are named
  function keyFrom(title) {
    var name = title.split(' - ').pop().replace(/\([^)]*\)|\[[^\]]*\]/g, ' ');
    name = name.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ł/g, 'l').replace(/Ł/g, 'L');
    return name.split(/[^A-Za-z0-9']+/).filter(Boolean).map(function (word) {
      return word.charAt(0).toUpperCase() + word.slice(1);
    }).join('').replace(/^'+/, '').slice(0, 80);
  }

  // ---- counting

  function slice(from, to) {
    var a = Math.max(0, Math.round(from * RATE)), b = Math.min(source.length, Math.round(to * RATE));
    var out = audio().createBuffer(source.numberOfChannels, Math.max(1, b - a), RATE);
    for (var c = 0; c < source.numberOfChannels; c++) out.getChannelData(c).set(source.getChannelData(c).subarray(a, Math.max(a + 1, b)));
    return out;
  }

  // the first beat from which the song is as loud as half its middle, past
  // a quiet intro
  function firstLoudBeat() {
    var p = period(), data = [], c;
    for (c = 0; c < source.numberOfChannels; c++) data.push(source.getChannelData(c));
    var levels = [];
    for (var t = beat.phase; t + p <= source.duration; t += p) {
      var a = Math.round(t * RATE), b = Math.round((t + p) * RATE), sum = 0;
      for (var s = a; s < b; s += 16) for (c = 0; c < data.length; c++) sum += data[c][s] * data[c][s];
      levels.push(sum);
    }
    var middle = levels.slice().sort(function (x, y) { return x - y; })[Math.floor(levels.length / 2)] || 0;
    var first = levels.findIndex(function (level) { return level >= 0.5 * middle; });
    return beat.phase + Math.max(0, first) * p;
  }

  function detect() {
    var counted = analyse(source);
    beat.bpm = Math.round(counted.bpm * 100) / 100;
    beat.phase = counted.start;
    paintTempo();
  }

  // the characteristic hits of the whole song on the beat set here (accents,
  // crashes, a piano's and a bell's notes, as analyse() finds them), counted
  // a moment after what changed them, the last asked for only
  var moments = [], momentsTicket = 0;
  function findMoments() {
    var ticket = ++momentsTicket;
    setTimeout(function () {
      if (ticket !== momentsTicket || !source) return;
      var p = period(), found = analyse(source, { period: p, phase: beat.phase % p });
      moments = found.moments.map(function (m) { return { t: found.start + m.c * found.step, kind: m.kind }; });
      redraw();
    }, 60);
  }
  var MOMENTS = { accent: 'A', crash: 'T', piano: 'P', bells: 'C' };

  // a map counted from the sound of the loop or the buildup, on the beat
  // set here; only into the cells chosen, if any
  function counted(part) {
    var p = period();
    if (part === 'loop') {
      var loop = analyse(slice(cut.l, cut.e), { period: p, phase: 0 }).chars;
      return fit(loop, maps.loop.length, false);
    }
    if (!hasBuild()) return '';
    var length = cut.l - cut.b;
    var build = analyse(slice(cut.b, cut.l), { period: p, phase: length % p }).chars;
    return fit(build, maps.build.length, true);
  }

  function fillFromSound(part) {
    var chars = counted(part);
    if (chars) maps[part] = chars;
  }

  // ---- playing

  var player = null;
  function play(offset) {
    if (!source) return;
    stop();
    var c = audio();
    if (c.state === 'suspended') c.resume();
    var node = c.createBufferSource();
    node.buffer = source;
    node.loop = offset < cut.e;
    node.loopStart = cut.l;
    node.loopEnd = cut.e;
    node.connect(gain);
    offset = Math.max(0, Math.min(source.duration - 0.01, offset));
    node.start(c.currentTime + 0.02, offset);
    player = { node: node, pos: offset, last: c.currentTime + 0.02 };
    lastCell = null;
    node.onended = function () { if (player && player.node === node) stop(); };
    previewReset();
    tick();
  }
  function stop() {
    if (!player) return;
    var node = player.node;
    player = null;
    try { node.stop(); } catch (_) { /* never started */ }
    paintNow(-1, null);
  }
  var lastPos = 0;
  function position() {
    if (!player) return lastPos;
    var now = audio().currentTime;
    if (now > player.last) {
      player.pos += now - player.last;
      player.last = now;
      if (player.node.loop && player.pos >= cut.e && cut.e > cut.l) player.pos = cut.l + (player.pos - cut.l) % (cut.e - cut.l);
    }
    lastPos = player.pos;
    return player.pos;
  }

  // the cell a time falls in: the buildup's or the loop's
  function cellAt(t) {
    if (t >= cut.l && t < cut.e) return { part: 'loop', i: Math.floor((t - cut.l) / loopStep()) };
    if (hasBuild() && t >= cut.b && t < cut.l) return { part: 'build', i: Math.floor((t - cut.b) / buildStep()) };
    return null;
  }
  function cellTime(part, i) {
    return part === 'loop' ? cut.l + i * loopStep() : cut.b + i * buildStep();
  }

  var lastCell = null;
  function tick() {
    if (!player) return;
    var t = position();
    $('clock').textContent = clock(t);
    var cell = cellAt(t);
    if (cell && (!lastCell || cell.part !== lastCell.part || cell.i !== lastCell.i)) {
      var map = maps[cell.part];
      fire(map.charAt(cell.i), cell);
      paintNow(cell.i, cell.part);
    }
    lastCell = cell;
    drawWaves();
    drawPreview();
    requestAnimationFrame(tick);
  }

  function clock(t) {
    var m = Math.floor(t / 60), s = t - m * 60;
    return m + ':' + (s < 10 ? '0' : '') + s.toFixed(3);
  }

  // ---- the preview: what 0x40 does on each character, small

  var LEVELS = [0, 0x55, 0xaa, 0xff];
  var pictures = [], fx = null;
  function hueRgb(i) { return [LEVELS[i & 3], LEVELS[(i >> 2) & 3], LEVELS[(i >> 4) & 3]]; }
  function previewReset() {
    fx = { rgb: hueRgb(0x3f), fade: null, picture: 0, blur: null, slice: null, invert: false, blackout: null };
  }
  previewReset();
  function now() { return performance.now() / 1000; }

  function fire(ch, cell) {
    var effect = EFFECTS[ch], t = now();
    if (!effect) return;
    var step = cell.part === 'loop' ? loopStep() : buildStep();
    if (fx.blackout && fx.blackout.hold) fx.blackout = { t0: t, release: t, out: 0.08 };
    fx.invert = false;
    if (effect.colour) {
      var rgb = hueRgb(1 + Math.floor(Math.random() * 63));
      if (effect.colour === 'fade') {
        var dots = 1, map = maps[cell.part];
        while (dots < 32 && map.charAt(cell.i + dots) === '.') dots++;
        fx.fade = { from: currentRgb(), to: rgb, t0: t, dur: Math.min(4, dots * step) };
      } else fx.fade = null;
      fx.rgb = rgb;
    }
    var before = fx.picture;
    if (effect.image && pictures.length > 1) {
      var next;
      do next = Math.floor(Math.random() * pictures.length); while (next === fx.picture);
      fx.picture = next;
    }
    if (effect.blur) fx.blur = { axis: effect.blur, t0: t };
    if (effect.slice) fx.slice = { axis: effect.slice, t0: t, from: before };
    if (effect.invert) fx.invert = true;
    if (effect.blackout === 'hold') fx.blackout = { t0: t, hold: true };
    if (effect.blackout === 'short') fx.blackout = { t0: t, release: t + Math.min(0.12, step * 0.5), out: step * 0.6 };
  }

  function currentRgb() {
    var f = fx.fade;
    if (!f) return fx.rgb;
    var p = Math.min(1, (now() - f.t0) / f.dur);
    if (p >= 1) { fx.fade = null; return fx.rgb; }
    return [0, 1, 2].map(function (k) { return f.from[k] + (f.to[k] - f.from[k]) * p; });
  }

  var preview = $('preview'), pg = preview.getContext('2d');
  function drawPreview() {
    var dpr = window.devicePixelRatio || 1, w = Math.round(preview.clientWidth * dpr), h = Math.round(w * 9 / 16);
    if (preview.width !== w || preview.height !== h) { preview.width = w; preview.height = h; }
    var t = now();
    pg.globalCompositeOperation = 'source-over';
    pg.globalAlpha = 1;
    pg.fillStyle = '#fff';
    pg.fillRect(0, 0, w, h);
    var img = pictures[fx.picture];
    if (img && img.complete && img.naturalWidth) {
      var ih = h, iw = img.naturalWidth * h / img.naturalHeight, x = (w - iw) / 2;
      var d = fx.blur ? h * 0.12 * Math.exp(-(t - fx.blur.t0) / 0.18) : 0;
      var s = fx.slice ? h * 0.08 * Math.exp(-(t - fx.slice.t0) / 0.12) : 0;
      if (d < 0.5) { fx.blur = null; d = 0; }
      if (s < 0.5) { fx.slice = null; s = 0; }
      if (s) {
        // between the picture there was and the new one, as hues.js cuts
        var strips = 8, old = pictures[fx.slice.from], done = 1 - s / (h * 0.08);
        if (!old || !old.naturalWidth) old = img;
        var ow = old.naturalWidth * h / old.naturalHeight, ox = (w - ow) / 2;
        for (var k = 0; k < strips; k++) {
          var shift = (k % 2 ? 1 : -1) * s, pick = (k * 5 % strips) / strips < 0.4 + 0.6 * done;
          var p = pick ? img : old, px = pick ? x : ox, pw = pick ? iw : ow;
          if (fx.slice.axis !== 'v') pg.drawImage(p, 0, k * p.naturalHeight / strips, p.naturalWidth, p.naturalHeight / strips, px + shift, k * ih / strips, pw, ih / strips);
          if (fx.slice.axis !== 'h') pg.drawImage(p, k * p.naturalWidth / strips, 0, p.naturalWidth / strips, p.naturalHeight, px + k * pw / strips, shift, pw / strips, ih);
        }
      } else if (d) {
        for (var i = 0; i < 8; i++) {
          var off = (i / 7 - 0.5) * d;
          pg.globalAlpha = 1 / (i + 1);
          pg.drawImage(img, x + (fx.blur.axis === 'h' ? off : 0), fx.blur.axis === 'v' ? off : 0, iw, ih);
        }
        pg.globalAlpha = 1;
      } else pg.drawImage(img, x, 0, iw, ih);
    }
    var rgb = currentRgb();
    pg.globalAlpha = 0.7;
    pg.globalCompositeOperation = 'hard-light';
    pg.fillStyle = 'rgb(' + rgb.map(Math.round).join(',') + ')';
    pg.fillRect(0, 0, w, h);
    pg.globalAlpha = 1;
    if (fx.invert) {
      pg.globalCompositeOperation = 'difference';
      pg.fillStyle = '#fff';
      pg.fillRect(0, 0, w, h);
    }
    var b = fx.blackout;
    if (b) {
      var dark = b.hold ? Math.min(1, (t - b.t0) / 0.05) : t < b.release ? 1 : 1 - (t - b.release) / b.out;
      if (dark <= 0) { if (!b.hold) fx.blackout = null; }
      else {
        pg.globalCompositeOperation = 'source-over';
        pg.globalAlpha = dark;
        pg.fillStyle = '#000';
        pg.fillRect(0, 0, w, h);
        pg.globalAlpha = 1;
      }
    }
    pg.globalCompositeOperation = 'source-over';
  }

  // ---- the waves

  var overview = $('overview'), og = overview.getContext('2d');
  var detail = $('detail'), dg = detail.getContext('2d');
  var view = { from: 0, to: 10 };

  function sizeCanvas(canvas) {
    var dpr = window.devicePixelRatio || 1;
    var w = Math.round(canvas.clientWidth * dpr), h = Math.round(canvas.clientHeight * dpr);
    if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
    return dpr;
  }

  function showAround() {
    var pad = Math.max(1, (cut.e - Math.min(cut.b, cut.l)) * 0.08);
    view.from = Math.max(0, Math.min(cut.b, cut.l) - pad);
    view.to = Math.min(source.duration, cut.e + pad);
    if (view.to - view.from < 1) view.to = view.from + 1;
  }

  // min and max of the mono mix over each column of the detailed wave
  var detailCache = null;
  function detailPeaks(width) {
    var key = view.from + ':' + view.to + ':' + width;
    if (detailCache && detailCache.key === key) return detailCache.peaks;
    var out = new Float32Array(width * 2), chans = [];
    for (var c = 0; c < source.numberOfChannels; c++) chans.push(source.getChannelData(c));
    var per = (view.to - view.from) * RATE / width, start = view.from * RATE;
    // a column of many samples looks at some of them only
    var stride = Math.max(1, Math.floor(per / 400));
    for (var x = 0; x < width; x++) {
      var lo = 0, hi = 0, from = Math.max(0, Math.floor(start + x * per)), to = Math.min(source.length, Math.max(from + 1, Math.floor(start + (x + 1) * per)));
      for (var s = from; s < to; s += stride) {
        var v = 0;
        for (c = 0; c < chans.length; c++) v += chans[c][s];
        v /= chans.length;
        if (v < lo) lo = v;
        if (v > hi) hi = v;
      }
      out[x * 2] = lo;
      out[x * 2 + 1] = hi;
    }
    detailCache = { key: key, peaks: out };
    return out;
  }

  function marks(g, toX, h, dpr, labels) {
    [['b', 'B', COLOURS.warning], ['l', 'L', COLOURS.accent], ['e', 'E', COLOURS.accent]].forEach(function (mark) {
      var x = Math.round(toX(cut[mark[0]])) + 0.5;
      g.strokeStyle = mark[2];
      g.lineWidth = 2 * dpr;
      g.beginPath();
      g.moveTo(x, 0);
      g.lineTo(x, h);
      g.stroke();
      if (labels) {
        g.fillStyle = mark[2];
        g.font = (11 * dpr) + 'px "Sanakan Mono", monospace';
        g.fillText(mark[1], x + 4 * dpr, 12 * dpr);
      }
    });
  }

  function drawWaves() {
    if (!source) return;
    var dpr = sizeCanvas(overview), w = overview.width, h = overview.height, i, x;
    og.clearRect(0, 0, w, h);
    var toX = function (t) { return t / source.duration * w; };
    og.fillStyle = 'rgba(224, 160, 64, 0.16)';
    og.fillRect(toX(cut.b), 0, toX(cut.l) - toX(cut.b), h);
    og.fillStyle = 'rgba(155, 89, 182, 0.24)';
    og.fillRect(toX(cut.l), 0, toX(cut.e) - toX(cut.l), h);
    og.fillStyle = COLOURS.muted;
    var n = peaks.length / 2;
    for (x = 0; x < w; x++) {
      var a = Math.floor(x / w * n), b = Math.max(a + 1, Math.floor((x + 1) / w * n)), lo = 0, hi = 0;
      for (i = a; i < b; i++) { lo = Math.min(lo, peaks[i * 2]); hi = Math.max(hi, peaks[i * 2 + 1]); }
      og.fillRect(x, h / 2 - hi * h / 2, 1, Math.max(1, (hi - lo) * h / 2));
    }
    og.strokeStyle = COLOURS.text;
    og.lineWidth = dpr;
    og.strokeRect(toX(view.from) + 0.5, 0.5, toX(view.to) - toX(view.from) - 1, h - 1);
    marks(og, toX, h, dpr, true);
    var pos = position();
    og.fillStyle = COLOURS.text;
    og.fillRect(Math.round(toX(pos)), 0, dpr, h);

    // the detail: the grid of beats, the cells with their characters below
    dpr = sizeCanvas(detail);
    w = detail.width;
    h = detail.height;
    dg.clearRect(0, 0, w, h);
    var span = view.to - view.from, dx = function (t) { return (t - view.from) / span * w; };
    var strip = 22 * dpr, wave = h - strip, lane = 14 * dpr;
    dg.fillStyle = 'rgba(224, 160, 64, 0.12)';
    dg.fillRect(dx(cut.b), 0, dx(cut.l) - dx(cut.b), h);
    dg.fillStyle = 'rgba(155, 89, 182, 0.18)';
    dg.fillRect(dx(cut.l), 0, dx(cut.e) - dx(cut.l), h);
    var p = period(), first = Math.ceil((view.from - beat.phase) / (p / 2)), last = Math.floor((view.to - beat.phase) / (p / 2));
    if (last - first < w / (3 * dpr)) {
      for (i = first; i <= last; i++) {
        x = Math.round(dx(beat.phase + i * p / 2)) + 0.5;
        var bar = i % 8 === 0, onBeat = i % 2 === 0;
        dg.strokeStyle = bar ? 'rgba(197, 138, 240, 0.55)' : onBeat ? 'rgba(197, 138, 240, 0.25)' : 'rgba(197, 138, 240, 0.1)';
        dg.lineWidth = dpr;
        dg.beginPath();
        dg.moveTo(x, 0);
        dg.lineTo(x, wave);
        dg.stroke();
      }
    }
    var dp = detailPeaks(w);
    dg.fillStyle = COLOURS.muted;
    for (x = 0; x < w; x++) {
      var lo2 = dp[x * 2], hi2 = dp[x * 2 + 1];
      dg.fillRect(x, wave / 2 - hi2 * wave / 2, 1, Math.max(1, (hi2 - lo2) * wave / 2));
    }
    // the characteristic hits, along the top
    dg.font = (10 * dpr) + 'px "Sanakan Mono", monospace';
    dg.textAlign = 'center';
    moments.forEach(function (m) {
      if (m.t < view.from || m.t > view.to) return;
      var mx = Math.round(dx(m.t)) + 0.5, tint = { accent: COLOURS.danger, crash: COLOURS.warning, piano: COLOURS.link, bells: COLOURS.accent }[m.kind];
      dg.strokeStyle = tint;
      dg.lineWidth = dpr;
      dg.beginPath();
      dg.moveTo(mx, lane);
      dg.lineTo(mx, lane + 8 * dpr);
      dg.stroke();
      if (span / w * 12 * dpr < 4 * period()) {
        dg.fillStyle = tint;
        dg.fillText(MOMENTS[m.kind], mx, lane - 3 * dpr);
      }
    });
    dg.textAlign = 'start';
    // the cells
    dg.font = (12 * dpr) + 'px "Sanakan Mono", monospace';
    dg.textAlign = 'center';
    dg.textBaseline = 'middle';
    ['build', 'loop'].forEach(function (part) {
      var map = maps[part], step = part === 'loop' ? loopStep() : buildStep();
      if (!map.length || !step) return;
      var cw = step / span * w;
      var a = Math.max(0, Math.floor((view.from - cellTime(part, 0)) / step)), b = Math.min(map.length - 1, Math.ceil((view.to - cellTime(part, 0)) / step));
      for (var k = a; k <= b; k++) {
        var x0 = dx(cellTime(part, k));
        var chosen = selection.part === part && k >= Math.min(selection.a, selection.b) && k <= Math.max(selection.a, selection.b);
        dg.fillStyle = chosen ? 'rgba(197, 138, 240, 0.4)' : k % 2 ? 'rgba(255, 255, 255, 0.03)' : 'rgba(255, 255, 255, 0.07)';
        dg.fillRect(x0, wave, Math.max(1, cw - 1), strip);
        var ch = map.charAt(k);
        if (cw >= 7 * dpr && ch !== '.') {
          dg.fillStyle = charColour(ch);
          dg.fillText(ch, x0 + cw / 2, wave + strip / 2);
        } else if (ch !== '.') {
          dg.fillStyle = charColour(ch);
          dg.fillRect(x0, wave + strip - 4 * dpr, Math.max(1, cw - 1), 3 * dpr);
        }
      }
    });
    dg.textAlign = 'start';
    dg.textBaseline = 'alphabetic';
    marks(dg, dx, h, dpr, true);
    dg.fillStyle = COLOURS.text;
    dg.fillRect(Math.round(dx(pos)), 0, dpr, h);
    drawSeam();
  }

  // ---- the loop's join, close up

  var seam = $('seam'), sg = seam.getContext('2d'), seamSpan = 0.02;

  // the mono mix of samples a to b (whole samples, silence outside the song)
  function mono(a, b) {
    var out = new Float32Array(Math.max(0, b - a)), chans = source.numberOfChannels;
    for (var c = 0; c < chans; c++) {
      var data = source.getChannelData(c);
      for (var i = Math.max(0, a); i < Math.min(source.length, b); i++) out[i - a] += data[i] / chans;
    }
    return out;
  }

  // a trace of the mono mix from sample a, n samples over the pixels x0 to
  // x1: a line through the samples close up, the min and max of each pixel
  // further out
  function trace(g, a, n, x0, x1, h, colour, dpr) {
    var samples = mono(a, a + n + 1), width = x1 - x0, per = n / width, mid = h / 2, scale = h * 0.45;
    g.strokeStyle = colour;
    g.lineWidth = dpr;
    g.beginPath();
    for (var x = 0; x <= width; x++) {
      var from = x * per, lo, hi;
      if (per <= 1) {
        var i = Math.floor(from), f = from - i;
        lo = hi = (samples[i] || 0) * (1 - f) + (samples[i + 1] || 0) * f;
      } else {
        lo = Infinity;
        hi = -Infinity;
        for (var s = Math.floor(from); s < Math.floor(from + per) && s < samples.length; s++) {
          if (samples[s] < lo) lo = samples[s];
          if (samples[s] > hi) hi = samples[s];
        }
        if (lo === Infinity) lo = hi = 0;
      }
      if (x === 0) g.moveTo(x0, mid - lo * scale);
      g.lineTo(x0 + x, mid - lo * scale);
      if (hi !== lo) g.lineTo(x0 + x, mid - hi * scale);
    }
    g.stroke();
  }

  function drawSeam() {
    var dpr = sizeCanvas(seam), w = seam.width, h = seam.height, half = Math.floor(w / 2);
    sg.clearRect(0, 0, w, h);
    var n = Math.max(2, Math.round(seamSpan * RATE)), e = Math.round(cut.e * RATE), l = Math.round(cut.l * RATE);
    sg.fillStyle = 'rgba(155, 89, 182, 0.12)';
    sg.fillRect(0, 0, w, h);
    sg.strokeStyle = 'rgba(197, 138, 240, 0.25)';
    sg.lineWidth = dpr;
    sg.beginPath();
    sg.moveTo(0, h / 2 + 0.5);
    sg.lineTo(w, h / 2 + 0.5);
    sg.stroke();
    // what follows E in the song, faint, under the loop's start
    trace(sg, e, n, half, w, h, 'rgba(154, 159, 164, 0.6)', dpr);
    trace(sg, e - n, n, 0, half, h, COLOURS.text, dpr);
    trace(sg, l, n, half, w, h, COLOURS.accent, dpr);
    sg.fillStyle = COLOURS.accent;
    sg.fillRect(half, 0, dpr, h);
    sg.font = (11 * dpr) + 'px "Sanakan Mono", monospace';
    sg.fillStyle = COLOURS.muted;
    var label = seamSpan >= 0.001 ? (seamSpan * 1000).toFixed(seamSpan >= 0.1 ? 0 : 1) + ' ms' : n + ' próbek';
    sg.fillText('← E   ' + label + ' na stronę   L →', 8 * dpr, 14 * dpr);
  }

  seam.addEventListener('wheel', function (event) {
    if (!source) return;
    event.preventDefault();
    seamSpan = Math.max(0.0002, Math.min(0.5, seamSpan * Math.pow(2, event.deltaY / 300)));
    redraw();
  }, { passive: false });

  // ---- placing a mark exactly

  var STEPS = {
    sample: function () { return 1 / RATE; },
    ms: function () { return 0.001; },
    '10ms': function () { return 0.01; },
    sixteenth: function () { return period() / 4; }
  };
  function nudge(direction) {
    if (!source) return;
    setMark(chosenMark, cut[chosenMark] + direction * STEPS[$('nudge-step').value](), { exact: true });
  }

  // the nearest rising zero crossing within 10 ms (a bass note crosses
  // seldom), or null
  function zeroNear(t) {
    var at = Math.round(t * RATE), r = Math.round(0.01 * RATE), first = at - r - 1;
    var samples = mono(first, at + r + 1), best = null;
    for (var i = 1; i < samples.length; i++) {
      if (samples[i - 1] <= 0 && samples[i] > 0) {
        var s = first + (Math.abs(samples[i - 1]) < Math.abs(samples[i]) ? i - 1 : i);
        if (best === null || Math.abs(s - at) < Math.abs(best - at)) best = s;
      }
    }
    return best === null ? null : best / RATE;
  }

  // The end of the loop where its start matches best what the song has
  // after the end, and what comes before its start what the song has
  // before the end: the normalised correlation of 40 ms on each side,
  // sample by sample within 30 ms of the end there is
  function fitSeam() {
    var W = Math.round(0.04 * RATE), R = Math.round(0.03 * RATE);
    var e0 = Math.round(cut.e * RATE), l0 = Math.round(cut.l * RATE);
    var around = mono(e0 - R - W, e0 + R + W), start = mono(l0 - W, l0 + W);
    var before = l0 - W >= 0, best = -Infinity, bestShift = 0;
    for (var shift = -R; shift <= R; shift++) {
      var e = e0 + shift;
      if (e - W <= l0 + W || e + W > source.length) continue;
      var score = 0, parts = 0;
      for (var side = before ? 0 : 1; side < 2; side++) {
        var xy = 0, xx = 0, yy = 0, ax = shift + R + side * W, ay = side * W;
        for (var k = 0; k < W; k++) {
          var x = around[ax + k], y = start[ay + k];
          xy += x * y;
          xx += x * x;
          yy += y * y;
        }
        score += xx && yy ? xy / Math.sqrt(xx * yy) : 0;
        parts++;
      }
      score /= parts;
      if (score > best) { best = score; bestShift = shift; }
    }
    return { e: (e0 + bestShift) / RATE, shift: bestShift / RATE, score: best };
  }

  function charColour(ch) {
    if ('xoXO'.indexOf(ch) >= 0) return COLOURS.accent;
    if ('|+'.indexOf(ch) >= 0) return COLOURS.danger;
    if ('isv#'.indexOf(ch) >= 0) return COLOURS.warning;
    return COLOURS.link;
  }

  var queued = false;
  function redraw() {
    if (queued || player) return;
    queued = true;
    requestAnimationFrame(function () {
      queued = false;
      drawWaves();
      drawPreview();
    });
  }

  // ---- the maps as cells

  var selection = { part: 'loop', a: 0, b: 0 };
  var cellNodes = { build: [], loop: [] };

  function renderMaps() {
    ['build', 'loop'].forEach(function (part) {
      var box = $(part + '-cells'), map = maps[part], nodes = [];
      box.textContent = '';
      // the buildup ends at the end of a row, where the loop starts
      var pad = part === 'build' ? (ROW - map.length % ROW) % ROW : 0;
      for (var k = 0; k < pad; k++) {
        var gap = document.createElement('span');
        gap.className = 'songs-cell songs-gap';
        box.appendChild(gap);
      }
      for (var i = 0; i < map.length; i++) {
        var cell = document.createElement('span'), ch = map.charAt(i);
        cell.className = 'songs-cell' + (ch === '.' ? ' songs-dot' : '') + ((pad + i) % 8 === 0 ? ' songs-measure' : '');
        cell.textContent = ch;
        cell.style.color = ch === '.' ? '' : charColour(ch);
        cell.setAttribute('data-part', part);
        cell.setAttribute('data-i', String(i));
        box.appendChild(cell);
        nodes.push(cell);
      }
      cellNodes[part] = nodes;
    });
    $('build-map').hidden = !maps.build.length;
    $('build-count').textContent = maps.build.length ? '(' + maps.build.length + ')' : '';
    $('loop-count').textContent = '(' + maps.loop.length + ')';
    paintSelection();
  }

  function paintSelection() {
    ['build', 'loop'].forEach(function (part) {
      var lo = Math.min(selection.a, selection.b), hi = Math.max(selection.a, selection.b);
      cellNodes[part].forEach(function (node, i) {
        node.classList.toggle('songs-chosen', selection.part === part && i >= lo && i <= hi);
      });
    });
  }

  var nowNode = null;
  function paintNow(i, part) {
    if (nowNode) nowNode.classList.remove('songs-now');
    nowNode = part ? cellNodes[part][i] || null : null;
    if (nowNode) nowNode.classList.add('songs-now');
  }

  function select(part, i, extend) {
    var length = maps[part].length;
    if (!length) return;
    i = Math.max(0, Math.min(length - 1, i));
    if (extend && selection.part === part) selection.b = i;
    else selection = { part: part, a: i, b: i };
    paintSelection();
    var node = cellNodes[part][i];
    if (node && node.scrollIntoView) node.scrollIntoView({ block: 'nearest' });
    redraw();
  }

  function chosenRange() {
    return { part: selection.part, lo: Math.min(selection.a, selection.b), hi: Math.max(selection.a, selection.b) };
  }

  // characters written over the cells chosen: one over all of them, or a
  // string from the first on
  function write(chars, fill) {
    var r = chosenRange(), map = maps[r.part];
    if (!map.length) return;
    snapshot();
    var out = map.split('');
    if (fill) for (var i = r.lo; i <= r.hi; i++) out[i] = chars;
    else for (var k = 0; k < chars.length && r.lo + k < out.length; k++) out[r.lo + k] = chars.charAt(k);
    maps[r.part] = out.join('');
    renderMaps();
    redraw();
  }

  $('maps').addEventListener('mousedown', function (event) {
    var cell = event.target.closest('[data-i]');
    if (!cell) return;
    event.preventDefault();
    $('maps').focus();
    select(cell.getAttribute('data-part'), parseInt(cell.getAttribute('data-i'), 10), event.shiftKey);
  });
  $('maps').addEventListener('dblclick', function (event) {
    var cell = event.target.closest('[data-i]');
    if (cell) play(cellTime(cell.getAttribute('data-part'), parseInt(cell.getAttribute('data-i'), 10)));
  });

  $('maps').addEventListener('keydown', function (event) {
    if (!source || event.altKey) return;
    var r = chosenRange(), single = r.lo === r.hi, key = event.key;
    if ((event.ctrlKey || event.metaKey) && key.toLowerCase() === 'a') {
      event.preventDefault();
      selection = { part: selection.part, a: 0, b: maps[selection.part].length - 1 };
      paintSelection();
      redraw();
      return;
    }
    if (event.ctrlKey || event.metaKey) return;
    var moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -ROW, ArrowDown: ROW, Home: -1e9, End: 1e9 };
    if (key in moves) {
      event.preventDefault();
      select(selection.part, (event.shiftKey ? selection.b : single ? r.lo : moves[key] < 0 ? r.lo : r.hi) + moves[key], event.shiftKey);
      return;
    }
    if (key === 'Tab') {
      if (!maps.build.length) return;
      event.preventDefault();
      select(selection.part === 'loop' ? 'build' : 'loop', 0, false);
      return;
    }
    if (key === 'Delete' || key === 'Backspace') {
      event.preventDefault();
      write('.', true);
      if (single && key === 'Backspace') select(selection.part, r.lo - 1, false);
      return;
    }
    if (key.length === 1 && CHARS.indexOf(key) >= 0) {
      event.preventDefault();
      write(key, true);
      if (single) select(selection.part, r.lo + 1, false);
    }
  });

  document.addEventListener('copy', function (event) {
    if (document.activeElement !== $('maps') || !source) return;
    var r = chosenRange();
    event.clipboardData.setData('text/plain', maps[r.part].slice(r.lo, r.hi + 1));
    event.preventDefault();
  });
  document.addEventListener('paste', function (event) {
    if (document.activeElement !== $('maps') || !source) return;
    var text = (event.clipboardData.getData('text/plain') || '').replace(/[^xoXO\-:*~=|+isv#.]/g, '');
    event.preventDefault();
    if (text) write(text, false);
  });

  // ---- the marks

  // A mark moved: dragged (quiet: the snapshot was taken when the drag
  // began), set where the song plays, or exactly (exact: a time typed or
  // nudged, on a whole sample, not snapped, the loop's start moving alone)
  var chosenMark = 'e';
  function setMark(which, t, options) {
    if (!source) return;
    options = options || {};
    if (!options.quiet) snapshot();
    chooseMark(which);
    t = Math.max(0, Math.min(source.duration, options.exact ? Math.round(t * RATE) / RATE : snap(t)));
    var gap = 0.05;
    if (which === 'b') cut.b = Math.min(t, cut.l);
    if (which === 'l') {
      var length = cut.e - cut.l;
      cut.l = Math.min(t, cut.e - gap);
      cut.b = Math.min(cut.b, cut.l);
      // the loop keeps its length when its start moves, if it can
      if (!options.quiet && !options.exact && cut.l + length <= source.duration) cut.e = cut.l + length;
    }
    if (which === 'e') cut.e = Math.max(t, cut.l + gap);
    soundChanged = true;
    if (player) { player.node.loopStart = cut.l; player.node.loopEnd = cut.e; }
    reshape();
    changed();
  }

  function chooseMark(which) {
    chosenMark = which;
    Array.prototype.forEach.call(document.querySelectorAll('[data-mark]'), function (button) {
      button.setAttribute('aria-pressed', String(button.getAttribute('data-mark') === which));
    });
  }

  function changed() {
    renderMaps();
    paintCut();
    unsaved = true;
    redraw();
  }

  function paintCut() {
    if (!source) return;
    var bars = (cut.e - cut.l) / (4 * period());
    $('cut').textContent = (hasBuild() ? 'Buildup ' + (cut.l - cut.b).toFixed(2) + ' s, ' : 'Bez buildupu, ')
      + 'pętla ' + (cut.e - cut.l).toFixed(4) + ' s (' + (Math.round(cut.e * RATE) - Math.round(cut.l * RATE)) + ' próbek) = '
      + (Math.abs(bars - Math.round(bars)) < 0.02 ? Math.round(bars) : bars.toFixed(2)) + ' taktów.';
    ['b', 'l', 'e'].forEach(function (m) {
      var field = $('mark-' + m);
      if (document.activeElement !== field) field.value = cut[m].toFixed(4);
      field.title = Math.round(cut[m] * RATE) + '. próbka';
    });
  }

  function paintTempo() {
    $('bpm').value = String(Math.round(beat.bpm * 100) / 100);
    $('phase').value = beat.phase.toFixed(3);
  }

  // dragging on the waves: a mark near the pointer, else the view (the
  // detail) or a place to hear from (the whole)
  function nearMark(x, toX, dpr) {
    var best = null, dist = 7 * dpr;
    ['e', 'l', 'b'].forEach(function (m) {
      var d = Math.abs(toX(cut[m]) - x);
      if (d < dist) { dist = d; best = m; }
    });
    return best;
  }

  function waveEvents(canvas, isDetail) {
    var drag = null;
    function geometry(event) {
      var rect = canvas.getBoundingClientRect(), dpr = window.devicePixelRatio || 1, x = (event.clientX - rect.left) * dpr, w = canvas.width;
      var from = isDetail ? view.from : 0, span = isDetail ? view.to - view.from : source.duration;
      return {
        x: x, dpr: dpr, y: (event.clientY - rect.top) * dpr, h: canvas.height,
        t: from + x / w * span,
        toX: function (t) { return (t - from) / span * w; }
      };
    }
    canvas.addEventListener('pointerdown', function (event) {
      if (!source || event.button !== 0) return;
      var g = geometry(event), mark = nearMark(g.x, g.toX, g.dpr);
      canvas.setPointerCapture(event.pointerId);
      if (mark) {
        snapshot();
        drag = { mark: mark };
      } else if (isDetail && g.y > g.h - 22 * g.dpr) {
        var cell = cellAt(g.t);
        if (cell) { $('maps').focus(); select(cell.part, cell.i, event.shiftKey); }
        drag = { cells: true };
      } else if (isDetail) {
        drag = { pan: true, x: g.x, from: view.from, to: view.to };
      } else {
        var span = view.to - view.from;
        view.from = Math.max(0, Math.min(source.duration - span, g.t - span / 2));
        view.to = view.from + span;
        drag = { scrub: true };
        redraw();
      }
    });
    canvas.addEventListener('pointermove', function (event) {
      if (!source) return;
      var g = geometry(event);
      if (!drag) {
        canvas.style.cursor = nearMark(g.x, g.toX, g.dpr) ? 'ew-resize' : isDetail ? 'grab' : 'pointer';
        return;
      }
      if (drag.mark) setMark(drag.mark, g.t, { quiet: true });
      else if (drag.cells) {
        var cell = cellAt(g.t);
        if (cell && cell.part === selection.part) select(cell.part, cell.i, true);
      } else if (drag.pan) {
        var shift = (g.x - drag.x) / canvas.width * (drag.to - drag.from);
        var from = Math.max(0, Math.min(source.duration - (drag.to - drag.from), drag.from - shift));
        view.to = from + (drag.to - drag.from);
        view.from = from;
        redraw();
      } else if (drag.scrub) {
        var span = view.to - view.from;
        view.from = Math.max(0, Math.min(source.duration - span, g.t - span / 2));
        view.to = view.from + span;
        redraw();
      }
    });
    function end(event) {
      if (!drag) return;
      if (drag.scrub && event.type === 'pointerup') play(geometry(event).t);
      drag = null;
    }
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    if (isDetail) {
      canvas.addEventListener('dblclick', function (event) { if (source) play(geometry(event).t); });
      canvas.addEventListener('wheel', function (event) {
        if (!source) return;
        event.preventDefault();
        var g = geometry(event), k = Math.pow(2, event.deltaY / 400);
        var span = Math.max(0.002, Math.min(source.duration, (view.to - view.from) * k));
        var from = g.t - (g.t - view.from) * span / (view.to - view.from);
        view.from = Math.max(0, Math.min(source.duration - span, from));
        view.to = view.from + span;
        redraw();
      }, { passive: false });
    }
  }
  waveEvents(overview, false);
  waveEvents(detail, true);

  // ---- the controls

  $('play-build').addEventListener('click', function () { play(hasBuild() ? cut.b : cut.l); });
  $('play-loop').addEventListener('click', function () { play(cut.l); });
  $('play-seam').addEventListener('click', function () { play(Math.max(cut.l, cut.e - 2)); });
  $('stop').addEventListener('click', stop);

  function setTempo(bpm, phase) {
    if (!source || !(bpm >= 20 && bpm <= 400)) { paintTempo(); return; }
    snapshot();
    beat.bpm = bpm;
    if (phase !== undefined) beat.phase = Math.max(0, phase);
    reshape('stretch');
    paintTempo();
    changed();
    findMoments();
  }
  $('bpm').addEventListener('change', function () { setTempo(parseFloat(this.value)); });
  $('phase').addEventListener('change', function () { setTempo(beat.bpm, parseFloat(this.value) || 0); });
  $('half').addEventListener('click', function () { setTempo(beat.bpm / 2); });
  $('double').addEventListener('click', function () { setTempo(beat.bpm * 2); });
  $('detect').addEventListener('click', function () {
    if (!source) return;
    snapshot();
    setState('Liczę tempo…');
    setTimeout(function () {
      detect();
      reshape('stretch');
      changed();
      findMoments();
      setState('Tempo: ' + beat.bpm.toFixed(2) + ' BPM, pierwsze uderzenie ' + beat.phase.toFixed(3) + ' s.');
    }, 30);
  });
  $('snap').addEventListener('change', redraw);

  Array.prototype.forEach.call(document.querySelectorAll('[data-bars]'), function (button) {
    button.addEventListener('click', function () {
      if (!source) return;
      var end = cut.l + parseInt(button.getAttribute('data-bars'), 10) * 4 * period();
      if (end > source.duration + 0.001) { setState('Za mało utworu po L na tyle taktów.'); return; }
      snapshot();
      cut.e = end;
      soundChanged = true;
      if (player) player.node.loopEnd = cut.e;
      reshape();
      changed();
    });
  });
  $('no-build').addEventListener('click', function () {
    if (!source) return;
    snapshot();
    cut.b = cut.l;
    soundChanged = true;
    reshape();
    changed();
  });
  $('build-bars').addEventListener('click', function () {
    if (!source) return;
    snapshot();
    cut.b = Math.max(0, cut.l - 16 * period());
    soundChanged = true;
    reshape();
    if (maps.build.replace(/\./g, '') === '') fillFromSound('build');
    changed();
  });

  Array.prototype.forEach.call(document.querySelectorAll('[data-mark]'), function (button) {
    button.addEventListener('click', function () { chooseMark(button.getAttribute('data-mark')); });
  });
  ['b', 'l', 'e'].forEach(function (m) {
    $('mark-' + m).addEventListener('focus', function () { chooseMark(m); });
    $('mark-' + m).addEventListener('change', function () {
      var t = parseFloat(this.value);
      if (source && t >= 0) setMark(m, t, { exact: true });
      else paintCut();
    });
  });
  $('nudge-back').addEventListener('click', function () { nudge(-1); });
  $('nudge-on').addEventListener('click', function () { nudge(1); });
  $('zero').addEventListener('click', function () {
    if (!source) return;
    var t = zeroNear(cut[chosenMark]);
    if (t === null) { setState('Brak przejścia przez zero w 10 ms od ' + chosenMark.toUpperCase() + '.'); return; }
    setMark(chosenMark, t, { exact: true });
    setState(chosenMark.toUpperCase() + ' na przejściu przez zero: ' + t.toFixed(5) + ' s.');
  });
  $('fit-seam').addEventListener('click', function () {
    if (!source) return;
    setState('Szukam szwu…');
    setTimeout(function () {
      var found = fitSeam();
      setMark('e', found.e, { exact: true });
      setState('Szew: E przesunięte o ' + (found.shift * 1000).toFixed(2) + ' ms, zgodność ' + found.score.toFixed(3) + ' (1 to idealnie).');
    }, 30);
  });

  $('fill').addEventListener('click', function () {
    if (!source) return;
    var r = chosenRange(), whole = r.lo === r.hi;
    setState('Liczę mapę…');
    setTimeout(function () {
      snapshot();
      var chars = counted(r.part);
      if (whole) maps[r.part] = chars;
      else maps[r.part] = maps[r.part].slice(0, r.lo) + chars.slice(r.lo, r.hi + 1) + maps[r.part].slice(r.hi + 1);
      changed();
      setState(whole ? 'Mapa ' + (r.part === 'loop' ? 'pętli' : 'buildupu') + ' policzona z dźwięku.' : 'Zaznaczone komórki policzone z dźwięku.');
    }, 30);
  });
  $('clear').addEventListener('click', function () {
    if (!source) return;
    var r = chosenRange();
    if (r.lo === r.hi) {
      snapshot();
      maps[r.part] = maps[r.part].replace(/./g, '.');
      changed();
    } else write('.', true);
  });

  LEGEND.forEach(function (item) {
    var dt = document.createElement('dt'), dd = document.createElement('dd');
    dt.textContent = item[0];
    dt.style.color = item[0] === '.' ? '' : charColour(item[0]);
    dt.title = 'Wpisz w zaznaczone';
    dt.addEventListener('click', function () { if (source) { write(item[0], true); $('maps').focus(); } });
    dd.textContent = item[1];
    $('legend').appendChild(dt);
    $('legend').appendChild(dd);
  });

  $('title').addEventListener('input', function () {
    unsaved = true;
    if (!keyTyped && !$('key').readOnly) $('key').value = keyFrom(this.value);
  });
  $('key').addEventListener('input', function () {
    keyTyped = true;
    this.classList.remove('invalid');
  });

  // ---- taking the vocals away

  function ramp(x, from, to) { return x <= from ? 0 : x >= to ? 1 : (x - from) / (to - from); }

  // The middle of a stereo song, in the voice's band: frame by frame (Hann,
  // 4096 points, a quarter overlapping), whatever is the same in both
  // channels, bin by bin (the real part of their correlation against their
  // power near 1), the bass and the highest air left alone. Taken away from
  // both channels it leaves the sides as they are, in stereo. In slices, so
  // the page keeps answering; progress(0 to 1), then done(the middle).
  function countCentre(buffer, progress, done) {
    var N = 4096, hop = N / 4, fft = makeFft(N), len = buffer.length;
    var left = buffer.getChannelData(0), right = buffer.getChannelData(1);
    var out = new Float32Array(len), win = new Float64Array(N), band = new Float64Array(N / 2 + 1);
    var re = new Float64Array(N), im = new Float64Array(N), cre = new Float64Array(N), cim = new Float64Array(N);
    for (var i = 0; i < N; i++) win[i] = 0.5 - 0.5 * Math.cos(2 * Math.PI * i / N);
    for (var k = 0; k <= N / 2; k++) {
      var f = k * buffer.sampleRate / N;
      band[k] = ramp(f, 110, 220) * (1 - ramp(f, 9000, 12000));
    }
    // Hann twice at a quarter's hop sums to 1.5
    var gain = 1 / 1.5, start = hop - N;
    function frame() {
      for (var i = 0; i < N; i++) {
        var at = start + i, w = win[i];
        re[i] = at >= 0 && at < len ? left[at] * w : 0;
        im[i] = at >= 0 && at < len ? right[at] * w : 0;
      }
      fft(re, im, false);
      for (var k = 0; k <= N / 2; k++) {
        var m = (N - k) % N;
        // the two real channels out of one complex transform
        var lr = (re[k] + re[m]) / 2, li = (im[k] - im[m]) / 2;
        var rr = (im[k] + im[m]) / 2, ri = (re[m] - re[k]) / 2;
        var power = lr * lr + li * li + rr * rr + ri * ri;
        var alike = power > 1e-12 ? 2 * (lr * rr + li * ri) / power : 0;
        var x = ramp(alike, 0.7, 0.97), keep = x * x * (3 - 2 * x) * band[k];
        cre[k] = keep * (lr + rr) / 2;
        cim[k] = keep * (li + ri) / 2;
        if (k && k < N / 2) {
          cre[N - k] = cre[k];
          cim[N - k] = -cim[k];
        }
      }
      cim[0] = cim[N / 2] = 0;
      fft(cre, cim, true);
      for (i = 0; i < N; i++) {
        var to = start + i;
        if (to >= 0 && to < len) out[to] += cre[i] * win[i] * gain;
      }
      start += hop;
    }
    (function slice() {
      var until = performance.now() + 40;
      while (start < len && performance.now() < until) frame();
      if (start < len) {
        progress(start / len);
        setTimeout(slice, 0);
      } else done(out);
    })();
  }

  function applied() { return removal.on ? removal.strength : 0; }
  function soundAltered() { return soundChanged || applied() !== removal.saved; }

  // the song heard, saved and drawn: as opened, or less its middle
  function mixVocals() {
    if (!original) return;
    var s = applied();
    if (!s || !centre) source = original;
    else {
      var mixed = audio().createBuffer(original.numberOfChannels, original.length, RATE);
      for (var c = 0; c < original.numberOfChannels; c++) {
        var from = original.getChannelData(c), to = mixed.getChannelData(c);
        for (var i = 0; i < from.length; i++) to[i] = from[i] - s * centre[i];
      }
      source = mixed;
    }
    computePeaks();
    detailCache = null;
    unsaved = true;
    findMoments();
    // what plays goes on from where it is, in the new sound
    if (player) play(position());
    redraw();
  }

  $('vocals').addEventListener('change', function () {
    if (!original) { this.checked = false; return; }
    removal.on = this.checked;
    if (!removal.on || centre) { mixVocals(); return; }
    if (removal.busy) return;
    removal.busy = true;
    var song = original;
    countCentre(original, function (p) {
      setState('Szukam wokalu… ' + Math.round(p * 100) + '%');
    }, function (found) {
      removal.busy = false;
      if (song !== original) return;
      centre = found;
      setState('Wokal usunięty. Posłuchaj; siła zmienia, ile go znika.');
      mixVocals();
    });
  });
  var mixQueued = false;
  $('vocals-strength').addEventListener('input', function () {
    removal.strength = this.value / 100;
    this.style.setProperty('--player-fill', this.value + '%');
    if (!removal.on || !centre || mixQueued) return;
    mixQueued = true;
    setTimeout(function () { mixQueued = false; mixVocals(); }, 120);
  });
  $('vocals-strength').style.setProperty('--player-fill', '100%');

  // ---- the pack's songs and pictures

  var packSongs = [];
  function loadPack(select) {
    return fetch('songs.php?a=pack', { cache: 'no-store' }).then(function (response) { return response.json(); }).then(function (pack) {
      packSongs = pack.songs || [];
      var pick = $('pick');
      while (pick.options.length > 1) pick.remove(1);
      packSongs.forEach(function (entry, i) {
        var option = document.createElement('option');
        option.value = String(i);
        option.textContent = (entry.title || entry.name) + (entry.build ? ' (z buildupem)' : '');
        pick.appendChild(option);
      });
      if (select) pick.value = String(packSongs.findIndex(function (entry) { return entry.loop === select; }));
      if (!pictures.length) {
        (pack.images || []).slice().sort(function () { return Math.random() - 0.5; }).slice(0, 16).forEach(function (name) {
          var img = new Image();
          img.src = 'songs.php?a=file&f=' + encodeURIComponent('Images/' + name + '.webp');
          pictures.push(img);
        });
        pictures[0] && (pictures[0].onload = redraw);
      }
    });
  }
  loadPack().catch(function () { setState('Nie wczytałem listy paczki.'); });

  $('pick').addEventListener('change', function () {
    var entry = packSongs[parseInt(this.value, 10)];
    if (!entry) return;
    if (unsaved && source && !confirm('Porzucić niezapisane zmiany?')) return;
    audio();
    openPack(entry);
  });
  $('open').addEventListener('change', function () {
    if (this.files[0] && (!unsaved || !source || confirm('Porzucić niezapisane zmiany?'))) { audio(); openFile(this.files[0]); }
    this.value = '';
  });
  document.addEventListener('dragover', function (event) { event.preventDefault(); });
  document.addEventListener('drop', function (event) {
    event.preventDefault();
    var file = event.dataTransfer.files[0];
    if (file && (!unsaved || !source || confirm('Porzucić niezapisane zmiany?'))) { audio(); openFile(file); }
  });

  // ---- saving

  // 16-bit PCM of a part of the song
  function wav(from, to) {
    var a = Math.max(0, Math.round(from * RATE)), b = Math.min(source.length, Math.round(to * RATE));
    var chans = source.numberOfChannels, frames = b - a, bytes = frames * chans * 2;
    var view = new DataView(new ArrayBuffer(44 + bytes));
    function text(at, s) { for (var i = 0; i < s.length; i++) view.setUint8(at + i, s.charCodeAt(i)); }
    text(0, 'RIFF'); view.setUint32(4, 36 + bytes, true); text(8, 'WAVE');
    text(12, 'fmt '); view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, chans, true);
    view.setUint32(24, RATE, true); view.setUint32(28, RATE * chans * 2, true); view.setUint16(32, chans * 2, true); view.setUint16(34, 16, true);
    text(36, 'data'); view.setUint32(40, bytes, true);
    var data = [];
    for (var c = 0; c < chans; c++) data.push(source.getChannelData(c));
    var at = 44;
    for (var s = a; s < b; s++) {
      for (c = 0; c < chans; c++) {
        var v = Math.max(-1, Math.min(1, data[c][s]));
        view.setInt16(at, v < 0 ? v * 0x8000 : v * 0x7fff, true);
        at += 2;
      }
    }
    return new Blob([view.buffer], { type: 'audio/wav' });
  }

  var ERRORS = {
    key: 'zła nazwa pliku (litery, cyfry, \' ( ) _ -)', map: 'zła mapa', upload: 'nie doszedł plik', wav: 'zły WAV',
    loop: 'nowy utwór bez pętli', write: 'nie udało się zapisać', encode: 'ffmpeg nie zrobił Opus (ustaw FFMPEG)',
    missing: 'nie ma go już w paczce'
  };
  function save(overwrite) {
    if (!source) return;
    var key = $('key').value.trim();
    if (!/^[A-Za-z0-9][A-Za-z0-9'()_-]{0,79}$/.test(key)) {
      $('key').classList.add('invalid');
      $('key').focus();
      setState('Nazwa pliku: ' + ERRORS.key + '.');
      return;
    }
    var form = new FormData();
    form.append('key', key);
    form.append('title', $('title').value.trim());
    form.append('rhythm', maps.loop);
    form.append('buildupRhythm', hasBuild() ? maps.build : '');
    if (!packSong) form.append('fresh', '1');
    if (overwrite) form.append('overwrite', '1');
    if (soundAltered() || !packSong) {
      form.append('loop', wav(cut.l, cut.e), 'loop.wav');
      if (hasBuild()) form.append('build', wav(cut.b, cut.l), 'build.wav');
      else form.append('nobuild', '1');
    }
    $('save').disabled = true;
    setState(soundAltered() || !packSong ? 'Zapisuję dźwięk i mapę…' : 'Zapisuję mapę…');
    fetch('songs.php?a=save', { method: 'POST', body: form }).then(function (response) {
      return response.json().then(function (answer) { return { status: response.status, answer: answer }; });
    }).then(function (reply) {
      $('save').disabled = false;
      if (reply.status === 409) {
        if (confirm('Utwór loop_' + key + '.ogg już jest w paczce. Zastąpić?')) save(true);
        else setState('Nie zapisałem.');
        return;
      }
      if (reply.status !== 200) {
        setState('Nie zapisałem: ' + (ERRORS[reply.answer.error] || reply.answer.error) + (reply.answer.output ? ' ' + reply.answer.output : ''));
        return;
      }
      var a = reply.answer;
      if (a.song) {
        packSong = a.song;
        soundChanged = false;
        removal.saved = applied();
        unsaved = false;
        $('key').readOnly = true;
        $('remove').hidden = false;
        loadPack(a.song.loop);
      }
      setState(a.code === 0 ? 'Zapisane: ' + (a.song ? a.song.loop : key) + '. ' + a.output.split('\n').pop() : 'respack.php (kod ' + a.code + '): ' + a.output);
    }).catch(function (error) {
      $('save').disabled = false;
      setState('Nie zapisałem: ' + error.message);
    });
  }
  $('save').addEventListener('click', function () { save(false); });

  // the song taken out of the pack; what is open stays, as a new song that
  // could go in again
  $('remove').addEventListener('click', function () {
    if (!packSong) return;
    var key = packSong.loop.replace(/^loop_|\.ogg$/g, ''), name = packSong.title || packSong.name;
    if (!confirm('Usunąć „' + name + '” z paczki? Znikną loop_' + key + '.ogg' + (packSong.build ? ' i ' + packSong.build : '') + ' z hues/.pack/Songs.')) return;
    var form = new FormData();
    form.append('key', key);
    fetch('songs.php?a=delete', { method: 'POST', body: form }).then(function (response) {
      return response.json().then(function (answer) { return { status: response.status, answer: answer }; });
    }).then(function (reply) {
      if (reply.status !== 200) {
        setState('Nie usunąłem: ' + (ERRORS[reply.answer.error] || reply.answer.error));
        return;
      }
      packSong = null;
      soundChanged = true;
      unsaved = true;
      $('key').readOnly = false;
      $('remove').hidden = true;
      $('pick').value = '';
      loadPack();
      setState('Usunięty z paczki: ' + name + '. ' + reply.answer.output.split('\n').pop() + ' Na serwerze plik zostaje, ale po deployu nie będzie go na liście.');
    }).catch(function (error) {
      setState('Nie usunąłem: ' + error.message);
    });
  });

  // ---- keys

  document.addEventListener('keydown', function (event) {
    var typing = /^(INPUT|SELECT|TEXTAREA)$/.test(event.target.tagName) && event.target.type !== 'checkbox';
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      save(false);
      return;
    }
    if (typing || !source) return;
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
      event.preventDefault();
      undo();
      return;
    }
    if (event.ctrlKey || event.metaKey || event.altKey) return;
    var onMaps = document.activeElement === $('maps');
    var actions = {
      ' ': function () { if (player) stop(); else play(hasBuild() ? cut.b : cut.l); },
      'Escape': stop,
      'p': function () { play(cut.l); },
      'j': function () { play(Math.max(cut.l, cut.e - 2)); },
      'b': function () { setMark('b', position()); },
      'l': function () { setMark('l', position()); },
      'e': function () { setMark('e', position()); },
      '[': function () { nudge(-1); },
      ']': function () { nudge(1); }
    };
    var key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
    // on the maps a character is a character, except the marks' letters,
    // which none of them is
    if (onMaps && event.key.length === 1 && CHARS.indexOf(event.key) >= 0) return;
    if (actions[key]) {
      event.preventDefault();
      actions[key]();
    }
  });

  window.addEventListener('resize', redraw);
  window.addEventListener('beforeunload', function (event) {
    if (source && unsaved) event.preventDefault();
  });
  previewReset();
  drawPreview();
})();
