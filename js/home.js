// The home page: the motto in Morse code (which also listens to Morse, and
// says something else on 1 April and at the holidays), the bot status dot,
// Sanakan's Christmas hat, the cake of the bot's birthday, the page falling
// asleep when left alone and the Discord account in the top right corner.
// Kept out of index.html, so the security policy can allow only the site's
// own script files (server/nginx).

// the date, or the one js/safeguard.js was told to pretend (for trying out
// the days that have an easter egg of their own)
function homeToday() {
  if (window.SanakanSafeguard) return SanakanSafeguard.today();
  var now = new Date();
  return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate() };
}

// "xmas", "easter", "birthday" or null (js/safeguard.js)
function homeSeason() {
  return window.SanakanSafeguard ? SanakanSafeguard.season() : null;
}

// Shows the motto in Morse code; every symbol lights up in real Morse timing,
// like a signal being sent (dot 1 unit, dash 3, gaps 1 / 3 / 7). On 1 April,
// at Christmas and Easter and on the bot's birthday it sends another one. It also takes a signal: dots and dashes keyed on it
// with the mouse (a short press, a long one) or with the space bar, and when
// the keying stops, the Safeguard answers in Morse, then in plain text. A word
// it knows is a find of the Safeguard's register (js/safeguard.js).
(function () {
  var CODE = {
    A: '.-', B: '-...', C: '-.-.', D: '-..', E: '.', F: '..-.', G: '--.', H: '....', I: '..',
    J: '.---', K: '-.-', L: '.-..', M: '--', N: '-.', O: '---', P: '.--.', Q: '--.-', R: '.-.',
    S: '...', T: '-', U: '..-', V: '...-', W: '.--', X: '-..-', Y: '-.--', Z: '--..', ',': '--..--',
    // the Polish letters of Polish Morse
    'Ą': '.-.-', 'Ć': '-.-..', 'Ę': '..-..', 'Ł': '.-..-', 'Ń': '--.--', 'Ó': '---.', 'Ś': '...-...',
    'Ź': '--..-.', 'Ż': '--..-',
    0: '-----', 1: '.----', 2: '..---', 3: '...--', 4: '....-', 5: '.....', 6: '-....', 7: '--...',
    8: '---..', 9: '----.'
  };
  var LETTERS = {};
  Object.keys(CODE).forEach(function (ch) { LETTERS[CODE[ch]] = ch; });

  var UNIT = 60;      // ms
  var FAST = 45;      // ms, the unit of the Safeguard's answer
  var START = 1200;   // ms, after the entrance animation
  var PAUSE = 21;     // units of silence before the signal repeats
  var APRIL = 'Zagrajmy w ping ponga';
  var HOLIDAYS = { xmas: 'Wesołych świąt', easter: 'Smacznego jajka', birthday: 'Sto lat' };

  // what the Safeguard answers to a word keyed in; anything else gets AGAIN
  var REPLIES = {
    SOS: 'Safeguard już idzie',
    SANAKAN: 'Słucham',
    KILLY: 'Cel namierzony',
    CIBO: 'Gen nadal nie istnieje',
    NETSFERA: 'Odmowa dostępu',
    NETSPHERE: 'Odmowa dostępu',
    PING: 'Pong',
    HEJ: 'Hej, mieszkańcze'
  };
  var AGAIN = 'Powtórz';

  // the keying: a press shorter than DOT is a dot; after LETTER of silence the
  // letter ends, after END the whole signal
  var DOT = 200, LETTER = 600, END = 1600;

  var motto = document.getElementById('motto');
  var text = motto.textContent.trim();
  var day = homeToday();
  var special = day.month === 4 && day.day === 1 ? APRIL : HOLIDAYS[homeSeason()];
  if (special) {
    text = special;
    motto.title = special;
  }

  function el(tag, cls, content) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (content != null) node.textContent = content;
    return node;
  }

  // the text as symbols lit from start ms on, over and over, unit ms a dot;
  // the cycle goes into --cycle and is returned, in ms
  function send(message, start, unit) {
    var signal = el('span');
    signal.setAttribute('aria-hidden', 'true');
    var t = 0;
    message.toUpperCase().split(/\s+/).forEach(function (word, w) {
      var wordEl = el('span', 'morse-word');
      word.split('').forEach(function (ch, c) {
        var code = CODE[ch];
        if (!code) return;
        var letterEl = el('span', 'morse-letter');
        code.split('').forEach(function (sym, i) {
          t += i ? 1 : (c ? 3 : (w ? 7 : 0));
          var symbol = el('i', sym === '.' ? 'dot' : 'dash');
          symbol.style.animationDelay = (start + t * unit) + 'ms';
          letterEl.appendChild(symbol);
          t += sym === '.' ? 1 : 3;
        });
        wordEl.appendChild(letterEl);
      });
      signal.appendChild(wordEl);
    });

    var cycle = (t + PAUSE) * unit;
    motto.style.setProperty('--cycle', cycle + 'ms');
    motto.classList.remove('decoded');
    motto.replaceChildren(signal, el('span', 'sr-only', message));
    return cycle;
  }

  send(text, START, UNIT);

  // ---- Keying ----

  var letters = [];   // the letters keyed so far, as dots and dashes
  var symbols = '';   // the letter being keyed
  var down = 0;       // when the key went down
  var timer = null;
  var busy = false;   // while the answer goes out

  // what was keyed, lit for good, as it comes
  function echo() {
    var signal = el('span');
    signal.setAttribute('aria-hidden', 'true');
    var word = el('span', 'morse-word');
    letters.concat(symbols ? [symbols] : []).forEach(function (code) {
      var letter = el('span', 'morse-letter');
      code.split('').forEach(function (sym) { letter.appendChild(el('i', (sym === '.' ? 'dot' : 'dash') + ' lit')); });
      word.appendChild(letter);
    });
    signal.appendChild(word);
    motto.classList.remove('decoded');
    motto.replaceChildren(signal);
  }

  function press() {
    if (busy || down) return;
    clearTimeout(timer);
    down = performance.now();
    motto.classList.add('keying');
    if (!letters.length && !symbols) echo();
  }

  function release() {
    if (!down) return;
    symbols += performance.now() - down < DOT ? '.' : '-';
    down = 0;
    motto.classList.remove('keying');
    echo();
    timer = setTimeout(endLetter, LETTER);
  }

  function endLetter() {
    letters.push(symbols);
    symbols = '';
    timer = setTimeout(finish, END - LETTER);
  }

  // the answer goes out once, then both sides in plain text, then the motto again
  function finish() {
    var heard = letters.map(function (code) { return LETTERS[code] || '?'; }).join('');
    letters = [];
    var answer = REPLIES[heard] || AGAIN;
    busy = true;
    var sent = 300 + send(answer, 300, FAST) - PAUSE * FAST;
    if (REPLIES[heard] && window.SanakanSafeguard) SanakanSafeguard.found('morse');

    setTimeout(function () {
      motto.classList.add('decoded');
      motto.replaceChildren(el('span', null, 'odebrano: ' + heard + ' · odpowiedź: ' + answer.toUpperCase()));
    }, sent);

    setTimeout(function () {
      busy = false;
      send(text, 0, UNIT);
    }, sent + 6000);
  }

  motto.addEventListener('pointerdown', function (e) {
    if (e.button > 0) return;
    e.preventDefault();
    press();
  });
  ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (type) {
    motto.addEventListener(type, release);
  });

  // the space bar anywhere on the page, unless something of its own takes it
  function spaceKey(e) {
    if (e.code !== 'Space' && e.key !== ' ') return false;
    if (e.ctrlKey || e.metaKey || e.altKey) return false;
    var t = e.target;
    if (t && (t.isContentEditable || (t.closest && t.closest('a, button, input, textarea, select, [role="dialog"], [role="alertdialog"]')))) return false;
    // the motto shows only in a wide window
    return motto.offsetParent !== null && !document.body.classList.contains('ns-open') && !document.body.classList.contains('pl-open');
  }
  document.addEventListener('keydown', function (e) {
    if (!spaceKey(e)) return;
    e.preventDefault();
    if (!e.repeat) press();
  });
  document.addEventListener('keyup', function (e) {
    if (!spaceKey(e)) return;
    e.preventDefault();
    release();
  });
})();

