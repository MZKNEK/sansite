// The 500 page, shown by nginx when PHP fails or does not answer, by PHP when
// a page of it fails, and where the Safeguard's terminal leads once told to
// remove the Megastructure (js/netsphere.js).

// The warning sign hides something older than the Safeguard, crawling in the
// failed machinery: a snake. Fed FED times it is a find of the register. The
// server believes a game only once it has played it again (snakeAnswer() in
// inc/megastructure.php): each game gets a ticket, whose nonce places the
// food, and its way, tick by tick, goes to the server, which gives the find
// and keeps the best game of an account. A game begun before its ticket came
// is played all the same, without the server.
(function () {
  var COLS = 20;
  var ROWS = 16;
  var FED = 15;
  var START_MS = 140;
  var FASTEST_MS = 70;
  var BEST = 'safeguard-snake';  // the best game without an account, in this browser
  var mark = document.getElementById('failure-mark');
  var box = document.querySelector('.failure');
  if (!mark || !box) return;
  var sg = window.SanakanSafeguard;

  var game = null;        // this game: its ticket, the numbers placing its food, its way
  var serverBest = null;  // the account's best game, as the server keeps it
  var opened = null;  // the page's own content while the game is open
  var canvas, ctx, cell, scoreEl, bestEl, noteEl;
  var snake, dir, next, food, eaten, timer, state;  // state: ready, run, pause, over
  var colorWatch, palette, paletteKey, lastDead;

  function best() {
    if (serverBest !== null) return serverBest;
    try {
      return parseInt(localStorage.getItem(BEST), 10) || 0;
    } catch (err) {
      return 0;
    }
  }

  function keepBest(n) {
    if (serverBest !== null) return;  // the server keeps it
    try {
      if (n > best()) localStorage.setItem(BEST, String(n));
    } catch (err) {
      // remembered for this game only
    }
  }

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text != null) node.textContent = text;
    return node;
  }

  function open() {
    if (opened) return;
    opened = Array.prototype.slice.call(box.children);
    opened.forEach(function (child) { child.hidden = true; });

    var wrap = el('div', 'snake');
    var title = el('h2', null, 'Coś pełza w maszynerii');
    canvas = document.createElement('canvas');
    canvas.setAttribute('aria-label', 'Wąż');
    var score = el('p', 'snake-score');
    scoreEl = el('b', null, '0');
    bestEl = el('b', null, String(best()));
    var a = el('span', null, 'usterki: ');
    a.appendChild(scoreEl);
    var b = el('span', null, 'rekord: ');
    b.appendChild(bestEl);
    score.append(a, b);
    noteEl = el('p', 'snake-note');
    var close = el('button', 'admin-btn', 'Wróć do awarii');
    close.type = 'button';
    close.style.marginTop = '14px';
    close.addEventListener('click', shut);
    wrap.append(title, canvas, score, noteEl, close);
    box.appendChild(wrap);

    size();
    reset();
    colorWatch = new MutationObserver(function () { draw(lastDead); });
    colorWatch.observe(document.documentElement, { attributes: true, attributeFilter: ['data-skin', 'data-hud', 'class', 'style'] });
    document.addEventListener('keydown', onKey);
    window.addEventListener('resize', size);
    canvas.addEventListener('touchstart', onTouchStart, { passive: false });
    canvas.addEventListener('touchend', onTouchEnd);
    canvas.addEventListener('click', function () { if (state !== 'run') toggle(); });

  }

  function setServerBest(n) {
    serverBest = n;
    if (bestEl) bestEl.textContent = String(Math.max(n, state === 'over' ? 0 : eaten));
  }

  // a xorshift from a seed, the same as snakeNext() in inc/megastructure.php
  function xorshift(seed) {
    var x = seed >>> 0 || 1;
    return function () {
      x ^= x << 13;
      x ^= x >>> 17;
      x ^= x << 5;
      x >>>= 0;
      return x;
    };
  }

  // a new game asks for its ticket; once it comes, while the game waits to
  // begin, its nonce places the food (snakeSeed())
  function newGame() {
    var g = { ticket: null, next: null, way: [] };
    game = g;
    if (!sg) return;
    sg.mega({ ticket: 'snake' }).then(function (answer) {
      if (!answer || !answer.ticket || game !== g || !opened) return;
      if (typeof answer.best === 'number') setServerBest(answer.best);
      if (state !== 'ready') return;
      g.ticket = answer.ticket;
      g.next = xorshift(parseInt((answer.ticket.split('.')[1] || '').slice(0, 8), 16));
      place();
      draw();
    });
  }

  // the game so far to the server, "R12D3…", which plays it again: the find
  // once fed FED times, and the account's best game
  function send(g) {
    if (!sg || !g.ticket) return;
    var way = g.way.map(function (run) { return run[0] + run[1]; }).join('');
    sg.mega({}, { snake: way, ticket: g.ticket }).then(function (answer) {
      if (!answer) return;
      if (answer.proof) sg.found('snake', answer.proof);
      if (typeof answer.best === 'number') setServerBest(answer.best);
    });
  }

  function shut() {
    if (!opened) return;
    clearTimeout(timer);
    colorWatch.disconnect();
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('resize', size);
    box.removeChild(box.lastChild);
    opened.forEach(function (child) { child.hidden = false; });
    opened = null;
    mark.focus();
  }

  // the grid as wide as the box allows, sharp on a dense screen
  function size() {
    var width = Math.min(box.clientWidth - 8, 400);
    cell = Math.max(10, Math.floor(width / COLS));
    var ratio = window.devicePixelRatio || 1;
    canvas.style.width = cell * COLS + 'px';
    canvas.style.height = cell * ROWS + 'px';
    canvas.width = cell * COLS * ratio;
    canvas.height = cell * ROWS * ratio;
    ctx = canvas.getContext('2d');
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    if (snake) draw();
  }

  function reset() {
    var y = Math.floor(ROWS / 2);
    snake = [{ x: 6, y: y }, { x: 5, y: y }, { x: 4, y: y }];
    dir = next = { x: 1, y: 0 };
    eaten = 0;
    scoreEl.textContent = '0';
    newGame();
    place();
    state = 'ready';
    note('Spacja albo dotknięcie zaczyna. Strzałki, WASD albo przesunięcie palcem kierują. Esc wraca.');
    draw();
  }

  function note(text) {
    noteEl.textContent = text;
  }

  function place() {
    var free = [];
    for (var x = 0; x < COLS; x++)
      for (var y = 0; y < ROWS; y++)
        if (!snake.some(function (s) { return s.x === x && s.y === y; })) free.push({ x: x, y: y });
    food = free[game.next ? game.next() % free.length : Math.floor(Math.random() * free.length)];
  }

  function toggle() {
    if (state === 'over') {
      reset();
      return;
    }
    if (state === 'run') {
      state = 'pause';
      clearTimeout(timer);
      note('Pauza. Spacja wznawia.');
      return;
    }
    // a game goes on a tick later, never sooner than its ticks (snakeReplay())
    var resumed = state === 'pause';
    state = 'run';
    note('');
    if (resumed) timer = setTimeout(tick, speed());
    else tick();
  }

  // a tick's milliseconds, shorter the more it ate (SNAKE_START_MS and the rest)
  function speed() {
    return Math.max(FASTEST_MS, START_MS - eaten * 3);
  }

  function turn(x, y) {
    // never straight back into itself
    if (x === -dir.x && y === -dir.y) return;
    next = { x: x, y: y };
    if (state === 'ready') toggle();
  }

  function tick() {
    if (state !== 'run') return;
    dir = next;
    var way = dir.x === 1 ? 'R' : dir.x === -1 ? 'L' : dir.y === 1 ? 'D' : 'U';
    var last = game.way[game.way.length - 1];
    if (last && last[0] === way) last[1]++;
    else game.way.push([way, 1]);
    var head = { x: snake[0].x + dir.x, y: snake[0].y + dir.y };
    var grows = head.x === food.x && head.y === food.y;
    var body = grows ? snake : snake.slice(0, -1);
    if (head.x < 0 || head.y < 0 || head.x >= COLS || head.y >= ROWS
        || body.some(function (s) { return s.x === head.x && s.y === head.y; })) {
      over();
      return;
    }
    snake.unshift(head);
    if (grows) {
      eaten++;
      scoreEl.textContent = String(eaten);
      if (eaten > best()) bestEl.textContent = String(eaten);
      if (eaten === FED && sg && !sg.finds().snake) send(game);
      if (snake.length === COLS * ROWS) {
        over(true);
        return;
      }
      place();
    } else {
      snake.pop();
    }
    draw();
    timer = setTimeout(tick, speed());
  }

  function over(won) {
    state = 'over';
    keepBest(eaten);
    bestEl.textContent = String(best());
    if (eaten) send(game);
    draw(true);
    note((won ? 'Maszyneria cała pożarta. Safeguard nie ma słów.' : 'Maszyneria pożarła węża. Spacja albo dotknięcie zaczyna od nowa.')
      + (serverBest !== null && !game.ticket ? ' Safeguard nie widział tej gry, więc jej nie zapisał.' : ''));
  }

  function colors() {
    var theme = getComputedStyle(document.documentElement);
    var names = ['--snake-head', '--snake-body', '--snake-food', '--snake-grid'];
    var key = theme.colorScheme + names.map(function (name) { return theme.getPropertyValue(name); }).join('|');
    if (palette && key === paletteKey) return palette;
    // Canvas cannot resolve light-dark(). Let CSS resolve the palette first,
    // and cache it until a skin or its native colour scheme changes.
    var swatch = document.createElement('span');
    swatch.hidden = true;
    canvas.parentNode.appendChild(swatch);
    palette = names.map(function (name) {
      swatch.style.color = 'var(' + name + ')';
      return getComputedStyle(swatch).color;
    });
    swatch.remove();
    paletteKey = key;
    return palette;
  }

  function draw(dead) {
    lastDead = !!dead;
    var palette = colors();
    var ink = palette[0], accent = palette[1], danger = palette[2];
    var w = cell * COLS;
    var h = cell * ROWS;
    ctx.clearRect(0, 0, w, h);
    ctx.strokeStyle = palette[3];
    ctx.lineWidth = 1;
    for (var x = 1; x < COLS; x++) {
      ctx.beginPath();
      ctx.moveTo(x * cell + 0.5, 0);
      ctx.lineTo(x * cell + 0.5, h);
      ctx.stroke();
    }
    for (var y = 1; y < ROWS; y++) {
      ctx.beginPath();
      ctx.moveTo(0, y * cell + 0.5);
      ctx.lineTo(w, y * cell + 0.5);
      ctx.stroke();
    }

    ctx.shadowBlur = 8;
    ctx.shadowColor = danger;
    ctx.fillStyle = danger;
    ctx.fillRect(food.x * cell + cell * 0.25, food.y * cell + cell * 0.25, cell * 0.5, cell * 0.5);

    snake.forEach(function (s, i) {
      ctx.shadowColor = dead ? danger : accent;
      ctx.shadowBlur = i === 0 ? 10 : 4;
      ctx.fillStyle = i === 0 ? (dead ? danger : ink) : accent;
      ctx.globalAlpha = i === 0 ? 1 : Math.max(0.35, 1 - i * 0.03);
      ctx.fillRect(s.x * cell + 1, s.y * cell + 1, cell - 2, cell - 2);
    });
    ctx.globalAlpha = 1;
    ctx.shadowBlur = 0;
  }

  var KEYS = {
    ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0],
    w: [0, -1], s: [0, 1], a: [-1, 0], d: [1, 0]
  };

  function onKey(e) {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    var t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
    if (e.key === 'Escape') {
      shut();
      return;
    }
    if (e.key === ' ') {
      e.preventDefault();
      toggle();
      return;
    }
    var move = KEYS[e.key.length === 1 ? e.key.toLowerCase() : e.key];
    if (!move) return;
    e.preventDefault();
    if (state === 'over') return;
    turn(move[0], move[1]);
  }

  var touch = null;

  function onTouchStart(e) {
    e.preventDefault();
    touch = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  }

  function onTouchEnd(e) {
    if (!touch) return;
    var dx = e.changedTouches[0].clientX - touch.x;
    var dy = e.changedTouches[0].clientY - touch.y;
    touch = null;
    if (Math.max(Math.abs(dx), Math.abs(dy)) < 20) {
      if (state !== 'run') toggle();
      return;
    }
    if (state === 'over') return;
    if (Math.abs(dx) > Math.abs(dy)) turn(dx > 0 ? 1 : -1, 0);
    else turn(0, dy > 0 ? 1 : -1);
  }

  mark.addEventListener('click', open);
})();
