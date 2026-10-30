// The bot's profile drawn in the browser, step by step as the bot draws it
// (GetUserProfileAsync() and what it calls in the bot's ImageProcessing.cs):
// the same pictures (mirrored at /pw/np/, inc/pw.php), positions, sizes,
// colours and fonts. With it the profile code of `konfiguracja profilu kod`
// (the bot's ProfileCode.cs): six bytes of the layout in base64url, and in
// version 2 the links to the images after them, read and written the same
// way. Shared by profilator.js and nothing else; no page work here.
window.Profile = (function () {
  'use strict';

  var W = 750;
  var H = 500;

  var STYLES = ['Stats', 'Img', 'StatsWithImg', 'Cards', 'CardsOnImg', 'StatsOnImg', 'MiniGallery', 'MiniGalleryOnImg'];
  var BORDERS = ['None', 'PurpleLeaves', 'Dzedai', 'Base', 'Water', 'Crows', 'Bow', 'Metal', 'RedThinLeaves', 'Skull',
    'Fire', 'Promium', 'Ice', 'Gold', 'Red', 'Rainbow', 'Pink', 'Simple', 'TurqLeaves'];
  var FLAG = {
    ShowAnime: 1, ShowManga: 2, ShowCards: 4, Flip: 8, HalfGallery: 16, ShowGallery: 32, ShowWaifu: 64,
    BarOnTop: 128, BorderColor: 256, RoundAvatar: 512, BarOpacity: 1024, ShowOverlay: 2048, ShowOverlayPro: 4096
  };
  var ALL_FLAGS = 8191;
  var DEFAULT_FLAGS = FLAG.ShowAnime | FLAG.ShowManga | FLAG.ShowCards | FLAG.ShowGallery
    | FLAG.ShowOverlay | FLAG.ShowOverlayPro | FLAG.BorderColor;
  var RARITIES = ['SSS', 'SS', 'S', 'A', 'B', 'C', 'D', 'E'];

  // where the bot puts each avatar border (GetProfileAvatarBorder())
  var BORDER_AT = {
    Bow: [26, 48], Dzedai: [23, 38], Water: [35, 58], Base: [43, 59], Crows: [25, 42], Metal: [49, 65],
    RedThinLeaves: [30, 44], Skull: [28, 47], Fire: [45, 60], Promium: [38, 53], Ice: [44, 61], Gold: [18, 33],
    Red: [40, 58], Rainbow: [44, 60], Pink: [38, 53], Simple: [36, 53]
  };

  // where SixLabors.Fonts puts the baseline of Lato under the top of the text:
  // its typographic ascender and half of the line gap, (1610 + 400 / 2) / 2000
  var ASCENT = 1810 / 2000;

  function has(flags, flag) {
    return (flags & flag) === flag;
  }

  function styleNeedsImage(style) {
    return style !== 'Stats' && style !== 'Cards' && style !== 'MiniGallery';
  }

  // the styles whose shadows and panels can be set (IsConfigurableStyle())
  function configurableStyle(style) {
    return style !== 'Img' && style !== 'Cards';
  }

  // whether a switch does anything on a style (CanUseSettingOnStyle())
  function settingWorks(flag, style) {
    var gallery = style === 'MiniGallery' || style === 'MiniGalleryOnImg';
    switch (flag) {
      case FLAG.ShowWaifu: case FLAG.BarOnTop: case FLAG.BorderColor: case FLAG.RoundAvatar:
      case FLAG.BarOpacity: case FLAG.ShowOverlay: case FLAG.ShowOverlayPro:
        return true;
      case FLAG.HalfGallery: case FLAG.ShowGallery:
        return gallery;
      case FLAG.ShowAnime: case FLAG.ShowManga:
        return configurableStyle(style) && !gallery;
      default:
        return configurableStyle(style);
    }
  }

  // --- the code ---

  // the images a code can carry, by their number in it (ProfileImage)
  var IMAGES = ['background', 'style', 'overlay', 'premium', 'combined'];
  var MAX_CODE = 2000;
  var MAX_TEXT = 1024;
  var SITE = 'https://sanakan.pl/';
  var DISCORD = /^https:\/\/cdn\.discordapp\.com\/attachments\/(\d{1,20})\/(\d{1,20})\/([^/?#]+)\?ex=([0-9a-f]{8})&is=([0-9a-f]{8})&hm=([0-9a-f]{64})&?$/;
  var MAX_ID = BigInt('18446744073709551615');

  function hasStyleImage(images) {
    return !!(images && (images.style || images.combined));
  }

  function number(out, value, bytes) {
    for (var i = bytes - 1; i >= 0; i--)
      out.push(Number((BigInt(value) >> BigInt(i * 8)) & BigInt(255)));
  }

  // the length as a LEB128 number, then the UTF-8 bytes
  function textBytes(out, text) {
    var bytes = new TextEncoder().encode(text);
    var length = bytes.length;
    do {
      var part = length & 0x7F;
      length >>>= 7;
      out.push(length > 0 ? part | 0x80 : part);
    } while (length > 0);
    bytes.forEach(function (b) { out.push(b); });
  }

  // a Discord attachment as its numbers (about half of its link), one of the
  // site by its path, any other whole
  function imageBytes(out, slot, url) {
    var discord = DISCORD.exec(url);
    if (discord && BigInt(discord[1]) <= MAX_ID && BigInt(discord[2]) <= MAX_ID) {
      out.push((slot << 4) | 1);
      number(out, BigInt(discord[1]), 8);
      number(out, BigInt(discord[2]), 8);
      textBytes(out, discord[3]);
      number(out, parseInt(discord[4], 16), 4);
      number(out, parseInt(discord[5], 16), 4);
      for (var i = 0; i < 64; i += 2)
        out.push(parseInt(discord[6].slice(i, i + 2), 16));
    } else if (url.indexOf(SITE) === 0) {
      out.push((slot << 4) | 2);
      textBytes(out, url.slice(SITE.length));
    } else {
      out.push(slot << 4);
      textBytes(out, url);
    }
  }

  // version 1 without images (8 characters), version 2 with them
  function encode(state) {
    var images = state.images || {};
    var slots = IMAGES.filter(function (name) { return images[name]; });
    var bytes = [slots.length ? 2 : 1, STYLES.indexOf(state.style), BORDERS.indexOf(state.border), state.flags & 0xFF, (state.flags >> 8) & 0xFF, state.shadows];
    slots.forEach(function (name) { imageBytes(bytes, IMAGES.indexOf(name), images[name]); });
    var binary = '';
    bytes.forEach(function (b) { binary += String.fromCharCode(b); });
    return btoa(binary).replace(/=+$/, '').replace(/\+/g, '-').replace(/\//g, '_');
  }

  function reader(bytes, at) {
    function take(count) {
      if (at + count > bytes.length)
        return null;
      var part = bytes.slice(at, at + count);
      at += count;
      return part;
    }
    return {
      end: function () { return at >= bytes.length; },
      byte: function () { return at < bytes.length ? bytes[at++] : null; },
      take: take,
      number: function (count) {
        var part = take(count);
        if (!part)
          return null;
        return part.reduce(function (value, b) { return (value << BigInt(8)) | BigInt(b); }, BigInt(0));
      },
      text: function () {
        var length = 0;
        for (var shift = 0; ; shift += 7) {
          var b = this.byte();
          if (b === null || shift > 14)
            return null;
          length |= (b & 0x7F) << shift;
          if ((b & 0x80) === 0)
            break;
        }
        if (length === 0 || length > MAX_TEXT)
          return null;
        var part = take(length);
        if (!part)
          return null;
        try {
          return new TextDecoder('utf-8', { fatal: true }).decode(new Uint8Array(part));
        } catch (e) {
          return null;
        }
      }
    };
  }

  function hex(value, digits) {
    var text = value.toString(16);
    while (text.length < digits)
      text = '0' + text;
    return text;
  }

  function readUrl(r, type) {
    var url = null;
    if (type === 1) {
      var channel = r.number(8);
      var message = r.number(8);
      var name = r.text();
      var ex = r.number(4);
      var issued = r.number(4);
      var hash = r.take(32);
      if (channel === null || message === null || name === null || ex === null || issued === null || hash === null || /[/?#]/.test(name))
        return null;
      url = 'https://cdn.discordapp.com/attachments/' + channel + '/' + message + '/' + name + '?ex=' + hex(ex, 8)
        + '&is=' + hex(issued, 8) + '&hm=' + hash.map(function (b) { return hex(b, 2); }).join('') + '&';
    } else if (type === 2) {
      var path = r.text();
      url = path === null ? null : SITE + path;
    } else if (type === 0) {
      url = r.text();
    } else {
      return null;
    }
    try {
      var parsed = new URL(url);
      return parsed.protocol === 'https:' || parsed.protocol === 'http:' ? url : null;
    } catch (e) {
      return null;
    }
  }

  // the state of a code, or null for one the bot would refuse
  function decode(code) {
    if (typeof code !== 'string' || !code.trim() || code.length > MAX_CODE)
      return null;
    var base64 = code.trim().replace(/-/g, '+').replace(/_/g, '/');
    var binary;
    try {
      binary = atob(base64 + '===='.slice((base64.length % 4) || 4));
    } catch (e) {
      return null;
    }
    var bytes = [];
    for (var i = 0; i < binary.length; i++)
      bytes.push(binary.charCodeAt(i));
    if (bytes.length < 6 || (bytes[0] === 1 && bytes.length !== 6) || bytes[0] < 1 || bytes[0] > 2)
      return null;
    var flags = bytes[3] | (bytes[4] << 8);
    var state = {
      style: STYLES[bytes[1]],
      border: BORDERS[bytes[2]],
      flags: flags,
      shadows: bytes[5],
      images: {}
    };
    if (!state.style || !state.border || (flags & ~ALL_FLAGS) !== 0 || state.shadows > 100)
      return null;

    var r = reader(bytes, 6);
    var count = 0;
    while (!r.end()) {
      var head = r.byte();
      var slot = IMAGES[head >> 4];
      var url = readUrl(r, head & 0x0F);
      if (url === null || !slot || state.images[slot])
        return null;
      state.images[slot] = url;
      count++;
    }
    if (bytes[0] === 2 && count === 0)
      return null;
    if (state.images.combined && (state.images.background || state.images.style))
      return null;
    return state;
  }

  // --- drawing helpers, each doing what the bot's ImageSharp call does ---

  function canvas(w, h) {
    var c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    return c;
  }

  // the bot's Round(): every corner cut by a circle of the radius, also past
  // half of the side (the avatar), ImageExtension.BuildCorners()
  function round(c, r) {
    var x = c.getContext('2d');
    var w = c.width;
    var h = c.height;
    x.save();
    x.globalCompositeOperation = 'destination-out';
    [[0, 0, 1, 1], [w, 0, -1, 1], [0, h, 1, -1], [w, h, -1, -1]].forEach(function (k) {
      x.save();
      x.translate(k[0], k[1]);
      x.scale(k[2], k[3]);
      x.beginPath();
      x.rect(-0.5, -0.5, r, r);
      x.clip();
      x.beginPath();
      // wider than the clip, so its edges never meet the clip's inside the circle
      x.rect(-r, -r, 3 * r, 3 * r);
      x.moveTo(2 * r - 0.5, r - 0.5);
      x.arc(r - 0.5, r - 0.5, r, 0, Math.PI * 2);
      x.fill('evenodd');
      x.restore();
    });
    x.restore();
    return c;
  }

  function box(w, h, color, radius) {
    var c = canvas(w, h);
    var x = c.getContext('2d');
    x.fillStyle = color;
    x.fillRect(0, 0, w, h);
    return radius ? round(c, radius) : c;
  }

  function draw(ctx, picture, x, y, alpha, w, h) {
    if (!picture)
      return;
    ctx.save();
    ctx.globalAlpha = alpha === undefined ? 1 : alpha;
    ctx.imageSmoothingQuality = 'high';
    if (w !== undefined)
      ctx.drawImage(picture, x, y, w, h);
    else
      ctx.drawImage(picture, x, y);
    ctx.restore();
  }

  function size(picture) {
    return [picture.naturalWidth || picture.width, picture.naturalHeight || picture.height];
  }

  // ResizeMode.Max to a height: the width keeps the proportions
  function drawToHeight(ctx, picture, x, y, height) {
    var s = size(picture);
    draw(ctx, picture, x, y, 1, Math.round(s[0] * height / s[1]), height);
    return Math.round(s[0] * height / s[1]);
  }

  function font(bold, px) {
    // the site's Lato is the bot's own (1.104), so the letters come out the same
    return (bold ? '700 ' : '400 ') + px + 'px Lato, sans-serif';
  }

  // text from its top left corner, as the bot's DrawText() puts it; align
  // right puts its right end at x (HorizontalAlignment.Right)
  function text(ctx, value, bold, px, color, x, y, align) {
    ctx.save();
    ctx.font = font(bold, px);
    ctx.fillStyle = color;
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = align || 'left';
    ctx.fillText(value, x, y + ASCENT * px);
    ctx.restore();
  }

  function measure(ctx, value, bold, px) {
    ctx.save();
    ctx.font = font(bold, px);
    var width = ctx.measureText(value).width;
    ctx.restore();
    return width;
  }

  // the bot's ToShortSI(), with a dot for decimals
  function shortSI(n) {
    function fixed(value, digits) {
      return value.toFixed(digits).replace(/\.?0+$/, '');
    }
    if (n >= 100000000) return fixed(n / 1000000, 1) + 'M';
    if (n >= 1000000) return fixed(n / 1000000, 2) + 'M';
    if (n >= 100000) return fixed(n / 1000, 1) + 'K';
    if (n >= 10000) return fixed(n / 1000, 2) + 'K';
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function expForLevel(level) {
    return level <= 0 ? 0 : Math.floor(Math.pow(level / 0.35, 2)) + 1;
  }

  // --- the parts of the profile ---

  // GetCurrencyImage(): a coin and its number
  function currency(ctx, value, coin, x, y) {
    if (!coin)
      return;
    draw(ctx, coin, x, y);
    text(ctx, shortSI(value), false, 16, '#a7a7a7', x + size(coin)[0] + 3.5, y);
  }

  // GetTopLevelImage(): the place in the ranking, the level and its bar
  function topLevel(ctx, a, data, x, y) {
    var top = data.top > 999 ? '>1K' : shortSI(data.top);
    draw(ctx, a.pic('mtop'), x, y);
    text(ctx, top, false, 16, '#a7a7a7', x + 19.5, y);
    draw(ctx, a.pic('mlvl'), x + 60, y);
    text(ctx, shortSI(data.level), false, 16, '#a7a7a7', x + 79.5, y);

    var previous = expForLevel(data.level);
    var next = expForLevel(data.level + 1);
    var onLevel = Math.max(0, data.exp - previous);
    var levelExp = next - previous;
    levelExp = levelExp < 0 ? onLevel + 1 : levelExp;

    draw(ctx, box(74, 14, '#3f3f3f', 3), x + 114, y + 1);
    var length = Math.trunc(72 * (onLevel / levelExp));
    if (length > 0)
      draw(ctx, box(length, 12, '#145DA0', 3), x + 115, y + 2);

    var label = onLevel + ' / ' + levelExp;
    text(ctx, label, true, 10, '#FFFFFF', x + 114 + Math.trunc(Math.trunc(74 - measure(ctx, label, true, 10)) / 2), y + 3);
  }

  // GetProfileBar(): drawn last, over everything
  function bar(ctx, a, state, data) {
    var top = has(state.flags, FLAG.BarOnTop);
    var opacity = has(state.flags, FLAG.BarOpacity) ? 1 - state.shadows / 100 : 0.75;
    draw(ctx, box(700, 40, '#000000', 10), 25, top ? -16 : 476, opacity);

    var y = top ? 3 : 480;
    topLevel(ctx, a, data, 32, y);
    var coins = [['mmsg', data.messages], ['msc', data.sc], ['mtc', data.tc], ['mac', data.ac], ['mct', data.ct], ['mpc', data.pc]];
    coins.forEach(function (coin, i) {
      currency(ctx, coin[1], a.pic(coin[0]), 227 + 84 * i, y);
    });
  }

  // GetStatusBar(): the list's parts as coloured lengths of 311 px
  function statusBar(status) {
    var length = 311;
    var values = [status.inProgress, status.completed, status.skipped, status.onHold, status.dropped, status.inPlan];
    var colors = ['#2db039', '#26448f', '#9966ff', '#f9d457', '#a12f31', '#c3c3c3'];
    var lengths = [0, 0, 0, 0, 0, 0];
    var fixedLength = 0;
    values.forEach(function (value, i) {
      if (value) {
        lengths[i] = Math.trunc(Math.max(1, length * (value / status.total) + 0.5));
        fixedLength += lengths[i];
      }
    });
    if (fixedLength > length) {
      var longest = Math.max.apply(null, lengths);
      lengths[lengths.indexOf(longest)] -= fixedLength - length;
    }
    var c = canvas(length, 17);
    var x = c.getContext('2d');
    var offset = 0;
    values.forEach(function (value, i) {
      if (value) {
        x.fillStyle = colors[i];
        x.fillRect(offset, 0, lengths[i] < 1 ? 1 : lengths[i], 17);
        offset += lengths[i];
      }
    });
    return round(c, 5);
  }

  // GetRWStats(): the panel of the anime or manga list
  function listStats(base, status) {
    var s = size(base);
    var c = canvas(s[0], s[1]);
    var ctx = c.getContext('2d');
    draw(ctx, base, 0, 0);
    if (status.total > 0)
      draw(ctx, statusBar(status), 7, 3);

    var x = 117;
    var y = 26;
    [status.inProgress, status.completed, status.skipped, status.onHold, status.dropped, status.inPlan].forEach(function (value) {
      text(ctx, String(value), true, 13, '#a7a7a7', x, y);
      y += 16;
    });

    var right = x + 200;
    var row = 26;
    [status.rating.toFixed(1), String(status.total), String(status.count)].forEach(function (value) {
      text(ctx, value, true, 13, '#a7a7a7', right, row, 'right');
      row += 16;
    });
    row += 16;

    var time = [];
    var t = status.time;
    if (t.years) time.push(t.years + ' lat');
    if (t.months) time.push(t.months + ' mies.');
    if (t.days) time.push(t.days + ' dni');
    if (t.hours) time.push(t.hours + ' h');
    if (t.minutes) time.push(t.minutes + ' m');
    if (time.length > 2) {
      text(ctx, time[0] + ' ' + time[1], true, 13, '#a7a7a7', right, row, 'right');
      text(ctx, time.slice(2).join(' '), true, 13, '#a7a7a7', right, row + 16, 'right');
    } else {
      text(ctx, time.join(' '), true, 13, '#a7a7a7', right, row, 'right');
    }
    return c;
  }

  // GetWaifuProfileStyleImage(): the panel of the card game
  function cardsPanel(a, data) {
    var c = canvas(364, 269);
    var ctx = c.getContext('2d');
    var x = 213;
    var y = 18;
    text(ctx, 'Posiadane', true, 9, '#7f7f7f', x, y - 10);
    text(ctx, 'Limit', true, 9, '#7f7f7f', x + 60, y - 10);
    text(ctx, String(data.cards), true, 16, '#a7a7a7', x, y);
    text(ctx, String(data.limit), true, 16, '#a7a7a7', x + 60, y);

    var karma = data.karma <= -2000 ? 'kd' : data.karma >= 2000 ? 'kl' : data.karma > -100 && data.karma < 100 ? 'kn' : null;
    if (karma)
      draw(ctx, a.pic(karma), 330, y - 6);

    y += 29;
    RARITIES.forEach(function (rarity) {
      var count = data.rarities[rarity] || 0;
      if (count > 0) {
        draw(ctx, a.pic('r' + rarity), x, y);
        text(ctx, String(count), true, 17, '#a7a7a7', x + 38, y + 1);
        y += 24;
      }
    });

    if (data.scalpels > 0) currency(ctx, data.scalpels, a.pic('mscal'), x, 246);
    if (data.scissors > 0) currency(ctx, data.scissors, a.pic('mbor'), x + 50, 246);
    if (data.ultimates > 0) currency(ctx, data.ultimates, a.pic('mul'), x + 100, 246);

    if (data.waifu)
      drawToHeight(ctx, data.waifu, 10, 4, 260);
    return c;
  }

  // GetUserNewProfileStyleAsync(): the lower part, 750 x 340 under the top
  function lower(a, state, data, images) {
    var c = canvas(750, 340);
    var ctx = c.getContext('2d');
    var style = state.style;
    var flags = state.flags;
    var shadows = 1 - state.shadows / 100;

    function picture() {
      if (images.style)
        draw(ctx, images.style, 0, 0, 1, 750, 340);
    }

    function gallery() {
      var y = 4;
      var x = 19;
      data.gallery.slice(0, 12).forEach(function (card) {
        drawToHeight(ctx, card, x, y, 150);
        x += 121;
        if (x > 730) {
          x = 19;
          y += 155;
        }
      });
    }

    function stats() {
      var mini = style === 'MiniGallery' || style === 'MiniGalleryOnImg';
      var onImg = style === 'StatsOnImg' || style === 'MiniGalleryOnImg';
      var flip = has(flags, FLAG.Flip);
      var x = flip ? 407 : 16;
      var y = 24;

      if (mini) {
        if (has(flags, FLAG.ShowGallery)) {
          if (onImg)
            draw(ctx, box(331, 275, '#000000', 10), x - 3, y - 3, shadows);
          var small = has(flags, FLAG.HalfGallery);
          var startX = x + (small ? 6 : 12);
          var gap = small ? 160 : 104;
          var cardSize = small ? 215 : 131;
          var cardX = startX;
          var cardY = y + (small ? 26 : 2);
          data.gallery.slice(0, small ? 2 : 6).forEach(function (card) {
            drawToHeight(ctx, card, cardX, cardY, cardSize);
            cardX += gap;
            if (cardX > gap * 2 + startX) {
              cardX = startX;
              cardY += 135;
            }
          });
        }
      } else if (data.anime || data.manga) {
        var shadow = box(331, 128, '#000000', 10);
        if (data.anime && has(flags, FLAG.ShowAnime) && a.pic('statsAnime')) {
          if (onImg)
            draw(ctx, shadow, x - 3, y - 3, shadows);
          draw(ctx, listStats(a.pic('statsAnime'), data.anime), x, y);
          y += 147;
        }
        if (data.manga && has(flags, FLAG.ShowManga) && a.pic('statsManga')) {
          if (onImg)
            draw(ctx, shadow, x - 3, y - 3, shadows);
          draw(ctx, listStats(a.pic('statsManga'), data.manga), x, y);
        }
      }

      y = 24;
      x += flip ? -391 : 352;
      if (has(flags, FLAG.ShowCards)) {
        if (onImg)
          draw(ctx, box(370, 275, '#000000', 10), x - 3, y - 3, shadows);
        draw(ctx, cardsPanel(a, data), x, y);
      }
    }

    switch (style) {
      case 'Img':
        picture();
        break;
      case 'StatsOnImg':
      case 'MiniGalleryOnImg':
        picture();
        stats();
        break;
      case 'CardsOnImg':
        picture();
        gallery();
        break;
      case 'Stats':
      case 'MiniGallery':
        stats();
        break;
      case 'StatsWithImg':
        stats();
        picture();
        break;
      case 'Cards':
        gallery();
        break;
    }
    return c;
  }

  // the level border, from the highest level folder file not above the level
  function borderPicture(a, state, data) {
    var name = 'ab/' + state.border;
    if (has(state.flags, FLAG.BorderColor)) {
      var best = 0;
      (a.levels[state.border] || []).forEach(function (level) {
        if (level <= data.level && level > best)
          best = level;
      });
      if (best > 0)
        name = 'ab/' + state.border + 'Lv/' + best;
    }
    return a.pic(name);
  }

  // GetUserProfileAsync(): the whole profile on a 750 x 500 canvas
  function render(target, a, state, data, images) {
    var ctx = target.getContext('2d');
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#313338';
    ctx.fillRect(0, 0, W, H);

    draw(ctx, images.background || a.pic('pbg'), 0, 0, 1, 750, 160);
    draw(ctx, a.pic('pbase'), 0, 0);
    draw(ctx, lower(a, state, data, images), 0, 160);

    var nameX = 27 + 52 + 84;
    var nameY = 103;
    var nickSize = 28;
    while (measure(ctx, data.nick, true, nickSize) > 290 && nickSize > 1)
      nickSize--;
    text(ctx, data.nick, true, nickSize, '#a7a7a7', nameX, nameY + Math.trunc((30 - nickSize) / 2));
    text(ctx, data.rank, false, 16, '#7f7f7f', nameX, nameY + 32);

    if (has(state.flags, FLAG.ShowOverlay) && images.overlay)
      draw(ctx, images.overlay, 0, 98, 1, 750, 402);

    var bordered = state.border !== 'None';
    var rounded = has(state.flags, FLAG.RoundAvatar) || bordered;
    var radius = rounded ? (bordered ? 40 : 44) : 0;
    draw(ctx, box(84, 84, '#3f3f3f', radius), 52, 68);
    draw(ctx, box(82, 82, data.color, radius), 53, 69);
    if (data.avatar) {
      var avatar = canvas(80, 80);
      draw(avatar.getContext('2d'), data.avatar, 0, 0, 1, 80, 80);
      draw(ctx, radius ? round(avatar, radius) : avatar, 54, 70);
    }

    if (data.waifu && has(state.flags, FLAG.ShowWaifu)) {
      var s = size(data.waifu);
      var cardW = Math.round(s[0] * 120 / s[1]);
      draw(ctx, box(cardW + 8, 128, '#000000', 10), 618, 30, 0.32);
      draw(ctx, data.waifu, 622, 34, 1, cardW, 120);
    }

    if (bordered) {
      var at = BORDER_AT[state.border] || [24, 39];
      draw(ctx, borderPicture(a, state, data), at[0], at[1]);
    }

    if (has(state.flags, FLAG.ShowOverlayPro) && images.premium)
      draw(ctx, images.premium, 0, 0, 1, 750, 500);

    bar(ctx, a, state, data);
  }

  // every picture of the mirror the profile may draw, by the names render() asks for
  function pictureNames(levels) {
    var names = ['pbase', 'pbg', 'mtop', 'mlvl', 'mmsg', 'msc', 'mtc', 'mac', 'mct', 'mpc', 'kn', 'kd', 'kl',
      'mscal', 'mbor', 'mul', 'statsAnime', 'statsManga'];
    RARITIES.forEach(function (rarity) { names.push('r' + rarity); });
    BORDERS.forEach(function (border) {
      if (border !== 'None')
        names.push('ab/' + border);
      (levels[border] || []).forEach(function (level) { names.push('ab/' + border + 'Lv/' + level); });
    });
    return names;
  }

  return {
    W: W,
    H: H,
    STYLES: STYLES,
    BORDERS: BORDERS,
    FLAG: FLAG,
    DEFAULT_FLAGS: DEFAULT_FLAGS,
    RARITIES: RARITIES,
    IMAGES: IMAGES,
    hasStyleImage: hasStyleImage,
    encode: encode,
    decode: decode,
    styleNeedsImage: styleNeedsImage,
    configurableStyle: configurableStyle,
    settingWorks: settingWorks,
    expForLevel: expForLevel,
    pictureNames: pictureNames,
    render: render
  };
})();