// Christmas: Sanakan wears a Santa hat. A click knocks it off, and the
// Safeguard makes clear what it thinks of the holidays; the first time it is
// a seasonal find of the Safeguard's register. Knocked off, it stays off for
// the rest of those holidays (localStorage "safeguard-hat": their year).
(function () {
  if (homeSeason() !== 'xmas') return;
  var day = homeToday();
  // the holidays that begin in December, also in their days of January
  var holidays = String(day.month === 1 ? day.year - 1 : day.year);
  var STORE = 'safeguard-hat';
  try {
    if (localStorage.getItem(STORE) === holidays) return;
  } catch (err) {
    // no storage: the hat is back on every visit
  }
  var wrap = document.querySelector('.logo-wrap');
  var hat = document.createElement('button');
  hat.type = 'button';
  hat.className = 'santa-hat';
  hat.title = 'Czapka Mikołaja';
  hat.setAttribute('aria-label', 'Czapka Mikołaja');
  hat.innerHTML = '<svg viewBox="0 0 100 80" aria-hidden="true">'
    + '<path class="cap" d="M12 62C24 30 52 6 86 20 78 30 72 44 70 62Z" />'
    + '<path class="shade" d="M52 18C64 14 76 15 86 20 80 27 75 36 72 46 66 34 60 25 52 18Z" />'
    + '<rect class="fur" x="4" y="55" width="74" height="17" rx="8.5" />'
    + '<circle class="fur" cx="87" cy="21" r="9" /></svg>';
  wrap.appendChild(hat);

  hat.addEventListener('click', function () {
    hat.disabled = true;
    hat.classList.add('off');
    try {
      localStorage.setItem(STORE, holidays);
    } catch (err) {
      // the same
    }
    var say = document.createElement('p');
    say.className = 'santa-say';
    say.setAttribute('role', 'status');
    say.textContent = 'Safeguard nie obchodzi świąt. Ale dziękuje za pamięć.';
    wrap.appendChild(say);
    if (window.SanakanSafeguard) SanakanSafeguard.found('xmas');
    setTimeout(function () { say.remove(); }, 4500);
  });
})();

