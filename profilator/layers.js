// The bot's profile of layers drawn in the browser, part by part as the bot draws it
// (ImageProcessing.LayeredProfile.cs): the header and style pictures in their fields,
// the nick, the free layers bottom to top (overlays cut to their field), the ultra overlay,
// the bar, the avatar and its frame on top. A sketch for editing: the bot's own picture is a button away (preview).
// The layout is the bot's JSON (Services/Profiles/ProfileLayout.cs), the data the
// answer of api/Profile/discord/<id> (data). Shared by editor.js and nothing else.
window.Layers = (function () {
  'use strict';

  var W = 750;
  var H = 500;
  var C = { bg: '#131217', panel: '#1B1A21', line: '#34303F', track: '#2A2733', text: '#F2F0F7', dim: '#C4BFCE', gold: '#F5C445' };
  var STATUS = ['#A77BF3', '#6FD47E', '#8A8A8F', '#F5C445', '#F0525C', '#4FC3E8'];
  var ANIME = ['Oglądam', 'Obejrzane', 'Pomijam', 'Wstrzymane', 'Porzucone', 'Planuję'];
  var MANGA = ['Czytam', 'Przeczytane', 'Pomijam', 'Wstrzymane', 'Porzucone', 'Planuję'];
  var RARITIES = ['SSS', 'SS', 'S', 'A', 'B', 'C', 'D', 'E'];
  var RARITY = {
    SSS: [null, '#131217'], SS: ['#F06292', '#131217'], S: ['#F5D547', '#131217'], A: ['#F0884A', '#131217'],
    B: ['#9A5BE0', '#FFFFFF'], C: ['#2B83C9', '#FFFFFF'], D: ['#3F7E26', '#FFFFFF'], E: ['#9A9A9A', '#131217']
  };
  var CURRENCY = { sc: '#E8B83A', tc: '#A77BF3', ac: '#6FD47E', ct: '#F0525C', pc: '#4FC3E8' };
  var KARMA = { angel: ['anioł', '#F5C445'], demon: ['demon', '#F0525C'], neutral: ['neutralna', '#8A8A8F'] };
  // where the bot puts each border for the avatar box at 52, 68 of size 84
  var FRAME_AT = {
    Bow: [26, 48], Dzedai: [23, 38], Water: [35, 58], Base: [43, 59], Crows: [25, 42], Metal: [49, 65], RedThinLeaves: [30, 44],
    Skull: [28, 47], Fire: [45, 60], Promium: [38, 53], Ice: [44, 61], Gold: [18, 33], Red: [40, 58], Rainbow: [44, 60], Pink: [38, 53], Simple: [36, 53]
  };

  var HEADER = { x: 0, y: 0, w: 750, h: 160 };
  var STYLE = { x: 0, y: 160, w: 750, h: 340 };
  var OVERLAY = { x: 0, y: 98, w: 750, h: 402 };
  var SIZE = { list: 331, small: 130, large: 276, cards: 370, panel: 276, big: [718, 304], rank: 22, nick: 34 };
  var CARD_RATIO = 475 / 667;
  var MIN_INSIDE = 16;

  var measurer = document.createElement('canvas').getContext('2d');
  var blurSupported = 'filter' in measurer;

  function font(size, bold) { return (bold ? '700 ' : '400 ') + size + 'px Lato, sans-serif'; }

  function width(text, size, bold) {
    measurer.font = font(size, bold);
    return measurer.measureText(text || '').width;
  }

  function text(c, value, size, bold, color, x, y, align) {
    if (!value)
      return 0;
    c.font = font(size, bold);
    c.fillStyle = color;
    c.textAlign = align || 'left';
    c.textBaseline = 'middle';
    c.fillText(value, x, y);
    return c.measureText(value).width;
  }

  function fit(value, size, bold, max) {
    while (size > 1 && width(value, size, bold) > max)
      size--;
    return size;
  }

  function trim(value, size, bold, max) {
    value = value || '';
    if (width(value, size, bold) <= max)
      return value;
    var n = value.length;
    while (n > 1 && width(value.slice(0, n).trim() + '…', size, bold) > max)
      n--;
    return value.slice(0, n).trim() + '…';
  }

  function group(n) { return String(Math.max(0, Math.round(n || 0))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }

  function short(n) {
    n = n || 0;
    if (n >= 1e6) return (Math.floor(n / 1e5) / 10).toString().replace('.', ',') + 'M';
    if (n >= 1e4) return (Math.floor(n / 100) / 10).toString().replace('.', ',') + 'K';
    return group(n);
  }

  function rr(c, x, y, w, h, r) {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    c.beginPath();
    c.moveTo(x + r, y);
    c.arcTo(x + w, y, x + w, y + h, r);
    c.arcTo(x + w, y + h, x, y + h, r);
    c.arcTo(x, y + h, x, y, r);
    c.arcTo(x, y, x + w, y, r);
    c.closePath();
  }

  function size(img) { return [img.naturalWidth || img.width, img.naturalHeight || img.height]; }

  function alpha(hex, a) {
    var n = parseInt(hex.slice(1), 16);
    return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
  }

  // the frame of the moment of an animated picture (editor.js plays it), else the picture
  function still(img) { return img && img.frame ? img.frame : img; }

  // ---- boxes, as ProfileCatalog.Size() and ProfileRules.Box() ----

  function stickerWidth(h) { return Math.round(CARD_RATIO * h); }

  function rankWidth(rank) { return rank ? width(trim(rank, 13, true, 300), 13, true) + 22 : 0; }

  function box(layer, part, assets, data) {
    switch (part) {
      case 'avatar': return { x: layer.x, y: layer.y, w: layer.size, h: layer.size };
      case 'nick': return { x: layer.x, y: layer.y, w: layer.w, h: SIZE.nick };
      case 'header': return HEADER;
      case 'style': return STYLE;
    }
    var w = 0, h = 0;
    switch (layer.t) {
      case 'anime': case 'manga': w = SIZE.list; h = layer.size === 'large' ? SIZE.large : SIZE.small; break;
      case 'cards': w = SIZE.cards; h = SIZE.panel; break;
      case 'gallery': w = SIZE.list; h = SIZE.panel; break;
      case 'bigGallery': w = SIZE.big[0]; h = SIZE.big[1]; break;
      case 'sticker': w = stickerWidth(layer.h); h = layer.h; break;
      case 'rank': w = rankWidth(data && data.rank) || 120; h = SIZE.rank; break;
      case 'overlay': case 'ultra': {
        var p = assets && assets.picture(layer.image);
        if (p) { var s = size(p); w = Math.round(s[0] * (layer.scale || 1)); h = Math.round(s[1] * (layer.scale || 1)); }
        break;
      }
    }
    var x = layer.t === 'rank' && layer.align === 'center' ? layer.x - w / 2 : layer.x;
    return { x: x, y: layer.y, w: w, h: h };
  }

  // ---- parts ----

  function ground(c, layout, x, y, w, h) {
    c.save();
    if (layout.panels === 'glass') {
      // the blur of what lies under the panel
      var under = document.createElement('canvas');
      under.width = w;
      under.height = h;
      var u = under.getContext('2d');
      if (blurSupported) {
        u.filter = 'blur(10px)';
        u.drawImage(c.canvas, x - 0, y - 0, w, h, 0, 0, w, h);
      } else {
        var small = document.createElement('canvas');
        small.width = Math.max(1, Math.round(w / 10));
        small.height = Math.max(1, Math.round(h / 10));
        small.getContext('2d').drawImage(c.canvas, x, y, w, h, 0, 0, small.width, small.height);
        u.imageSmoothingQuality = 'high';
        u.drawImage(small, 0, 0, w, h);
      }
      rr(c, x, y, w, h, 12);
      c.clip();
      c.drawImage(under, x, y);
      c.fillStyle = 'rgba(32,30,40,0.55)';
      c.fillRect(x, y, w, h);
      c.restore();
      c.save();
      rr(c, x + 0.5, y + 0.5, w - 1, h - 1, 12);
      c.strokeStyle = 'rgba(255,255,255,0.16)';
      c.lineWidth = 1;
      c.stroke();
    } else if (layout.panels === 'shadow') {
      rr(c, x, y, w, h, 12);
      c.fillStyle = 'rgba(0,0,0,' + Math.max(0, Math.min(100, layout.shadow == null ? 70 : layout.shadow)) / 100 + ')';
      c.fill();
    } else {
      rr(c, x, y, w, h, 12);
      c.fillStyle = C.panel;
      c.fill();
      c.strokeStyle = C.line;
      c.lineWidth = 1.5;
      c.stroke();
    }
    c.restore();
  }

  function stroke(c, color, lineWidth, draw) {
    c.save();
    c.strokeStyle = color;
    c.lineWidth = lineWidth;
    c.lineJoin = 'round';
    draw();
    c.restore();
  }

  function clapper(c, cx, cy, col) {
    stroke(c, col, 1.6, function () {
      rr(c, cx - 9, cy - 2, 18, 10, 2); c.stroke();
      c.strokeRect(cx - 9, cy - 7, 18, 4);
      [[-6, -2, -3, -7], [-1, -2, 2, -7], [4, -2, 7, -7]].forEach(function (l) { c.beginPath(); c.moveTo(cx + l[0], cy + l[1]); c.lineTo(cx + l[2], cy + l[3]); c.stroke(); });
    });
  }

  function book(c, cx, cy, col) {
    stroke(c, col, 1.6, function () {
      c.beginPath(); c.moveTo(cx - 1, cy - 5); c.lineTo(cx - 9, cy - 7); c.lineTo(cx - 9, cy + 5); c.lineTo(cx - 1, cy + 7); c.closePath(); c.stroke();
      c.beginPath(); c.moveTo(cx + 1, cy - 5); c.lineTo(cx + 9, cy - 7); c.lineTo(cx + 9, cy + 5); c.lineTo(cx + 1, cy + 7); c.closePath(); c.stroke();
    });
  }

  function cardsIcon(c, cx, cy, col) {
    stroke(c, col, 1.6, function () {
      c.translate(cx, cy); c.rotate(-0.2); rr(c, -9, -8, 11, 15, 2); c.stroke(); c.rotate(0.4); rr(c, -2, -8, 11, 15, 2); c.stroke();
    });
  }

  function clock(c, cx, cy, col) {
    stroke(c, col, 1.5, function () {
      c.beginPath(); c.arc(cx, cy, 6.5, 0, Math.PI * 2); c.stroke();
      c.beginPath(); c.moveTo(cx, cy - 3.5); c.lineTo(cx, cy); c.lineTo(cx + 2.5, cy + 2); c.stroke();
    });
  }

  function crown(c, cx, cy) {
    c.save();
    c.fillStyle = C.gold;
    c.beginPath();
    [[-7, 5], [-7, -4], [-3, 0], [0, -6], [3, 0], [7, -4], [7, 5]].forEach(function (p, i) { c[i ? 'lineTo' : 'moveTo'](cx + p[0], cy + p[1]); });
    c.closePath();
    c.fill();
    c.restore();
  }

  function envelope(c, x, cy) {
    stroke(c, C.dim, 1.5, function () {
      rr(c, x, cy - 6, 18, 12, 2); c.stroke();
      c.beginPath(); c.moveTo(x + 1, cy - 5); c.lineTo(x + 9, cy + 1); c.lineTo(x + 17, cy - 5); c.stroke();
    });
  }

  function dot(c, x, y, color) {
    c.fillStyle = color;
    c.beginPath();
    c.arc(x, y, 4, 0, Math.PI * 2);
    c.fill();
  }

  function segments(c, values, x, y, w, h) {
    var total = values.reduce(function (a, b) { return a + b; }, 0);
    c.save();
    rr(c, x, y, w, h, h / 2);
    c.clip();
    c.fillStyle = C.track;
    c.fillRect(x, y, w, h);
    if (total > 0) {
      var used = values.filter(function (v) { return v > 0; }).length;
      var available = w - 2 * (used - 1);
      var lengths = values.map(function (v) { return v ? Math.max(3, Math.round(available * v / total)) : 0; });
      var longest = lengths.indexOf(Math.max.apply(null, lengths));
      lengths[longest] += available - lengths.reduce(function (a, b) { return a + b; }, 0);
      var offset = x;
      lengths.forEach(function (l, i) {
        if (!l) return;
        c.fillStyle = STATUS[i];
        c.fillRect(offset, y, l, h);
        offset += l + 2;
      });
    }
    c.restore();
  }

  function shows(layer, part) { return !layer.hide || layer.hide.indexOf(part) < 0; }

  function legend(c, stats, labels, x, y, w, all) {
    if (all) {
      stats.statuses.forEach(function (v, i) {
        var lx = x + 14 + i * ((w - 28) / 6);
        dot(c, lx + 4, y, STATUS[i]);
        text(c, group(v), 13, true, C.text, lx + 12, y);
      });
      return;
    }
    [1, 0, 5].forEach(function (s, i) {
      var lx = x + 14 + (w - 28) / 3 * i;
      dot(c, lx + 4, y, STATUS[s]);
      var tw = text(c, labels[s], 12, false, C.dim, lx + 12, y);
      text(c, group(stats.statuses[s]), 13, true, C.text, lx + 16 + tw, y);
    });
  }

  function listPanel(c, layout, layer, stats, accent, assets) {
    var manga = layer.t === 'manga';
    var labels = manga ? MANGA : ANIME;
    var large = layer.size === 'large';
    var w = SIZE.list, h = large ? SIZE.large : SIZE.small, x = layer.x, y = layer.y;
    ground(c, layout, x, y, w, h);
    (manga ? book : clapper)(c, x + 22, y + 19, accent);
    text(c, manga ? 'Manga' : 'Anime', 16, true, C.text, x + 38, y + 19);

    var meanInRow = large && shows(layer, 'numbers');
    if (shows(layer, 'mean') && !meanInRow) {
      var mw = text(c, stats.mean, 15, true, C.text, x + w - 14, y + 19, 'right');
      text(c, 'średnia', 12, false, C.dim, x + w - 20 - mw, y + 20, 'right');
    }

    var cy = y + 38;
    if (shows(layer, 'numbers')) {
      var columns = [['Tytuły', stats.total], [manga ? 'Rozdziały' : 'Odcinki', stats.units], ['Czas', stats.time]];
      if (meanInRow && shows(layer, 'mean'))
        columns.push(['Średnia', stats.mean]);
      var cw = (w - 28) / columns.length;
      columns.forEach(function (col, i) {
        var center = x + 14 + cw * (i + 0.5);
        text(c, col[1], fit(col[1], columns.length > 3 ? 18 : 20, true, cw - 8), true, C.text, center, cy + 14, 'center');
        text(c, col[0], 12, false, C.dim, center, cy + 33, 'center');
        if (i > 0) {
          c.fillStyle = C.line;
          c.fillRect(Math.floor(x + 14 + cw * i), cy + 4, 1.5, 38);
        }
      });
      cy += 49;
    }

    if (shows(layer, 'bar')) {
      segments(c, stats.statuses, x + 14, cy, w - 28, 8);
      cy += 16;
    }

    var recent = (stats.recent || []).slice(0, large ? layer.recent || 0 : Math.min(1, layer.recent || 0));
    if (!large) {
      if (recent.length) {
        var r = recent[0];
        var pw = width(r.progress, 12, false);
        clock(c, x + 20, cy + 8, accent);
        text(c, trim(r.title, 13, true, w - 58 - pw), 13, true, C.text, x + 32, cy + 8);
        text(c, r.progress, 12, false, C.dim, x + w - 14, cy + 8, 'right');
      } else if (shows(layer, 'legend')) {
        legend(c, stats, labels, x, cy + 8, w, layer.legend === 'all');
      }
      return;
    }

    if (shows(layer, 'legend')) {
      if (layer.legend === 'all') {
        labels.forEach(function (label, i) {
          var lx = x + 14 + (i % 3) * ((w - 28) / 3);
          var ly = cy + 8 + Math.floor(i / 3) * 20;
          dot(c, lx + 4, ly, STATUS[i]);
          var tw = text(c, label, 12, false, C.dim, lx + 12, ly);
          text(c, group(stats.statuses[i]), 12, true, C.text, lx + 15 + tw, ly);
        });
        cy += 44;
      } else {
        legend(c, stats, labels, x, cy + 8, w, false);
        cy += 22;
      }
    }

    if (!recent.length)
      return;
    c.fillStyle = C.line;
    c.fillRect(x + 14, cy + 1, w - 28, 1.5);
    clock(c, x + 21, cy + 16, accent);
    text(c, manga ? 'Ostatnio czytane' : 'Ostatnio oglądane', 13, true, C.text, x + 33, cy + 16);
    var rowY = cy + 29;
    var fits = Math.max(0, Math.floor((y + h - 6 - rowY) / 30));
    recent.slice(0, fits).forEach(function (t, i) {
      var ry = rowY + i * 30;
      var cover = assets.cover(t.cover);
      c.save();
      rr(c, x + 14, ry, 26, 26, 5);
      c.clip();
      if (cover) {
        var s = size(cover), side = Math.min(s[0], s[1]);
        c.drawImage(cover, (s[0] - side) / 2, (s[1] - side) / 2, side, side, x + 14, ry, 26, 26);
      } else {
        c.fillStyle = C.track;
        c.fillRect(x + 14, ry, 26, 26);
      }
      c.restore();
      text(c, trim(t.title, 13, true, w - 128), 13, true, C.text, x + 48, ry + 8);
      text(c, t.progress, 11, false, C.dim, x + 48, ry + 21);
      if (t.ratio != null) {
        var ratio = Math.max(0, Math.min(1, t.ratio));
        c.save();
        rr(c, x + w - 74, ry + 18, 60, 5, 2.5); c.fillStyle = C.track; c.fill();
        rr(c, x + w - 74, ry + 18, Math.max(5, 60 * ratio), 5, 2.5); c.fillStyle = ratio >= 1 ? STATUS[1] : STATUS[0]; c.fill();
        c.restore();
      }
    });
  }

  function chip(c, rarity, x, y, opacity) {
    var w = 34, h = 18;
    c.save();
    c.globalAlpha = opacity;
    rr(c, x, y, w, h, 5);
    if (rarity === 'SSS') {
      var g = c.createLinearGradient(x, y, x + w, y);
      ['#F0525C', '#F5C445', '#6FD47E', '#4FC3E8', '#A77BF3'].forEach(function (col, i) { g.addColorStop(i / 4, col); });
      c.fillStyle = g;
    } else {
      c.fillStyle = RARITY[rarity][0];
    }
    c.fill();
    text(c, rarity, 11, true, RARITY[rarity][1], x + w / 2, y + h / 2 + 0.5, 'center');
    c.restore();
  }

  function card(c, img, x, y, height) {
    img = still(img);
    if (!img)
      return;
    var s = size(img);
    c.drawImage(img, x, y, Math.round(s[0] * height / s[1]), height);
  }

  function cardsPanel(c, layout, layer, data, accent, assets) {
    var w = SIZE.cards, x = layer.x, y = layer.y;
    ground(c, layout, x, y, w, SIZE.panel);
    cardsIcon(c, x + 22, y + 19, accent);
    text(c, 'Karcianka', 16, true, C.text, x + 38, y + 19);

    var karma = KARMA[data.karma];
    if (shows(layer, 'karma') && karma) {
      var label = 'Karma: ' + karma[0];
      var kw = width(label, 12, true) + 18;
      c.save();
      rr(c, x + w - 14 - kw, y + 9, kw, 20, 10);
      c.fillStyle = alpha(karma[1], 0.18); c.fill();
      c.strokeStyle = karma[1]; c.lineWidth = 1.2; c.stroke();
      c.restore();
      text(c, label, 12, true, C.text, x + w - 14 - kw / 2, y + 19.5, 'center');
    }

    var rx = x + 14;
    if (shows(layer, 'waifu')) {
      if (data.waifu)
        card(c, assets.card(data.waifu.id), x + 12, y + 40, 226);
      rx = x + 12 + stickerWidth(226) + 14;
    }
    var rw = x + w - 14 - rx;
    var cy = y + 40;
    if (shows(layer, 'count')) {
      text(c, 'Karty', 12, false, C.dim, rx, cy + 7);
      var vw = text(c, group(data.cards), 20, true, C.text, rx, cy + 27);
      text(c, ' / ' + group(data.limit), 14, false, C.dim, rx + vw, cy + 28);
      var ratio = data.limit > 0 ? Math.max(0, Math.min(1, data.cards / data.limit)) : 0;
      c.save();
      rr(c, rx, cy + 41, rw, 6, 3); c.fillStyle = C.track; c.fill();
      rr(c, rx, cy + 41, Math.max(6, rw * ratio), 6, 3); c.fillStyle = accent; c.fill();
      c.restore();
      cy += 60;
    }
    if (shows(layer, 'rarity')) {
      var columns = shows(layer, 'waifu') ? 2 : 4;
      RARITIES.forEach(function (r, i) {
        var gx = rx + (i % columns) * (rw / columns), gy = cy + Math.floor(i / columns) * 25;
        var n = (data.rarities || {})[r] || 0;
        chip(c, r, gx, gy, n ? 1 : 0.35);
        text(c, group(n), 14, true, n ? C.text : C.dim, gx + 42, gy + 9.5);
      });
      cy += Math.ceil(8 / columns) * 25 + 4;
    }
    var extras = [['ult', 'Ultimate', data.ultimates], ['scal', 'Własne obrazki', data.customImages], ['scis', 'Własne ramki', data.customBorders]]
      .filter(function (e) { return shows(layer, e[0]); });
    if (!extras.length)
      return;
    if (cy > y + 40) {
      c.fillStyle = C.line;
      c.fillRect(rx, cy, rw, 1.5);
      cy += 17;
    } else {
      cy += 7;
    }
    extras.forEach(function (e, i) {
      text(c, e[1], 12, false, C.dim, rx, cy + i * 20);
      text(c, group(e[2]), 13, true, e[2] ? C.text : C.dim, rx + rw, cy + i * 20, 'right');
    });
  }

  function gallery(c, layout, layer, data, assets) {
    var w = SIZE.list, h = SIZE.panel, x = layer.x, y = layer.y;
    if (layer.panel !== false)
      ground(c, layout, x, y, w, h);
    var two = layer.count === 2;
    var cards = (data.gallery || []).slice(0, two ? 2 : 6);
    var ch = two ? 214 : 124, cw = stickerWidth(ch);
    cards.forEach(function (g, i) {
      var at;
      if (two) {
        var left = x + Math.floor((w - 2 * cw - 8) / 2);
        at = [left + i * (cw + 8), y + Math.floor((h - ch) / 2)];
      } else {
        var gap = Math.floor((w - 24 - 3 * cw) / 2);
        at = [x + 12 + (i % 3) * (cw + gap), y + 12 + Math.floor(i / 3) * (ch + 4)];
      }
      if (g)
        card(c, assets.card(g.id), at[0], at[1], ch);
    });
  }

  function bigGallery(c, layout, layer, data, assets) {
    var x = layer.x, y = layer.y;
    if (layer.panel !== false)
      ground(c, layout, x, y, SIZE.big[0], SIZE.big[1]);
    var ch = 140, cw = stickerWidth(ch), gap = Math.floor((SIZE.big[0] - 24 - 6 * cw) / 5);
    (data.gallery || []).slice(0, 12).forEach(function (g, i) {
      if (g)
        card(c, assets.card(g.id), x + 12 + (i % 6) * (cw + gap), y + 12 + Math.floor(i / 6) * (ch + 4), ch);
    });
  }

  // an overlay is cut to its field, the ultra overlay is not
  function picture(c, layer, assets, field) {
    var img = still(assets.picture(layer.image));
    if (!img)
      return;
    var s = size(img), scale = layer.scale || 1;
    c.save();
    if (field) {
      c.beginPath();
      c.rect(field.x, field.y, field.w, field.h);
      c.clip();
    }
    c.globalAlpha = layer.opacity == null ? 1 : Math.max(0, Math.min(1, layer.opacity));
    c.imageSmoothingQuality = 'high';
    c.drawImage(img, layer.x, layer.y, Math.max(1, Math.round(s[0] * scale)), Math.max(1, Math.round(s[1] * scale)));
    c.restore();
  }

  function sticker(c, layer, assets) {
    var img = still(assets.card(layer.card));
    if (!img)
      return;
    var s = size(img), h = layer.h, w = Math.round(s[0] * h / s[1]);
    c.save();
    c.translate(layer.x + w / 2, layer.y + h / 2);
    c.rotate((layer.tilt || 0) * Math.PI / 180);
    c.translate(-w / 2, -h / 2);
    if (layer.bg !== false) {
      rr(c, -4, -4, w + 8, h + 8, 10);
      c.fillStyle = 'rgba(19,18,23,0.45)'; c.fill();
      c.strokeStyle = 'rgba(255,255,255,0.16)'; c.lineWidth = 1; c.stroke();
    } else {
      c.shadowColor = 'rgba(0,0,0,0.5)';
      c.shadowBlur = 12;
      c.shadowOffsetY = 3;
    }
    c.drawImage(img, 0, 0, w, h);
    c.restore();
  }

  function rank(c, layer, value, accent) {
    if (!value || !value.trim())
      return;
    value = trim(value.trim(), 13, true, 300);
    var w = width(value, 13, true) + 22;
    var x = layer.align === 'center' ? layer.x - w / 2 : layer.x;
    c.save();
    rr(c, x, layer.y, w, SIZE.rank, 11);
    c.fillStyle = 'rgba(19,18,23,0.72)'; c.fill();
    c.strokeStyle = accent; c.lineWidth = 1.5; c.stroke();
    c.restore();
    text(c, value, 13, true, C.text, x + w / 2, layer.y + 11.5, 'center');
  }

  function nick(c, layer, value) {
    if (!value)
      return;
    var sizePx = fit(value, 28, true, layer.w);
    var center = layer.align === 'center';
    c.save();
    c.shadowColor = 'rgba(0,0,0,0.75)';
    c.shadowBlur = 6;
    c.shadowOffsetY = 1;
    text(c, value, sizePx, true, C.text, center ? layer.x + layer.w / 2 : layer.x, layer.y + SIZE.nick / 2, center ? 'center' : 'left');
    c.restore();
  }

  function bar(c, layout, data, accent) {
    var b = layout.bar || {};
    if (b.at === 'hidden')
      return;
    var hide = b.hide || [];
    function on(key) { return hide.indexOf(key) < 0; }
    var currencies = [['msgs', data.messages], ['sc', data.sc], ['tc', data.tc], ['ac', data.ac], ['ct', data.ct], ['pc', data.pc]].filter(function (x) { return on(x[0]); });
    if (!on('top') && !on('level') && !on('exp') && !currencies.length)
      return;

    var y0 = b.at === 'top' ? 4 : 468, cy = y0 + 13;
    c.save();
    rr(c, 12, y0, 726, 26, 13);
    c.fillStyle = 'rgba(19,18,23,' + Math.max(20, Math.min(100, b.opacity == null ? 85 : b.opacity)) / 100 + ')';
    c.fill();
    c.strokeStyle = 'rgba(255,255,255,0.08)';
    c.lineWidth = 1;
    c.stroke();
    c.restore();

    var top = '#' + (data.top > 9999 ? '>10K' : group(data.top));
    var progress = Math.max(0, Math.min(1, data.levelProgress || 0));
    var percent = Math.floor(progress * 100) + '%';
    var widths = currencies.map(function (it) { return (it[0] === 'msgs' ? 18 : 24) + 5 + width(short(it[1]), 15, true); });
    var sum = widths.reduce(function (a, b2) { return a + b2; }, 0);

    // large numbers: the currencies close up first, then the experience bar gets shorter
    var left = 24 + 12
      + (on('top') ? 18 + width(top, 15, true) + 14 : 0)
      + (on('level') ? width('Poz.', 12, false) + 5 + width(short(data.level), 15, true) + 8 : 0)
      + (on('exp') ? 6 + width(percent, 12, false) : 0);
    var gap = 14, track = on('exp') ? 90 : 0, gaps = Math.max(0, currencies.length - 1);
    var overflow = left + track + sum + gap * gaps - 726;
    if (overflow > 0 && gaps > 0) {
      var less = Math.min(overflow, (gap - 8) * gaps);
      gap -= less / gaps;
      overflow -= less;
    }
    if (overflow > 0 && track > 0)
      track = Math.max(36, track - overflow);

    var x = 24;
    if (on('top')) {
      crown(c, x + 7, cy);
      x += 18;
      x += text(c, top, 15, true, C.text, x, cy) + 14;
    }
    if (on('level')) {
      x += text(c, 'Poz.', 12, false, C.dim, x, cy) + 5;
      x += text(c, short(data.level), 15, true, C.text, x, cy) + 8;
    }
    if (on('exp')) {
      c.save();
      rr(c, x, cy - 4, track, 8, 4); c.fillStyle = C.track; c.fill();
      rr(c, x, cy - 4, Math.max(8, track * progress), 8, 4); c.fillStyle = accent; c.fill();
      c.restore();
      x += track + 6;
      text(c, percent, 12, false, C.dim, x, cy);
    }
    var ix = 726 - sum - gap * gaps;
    currencies.forEach(function (it, i) {
      if (it[0] === 'msgs') {
        envelope(c, ix, cy);
      } else {
        c.save();
        rr(c, ix, cy - 8, 24, 16, 4);
        c.fillStyle = CURRENCY[it[0]];
        c.fill();
        c.restore();
        text(c, it[0].toUpperCase(), 10, true, '#131217', ix + 12, cy + 0.5, 'center');
      }
      text(c, short(it[1]), 15, true, C.text, ix + (it[0] === 'msgs' ? 18 : 24) + 5, cy);
      ix += widths[i] + gap;
    });
  }

  function avatar(c, layer, data, accent, assets) {
    var s = layer.size, framed = layer.frame && layer.frame !== 'None';
    var shape = framed ? 'circle' : layer.shape;
    var r = shape === 'circle' ? s / 2 : shape === 'rounded' ? s * 0.21 : 5;
    var inset = Math.round(3 * s / 84);
    c.save();
    c.shadowColor = 'rgba(0,0,0,0.45)';
    c.shadowBlur = 10;
    rr(c, layer.x, layer.y, s, s, r);
    c.fillStyle = accent;
    c.fill();
    c.restore();

    var img = still(assets.avatar);
    if (img) {
      var inner = s - 2 * inset, is = size(img), side = Math.min(is[0], is[1]);
      c.save();
      rr(c, layer.x + inset, layer.y + inset, inner, inner, Math.max(2, r - inset));
      c.clip();
      c.drawImage(img, (is[0] - side) / 2, (is[1] - side) / 2, side, side, layer.x + inset, layer.y + inset, inner, inner);
      c.restore();
    }

    var k = s / 84;
    if (layer.frame === 'custom') {
      var custom = still(assets.picture(layer.customImage));
      if (!custom)
        return;
      var cs = size(custom), fw = Math.round(s * 1.45 * (layer.customScale || 1)), fh = Math.round(fw * cs[1] / cs[0]);
      c.drawImage(custom, Math.round(layer.x + s / 2 - fw / 2 + (layer.customX || 0) * k), Math.round(layer.y + s / 2 - fh / 2 + (layer.customY || 0) * k), fw, fh);
      return;
    }
    if (!framed)
      return;
    var frame = assets.frame(layer.frame, layer.levelFrame !== false ? data.level : 0);
    if (!frame)
      return;
    var at = FRAME_AT[layer.frame] || [24, 39], fs = size(frame);
    c.drawImage(frame, Math.round(layer.x + (at[0] - 52) * k), Math.round(layer.y + (at[1] - 68) * k), Math.round(fs[0] * k), Math.round(fs[1] * k));
  }

  function slot(c, value, field, assets) {
    var img = still(assets.picture(value.image));
    if (!img)
      return;
    var s = size(img);
    var scale = Math.max(value.scale || 1, field.w / s[0], field.h / s[1]);
    c.save();
    c.beginPath();
    c.rect(field.x, field.y, field.w, field.h);
    c.clip();
    c.imageSmoothingQuality = 'high';
    c.drawImage(img, value.x, value.y, s[0] * scale, s[1] * scale);
    c.restore();
  }

  // as the old profile's template: 75% black from 98 px down, fading out to the right, a darker last row
  function band(c) {
    [[98, 61, 0.75], [159, 1, 1]].forEach(function (r) {
      var g = c.createLinearGradient(510, 0, 726, 0);
      g.addColorStop(0, 'rgba(0,0,0,' + r[2] + ')');
      g.addColorStop(1, 'rgba(0,0,0,0)');
      c.fillStyle = g;
      c.fillRect(0, r[0], 750, r[1]);
    });
  }

  function fade(c) {
    var g = c.createLinearGradient(0, 56, 0, 160);
    g.addColorStop(0, 'rgba(19,18,23,0)');
    g.addColorStop(1, 'rgba(19,18,23,0.92)');
    c.fillStyle = g;
    c.fillRect(0, 56, 750, 104);
  }

  // the whole profile on a 750 x 500 canvas
  function render(target, layout, data, assets) {
    var c = target.getContext('2d');
    c.setTransform(1, 0, 0, 1, 0, 0);
    c.clearRect(0, 0, W, H);
    c.fillStyle = C.bg;
    c.fillRect(0, 0, W, H);
    var accent = layout.accent || data.color || '#546E7A';

    if (!layout.header) {
      if (assets.defaultHeader)
        c.drawImage(assets.defaultHeader, 0, 0, 750, 160);
    } else if (!layout.header.hidden) {
      slot(c, layout.header, HEADER, assets);
    }
    var shade = layout.shade || 'fade';
    if (shade === 'fade')
      fade(c);
    else if (shade === 'band')
      band(c);

    if (layout.style && !layout.style.hidden) {
      slot(c, layout.style, STYLE, assets);
    } else {
      c.fillStyle = C.line;
      c.fillRect(0, 159, 750, 1);
    }

    if (!layout.nick.hidden)
      nick(c, layout.nick, data.nick);

    (layout.layers || []).forEach(function (layer) {
      if (layer.hidden)
        return;
      switch (layer.t) {
        case 'anime': if (data.anime) listPanel(c, layout, layer, data.anime, accent, assets); break;
        case 'manga': if (data.manga) listPanel(c, layout, layer, data.manga, accent, assets); break;
        case 'cards': cardsPanel(c, layout, layer, data, accent, assets); break;
        case 'gallery': gallery(c, layout, layer, data, assets); break;
        case 'bigGallery': bigGallery(c, layout, layer, data, assets); break;
        case 'overlay': picture(c, layer, assets, OVERLAY); break;
        case 'sticker': sticker(c, layer, assets); break;
        case 'rank': rank(c, layer, data.rank, accent); break;
      }
    });

    if (layout.ultra && !layout.ultra.hidden)
      picture(c, layout.ultra, assets);
    bar(c, layout, data, accent);
    avatar(c, layout.avatar, data, accent, assets);
  }

  return {
    W: W,
    H: H,
    HEADER: HEADER,
    STYLE: STYLE,
    OVERLAY: OVERLAY,
    MIN_INSIDE: MIN_INSIDE,
    AVATAR_SIZES: [84, 112, 140],
    FRAME_AT: FRAME_AT,
    stickerWidth: stickerWidth,
    box: box,
    render: render,
    width: width
  };
})();
