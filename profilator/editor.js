// Profilator: the bot's profile of layers edited in the browser. The layout and the
// player's data, pictures and unlocks come from the bot (api.php -> api/Profile), the
// preview is drawn here by layers.js, and a save goes back to the bot, which charges
// the missing one-time unlocks. Without a login the page shows made-up data and the
// ready layouts, and saves nothing. A layout can be shared by a link (#l=...), without
// the player's pictures and cards.
(function () {
  'use strict';

  var root = document.getElementById('profilator');
  var L = window.Layers;
  if (!root || !L)
    return;

  var $ = function (id) { return document.getElementById(id); };
  var canvas = $('profile-canvas');
  var status = $('profile-status');
  var api = root.getAttribute('data-api') || 'api.php';
  var csrf = root.getAttribute('data-csrf') || '';
  var logged = root.getAttribute('data-logged') === '1';
  var pw = root.getAttribute('data-pw') || '';
  var botCards = root.getAttribute('data-bot-cards') || '';
  var levels = {};
  try {
    levels = JSON.parse(root.getAttribute('data-levels') || '{}');
  } catch (e) {
    levels = {};
  }

  var catalog = null;
  var M = null;            // the layout being edited (the bot's JSON)
  var version = 0;
  var unlocks = [];
  var pictures = [];       // the player's pictures from the bot
  // parts locked in the editor only: clicks on the preview go through them, the arrows leave them be;
  // kept in this browser, never in the layout
  var locked = {};
  try { (JSON.parse(localStorage.getItem('profilator-locks') || '[]') || []).forEach(function (k) { locked[k] = true; }); } catch (e) { locked = {}; }
  function isLocked(key) { return key !== null && locked[String(key)] === true; }
  function setLocked(key, on) {
    if (on) locked[String(key)] = true; else delete locked[String(key)];
    try { localStorage.setItem('profilator-locks', JSON.stringify(Object.keys(locked))); } catch (e) { }
  }
  var cardChoices = [];
  var data = null;
  var sel = null;          // 'avatar', 'nick', 'header', 'style', 'ultra' or a layer id
  var guides = [];
  var drag = null;
  var snapOn = true;
  var outlines = false;
  var past = [];
  var future = [];
  var editing = false;
  var comparing = false;
  var present = null;
  var quote = null;

  // ---- the made-up player, without a login ----

  var SAMPLE = {
    nick: 'Gracz Sanakana', rank: 'Ranga z Shindena', avatar: root.getAttribute('data-avatar') || root.getAttribute('data-art'), color: '#9B59B6',
    top: 17, level: 37, levelProgress: 0.62, messages: 12840, sc: 98765, tc: 4321, ac: 120, ct: 40, pc: 900,
    cards: 2840, limit: 3000, karma: 'neutral',
    rarities: { SSS: 12, SS: 40, S: 95, A: 210, B: 380, C: 600, D: 700, E: 793 },
    ultimates: 3, customImages: 18, customBorders: 6,
    waifu: { id: 'sample-0' }, gallery: [],
    anime: { statuses: [12, 340, 4, 9, 21, 118], total: '504', units: '4 210', time: '105 dni', mean: '7,90',
      recent: [{ title: 'Pierwsze anime', progress: '5 / 12', ratio: 5 / 12 }, { title: 'Drugie anime', progress: '18 / 24', ratio: 0.75 }, { title: 'Trzecie anime', progress: '1 / 1', ratio: 1 }] },
    manga: { statuses: [5, 61, 0, 2, 3, 40], total: '111', units: '5 320', time: '33 dni', mean: '8,20',
      recent: [{ title: 'Pierwsza manga', progress: '12,5 / 40', ratio: 12.5 / 40 }] }
  };
  for (var g = 0; g < 12; g++)
    SAMPLE.gallery.push({ id: 'sample-' + g });

  // ---- pictures and cards ----

  var images = {};         // picture id -> Image
  var cardImages = {};     // card id -> Image or canvas
  var covers = {};
  var frames = {};
  var avatarImage = null;
  var defaultHeader = null;

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

  function frameDelay(microseconds) {
    var ms = (microseconds || 0) / 1000;
    return ms > 10 ? ms : 100;
  }

  // a picture with all its frames: an animated one gets its decoder and img.frame, the frame of the moment
  function decode(bytes, type) {
    var url = URL.createObjectURL(new Blob([bytes], { type: type }));
    return load(url).then(function (img) {
      if (!img || typeof ImageDecoder === 'undefined' || (type !== 'image/webp' && type !== 'image/gif'))
        return img;
      var decoder = new ImageDecoder({ data: bytes, type: type });
      return Promise.all([decoder.tracks.ready, decoder.completed]).then(function () {
        if (decoder.tracks.selectedTrack.frameCount < 2) {
          decoder.close();
          return img;
        }
        return decoder.decode({ frameIndex: 0 }).then(function (first) {
          var frame = document.createElement('canvas');
          frame.width = img.naturalWidth;
          frame.height = img.naturalHeight;
          frame.getContext('2d').drawImage(first.image, 0, 0, frame.width, frame.height);
          img.delay = frameDelay(first.image.duration);
          first.image.close();
          img.decoder = decoder;
          img.frames = decoder.tracks.selectedTrack.frameCount;
          img.at = 0;
          img.frame = frame;
          return img;
        });
      }).catch(function () {
        decoder.close();
        return img;
      });
    });
  }

  function typeOf(bytes) {
    var b = new Uint8Array(bytes, 0, Math.min(12, bytes.byteLength));
    if (b[0] === 0x89 && b[1] === 0x50) return 'image/png';
    if (b[0] === 0xff && b[1] === 0xd8) return 'image/jpeg';
    if (b[0] === 0x47 && b[1] === 0x49) return 'image/gif';
    return 'image/webp';
  }

  function fetchPicture(id) {
    if (images[id] !== undefined)
      return Promise.resolve(images[id]);
    images[id] = null;
    return fetch(api + '?a=picture&id=' + encodeURIComponent(id), { credentials: 'same-origin' }).then(function (res) {
      if (!res.ok)
        throw new Error('picture');
      return res.arrayBuffer();
    }).then(function (bytes) {
      return decode(bytes, typeOf(bytes));
    }).then(function (img) {
      images[id] = img;
      prepareHit(id, img);
      redraw();
      return img;
    }, function () {
      return null;
    });
  }

  // the bot's cards through the site (/profilator/cards/, passed on from Shinden's CDN), so an animated one can play
  function loadCard(view) {
    if (!view || cardImages[view.id] !== undefined)
      return Promise.resolve(view ? cardImages[view.id] : null);
    cardImages[view.id] = null;
    var id = /^https:\/\/cdn2\.shinden\.eu\/profile\/(\d+)\.webp$/.exec(view.url || '');
    var got = id && botCards
      ? fetch(botCards + id[1] + '.webp', { redirect: 'error' }).then(function (res) {
        if (!res.ok)
          throw new Error('card');
        return res.arrayBuffer();
      }).then(function (bytes) { return decode(bytes, 'image/webp'); }).catch(function () { return load(view.url); })
      : load(view.url);
    return got.then(function (img) {
      cardImages[view.id] = img;
      redraw();
      return img;
    });
  }

  var hits = {};
  function prepareHit(id, img) {
    try {
      var c = document.createElement('canvas');
      c.width = img.naturalWidth;
      c.height = img.naturalHeight;
      var x = c.getContext('2d');
      x.drawImage(img, 0, 0);
      hits[id] = x.getImageData(0, 0, c.width, c.height);
    } catch (e) {
      hits[id] = null;
    }
  }

  function frameImage(name, level) {
    var best = 0;
    (levels[name] || []).forEach(function (n) {
      if (n <= level && n > best)
        best = n;
    });
    var key = best ? name + 'Lv/' + best : name;
    if (frames[key] === undefined) {
      frames[key] = null;
      load(pw + 'ab/' + key + '.png').then(function (img) {
        frames[key] = img;
        redraw();
      });
    }
    return frames[key] || frames[name] || null;
  }

  var assets = {
    picture: function (id) { return id ? images[id] || null : null; },
    card: function (id) { return cardImages[id] || null; },
    cover: function (url) {
      if (!url)
        return null;
      if (covers[url] === undefined) {
        covers[url] = null;
        load(url).then(function (img) { covers[url] = img; redraw(); });
      }
      return covers[url];
    },
    frame: frameImage,
    get avatar() { return avatarImage; },
    get defaultHeader() { return defaultHeader; }
  };

  // ---- the one moving part ----

  var playing = null;
  var playToken = 0;
  var playTimer = null;
  var paused = false;
  try {
    var motion = localStorage.getItem('profilator-motion');
    paused = motion ? motion === 'off' : !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  } catch (e) {
    // no storage: it moves
  }

  function movingImage() {
    if (!M || !M.animate)
      return null;
    var a = M.animate;
    if (a === 'header') return M.header && images[M.header.image];
    if (a === 'style') return M.style && images[M.style.image];
    if (a === 'ultra') return M.ultra && images[M.ultra.image];
    if (a === 'frame') return M.avatar.frame === 'custom' && images[M.avatar.customImage];
    if (a === 'avatar') return avatarImage;
    var layer = findLayer(+a.slice(6));
    if (!layer) return null;
    if (layer.t === 'overlay') return images[layer.image];
    if (layer.t === 'sticker') return cardImages[layer.card];
    if (layer.t === 'cards') return data.waifu && cardImages[data.waifu.id];
    var cards = (data.gallery || []).slice(0, layer.t === 'bigGallery' ? 12 : layer.count === 2 ? 2 : 6);
    for (var i = 0; i < cards.length; i++) {
      var img = cards[i] && cardImages[cards[i].id];
      if (img && img.decoder)
        return img;
    }
    return null;
  }

  function play() {
    var img = movingImage();
    if (img && !img.decoder)
      img = null;
    if (img === playing)
      return;
    clearTimeout(playTimer);
    playing = img;
    var token = ++playToken;
    if (img && !paused)
      playTimer = setTimeout(function () { nextFrame(img, token); }, img.delay);
    $('motion-toggle').hidden = !img;
  }

  function nextFrame(img, token) {
    var at = (img.at + 1) % img.frames;
    img.decoder.decode({ frameIndex: at }).then(function (result) {
      if (token === playToken) {
        var x = img.frame.getContext('2d');
        x.clearRect(0, 0, img.frame.width, img.frame.height);
        x.drawImage(result.image, 0, 0, img.frame.width, img.frame.height);
        img.at = at;
        img.delay = frameDelay(result.image.duration);
        playTimer = setTimeout(function () { nextFrame(img, token); }, img.delay);
        draw();
      }
      result.image.close();
    }, function () {
      // a frame that cannot be decoded: it stays on the one shown
    });
  }

  function showMotion() {
    var b = $('motion-toggle');
    b.textContent = paused ? 'Wznów animację' : 'Zatrzymaj animację';
    b.setAttribute('aria-pressed', paused ? 'true' : 'false');
  }

  $('motion-toggle').addEventListener('click', function () {
    paused = !paused;
    try {
      localStorage.setItem('profilator-motion', paused ? 'off' : 'on');
    } catch (e) {
      // kept for this visit only
    }
    showMotion();
    playing = null;
    play();
  });

  // ---- the layout ----

  function clone(value) { return JSON.parse(JSON.stringify(value)); }

  function findLayer(id) {
    for (var i = 0; i < M.layers.length; i++)
      if (M.layers[i].id === id)
        return M.layers[i];
    return null;
  }

  function normalise(layout) {
    layout = clone(layout || {});
    layout.v = layout.v || 1;
    layout.layers = (layout.layers || []).filter(function (x) { return x && x.t; });
    layout.layers.forEach(function (x) {
      if (x.t !== 'sticker') return;
      x.ground = L.groundOf(x);
      delete x.bg;
      if (x.pad == null) x.pad = 4;
    });
    layout.bar = layout.bar || {};
    layout.bar.look = layout.bar.look || 'solid';
    layout.bar.at = layout.bar.at || 'bottom';
    layout.bar.hide = layout.bar.hide || [];
    if (layout.bar.opacity == null) layout.bar.opacity = 85;
    layout.avatar = layout.avatar || {};
    var a = layout.avatar;
    if (a.x == null) a.x = 52;
    if (a.y == null) a.y = 68;
    a.size = a.size || 84;
    a.shape = a.shape || 'circle';
    if (a.frame == null) a.frame = 'Base';
    if (a.levelFrame == null) a.levelFrame = true;
    layout.nick = layout.nick || {};
    var n = layout.nick;
    if (n.x == null) n.x = 163;
    if (n.y == null) n.y = 99;
    n.w = n.w || 445;
    n.align = n.align || 'left';
    layout.panels = layout.panels || 'solid';
    layout.shade = layout.shade || 'fade';
    if (layout.shadow == null) layout.shadow = 70;
    return layout;
  }

  function nextId() {
    return M.layers.reduce(function (m, x) { return Math.max(m, x.id || 0); }, 0) + 1;
  }

  // the parts as they are drawn, bottom to top: [key, object, kind]
  function parts() {
    var list = [];
    if (M.header && !M.header.hidden) list.push(['header', M.header, 'header']);
    if (M.style && !M.style.hidden) list.push(['style', M.style, 'style']);
    if (!M.nick.hidden) list.push(['nick', M.nick, 'nick']);
    M.layers.forEach(function (layer) { if (!layer.hidden) list.push([layer.id, layer, 'layer']); });
    if (M.ultra && !M.ultra.hidden) list.push(['ultra', M.ultra, 'ultra']);
    list.push(['avatar', M.avatar, 'avatar']);
    return list;
  }

  function boxOf(key) {
    if (key === 'avatar') return L.box(M.avatar, 'avatar');
    if (key === 'nick') return L.box(M.nick, 'nick');
    if (key === 'header') return L.HEADER;
    if (key === 'style') return L.STYLE;
    if (key === 'ultra') return L.box({ t: 'ultra', image: M.ultra.image, scale: M.ultra.scale, x: M.ultra.x, y: M.ultra.y }, null, assets, data);
    var layer = findLayer(key);
    return layer ? L.box(layer, null, assets, data) : null;
  }

  function objectOf(key) {
    if (key === 'avatar') return M.avatar;
    if (key === 'nick') return M.nick;
    if (key === 'header') return M.header;
    if (key === 'style') return M.style;
    if (key === 'ultra') return M.ultra;
    return findLayer(key);
  }

  var KIND = {
    anime: 'Panel anime', manga: 'Panel mangi', cards: 'Karcianka', gallery: 'Galeria', bigGallery: 'Duża galeria',
    overlay: 'Nakładka', sticker: 'Karta-naklejka', rank: 'Ranga'
  };

  function nameOf(key) {
    if (key === 'avatar') return 'Awatar';
    if (key === 'nick') return 'Nick';
    if (key === 'header') return 'Tło nagłówka';
    if (key === 'style') return 'Obrazek stylu';
    if (key === 'ultra') return 'Ultra nakładka';
    var layer = findLayer(key);
    if (!layer) return '';
    if (layer.t === 'gallery') return 'Galeria ' + (layer.count === 2 ? '2 karty' : '6 kart');
    if (layer.t === 'sticker') {
      var card = cardChoices.filter(function (c) { return c.id === layer.card; })[0];
      return 'Karta: ' + (card ? card.name : '#' + layer.card);
    }
    return KIND[layer.t] || layer.t;
  }

  // ---- history ----

  function snapshot() {
    past.push(JSON.stringify(M));
    if (past.length > 100)
      past.shift();
    future = [];
  }

  function beginEdit() {
    if (!editing) {
      snapshot();
      editing = true;
    }
  }

  function endEdit() { editing = false; }

  function restore(json) {
    M = JSON.parse(json);
    if (sel !== null && !objectOf(sel))
      sel = null;
    changed(true);
  }

  $('undo').addEventListener('click', undo);
  $('redo').addEventListener('click', redo);

  function undo() {
    if (!past.length) return;
    future.push(JSON.stringify(M));
    restore(past.pop());
    say('Cofnięto.');
  }

  function redo() {
    if (!future.length) return;
    past.push(JSON.stringify(M));
    restore(future.pop());
    say('Ponowiono.');
  }

  document.addEventListener('keydown', function (e) {
    if (!(e.ctrlKey || e.metaKey) || !M)
      return;
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'select' || tag === 'textarea')
      return;
    var k = e.key.toLowerCase();
    if (k === 'z' && !e.shiftKey) { undo(); e.preventDefault(); }
    else if (k === 'y' || (k === 'z' && e.shiftKey)) { redo(); e.preventDefault(); }
  });

  // ---- drawing ----

  function say(text, warn) {
    status.textContent = text;
    status.classList.toggle('warn', !!warn);
  }

  function draw() {
    if (!M || !data)
      return;
    if (comparing && present) {
      var c = canvas.getContext('2d');
      c.clearRect(0, 0, L.W, L.H);
      c.drawImage(present, 0, 0, L.W, L.H);
      return;
    }
    slotsLinked = linkedSlots();
    L.render(canvas, M, data, assets);
    var x = canvas.getContext('2d');
    x.save();
    if (outlines) {
      x.setLineDash([4, 4]);
      x.lineWidth = 1;
      x.strokeStyle = 'rgba(255,255,255,0.6)';
      parts().forEach(function (p) {
        var b = boxOf(p[0]);
        if (b) x.strokeRect(b.x + 0.5, b.y + 0.5, b.w, b.h);
      });
    }
    x.setLineDash([]);
    x.lineWidth = 1;
    x.strokeStyle = '#4FC3E8';
    guides.forEach(function (gd) {
      x.beginPath();
      if (gd.x !== undefined) { x.moveTo(gd.x + 0.5, 0); x.lineTo(gd.x + 0.5, L.H); } else { x.moveTo(0, gd.y + 0.5); x.lineTo(L.W, gd.y + 0.5); }
      x.stroke();
    });
    var b = sel !== null ? boxOf(sel) : null;
    if (b) {
      x.setLineDash([6, 4]);
      x.lineWidth = 2;
      x.strokeRect(b.x + 1, b.y + 1, Math.max(0, b.w - 2), Math.max(0, b.h - 2));
      x.setLineDash([]);
      var label = nameOf(sel);
      x.font = '700 11px Lato, sans-serif';
      var tw = x.measureText(label).width + 10;
      var ly = b.y - 17 < 0 ? b.y + 3 : b.y - 17;
      var lx = Math.max(0, Math.min(b.x, L.W - tw));
      x.fillStyle = '#4FC3E8';
      x.fillRect(lx, ly, tw, 15);
      x.fillStyle = '#131217';
      x.textBaseline = 'middle';
      x.fillText(label, lx + 5, ly + 8);
    }
    x.restore();
  }

  var queued = false;
  function redraw() {
    if (queued)
      return;
    queued = true;
    requestAnimationFrame(function () {
      queued = false;
      draw();
      play();
    });
  }

  // after any change of the layout: the drawing, the lists and the price
  function changed(rebuild) {
    slotsLinked = linkedSlots();
    redraw();
    renderLayers();
    if (rebuild) renderProps(); else refreshProps();
    renderLook();
    $('undo').disabled = !past.length;
    $('redo').disabled = !future.length;
    loadNeeded();
    askQuote();
    keepHash();
  }

  // ---- moving on the preview ----

  function point(e) {
    var r = canvas.getBoundingClientRect();
    return { x: (e.clientX - r.left) * L.W / r.width, y: (e.clientY - r.top) * L.H / r.height };
  }

  function hitsPart(p, key) {
    var b = boxOf(key);
    if (!b || p.x < b.x || p.y < b.y || p.x >= b.x + b.w || p.y >= b.y + b.h)
      return false;
    var o = objectOf(key);
    var picture = key === 'ultra' ? o.image : o && o.t === 'overlay' ? o.image : 0;
    if (picture && hits[picture]) {
      var d = hits[picture], s = o.scale || 1;
      var lx = Math.floor((p.x - o.x) / s), ly = Math.floor((p.y - o.y) / s);
      return d.data[(ly * d.width + lx) * 4 + 3] > 24;
    }
    return true;
  }

  function hitTest(p) {
    var list = parts();
    for (var i = list.length - 1; i >= 0; i--)
      if (!isLocked(list[i][0]) && hitsPart(p, list[i][0]))
        return list[i][0];
    return null;
  }

  function isSlot(key) { return key === 'header' || key === 'style'; }

  // the header and the style showing one picture in one frame make one picture of the whole profile:
  // as long as they do, they move and zoom together within the whole profile
  var slotsLinked = false;
  function linkedSlots() {
    var h = M && M.header, s = M && M.style;
    return !!(h && s && h.image === s.image && h.x === s.x && h.y === s.y && h.scale === s.scale);
  }

  function slotField(key) { return slotsLinked ? { x: 0, y: 0, w: L.W, h: L.H } : key === 'header' ? L.HEADER : L.STYLE; }

  // one picture over the whole profile, in both the header and the style
  function wholeAt(picture) {
    var s = Math.max(L.W / picture.width, L.H / picture.height);
    return { image: picture.id, scale: s, x: Math.round((L.W - picture.width * s) / 2), y: Math.round((L.H - picture.height * s) / 2) };
  }

  function coverScale(key) {
    var img = images[objectOf(key).image];
    if (!img) return 1;
    var f = slotField(key);
    return Math.max(f.w / img.naturalWidth, f.h / img.naturalHeight);
  }

  function clampSlot(key) {
    var o = objectOf(key), img = images[o.image];
    if (!img) return;
    var f = slotField(key);
    o.scale = Math.max(o.scale || 1, coverScale(key));
    var w = img.naturalWidth * o.scale, h = img.naturalHeight * o.scale;
    o.x = Math.round(Math.min(f.x, Math.max(f.x + f.w - w, o.x)));
    o.y = Math.round(Math.min(f.y, Math.max(f.y + f.h - h, o.y)));
    if (slotsLinked) {
      var other = M[key === 'header' ? 'style' : 'header'];
      other.x = o.x;
      other.y = o.y;
      other.scale = o.scale;
    }
  }

  function clamp(key) {
    if (isSlot(key)) {
      clampSlot(key);
      return;
    }
    var o = objectOf(key), b = boxOf(key);
    if (!o || !b) return;
    var m = L.MIN_INSIDE;
    var f = o.t === 'overlay' ? L.OVERLAY : { x: 0, y: 0, w: L.W, h: L.H };
    var dx = o.x - b.x;
    o.x = Math.round(Math.min(Math.max(b.x, f.x + m - b.w), f.x + f.w - m) + dx);
    o.y = Math.round(Math.min(Math.max(o.y, f.y + m - b.h), f.y + f.h - m));
  }

  function moveTo(key, x, y) {
    guides = [];
    var o = objectOf(key);
    if (isSlot(key) || !snapOn) {
      o.x = Math.round(x);
      o.y = Math.round(y);
      clamp(key);
      return;
    }
    var b = boxOf(key), dx = o.x - b.x;
    var xs = [0, 16, 375, 734, 750], ys = [0, 16, 160, 250, 484, 500];
    parts().forEach(function (p) {
      if (p[0] === key || isSlot(p[0])) return;
      var ob = boxOf(p[0]);
      if (!ob) return;
      xs.push(ob.x, ob.x + ob.w / 2, ob.x + ob.w);
      ys.push(ob.y, ob.y + ob.h / 2, ob.y + ob.h);
    });
    function best(pos, length, candidates) {
      var bestD = 6, shift = null, line = null;
      [0, length / 2, length].forEach(function (off) {
        candidates.forEach(function (cnd) {
          var d = Math.abs(pos + off - cnd);
          if (d < bestD) { bestD = d; shift = cnd - off; line = cnd; }
        });
      });
      return { pos: shift === null ? Math.round(pos / 2) * 2 : shift, line: line };
    }
    var bx = best(x - dx, b.w, xs), by = best(y, b.h, ys);
    o.x = Math.round(bx.pos + dx);
    o.y = Math.round(by.pos);
    clamp(key);
    if (bx.line !== null) guides.push({ x: Math.round(bx.line) });
    if (by.line !== null) guides.push({ y: Math.round(by.line) });
  }

  canvas.addEventListener('pointerdown', function (e) {
    if (!M || comparing)
      return;
    var p = point(e), key = hitTest(p);
    canvas.focus({ preventScroll: true });
    if (key === null) {
      if (sel !== null) {
        sel = null;
        changed(true);
      }
      return;
    }
    var o = objectOf(key);
    var again = sel === key;
    sel = key;
    snapshot();
    drag = { key: key, dx: p.x - o.x, dy: p.y - o.y, moved: false };
    canvas.setPointerCapture(e.pointerId);
    canvas.classList.add('dragging');
    say(nameOf(key) + ': ' + (isSlot(key) ? 'przeciągnij, żeby wybrać kadr w polu.' : key === 'avatar' ? 'zawsze na wierzchu.' : key === 'nick' ? 'leży pod warstwami, nakładka może go zakryć.' : 'przeciągnij, żeby przesunąć.'));
    if (!again) changed(true); else redraw();
    e.preventDefault();
  });

  canvas.addEventListener('pointermove', function (e) {
    if (!drag) {
      if (M && !comparing)
        canvas.style.cursor = hitTest(point(e)) !== null ? 'grab' : 'default';
      return;
    }
    var p = point(e);
    moveTo(drag.key, p.x - drag.dx, p.y - drag.dy);
    drag.moved = true;
    redraw();
    refreshProps();
  });

  function stopDrag() {
    if (!drag)
      return;
    if (!drag.moved)
      past.pop();
    drag = null;
    guides = [];
    canvas.classList.remove('dragging');
    canvas.style.cursor = 'default';
    changed(false);
  }
  canvas.addEventListener('pointerup', stopDrag);
  canvas.addEventListener('pointercancel', stopDrag);

  canvas.addEventListener('keydown', function (e) {
    if (!M || sel === null)
      return;
    var d = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
    if (d && isLocked(sel)) {
      say(nameOf(sel) + ': zablokowana, odblokuj ją na liście warstw.', true);
      e.preventDefault();
    } else if (d) {
      var step = e.shiftKey ? 10 : 1, o = objectOf(sel);
      snapshot();
      o.x += d[0] * step;
      o.y += d[1] * step;
      clamp(sel);
      changed(false);
      e.preventDefault();
    } else if ((e.key === 'Delete' || e.key === 'Backspace') && removable(sel)) {
      remove(sel);
      e.preventDefault();
    }
  });

  $('snap').addEventListener('click', function () {
    snapOn = !snapOn;
    this.setAttribute('aria-pressed', String(snapOn));
  });

  $('outlines').addEventListener('click', function () {
    outlines = !outlines;
    this.setAttribute('aria-pressed', String(outlines));
    redraw();
  });

  // ---- the list of layers ----

  function removable(key) { return key !== 'avatar' && key !== 'nick'; }

  function remove(key) {
    snapshot();
    setLocked(key, false);
    if (key === 'header') M.header = null;
    else if (key === 'style') M.style = null;
    else if (key === 'ultra') M.ultra = null;
    else M.layers = M.layers.filter(function (x) { return x.id !== key; });
    if (M.animate && (M.animate === key || M.animate === 'layer:' + key))
      M.animate = null;
    if (sel === key) sel = null;
    say(nameOf(key) + ': usunięto.');
    changed(true);
  }

  function moveLayer(layer, dir) {
    var i = M.layers.indexOf(layer), j = i + dir;
    if (j >= M.layers.length) { say('Wyżej są już tylko ultra nakładka, pasek i awatar.', true); return; }
    if (j < 0) { say('Niżej są tylko nick, obrazek stylu i tło nagłówka.', true); return; }
    snapshot();
    M.layers.splice(i, 1);
    M.layers.splice(j, 0, layer);
    changed(false);
  }

  // arrows for small corrections of the selected part: a pixel a click, ten with Shift
  function nudgePad() {
    var pad = document.createElement('div');
    pad.className = 'profilator-nudge';
    [['↑', 0, -1, 'w górę'], ['←', -1, 0, 'w lewo'], ['→', 1, 0, 'w prawo'], ['↓', 0, 1, 'w dół']].forEach(function (a) {
      var b = button(a[0], 'admin-btn small', 'Przesuń ' + a[3] + ' o 1 px (z Shift o 10 px)', function (e) {
        var o = sel !== null ? objectOf(sel) : null;
        if (!o) return;
        var step = e.shiftKey ? 10 : 1;
        snapshot();
        o.x += a[1] * step;
        o.y += a[2] * step;
        clamp(sel);
        changed(false);
      });
      b.dataset.dir = a[0];
      pad.appendChild(b);
    });
    var label = document.createElement('span');
    label.className = 'hud-field-label';
    label.textContent = 'Korekta (Shift: 10 px)';
    var wrap = document.createElement('div');
    wrap.className = 'profilator-nudge-wrap';
    wrap.appendChild(label);
    wrap.appendChild(pad);
    return wrap;
  }

  function button(text, cls, label, click) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = cls;
    b.textContent = text;
    if (label) {
      b.title = label;
      b.setAttribute('aria-label', label);
    }
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      click(e);
    });
    return b;
  }

  function layerRow(list, key, options) {
    var li = document.createElement('li');
    li.className = 'profilator-layer' + (key !== null && key === sel ? ' selected' : '');
    var o = key !== null ? objectOf(key) : null;
    if (options.hideable && o) {
      var eye = button(o.hidden ? '–' : '●', 'profilator-eye', (o.hidden ? 'Pokaż: ' : 'Ukryj: ') + options.name, function () {
        snapshot();
        o.hidden = !o.hidden;
        changed(false);
      });
      eye.setAttribute('aria-pressed', String(!o.hidden));
      li.appendChild(eye);
    } else {
      var fixed = document.createElement('span');
      fixed.className = 'profilator-fixed';
      fixed.textContent = options.fixed ? '■' : '';
      fixed.title = options.fixed || '';
      li.appendChild(fixed);
    }
    var name = document.createElement('span');
    name.className = 'profilator-layer-name';
    name.textContent = options.name;
    if (options.tag) {
      var tag = document.createElement('small');
      tag.textContent = ' ' + options.tag;
      name.appendChild(tag);
    }
    li.appendChild(name);
    var ops = document.createElement('span');
    ops.className = 'profilator-layer-ops';
    if (key !== null) {
      var on = isLocked(key);
      var lock = button(on ? '🔒' : '🔓', 'profilator-lock', (on ? 'Odblokuj: ' : 'Zablokuj: ') + options.name, function (e) {
        e.stopPropagation();
        setLocked(key, !isLocked(key));
        say(options.name + (isLocked(key) ? ': zablokowana, na podglądzie kliknięcie trafia w to, co leży pod nią.' : ': odblokowana.'));
        changed(true);
      });
      lock.setAttribute('aria-pressed', String(on));
      ops.appendChild(lock);
    }
    if (options.movable) {
      ops.appendChild(button('↑', 'admin-btn small', 'Wyżej', function () { moveLayer(o, 1); }));
      ops.appendChild(button('↓', 'admin-btn small', 'Niżej', function () { moveLayer(o, -1); }));
    }
    li.appendChild(ops);
    if (key !== null)
      li.addEventListener('click', function () {
        // a second click on the selected part lets it go
        sel = sel === key ? null : key;
        changed(true);
      });
    list.appendChild(li);
  }

  function renderLayers() {
    var list = $('layers');
    list.textContent = '';
    if (!M) return;
    var frameName = M.avatar.frame === 'custom' ? 'własna' : M.avatar.frame === 'None' ? 'bez ramki' : frameLabel(M.avatar.frame);
    layerRow(list, 'avatar', { name: 'Awatar', tag: frameName, fixed: 'zawsze na wierzchu' });
    layerRow(list, null, { name: 'Pasek', tag: M.bar.at === 'hidden' ? 'ukryty' : M.bar.at === 'top' ? 'na górze' : 'na dole', fixed: 'stałe miejsce' });
    if (M.ultra) layerRow(list, 'ultra', { name: 'Ultra nakładka', tag: 'cały profil', hideable: true });
    for (var i = M.layers.length - 1; i >= 0; i--)
      layerRow(list, M.layers[i].id, { name: nameOf(M.layers[i].id), hideable: true, movable: true });
    layerRow(list, 'nick', { name: 'Nick', hideable: true });
    if (M.style) layerRow(list, 'style', { name: 'Obrazek stylu', tag: '750 × 340', hideable: true });
    if (M.header) layerRow(list, 'header', { name: 'Tło nagłówka', tag: '750 × 160', hideable: true });
    renderAdd();
  }

  var ADDABLE = [['sticker', 'Karta-naklejka'], ['overlay', 'Nakładka'], ['ultra', 'Ultra nakładka'], ['header', 'Tło nagłówka'], ['style', 'Obrazek stylu'],
    ['anime', 'Panel anime'], ['manga', 'Panel mangi'], ['cards', 'Karcianka'], ['gallery', 'Galeria'], ['bigGallery', 'Duża galeria'], ['rank', 'Ranga']];

  function count(t) { return M.layers.filter(function (x) { return x.t === t; }).length; }

  function canAdd(t) {
    if (t === 'ultra') return !M.ultra;
    if (t === 'header') return !M.header;
    if (t === 'style') return !M.style;
    if (t === 'sticker') return count(t) < (catalog ? catalog.maxStickers : 4);
    if (t === 'overlay') return count(t) < (catalog ? catalog.maxOverlays : 3);
    return count(t) === 0;
  }

  function renderAdd() {
    var s = $('add-kind'), prev = s.value;
    s.textContent = '';
    ADDABLE.forEach(function (a) {
      var o = document.createElement('option');
      var ok = canAdd(a[0]);
      o.value = a[0];
      o.textContent = a[1] + (ok ? '' : ' (już jest)');
      o.disabled = !ok;
      s.appendChild(o);
    });
    var first = ADDABLE.filter(function (a) { return canAdd(a[0]); })[0];
    s.value = prev && canAdd(prev) ? prev : first ? first[0] : '';
  }

  // a picture of the player for a new part, the newest that fits its use first
  function pictureFor(use) {
    var fitting = pictures.filter(function (p) { return p.use === use; });
    var any = fitting.length ? fitting : pictures;
    return any.length ? any[any.length - 1] : null;
  }

  function coverAt(key, picture) {
    var f = slotField(key);
    var s = Math.max(f.w / picture.width, f.h / picture.height);
    return { image: picture.id, scale: s, x: Math.round(f.x + (f.w - picture.width * s) / 2), y: Math.round(f.y + (f.h - picture.height * s) / 2), };
  }

  $('add-btn').addEventListener('click', function () {
    var t = $('add-kind').value;
    if (!t || !canAdd(t))
      return;
    var needsPicture = t === 'overlay' || t === 'ultra' || t === 'header' || t === 'style';
    var picture = needsPicture ? pictureFor(t === 'header' ? 'header' : t === 'style' ? 'style' : t) : null;
    if (needsPicture && !picture) {
      say('Najpierw wyślij obrazek w sekcji „Obrazki”.', true);
      ($('picture-drop') || $('picture-file')).focus();
      return;
    }
    snapshot();
    var key;
    if (t === 'header' || t === 'style') {
      M[t] = coverAt(t, picture);
      key = t;
    } else if (t === 'ultra') {
      M.ultra = { t: 'ultra', image: picture.id, x: 0, y: 0, scale: Math.min(1, L.W / picture.width), opacity: 1 };
      key = 'ultra';
    } else {
      var layer = { t: t, id: nextId(), x: 0, y: 0 };
      if (t === 'overlay') {
        layer.image = picture.id;
        layer.scale = Math.min(1, L.W / picture.width, L.H / picture.height);
        layer.opacity = 1;
      }
      if (t === 'sticker') {
        if (!cardChoices.length) {
          past.pop();
          say('Nie masz kart do postawienia na profilu.', true);
          return;
        }
        layer.card = cardChoices[count('sticker') % cardChoices.length].id;
        layer.h = 140;
        layer.tilt = 0;
        layer.ground = 'panel';
        layer.pad = 4;
      }
      if (t === 'gallery') layer.count = 6;
      if (t === 'anime' || t === 'manga') { layer.size = 'small'; layer.legend = 'main'; layer.recent = 0; layer.hide = []; }
      if (t === 'cards') layer.hide = [];
      var b = L.box(layer, null, assets, data);
      layer.x = Math.round(375 - b.w / 2);
      layer.y = Math.round(250 - b.h / 2);
      M.layers.push(layer);
      key = layer.id;
    }
    sel = key;
    say(nameOf(key) + ': dodano. Przeciągnij na miejsce.');
    changed(true);
  });

  // ---- the properties of the chosen part ----

  function field(label, input) {
    var l = document.createElement('label');
    l.className = 'hud-field';
    var s = document.createElement('span');
    s.className = 'hud-field-label';
    s.textContent = label;
    l.appendChild(s);
    l.appendChild(input);
    return l;
  }

  function number(id, value, change) {
    var i = document.createElement('input');
    i.type = 'number';
    i.className = 'field hud-corners';
    i.id = id;
    i.value = value;
    i.step = 1;
    i.addEventListener('change', function () {
      snapshot();
      change(+i.value);
      changed(false);
    });
    return i;
  }

  function range(id, label, min, max, step, value, show, input) {
    var l = document.createElement('label');
    l.className = 'hud-field profilator-range';
    var s = document.createElement('span');
    s.className = 'hud-field-label';
    s.textContent = label + ' ';
    var out = document.createElement('span');
    out.className = 'muted';
    out.textContent = show(value);
    s.appendChild(out);
    var r = document.createElement('input');
    r.type = 'range';
    r.className = 'player-range';
    r.id = id;
    r.min = min;
    r.max = max;
    r.step = step;
    r.value = value;
    r.addEventListener('input', function () {
      beginEdit();
      input(+r.value);
      out.textContent = show(+r.value);
      redraw();
      refreshProps();
    });
    r.addEventListener('change', function () {
      endEdit();
      changed(false);
    });
    l.appendChild(s);
    l.appendChild(r);
    return l;
  }

  function segment(options, value, pick, disabled) {
    var d = document.createElement('div');
    d.className = 'profilator-seg';
    options.forEach(function (o) {
      var b = button(o[1], 'admin-btn small', null, function () {
        snapshot();
        pick(o[0]);
        changed(true);
      });
      b.setAttribute('aria-pressed', String(o[0] === value));
      b.disabled = !!disabled;
      d.appendChild(b);
    });
    return d;
  }

  function toggle(id, label, value, change) {
    var l = document.createElement('label');
    l.className = 'switch';
    var s = document.createElement('span');
    s.className = 'switch-label';
    s.textContent = label;
    var i = document.createElement('input');
    i.type = 'checkbox';
    i.id = id;
    i.checked = !!value;
    var st = document.createElement('span');
    st.className = 'switch-state';
    st.setAttribute('aria-hidden', 'true');
    st.textContent = value ? 'WŁ' : 'WYŁ';
    var tr = document.createElement('span');
    tr.className = 'switch-track';
    tr.setAttribute('aria-hidden', 'true');
    i.addEventListener('change', function () {
      snapshot();
      change(i.checked);
      st.textContent = i.checked ? 'WŁ' : 'WYŁ';
      changed(false);
    });
    l.appendChild(s);
    l.appendChild(i);
    l.appendChild(st);
    l.appendChild(tr);
    return l;
  }

  // parts of a panel to hide: pressed while shown
  function hiding(items, list, change) {
    var d = document.createElement('div');
    d.className = 'profilator-seg';
    items.forEach(function (it) {
      var off = list.indexOf(it[0]) >= 0;
      var b = button(it[1], 'admin-btn small' + (off ? ' off' : ''), (off ? 'Pokaż: ' : 'Ukryj: ') + it[1], function () {
        snapshot();
        change(it[0], !off);
        changed(true);
      });
      b.setAttribute('aria-pressed', String(!off));
      d.appendChild(b);
    });
    return d;
  }

  function note(text) {
    var p = document.createElement('p');
    p.className = 'hint';
    p.textContent = text;
    return p;
  }

  function labelled(label, element) {
    var d = document.createElement('div');
    d.className = 'profilator-group';
    var l = document.createElement('span');
    l.className = 'hud-field-label';
    l.textContent = label;
    d.appendChild(l);
    d.appendChild(element);
    return d;
  }

  function pictureSelect(value, change) {
    var s = document.createElement('select');
    pictures.forEach(function (p) {
      var o = document.createElement('option');
      o.value = p.id;
      o.textContent = 'Obrazek ' + p.id + ' (' + p.width + ' × ' + p.height + (p.animated ? ', ruchomy' : '') + ')';
      s.appendChild(o);
    });
    s.value = value;
    s.addEventListener('change', function () {
      snapshot();
      change(+s.value);
      changed(true);
    });
    var wrap = document.createElement('span');
    wrap.className = 'hud-select';
    wrap.appendChild(s);
    return field('Obrazek', wrap);
  }

  function frameLabel(key) {
    var f = catalog && catalog.frames.filter(function (x) { return x.key === key; })[0];
    return f ? f.name : key;
  }

  function frameGrid(a) {
    var d = document.createElement('div');
    d.className = 'profilator-frames';
    var list = (catalog ? catalog.frames : []).filter(function (f) { return f.selectable || f.key === a.frame; });
    list = list.concat([{ key: 'custom', name: 'własna', free: false }]);
    list.forEach(function (f) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'profilator-frame';
      b.title = 'Ramka: ' + f.name;
      b.setAttribute('aria-label', 'Ramka: ' + f.name);
      b.setAttribute('aria-pressed', String(a.frame === f.key));
      if (f.key === 'None' || (f.key === 'custom' && !a.customImage)) {
        var t = document.createElement('span');
        t.textContent = f.key === 'None' ? 'brak' : 'własna';
        b.appendChild(t);
      } else {
        var img = document.createElement('img');
        img.alt = '';
        img.src = f.key === 'custom' ? api + '?a=picture&id=' + a.customImage : pw + 'ab/' + f.key + '.png';
        b.appendChild(img);
      }
      if (!f.free) {
        var paid = document.createElement('i');
        paid.textContent = owns('frame:' + f.key) || (f.key === 'custom' && owns('customFrame')) ? '' : '●';
        b.appendChild(paid);
      }
      b.addEventListener('click', function () {
        if (f.key === 'custom' && !a.customImage) {
          var p = pictureFor('frame');
          if (!p) {
            say('Własną ramkę wyślij najpierw w sekcji „Obrazki” jako ramkę.', true);
            return;
          }
          snapshot();
          a.customImage = p.id;
        } else {
          snapshot();
        }
        a.frame = f.key;
        changed(true);
      });
      d.appendChild(b);
    });
    return d;
  }

  function renderProps() {
    var host = $('props');
    host.textContent = '';
    var title = $('props-title');
    var o = sel !== null ? objectOf(sel) : null;
    title.textContent = o ? nameOf(sel) : 'Właściwości';
    if (!o) {
      host.appendChild(note('Kliknij element na podglądzie albo na liście warstw.'));
      return;
    }

    if (!isSlot(sel)) {
      var two = document.createElement('div');
      two.className = 'hud-fields profilator-xy';
      two.appendChild(field('X', number('p-x', o.x, function (v) { o.x = v; clamp(sel); })));
      two.appendChild(field('Y', number('p-y', o.y, function (v) { o.y = v; clamp(sel); })));
      host.appendChild(two);
    }
    host.appendChild(nudgePad());

    if (isSlot(sel)) {
      var key = sel, cover = coverScale(key);
      host.appendChild(note(slotsLinked
        ? 'Tło nagłówka i obrazek stylu to jeden obrazek na cały profil: przesuwają się i powiększają razem.'
        : 'Obrazek zawsze wypełnia swoje pole. Przeciągnij go na podglądzie, żeby wybrać kadr.'));
      host.appendChild(pictureSelect(o.image, function (v) {
        var p = pictures.filter(function (x) { return x.id === v; })[0];
        if (p) M[key] = coverAt(key, p);
      }));
      host.appendChild(range('p-scale', 'Powiększenie', Math.ceil(cover * 100), Math.ceil(cover * 400), 1, Math.round((o.scale || cover) * 100), function (v) { return v + '%'; }, function (v) {
        var f = slotField(key), cx = f.x + f.w / 2, cy = f.y + f.h / 2, k = (v / 100) / (o.scale || cover);
        o.x = cx - (cx - o.x) * k;
        o.y = cy - (cy - o.y) * k;
        o.scale = v / 100;
        clampSlot(key);
      }));
    } else if (sel === 'ultra' || o.t === 'overlay') {
      host.appendChild(pictureSelect(o.image, function (v) { o.image = v; }));
      host.appendChild(range('p-scale', 'Skala', 10, 300, 1, Math.round((o.scale || 1) * 100), function (v) { return v + '%'; }, function (v) { o.scale = v / 100; clamp(sel); }));
      host.appendChild(range('p-opacity', 'Krycie', 10, 100, 1, Math.round((o.opacity == null ? 1 : o.opacity) * 100), function (v) { return v + '%'; }, function (v) { o.opacity = v / 100; }));
      host.appendChild(note(sel === 'ultra' ? 'Może leżeć na całym profilu, także na górze nagłówka, nad wszystkimi warstwami. Pod paskiem i awatarem.' : 'Widać ją tylko pod górą nagłówka (od 98 px w dół), w dowolnej kolejności wśród warstw. Leży nad nickiem, więc można na niej narysować własny.'));
    } else if (sel === 'avatar') {
      host.appendChild(labelled('Ramka', frameGrid(o)));
      if (o.frame === 'custom') {
        host.appendChild(pictureSelect(o.customImage, function (v) { o.customImage = v; }));
        host.appendChild(range('p-cscale', 'Skala ramki', 50, 200, 1, Math.round((o.customScale || 1) * 100), function (v) { return v + '%'; }, function (v) { o.customScale = v / 100; }));
        host.appendChild(range('p-cx', 'Przesunięcie w poziomie', -60, 60, 1, o.customX || 0, function (v) { return v + ' px'; }, function (v) { o.customX = v; }));
        host.appendChild(range('p-cy', 'Przesunięcie w pionie', -60, 60, 1, o.customY || 0, function (v) { return v + ' px'; }, function (v) { o.customY = v; }));
      } else if (o.frame !== 'None') {
        host.appendChild(toggle('p-level', 'Ramka na poziom (gdy ma takie warianty)', o.levelFrame, function (v) { o.levelFrame = v; }));
      }
      host.appendChild(labelled('Rozmiar', segment(L.AVATAR_SIZES.map(function (s) { return [s, s + ' px']; }), o.size, function (v) { o.size = v; clamp('avatar'); })));
      host.appendChild(labelled('Kształt', segment([['circle', 'Koło'], ['rounded', 'Zaokrąglony'], ['square', 'Kwadrat']], o.frame !== 'None' ? 'circle' : o.shape, function (v) { o.shape = v; }, o.frame !== 'None')));
      host.appendChild(note('Awatar zawsze jest widoczny i na wierzchu, zakrywa go tylko ramka. Z ramką jest okrągły. Kropka przy ramce: do odblokowania.'));
    } else if (sel === 'nick') {
      host.appendChild(range('p-w', 'Szerokość', 200, 600, 2, o.w, function (v) { return v + ' px'; }, function (v) { o.w = v; clamp('nick'); }));
      host.appendChild(labelled('Wyrównanie', segment([['left', 'Do lewej'], ['center', 'Do środka']], o.align, function (v) { o.align = v; })));
      host.appendChild(toggle('p-hidden', 'Ukryj wbudowany nick', o.hidden, function (v) { o.hidden = v; }));
      host.appendChild(note('Leży pod warstwami: nakładka czy naklejka może go zakryć. Za długi nick sam się zmniejsza.'));
    } else if (o.t === 'sticker') {
      var s = document.createElement('select');
      cardChoices.forEach(function (c) {
        var opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name + ' (' + c.rarity + (c.animated ? ', ruchoma' : '') + ')';
        s.appendChild(opt);
      });
      s.value = o.card;
      s.addEventListener('change', function () {
        snapshot();
        o.card = +s.value;
        changed(true);
      });
      var wrap = document.createElement('span');
      wrap.className = 'hud-select';
      wrap.appendChild(s);
      host.appendChild(field('Karta', wrap));
      host.appendChild(range('p-h', 'Rozmiar', 60, 300, 2, o.h, function (v) { return v + ' px'; }, function (v) { o.h = v; clamp(sel); }));
      host.appendChild(range('p-tilt', 'Pochylenie', -15, 15, 1, o.tilt || 0, function (v) { return v + '°'; }, function (v) { o.tilt = v; }));
      host.appendChild(labelled('Tło pod kartą', segment([['panel', 'Pełne'], ['glass', 'Szkło'], ['shadow', 'Cień'], ['none', 'Brak']], L.groundOf(o), function (v) {
        o.ground = v;
        delete o.groundOpacity;
      })));
      if (o.ground !== 'none')
        host.appendChild(range('p-gopacity', o.ground === 'shadow' ? 'Siła cienia' : 'Krycie tła', 0, 100, 1, Math.round(L.groundAlpha(o) * 100), function (v) { return v + '%'; }, function (v) { o.groundOpacity = v; }));
      if (o.ground === 'panel' || o.ground === 'glass')
        host.appendChild(range('p-pad', 'Odstęp tła od karty', 0, 40, 1, o.pad == null ? 4 : o.pad, function (v) { return v + ' px'; }, function (v) { o.pad = v; }));
      if (o.ground === 'glass')
        host.appendChild(note('Szkło jest częścią odblokowania „Wygląd paneli”.'));
    } else if (o.t === 'anime' || o.t === 'manga') {
      o.hide = o.hide || [];
      host.appendChild(labelled('Rozmiar', segment([['small', 'Mały'], ['large', 'Duży']], o.size || 'small', function (v) { o.size = v; clamp(sel); })));
      host.appendChild(labelled('Części (kliknij, by ukryć)', hiding([['mean', 'Średnia'], ['numbers', 'Liczby'], ['bar', 'Pasek statusów'], ['legend', 'Legenda']], o.hide, function (part, off) {
        o.hide = off ? o.hide.concat([part]) : o.hide.filter(function (x) { return x !== part; });
      })));
      host.appendChild(labelled('Legenda', segment([['main', 'Trzy najważniejsze'], ['all', 'Wszystkie statusy']], o.legend || 'main', function (v) { o.legend = v; })));
      host.appendChild(range('p-recent', o.t === 'anime' ? 'Ostatnio oglądane' : 'Ostatnio czytane', 0, 3, 1, o.recent || 0, function (v) { return v ? String(v) : 'brak'; }, function (v) { o.recent = v; }));
      host.appendChild(note('W małym panelu ostatni tytuł zastępuje legendę, w dużym mieszczą się do trzech.'));
    } else if (o.t === 'cards') {
      o.hide = o.hide || [];
      host.appendChild(labelled('Części (kliknij, by ukryć)', hiding([['karma', 'Karma'], ['waifu', 'Karta waifu'], ['count', 'Liczba kart'], ['rarity', 'Rzadkości'],
        ['ult', 'Ultimate'], ['scal', 'Własne obrazki'], ['scis', 'Własne ramki']], o.hide, function (part, off) {
        o.hide = off ? o.hide.concat([part]) : o.hide.filter(function (x) { return x !== part; });
      })));
    } else if (o.t === 'gallery') {
      host.appendChild(labelled('Karty', segment([[6, '6 małych'], [2, '2 duże']], o.count || 6, function (v) { o.count = v; })));
      host.appendChild(toggle('p-panel', 'Tło panelu', o.panel !== false, function (v) { o.panel = v; }));
    } else if (o.t === 'bigGallery') {
      host.appendChild(toggle('p-panel', 'Tło panelu', o.panel !== false, function (v) { o.panel = v; }));
    } else if (o.t === 'rank') {
      host.appendChild(labelled('Wyrównanie', segment([['left', 'Od lewej'], ['center', 'Środkiem']], o.align || 'left', function (v) {
        var b = boxOf(sel);
        o.align = v;
        o.x = v === 'center' ? Math.round(b.x + b.w / 2) : Math.round(b.x);
      })));
    }

    if (removable(sel))
      host.appendChild(button('Usuń element', 'admin-btn small', null, function () { remove(sel); }));
  }

  function refreshProps() {
    var o = sel !== null ? objectOf(sel) : null;
    if (!o) return;
    var x = $('p-x'), y = $('p-y');
    if (x && document.activeElement !== x) x.value = o.x;
    if (y && document.activeElement !== y) y.value = o.y;
  }

  // ---- the whole look ----

  var ACCENTS = [[null, 'kolor roli'], ['#4FC3E8', 'błękitny'], ['#F06292', 'różowy'], ['#6FD47E', 'zielony'], ['#F5C445', 'złoty']];
  var BAR_ITEMS = [['top', 'Ranking'], ['level', 'Poziom'], ['exp', 'Doświadczenie'], ['msgs', 'Wiadomości'], ['sc', 'SC'], ['tc', 'TC'], ['ac', 'AC'], ['ct', 'CT'], ['pc', 'PC']];

  function renderLook() {
    if (!M) return;
    var sw = $('accents');
    sw.textContent = '';
    ACCENTS.forEach(function (a) {
      var b = button('', 'profilator-swatch', a[1], function () {
        snapshot();
        M.accent = a[0];
        changed(false);
      });
      b.style.background = a[0] || (data && data.color) || '#9B59B6';
      b.setAttribute('aria-pressed', String((M.accent || null) === a[0]));
      sw.appendChild(b);
    });
    var pick = $('accent-pick');
    var custom = M.accent && !ACCENTS.some(function (a) { return a[0] === M.accent; });
    pick.parentNode.classList.toggle('on', !!custom);
    if (custom && document.activeElement !== pick)
      pick.value = M.accent.toLowerCase();

    fill($('look-panels'), segment([['solid', 'Pełne'], ['glass', 'Szkło'], ['shadow', 'Cień']], M.panels, function (v) { M.panels = v; }));
    fill($('look-shade'), segment([['band', 'Pasek'], ['fade', 'Cieniowanie'], ['none', 'Brak']], M.shade, function (v) { M.shade = v; }));
    fill($('look-bar'), segment([['bottom', 'Na dole'], ['top', 'Na górze'], ['hidden', 'Ukryty']], M.bar.at, function (v) { M.bar.at = v; }));
    fill($('look-bar-look'), segment([['solid', 'Pełny'], ['glass', 'Szkło']], M.bar.look, function (v) { M.bar.look = v; }));
    fill($('look-bar-items'), hiding(BAR_ITEMS, M.bar.hide, function (item, off) {
      M.bar.hide = off ? M.bar.hide.concat([item]) : M.bar.hide.filter(function (x) { return x !== item; });
    }));
    var shadow = $('look-shadow');
    shadow.value = M.shadow;
    shadow.disabled = M.panels !== 'shadow';
    $('look-shadow-value').textContent = M.shadow + '%';
    $('look-bar-opacity').value = M.bar.opacity;
    $('look-bar-opacity-value').textContent = M.bar.opacity + '%';

    var anim = $('look-animate');
    var current = M.animate || '';
    anim.textContent = '';
    var options = [['', 'nic']];
    options.push(['avatar', 'awatar (gdy ruchomy na Discordzie)']);
    if (M.header) options.push(['header', 'tło nagłówka']);
    if (M.style) options.push(['style', 'obrazek stylu']);
    if (M.ultra) options.push(['ultra', 'ultra nakładka']);
    if (M.avatar.frame === 'custom') options.push(['frame', 'własna ramka']);
    M.layers.forEach(function (layer) {
      if (['sticker', 'overlay', 'cards', 'gallery', 'bigGallery'].indexOf(layer.t) >= 0)
        options.push(['layer:' + layer.id, nameOf(layer.id)]);
    });
    options.forEach(function (o) {
      var opt = document.createElement('option');
      opt.value = o[0];
      opt.textContent = o[1];
      anim.appendChild(opt);
    });
    anim.value = options.some(function (o) { return o[0] === current; }) ? current : '';
  }

  function fill(host, element) {
    host.textContent = '';
    host.appendChild(element);
  }

  $('accent-pick').addEventListener('input', function () {
    beginEdit();
    M.accent = this.value.toUpperCase();
    redraw();
  });
  $('accent-pick').addEventListener('change', function () {
    endEdit();
    changed(false);
  });
  $('look-shadow').addEventListener('input', function () {
    beginEdit();
    M.shadow = +this.value;
    $('look-shadow-value').textContent = M.shadow + '%';
    redraw();
  });
  $('look-shadow').addEventListener('change', function () { endEdit(); changed(false); });
  $('look-bar-opacity').addEventListener('input', function () {
    beginEdit();
    M.bar.opacity = +this.value;
    $('look-bar-opacity-value').textContent = M.bar.opacity + '%';
    redraw();
  });
  $('look-bar-opacity').addEventListener('change', function () { endEdit(); changed(false); });
  $('look-animate').addEventListener('change', function () {
    snapshot();
    M.animate = this.value || null;
    changed(false);
  });

  // ---- ready layouts ----

  // a ready layout keeps the player's pictures, stickers and look; only the panels, nick, rank and avatar move
  function applyPreset(preset) {
    snapshot();
    var next = normalise(preset.layout);
    next.header = M.header;
    next.style = M.style;
    next.ultra = M.ultra;
    next.accent = M.accent;
    next.panels = M.panels;
    next.shadow = M.shadow;
    next.shade = M.shade;
    next.bar = M.bar;
    next.avatar.frame = M.avatar.frame;
    next.avatar.levelFrame = M.avatar.levelFrame;
    next.avatar.customImage = M.avatar.customImage;
    next.avatar.customScale = M.avatar.customScale;
    next.avatar.customX = M.avatar.customX;
    next.avatar.customY = M.avatar.customY;
    next.avatar.size = M.avatar.size;
    next.avatar.shape = M.avatar.shape;
    next.nick.hidden = M.nick.hidden;
    var id = next.layers.reduce(function (m, x) { return Math.max(m, x.id); }, 0);
    M.layers.forEach(function (layer) {
      if (layer.t === 'sticker' || layer.t === 'overlay') {
        var copy = clone(layer);
        copy.id = ++id;
        next.layers.push(copy);
      }
    });
    next.animate = null;
    M = next;
    sel = null;
    say('Układ „' + preset.name + '”: gotowe układy są darmowe, przesunięcie czegokolwiek to już własny układ.');
    changed(true);
  }

  function renderPresets() {
    var host = $('presets');
    host.textContent = '';
    (catalog ? catalog.presets : []).forEach(function (p) {
      host.appendChild(button(p.name, 'admin-btn small', null, function () { applyPreset(p); }));
    });
  }

  // ---- sharing ----

  // the layout without the player's pictures and cards, in the address
  function shareable() {
    var s = clone(M);
    s.header = null;
    s.style = null;
    s.ultra = null;
    if (s.avatar.frame === 'custom') s.avatar.frame = 'Base';
    delete s.avatar.customImage;
    s.layers = s.layers.filter(function (x) { return x.t !== 'overlay' && x.t !== 'sticker'; });
    s.animate = null;
    return s;
  }

  function encode(value) {
    var bytes = new TextEncoder().encode(JSON.stringify(value));
    var bin = '';
    bytes.forEach(function (b) { bin += String.fromCharCode(b); });
    return btoa(bin).replace(/=+$/, '').replace(/\+/g, '-').replace(/\//g, '_');
  }

  function decodeShared(text) {
    try {
      var b64 = text.replace(/-/g, '+').replace(/_/g, '/');
      var bin = atob(b64 + '===='.slice((b64.length % 4) || 4));
      var bytes = new Uint8Array(bin.length);
      for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
      var value = JSON.parse(new TextDecoder().decode(bytes));
      return value && typeof value === 'object' ? value : null;
    } catch (e) {
      return null;
    }
  }

  var sharing = false;
  function keepHash() {
    if (!sharing)
      return;
    history.replaceState(null, '', '#l=' + encode(shareable()));
  }

  $('share').addEventListener('click', function () {
    sharing = true;
    keepHash();
    var link = location.href;
    var done = function () { say('Link do układu skopiowany. Obrazki i karty zostają u ciebie, w linku jest sam układ.'); };
    if (navigator.clipboard && navigator.clipboard.writeText)
      navigator.clipboard.writeText(link).then(done, function () { say('Link do układu jest w pasku adresu.'); });
    else
      say('Link do układu jest w pasku adresu.');
  });

  // ---- the price and the save ----

  function owns(key) { return unlocks.indexOf(key) >= 0; }

  var quoteTimer = null;
  var quoteToken = 0;
  function askQuote() {
    if (!logged || !M || !data || data.sample)
      return;
    clearTimeout(quoteTimer);
    var token = ++quoteToken;
    quoteTimer = setTimeout(function () {
      post('quote', M).then(function (answer) {
        if (token !== quoteToken)
          return;
        quote = answer.status === 200 ? answer.body : null;
        renderCost(answer);
      });
    }, 500);
  }

  function post(action, body, headers) {
    var h = { 'Content-Type': 'application/json', 'X-CSRF': csrf };
    Object.keys(headers || {}).forEach(function (k) { h[k] = headers[k]; });
    return fetch(api + '?a=' + action, { method: 'POST', credentials: 'same-origin', headers: h, body: JSON.stringify(body) }).then(function (res) {
      return res.json().then(function (b) { return { status: res.status, body: b }; }, function () { return { status: res.status, body: null }; });
    }, function () {
      return { status: 0, body: null };
    });
  }

  function money(sc, tc) { return group(sc) + ' SC albo ' + group(tc) + ' TC'; }

  function group(n) { return String(n || 0).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }

  function renderCost(answer) {
    var list = $('cost-list');
    var total = $('cost-total');
    var errors = $('cost-errors');
    list.textContent = '';
    errors.textContent = '';
    if (!quote) {
      total.textContent = answer && answer.status ? 'Nie udało się wycenić układu (' + answer.status + ').' : 'Bot nie odpowiada, wycena za chwilę.';
      $('save').disabled = true;
      return;
    }
    (quote.errors || []).forEach(function (e) {
      var li = document.createElement('li');
      li.textContent = e;
      errors.appendChild(li);
    });
    (quote.missing || []).forEach(function (f) {
      var li = document.createElement('li');
      var name = document.createElement('span');
      name.textContent = f.name;
      var price = document.createElement('span');
      price.className = 'muted';
      price.textContent = money(f.sc, f.tc);
      li.appendChild(name);
      li.appendChild(price);
      list.appendChild(li);
    });
    if (!(quote.missing || []).length) {
      var li = document.createElement('li');
      li.className = 'muted';
      li.textContent = 'Wszystko, czego używa ten układ, masz już odblokowane.';
      list.appendChild(li);
    }
    var currency = $('currency').value;
    var afford = !quote.sc || (currency === 'tc' ? quote.walletTc >= quote.tc : quote.walletSc >= quote.sc);
    total.textContent = '';
    function row(label, value, cls) {
      var r = document.createElement('div');
      r.className = 'profilator-total-row ' + cls;
      var l = document.createElement('span');
      l.textContent = label;
      var v = document.createElement('b');
      v.textContent = value;
      r.appendChild(l);
      r.appendChild(v);
      total.appendChild(r);
    }
    if (quote.sc) {
      row('Do zapłaty raz', money(quote.sc, quote.tc), 'due');
      row('Masz', group(quote.walletSc) + ' SC i ' + group(quote.walletTc) + ' TC', 'wallet');
      if (!afford)
        row('Brakuje', currency === 'tc' ? group(quote.tc - quote.walletTc) + ' TC' : group(quote.sc - quote.walletSc) + ' SC', 'short');
    } else {
      row('Zapis', 'bez opłat', 'free');
    }
    $('currency-field').hidden = !quote.sc;
    $('save').disabled = !!(quote.errors && quote.errors.length) || !afford;
    $('save').textContent = quote.sc ? 'Zapłać i zapisz' : 'Zapisz';
  }

  $('currency').addEventListener('change', function () { renderCost(); });

  function key() {
    var b = new Uint8Array(16);
    crypto.getRandomValues(b);
    return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
  }

  $('save').addEventListener('click', function () {
    if (!M || !quote)
      return;
    var btn = this;
    btn.disabled = true;
    say('Zapisywanie…');
    post('save', { layout: M, currency: $('currency').value, version: version }, { 'X-Idempotency-Key': key() }).then(function (answer) {
      if (answer.status === 200 && answer.body) {
        version = answer.body.version;
        unlocks = unlocks.concat(answer.body.unlocked || []);
        say(answer.body.paid ? 'Zapisano. Pobrano ' + group(answer.body.paid) + ' ' + answer.body.currency.toUpperCase() + ' za odblokowania.' : 'Zapisano.');
        present = null;
        askQuote();
        renderProps();
        return;
      }
      var why = {
        409: 'Profil zmienił się w innym oknie albo karcie. Odśwież stronę, żeby zobaczyć obecny.',
        402: 'Za mało waluty na odblokowania.',
        400: 'Bot nie przyjął układu: ' + (Array.isArray(answer.body) ? answer.body.join('; ') : (answer.body && answer.body.error) || 'błędne dane') + '.',
        503: 'Bot nie odpowiada, spróbuj za chwilę.'
      }[answer.status] || 'Nie udało się zapisać (' + answer.status + ').';
      say(why, true);
      btn.disabled = false;
    });
  });

  // ---- the present profile and the bot's own picture ----

  function fetchImage(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin' }, options || {})).then(function (res) {
      if (!res.ok)
        return res.json().then(function (b) { throw new Error(Array.isArray(b) ? b.join('; ') : (b && b.error) || res.status); }, function () { throw new Error(res.status); });
      return res.blob();
    }).then(function (blob) { return load(URL.createObjectURL(blob)); });
  }

  $('compare').addEventListener('click', function () {
    var btn = this;
    if (comparing) {
      comparing = false;
      btn.setAttribute('aria-pressed', 'false');
      btn.textContent = 'Pokaż obecny';
      redraw();
      return;
    }
    var show = function () {
      comparing = true;
      btn.setAttribute('aria-pressed', 'true');
      btn.textContent = 'Pokaż nowy';
      say('Tak wygląda twój profil w bocie teraz.');
      redraw();
    };
    if (present) {
      show();
      return;
    }
    btn.disabled = true;
    say('Bot rysuje obecny profil…');
    fetchImage(api + '?a=current').then(function (img) {
      present = img;
      btn.disabled = false;
      show();
    }, function (e) {
      btn.disabled = false;
      say('Nie udało się pobrać obecnego profilu: ' + e.message, true);
    });
  });

  $('bot-preview').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    say('Bot rysuje ten układ…');
    fetchImage(api + '?a=preview', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf }, body: JSON.stringify(M)
    }).then(function (img) {
      btn.disabled = false;
      var box = $('bot-picture');
      box.hidden = false;
      $('bot-picture-img').src = img.src;
      say('Tak narysuje go bot.');
    }, function (e) {
      btn.disabled = false;
      say('Bot nie narysował układu: ' + e.message, true);
    });
  });

  $('bot-picture-close').addEventListener('click', function () { $('bot-picture').hidden = true; });

  // ---- the player's pictures ----

  var cutout = null;

  function renderPictures() {
    var host = $('pictures');
    host.textContent = '';
    if (!pictures.length) {
      var li = document.createElement('li');
      li.className = 'muted profilator-empty';
      li.textContent = logged ? 'Nie masz jeszcze obrazków. Wyślij pierwszy poniżej.' : 'Po zalogowaniu wyślesz tu swoje obrazki.';
      host.appendChild(li);
      return;
    }
    var USES = { header: 'tło nagłówka', style: 'obrazek stylu', overlay: 'nakładka', ultra: 'ultra nakładka', frame: 'ramka' };
    pictures.slice().reverse().forEach(function (p) {
      var li = document.createElement('li');
      var img = document.createElement('img');
      img.alt = '';
      img.loading = 'lazy';
      img.src = api + '?a=picture&id=' + p.id;
      var text = document.createElement('span');
      text.textContent = 'Obrazek ' + p.id + ' · ' + (USES[p.use] || p.use) + ' · ' + p.width + ' × ' + p.height + (p.animated ? ' · ruchomy' : '');
      li.appendChild(img);
      li.appendChild(text);
      if (logged)
        li.appendChild(button('Usuń', 'admin-btn small', 'Usuń obrazek ' + p.id, function () { removePicture(p); }));
      host.appendChild(li);
    });
  }

  // the pictures the layout being edited uses
  function picturesInLayout() {
    var used = [];
    if (M.header) used.push(M.header.image);
    if (M.style) used.push(M.style.image);
    if (M.ultra) used.push(M.ultra.image);
    if (M.avatar.frame === 'custom') used.push(M.avatar.customImage);
    M.layers.forEach(function (layer) { if (layer.t === 'overlay') used.push(layer.image); });
    return used;
  }

  // the bot refuses a picture its saved profile still shows
  function removePicture(p) {
    if (picturesInLayout().indexOf(p.id) >= 0) {
      say('Obrazek ' + p.id + ' jest w układzie. Najpierw usuń go z układu.', true);
      return;
    }
    if (!window.confirm('Usunąć obrazek ' + p.id + '? Tego nie da się cofnąć.'))
      return;
    fetch(api + '?a=delete&id=' + encodeURIComponent(p.id), { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf } }).then(function (res) {
      if (res.status === 200 || res.status === 404) {
        pictures = pictures.filter(function (x) { return x.id !== p.id; });
        delete images[p.id];
        renderPictures();
        say('Obrazek ' + p.id + ' usunięty.');
      } else if (res.status === 409) {
        say('Obrazek ' + p.id + ' jest w zapisanym profilu. Zapisz profil bez niego, potem go usuń.', true);
      } else {
        say('Nie udało się usunąć obrazka (' + res.status + ').', true);
      }
    }, function () {
      say('Bot nie odpowiada, spróbuj za chwilę.', true);
    });
  }

  // the place for the picture file, as the croppers have it: a click or a dropped file
  var drop = $('picture-drop');
  if (drop) {
    var fileInput = $('picture-file');
    var depth = 0;
    var showName = function () {
      var f = fileInput.files && fileInput.files[0];
      $('picture-name').textContent = f ? f.name : 'albo kliknij, by wybrać plik: png, jpg, gif, webp, do 10 MB';
      $('picture-name').classList.toggle('file', !!f);
    };
    drop.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('click', function (e) { e.stopPropagation(); });
    fileInput.addEventListener('change', showName);
    drop.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      e.preventDefault();
      fileInput.click();
    });
    drop.addEventListener('dragenter', function (e) { e.preventDefault(); depth++; drop.classList.add('over'); });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); });
    drop.addEventListener('dragleave', function () { if (--depth <= 0) { depth = 0; drop.classList.remove('over'); } });
    drop.addEventListener('drop', function (e) {
      e.preventDefault();
      depth = 0;
      drop.classList.remove('over');
      var f = e.dataTransfer.files[0];
      if (!f) return;
      if (!/^image\//.test(f.type)) {
        say('To nie jest obrazek.', true);
        return;
      }
      var dt = new DataTransfer();
      dt.items.add(f);
      fileInput.files = dt.files;
      showName();
    });
  }

  $('picture-send').addEventListener('click', function () {
    var input = $('picture-file');
    var file = input.files && input.files[0];
    if (!file) {
      say('Wybierz plik obrazka.', true);
      return;
    }
    if (catalog && file.size > catalog.maxPictureBytes) {
      say('Plik jest za duży, najwyżej ' + Math.round(catalog.maxPictureBytes / 1048576) + ' MB.', true);
      return;
    }
    var use = $('picture-use').value;
    var btn = this;
    btn.disabled = true;
    var ready = $('picture-cut').checked && cutout
      ? cutout.removeBackground(URL.createObjectURL(file), $('cut-model').value, function (stage, fraction) {
        say(stage === 'fetch' ? 'Pobieranie modelu' + (fraction != null ? ' ' + Math.round(fraction * 100) + '%' : '…') : 'Usuwanie tła…');
      }).then(function (blob) { return new File([blob], file.name.replace(/\.[^.]*$/, '') + '-bez-tla.png', { type: 'image/png' }); })
      : Promise.resolve(file);
    ready.then(function (sent) {
      say('Wysyłanie obrazka…');
      var form = new FormData();
      form.append('file', sent);
      form.append('use', use === 'whole' ? 'header' : use);
      return fetch(api + '?a=upload', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: form }).then(function (res) {
        return res.json().then(function (b) { return { status: res.status, body: b }; });
      });
    }).then(function (answer) {
      btn.disabled = false;
      if (answer.status !== 200) {
        say({ 429: 'Masz za dużo nieużywanych obrazków: zapisz profil, nieużywane znikną po godzinie.', 413: 'Plik jest za duży.' }[answer.status]
          || 'Bot nie przyjął obrazka (' + answer.status + ').', true);
        return;
      }
      var p = answer.body;
      if (!pictures.some(function (x) { return x.id === p.id; }))
        pictures.push(p);
      input.value = '';
      fetchPicture(p.id).then(function () {
        if (use === 'whole') {
          snapshot();
          M.header = wholeAt(p);
          M.style = wholeAt(p);
          sel = 'header';
          say('Obrazek ustawiony na całym profilu: górą w tle nagłówka, resztą w obrazku stylu.');
        } else {
          say('Obrazek wysłany. Dodaj go jako ' + { header: 'tło nagłówka', style: 'obrazek stylu', overlay: 'nakładkę', ultra: 'ultra nakładkę', frame: 'własną ramkę awatara' }[use] + '.');
          $('add-kind').value = use === 'frame' ? $('add-kind').value : use;
        }
        renderPictures();
        changed(true);
      });
    }, function (e) {
      btn.disabled = false;
      say('Nie udało się przygotować obrazka' + (e && e.message ? ': ' + e.message : '') + '.', true);
    });
  });

  if (root.getAttribute('data-cutout')) {
    import(new URL(root.getAttribute('data-cutout'), location.href).href).then(function (module) {
      cutout = module;
      var picked = 'anime';
      try {
        picked = localStorage.getItem('profilator-cut-model') || picked;
      } catch (e) {
        // no storage: the model for anime characters
      }
      module.bgModels.forEach(function (model) {
        var option = document.createElement('option');
        option.value = model.value;
        option.textContent = model.label + ' (' + model.tag + ')';
        $('cut-model').appendChild(option);
      });
      $('cut-model').value = module.bgModels.some(function (m) { return m.value === picked; }) ? picked : module.bgModels[0].value;
      $('cut-box').hidden = false;
    }, function () {
      // without the module the pictures keep their background
    });
  }

  $('cut-model').addEventListener('change', function () {
    try {
      localStorage.setItem('profilator-cut-model', this.value);
    } catch (e) {
      // kept for this visit only
    }
  });

  // ---- loading what the layout needs ----

  function loadNeeded() {
    if (!M || !data)
      return;
    var wanted = [];
    if (M.header) wanted.push(M.header.image);
    if (M.style) wanted.push(M.style.image);
    if (M.ultra) wanted.push(M.ultra.image);
    if (M.avatar.frame === 'custom' && M.avatar.customImage) wanted.push(M.avatar.customImage);
    M.layers.forEach(function (layer) { if (layer.t === 'overlay') wanted.push(layer.image); });
    wanted.forEach(function (id) { if (id && !data.sample) fetchPicture(id); });
    M.layers.forEach(function (layer) {
      if (layer.t !== 'sticker') return;
      var card = cardChoices.filter(function (c) { return c.id === layer.card; })[0];
      if (card) loadCard(card);
    });
  }

  // ---- start ----

  function sampleCards() {
    // the cards of the made-up player, put together like a card of Skalpelator
    var cards = root.getAttribute('data-cards') || '';
    return Promise.all([load(root.getAttribute('data-board')), load(root.getAttribute('data-art'))]).then(function (both) {
      var board = both[0], art = both[1];
      if (!art)
        return;
      var looks = [['SSS', 'Kamidere'], ['SS', 'Deredere'], ['S', 'Tsundere'], ['A', 'Yandere'], ['B', 'Dandere'], ['C', 'Kuudere'],
        ['SSS', 'Mayadere'], ['SS', 'Bodere'], ['S', 'Yami'], ['A', 'Raito'], ['B', 'Yato'], ['E', 'Kamidere']];
      return Promise.all(looks.map(function (look, i) {
        return Promise.all([load(cards + look[0] + '.png'), load(cards + look[1] + '.png')]).then(function (pieces) {
          var c = document.createElement('canvas');
          c.width = 475;
          c.height = 667;
          var x = c.getContext('2d');
          if (board) x.drawImage(board, 13, 13);
          var side = Math.min(art.naturalWidth / 448, art.naturalHeight / 650);
          x.drawImage(art, (art.naturalWidth - 448 * side) / 2, (art.naturalHeight - 650 * side) / 2, 448 * side, 650 * side, 13, 13, 448, 650);
          if (pieces[0]) x.drawImage(pieces[0], 0, 0);
          if (pieces[1]) x.drawImage(pieces[1], 0, 0);
          cardImages['sample-' + i] = c;
        });
      }));
    }).then(redraw);
  }

  function useData(answer) {
    var mine = answer && answer.status === 200 && answer.body && !answer.body.error ? answer.body : null;
    if (!mine) {
      data = clone(SAMPLE);
      data.sample = true;
      M = normalise(catalog ? catalog.presets[0].layout : {});
      cardChoices = [];
      sampleCards();
      $('profile-source').textContent = logged
        ? (answer && answer.body && answer.body.error === 'none' ? 'Nie masz jeszcze profilu w bocie, ' : 'Bot nie podał teraz twojego profilu, ') + 'więc dane są przykładowe i zapis jest wyłączony.'
        : 'Dane są przykładowe. Zaloguj się kontem Discord, żeby edytować i zapisać swój profil.';
      $('save').disabled = true;
    } else {
      data = mine.data;
      M = normalise(mine.layout);
      version = mine.version;
      unlocks = mine.unlocks || [];
      pictures = mine.pictures || [];
      cardChoices = mine.cards || [];
      $('profile-source').textContent = 'To twój profil z bota: twoje liczby, karty, statystyki z Shindena i obrazki.';
      if (data.waifu) loadCard(data.waifu);
      (data.gallery || []).forEach(loadCard);
    }
    load(data.avatar).then(function (img) { avatarImage = img; redraw(); });

    var shared = location.hash.indexOf('#l=') === 0 ? decodeShared(location.hash.slice(3)) : null;
    if (shared) {
      var keep = M;
      M = normalise(shared);
      M.header = keep.header;
      M.style = keep.style;
      M.ultra = keep.ultra;
      sharing = true;
      say('Wczytano układ z linku. Twoje obrazki i karty dodasz sam; zapis pokaże, co trzeba odblokować.');
    }
    renderPictures();
    renderPresets();
    showMotion();
    $('editor').inert = false;
    $('profile-loading').hidden = true;
    changed(true);
  }

  load(pw + 'pbg.png').then(function (img) { defaultHeader = img; redraw(); });
  ['Base'].forEach(function (name) { frameImage(name, 0); });

  var fonts = document.fonts && document.fonts.load
    ? Promise.all([document.fonts.load('700 28px Lato', 'Ąą'), document.fonts.load('400 16px Lato', 'Ąą')]).catch(function () {})
    : Promise.resolve();

  var catalogLoad = fetch(api + '?a=catalog', { credentials: 'same-origin' }).then(function (res) { return res.ok ? res.json() : null; }).catch(function () { return null; });
  var meLoad = logged
    ? fetch(api + '?a=me', { credentials: 'same-origin' }).then(function (res) {
      return res.json().then(function (b) { return { status: res.status, body: b }; }, function () { return { status: res.status, body: null }; });
    }, function () { return { status: 0, body: null }; })
    : Promise.resolve(null);

  Promise.all([catalogLoad, meLoad, fonts]).then(function (got) {
    catalog = got[0];
    if (!catalog) {
      $('profile-source').textContent = 'Bot nie odpowiada, więc Profilator nie może teraz działać. Spróbuj za chwilę.';
      $('profile-loading').hidden = true;
      return;
    }
    useData(got[1]);
  });
})();