// The bot's birthday, 13 October (it first ran on 13 October 2017): a cake
// with candles in the shape of the digits of its age stands by Sanakan. A click blows them
// out, glyphs burst out of it and the Safeguard notes the day; the first time
// it is a seasonal find of the Safeguard's register. Blown out, the candles
// stay out for the rest of the day (localStorage "safeguard-cake": the year).
(function () {
  if (homeSeason() !== 'birthday') return;
  var BORN = 2017;
  var STORE = 'safeguard-cake';
  var day = homeToday();
  var years = day.year - BORN;
  var year = String(day.year);
  var blown = false;
  try {
    blown = localStorage.getItem(STORE) === year;
  } catch (err) {
    // no storage: the candles burn again on every visit
  }

  // "1 rok", "2 lata", "5 lat", "22 lata"
  function age(n) {
    if (n === 1) return '1 rok';
    var last = n % 10, two = n % 100;
    return n + (last >= 2 && last <= 4 && (two < 12 || two > 14) ? ' lata' : ' lat');
  }

  // a candle for each digit, side by side over the middle of the cake, each
  // with its wick and flame
  var digits = String(Math.max(1, years)).split('');
  var candles = '';
  digits.forEach(function (digit, i) {
    var x = 50 + (i - (digits.length - 1) / 2) * 24;
    candles += '<text class="candle" x="' + x + '" y="47" text-anchor="middle">' + digit + '</text>'
      + '<path class="wick" d="M' + x + ' 18.5v7.5" />'
      + '<path class="smoke" style="--i: ' + i + '" d="M' + x + ' 18c-3-3 3-6 0-9s3-6 0-9" />'
      + '<path class="flame" style="--i: ' + i + '" d="M' + x + ' 4c4.5 5 5 10 0 15-5-5-4.5-10 0-15Z" />'
      + '<path class="flame-core" style="--i: ' + i + '" d="M' + x + ' 11c2 2.5 2 5 0 7.5-2-2.5-2-5 0-7.5Z" />';
  });

  var wrap = document.querySelector('.logo-wrap');
  var cake = document.createElement('button');
  cake.type = 'button';
  cake.className = 'birthday-cake' + (blown ? ' blown' : '');
  cake.title = 'Tort na ' + age(years) + ' bota';
  cake.setAttribute('aria-label', blown ? cake.title + ', świeczki zdmuchnięte' : cake.title + ': zdmuchnij świeczki');
  cake.innerHTML = '<svg viewBox="0 -6 100 96" aria-hidden="true">'
    + '<ellipse class="plate" cx="50" cy="84" rx="47" ry="5" />'
    + '<rect class="sponge" x="10" y="46" width="80" height="36" rx="4" />'
    + '<path class="cream" d="M10 52c0-4 2-6 6-6h68c4 0 6 2 6 6v5c-4 4-7 4-10 0-3 5-7 5-10 0-3 5-7 5-10 0-3 5-7 5-10 0-3 5-7 5-10 0-3 5-7 5-10 0-3 5-7 5-10 0-3 4-6 4-10 0Z" />'
    + '<path class="band" d="M10 70h80" />' + candles + '</svg>';
  wrap.appendChild(cake);
  if (blown) {
    cake.disabled = true;
    return;
  }

  // glyphs out of the cake in every direction, unless the system asks for no motion
  function burst() {
    if ((window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) || !document.body.animate) return;
    var GLYPHS = ['✦', '✧', '*', '+', '◆', '·', '▪', '❖'];
    var COLOURS = ['#b670d3', '#4fd1c5', '#e86262', '#f0a35e', '#5fd38d', '#e8d36a'];
    var r = cake.getBoundingClientRect();
    for (var i = 0; i < 28; i++) {
      var bit = document.createElement('i');
      bit.className = 'cake-bit';
      bit.textContent = GLYPHS[i % GLYPHS.length];
      bit.style.left = (r.left + scrollX + r.width / 2) + 'px';
      bit.style.top = (r.top + scrollY + r.height * 0.2) + 'px';
      bit.style.color = COLOURS[i % COLOURS.length];
      document.body.appendChild(bit);
      var angle = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.4;
      var far = 70 + Math.random() * 120;
      var dx = Math.cos(angle) * far, dy = Math.sin(angle) * far;
      bit.animate([
        { transform: 'translate(-50%, -50%) scale(0.6)', opacity: 1 },
        { transform: 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + dy + 'px)) scale(1)', opacity: 1, offset: 0.6 },
        { transform: 'translate(calc(-50% + ' + dx * 1.1 + 'px), calc(-50% + ' + (dy + 60) + 'px)) rotate(' + (Math.random() * 360 - 180) + 'deg)', opacity: 0 }
      ], { duration: 1300 + Math.random() * 700, easing: 'cubic-bezier(0.2, 0.7, 0.4, 1)' }).onfinish = (function (node) {
        return function () { node.remove(); };
      })(bit);
    }
  }

  cake.addEventListener('click', function () {
    cake.disabled = true;
    // a wisp of smoke rises from each digit only now, not on a later visit
    cake.classList.add('blown', 'blowing');
    cake.setAttribute('aria-label', cake.title + ', świeczki zdmuchnięte');
    try {
      localStorage.setItem(STORE, year);
    } catch (err) {
      // the same
    }
    burst();
    var say = document.createElement('p');
    say.className = 'santa-say';
    say.setAttribute('role', 'status');
    say.textContent = 'Sanakan kończy dziś ' + age(years) + '. Safeguard nie świętuje, ale odnotował.';
    wrap.appendChild(say);
    if (window.SanakanSafeguard) SanakanSafeguard.found('birthday');
    setTimeout(function () { say.remove(); }, 5000);
  });
})();

// Left alone for a few minutes, the page falls asleep: everything goes dark
// and grey but Sanakan herself, whose eyes glow faintly. Anything done wakes
// it. The first time it is a find of the Safeguard's register. To try it out,
// localStorage "safeguard-idle" set to a number of seconds sleeps sooner.
(function () {
  var IDLE = 3 * 60 * 1000;  // ms without anything done
  var CHECK = 5000;          // ms between the looks at the clock
  try {
    var test = parseInt(localStorage.getItem('safeguard-idle'), 10);
    if (test > 0) {
      IDLE = test * 1000;
      CHECK = 1000;
    }
  } catch (err) {
    // no storage: the real time
  }
  var body = document.body;
  var last = Date.now();
  var veil = document.createElement('div');
  veil.className = 'idle-veil';
  veil.setAttribute('aria-hidden', 'true');
  body.appendChild(veil);

  function wake() {
    last = Date.now();
    body.classList.remove('idle');
  }

  ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart', 'scroll'].forEach(function (type) {
    window.addEventListener(type, wake, { passive: true, capture: true });
  });

  setInterval(function () {
    if (body.classList.contains('idle') || Date.now() - last < IDLE) return;
    body.classList.add('idle');
    if (window.SanakanSafeguard) SanakanSafeguard.found('idle');
  }, CHECK);
})();

// status.php asks the bot API at most once a minute and says online, idle,
// offline or maintenance (a planned break set in the admin panel)
(function () {
  var dot = document.getElementById('bot-status');
  var labels = {
    online: 'Dostępny: bot działa',
    idle: 'Zaraz wracam: bot działa, ale w ciągu ostatnich 24 h odpowiadał tylko w {uptime}% sprawdzeń',
    offline: 'Niedostępny: bot nie odpowiada {down}',
    maintenance: 'Nie przeszkadzać: przerwa techniczna, bot może nie odpowiadać'
  };

  var opts = {};
  if (window.AbortSignal && AbortSignal.timeout) opts.signal = AbortSignal.timeout(8000);

  fetch('./status.php', opts)
    .then(function (res) { return res.ok ? res.json() : null; })
    .then(function (data) {
      if (!data || !labels[data.status]) throw new Error('unknown status');

      var label = labels[data.status]
        .replace('{uptime}', Number(data.uptime).toLocaleString('pl-PL'))
        .replace(' {down}', data.down ? ' ' + data.down : '');
      if (data.status === 'idle' && data.issues) label = 'Zaraz wracam: bot działa, ale ' + data.issues;
      dot.className = 'bot-status ' + data.status;
      dot.title = label + ' (kliknij, żeby zobaczyć dostępność)';
      dot.setAttribute('aria-label', label);
    })
    // without an answer nothing is known about the bot, so no dot at all
    .catch(function () { dot.remove(); });

  // a click on the dot opens the public status page; held for 3 seconds it
  // charges up and fires like Killy's Gravitational Beam Emitter in BLAME!:
  // a thin beam straight through everything, then the blast along it. The
  // third shot in a row overloads it: the beam goes back through the logo,
  // which splits along it, and Sanakan regenerates, as in the manga.
  var timer, pressed = 0;
  var SERIES = 3;        // shots in a row that break the logo
  var GAP = 15000;       // ms at most between two shots of a row
  var shots = [];        // the times of the shots of this row
  var broken = false;    // while the logo is split and growing back

  function hold(e) {
    if (e.button > 0 || broken) return;
    pressed = Date.now();
    dot.classList.add('charging');
    timer = setTimeout(fire, 3000);
  }

  function release() {
    clearTimeout(timer);
    dot.classList.remove('charging');
    pressed = 0;
  }

  // the beam goes in next to the dot, a layer below it, so it comes out from
  // under the dot and runs to the right edge of the window
  function fire() {
    release();
    var now = Date.now();
    if (shots.length && now - shots[shots.length - 1] > GAP) shots = [];
    shots.push(now);
    if (shots.length >= SERIES) {
      shots = [];
      overload();
    } else {
      var wrap = dot.parentNode;
      var r = dot.getBoundingClientRect(), w = wrap.getBoundingClientRect();
      var beam = document.createElement('div');
      beam.className = 'gbe-beam';
      beam.style.left = (r.left - w.left + r.width / 2) + 'px';
      beam.style.top = (r.top - w.top + r.height / 2) + 'px';
      beam.style.width = Math.max(0, document.documentElement.clientWidth - (r.left + r.width / 2)) + 'px';
      wrap.appendChild(beam);
      setTimeout(function () { beam.remove(); }, 1700);
    }
    document.body.classList.add('gbe-shot');
    dot.classList.add('recoil');
    // a find of the Safeguard's register (js/safeguard.js)
    if (window.SanakanSafeguard) SanakanSafeguard.found('gbe');
    setTimeout(function () {
      document.body.classList.remove('gbe-shot');
      dot.classList.remove('recoil');
    }, 1700);
  }

  // The shape of one side of a line through (px, py) along (dx, dy), cut out
  // of a w x h box, as a CSS polygon; side is 1 or -1.
  function half(w, h, px, py, dx, dy, side) {
    var corners = [[0, 0], [w, 0], [w, h], [0, h]];
    var out = [];
    function at(p) { return side * ((p[0] - px) * -dy + (p[1] - py) * dx); }
    corners.forEach(function (a, i) {
      var b = corners[(i + 1) % 4], fa = at(a), fb = at(b);
      if (fa >= 0) out.push(a);
      if ((fa >= 0) !== (fb >= 0)) {
        var t = fa / (fa - fb);
        out.push([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t]);
      }
    });
    return 'polygon(' + out.map(function (p) { return p[0].toFixed(1) + 'px ' + p[1].toFixed(1) + 'px'; }).join(', ') + ')';
  }

  // The overloaded shot: from the dot up and left across the face to the edge
  // of the window; at the blast the logo splits in two along it, hangs apart,
  // then the halves grow back together under a scan of the Safeguard.
  function overload() {
    broken = true;
    var calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var wrap = dot.parentNode;
    var r = dot.getBoundingClientRect(), w = wrap.getBoundingClientRect();
    var x0 = r.left + r.width / 2, y0 = r.top + r.height / 2;
    // aimed at the left of the logo, a third of the way down
    var dx = w.left + w.width * 0.08 - x0, dy = w.top + w.height * 0.3 - y0;
    var len = Math.hypot(dx, dy);
    dx /= len;
    dy /= len;
    var reach = Math.min(dx < 0 ? x0 / -dx : Infinity, dy < 0 ? y0 / -dy : Infinity);
    var angle = Math.atan2(dy, dx) * 180 / Math.PI;
    var px = x0 - w.left, py = y0 - w.top;

    var aim = document.createElement('div');
    aim.className = 'gbe-aim';
    aim.style.left = px + 'px';
    aim.style.top = py + 'px';
    aim.style.transform = 'rotate(' + angle + 'deg)';
    var beam = document.createElement('div');
    beam.className = 'gbe-beam';
    beam.style.width = reach + 'px';
    aim.appendChild(beam);
    wrap.appendChild(aim);

    // the crack: where the line crosses the round logo
    var cx = w.width / 2, cy = w.height / 2, rad = w.width / 2;
    var bx = px - cx, by = py - cy;
    var b = bx * dx + by * dy, c = bx * bx + by * by - rad * rad;
    var root = Math.sqrt(Math.max(0, b * b - c));
    var t1 = Math.max(0, -b - root), t2 = -b + root;
    var crack = document.createElement('div');
    crack.className = 'logo-crack';
    crack.style.left = (px + dx * t1) + 'px';
    crack.style.top = (py + dy * t1) + 'px';
    crack.style.width = Math.max(0, t2 - t1) + 'px';
    crack.style.transform = 'rotate(' + angle + 'deg)';

    var shards = [1, -1].map(function (side) {
      var shard = document.createElement('div');
      shard.className = 'logo-shard';
      shard.style.clipPath = half(w.width, w.height, px, py, dx, dy, side);
      return shard;
    });
    // apart along the normal of the crack, the lower piece sagging a little more
    var nx = -dy, ny = dx;
    var apart = calm ? [0, 0] : [9, 7];
    var moves = [
      'translate(' + (nx * apart[0]) + 'px, ' + (ny * apart[0]) + 'px) rotate(2deg)',
      'translate(' + (-nx * apart[1]) + 'px, ' + (-ny * apart[1] + (calm ? 0 : 4)) + 'px) rotate(-1.5deg)'
    ];
    var regen = document.createElement('div');
    regen.className = 'logo-regen';

    // the blast, as the beam swells
    setTimeout(function () {
      shards.forEach(function (shard) { wrap.appendChild(shard); });
      wrap.append(crack, regen);
      wrap.classList.add('broken');
      void wrap.offsetWidth;
      shards.forEach(function (shard, i) { shard.style.transform = moves[i]; });
    }, 550);

    setTimeout(function () { aim.remove(); }, 1700);

    // growing back together
    setTimeout(function () {
      wrap.classList.add('regenerating');
      shards.forEach(function (shard) { shard.style.transform = ''; });
    }, 2600);

    setTimeout(function () {
      shards.forEach(function (shard) { shard.remove(); });
      crack.remove();
      regen.remove();
      wrap.classList.remove('broken', 'regenerating');
      broken = false;
      if (window.SanakanSafeguard) SanakanSafeguard.found('regen');
    }, 3800);
  }

  dot.addEventListener('pointerdown', hold);
  dot.addEventListener('pointerup', function () {
    var click = pressed && Date.now() - pressed < 400;
    release();
    if (click) location.href = 'state/';
  });
  ['pointerleave', 'pointercancel'].forEach(function (type) {
    dot.addEventListener(type, release);
  });
  // a long touch would open the context menu instead
  dot.addEventListener('contextmenu', function (e) { e.preventDefault(); });
})();

// The Discord account in the top right corner (account.php): a login button,
// or the account menu of every page, with the gallery and the panel when it
// may open them (js/account.js). An account that may read the API gets the
// API button unlocked.
(function () {
  var box = document.getElementById('home-account');
  var api = document.getElementById('api-link');

  SanakanAccount.load(box).then(function (me) {
    if (!me) return;
    if (!me.menu) {
      if (!me.login) return;
      var login = document.createElement('a');
      login.href = 'account.php?login';
      login.className = 'home-login hud-corners';
      login.textContent = 'Zaloguj przez Discord';
      box.appendChild(login);
      box.hidden = false;
      return;
    }

    if (me.api) {
      api.classList.remove('restricted');
      api.title = 'Dokumentacja API';
      var lock = api.querySelector('.lock');
      lock.setAttribute('aria-label', 'masz dostęp');
      lock.querySelector('path').setAttribute('d', 'M8 11V8a4 4 0 0 1 7.7-1.5');
    }

    // "Zalogowano jako ..." after coming back from Discord
    if (me.flash) {
      var flash = document.createElement('p');
      flash.className = 'home-flash';
      flash.setAttribute('role', 'status');
      flash.textContent = me.flash;
      document.body.appendChild(flash);
      setTimeout(function () { flash.remove(); }, 4500);
    }
  });
})();
