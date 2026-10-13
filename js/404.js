// The 404 page, shown by nginx for any missing address.

// a short link to a command (/cmd/daily) where nginx has no rule for it yet
var command = location.pathname.match(/^\/cmd\/([^\/.]+)\/?$/);
if (command) location.replace('/cmd/#' + command[1]);

// the address that was not found
var path = location.pathname;
try {
  path = decodeURIComponent(path);
} catch (err) {
  // a broken %-code stays as it is
}
document.getElementById('lost').textContent = path;

// Where in the Megastructure of BLAME! the address leads: a floor, a sector
// and a stretch of corridor whose way out the Builders have just walled up.
// All of it comes from the address, so the same wrong link always leads to
// the same place. A find of the Safeguard's register (js/safeguard.js).
(function () {
  if (command) return;

  var W = 17, H = 5;  // the corridor's cells; the drawing is twice as many plus one
  var NOTES = [
    'Budowniczowie dobudowali tu ścianę, zanim ktokolwiek zdążył przejść.',
    'Ten korytarz rozrósł się od czasu, gdy ktoś zapisał ten link.',
    'Safeguard nie patroluje tego poziomu. Nikt tu nie patroluje.',
    'Netsfera nie zna tego adresu. Megastruktura też już nie.',
    'Tu kiedyś było przejście. Budowniczowie mieli inny plan.',
    'Ślady ostatnich mieszkańców kończą się przy tej ścianie.'
  ];

  // FNV-1a of the address, then mulberry32 from it: the same numbers every time
  function hash(text) {
    var h = 0x811c9dc5;
    for (var i = 0; i < text.length; i++) {
      h ^= text.charCodeAt(i);
      h = Math.imul(h, 0x01000193);
    }
    return h >>> 0;
  }

  function random(seed) {
    return function () {
      seed = (seed + 0x6d2b79f5) | 0;
      var t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  var rand = random(hash(path));
  function pick(n) { return Math.floor(rand() * n); }
  function number(n) { return n.toLocaleString('pl-PL'); }

  // a maze dug from a cell on the left (a depth-first walk), drawn as '#' and
  // spaces; the way out on the right is the Builders' new wall, '='
  var grid = [];
  for (var y = 0; y < H * 2 + 1; y++) {
    grid.push([]);
    for (var x = 0; x < W * 2 + 1; x++) grid[y].push('#');
  }
  var seen = {};
  var startY = pick(H);
  var stack = [[0, startY]];
  seen[0 + ',' + startY] = true;
  grid[startY * 2 + 1][1] = ' ';
  while (stack.length) {
    var cell = stack[stack.length - 1];
    var next = [[1, 0], [-1, 0], [0, 1], [0, -1]].filter(function (d) {
      var nx = cell[0] + d[0], ny = cell[1] + d[1];
      return nx >= 0 && ny >= 0 && nx < W && ny < H && !seen[nx + ',' + ny];
    });
    if (!next.length) {
      stack.pop();
      continue;
    }
    var d = next[pick(next.length)];
    var nx = cell[0] + d[0], ny = cell[1] + d[1];
    seen[nx + ',' + ny] = true;
    grid[cell[1] * 2 + 1 + d[1]][cell[0] * 2 + 1 + d[0]] = ' ';
    grid[ny * 2 + 1][nx * 2 + 1] = ' ';
    stack.push([nx, ny]);
  }
  grid[startY * 2 + 1][0] = '@';
  grid[pick(H) * 2 + 1][W * 2] = '=';

  // the drawing, with the visitor and the new wall in their own colours
  var map = document.getElementById('mega-map');
  grid.forEach(function (row, i) {
    var text = row.join('');
    text.split(/([@=])/).forEach(function (part) {
      if (!part) return;
      if (part === '@' || part === '=') {
        var mark = document.createElement('b');
        mark.className = part === '@' ? 'mega-you' : 'mega-wall';
        mark.textContent = part;
        mark.title = part === '@' ? 'Ty' : 'Nowa ściana Budowniczych';
        map.appendChild(mark);
      } else {
        map.appendChild(document.createTextNode(part));
      }
    });
    if (i < grid.length - 1) map.appendChild(document.createTextNode('\n'));
  });

  var floor = 1000 + pick(9000000);
  var sector = (0x100 + pick(0xf00)).toString(16).toUpperCase() + '-' + (0x10 + pick(0xf0)).toString(16).toUpperCase();
  document.getElementById('mega-where').textContent = 'Piętro −' + number(floor) + ' · sektor ' + sector;
  document.getElementById('mega-terminal').textContent = number(200 + pick(9800)) + ' km';
  document.getElementById('mega-people').textContent = number(300 + pick(4700)) + ' lat temu';
  document.getElementById('mega-note').textContent = NOTES[pick(NOTES.length)];
  document.getElementById('mega').hidden = false;

  if (window.SanakanSafeguard) SanakanSafeguard.witness('lost');

  // ---- The way to the Net Terminal Gene (inc/megastructure.php) ----
  // The server says whether this address is more than a dead end: at the
  // Builders' hour a door opens on any of them, an address of their corridor
  // feels a draught from the next one, and its end is floor zero.
  if (!window.SanakanSafeguard) return;
  var sg = SanakanSafeguard;
  var mega = document.getElementById('mega');

  function line(cls, text) {
    var p = document.createElement('p');
    p.className = cls;
    p.textContent = text;
    mega.appendChild(p);
    return p;
  }

  function code(text) {
    var c = document.createElement('code');
    c.textContent = text;
    return c;
  }

  // Floor zero: a maze in the dark, the visitor at its way in on the left and
  // a terminal (T) deep inside. The arrows or WASD take a step, a swipe runs
  // on to the next turn; nothing on the page says so. Only what is near
  // shows, what was seen stays dim. The page never has the whole maze: after
  // each step the walk so far, without its steps back, goes to
  // megastructure.php with the proof of floor zero, which checks it and
  // answers with what is seen where it ends, and at the terminal with a word
  // for the Safeguard in the console.
  var SIGHT = 3;   // characters around the visitor that show (MEGA_SIGHT)
  var STEPS = { U: [0, -1], D: [0, 1], L: [-1, 0], R: [1, 0] };
  var BACK = { U: 'D', D: 'U', L: 'R', R: 'L' };
  var KEYS = { arrowup: 'U', arrowdown: 'D', arrowleft: 'L', arrowright: 'R', w: 'U', s: 'D', a: 'L', d: 'R' };

  function floorZero(zero) {
    document.getElementById('mega-where').textContent = 'Piętro 0 · sektor 0-0';
    document.getElementById('mega-terminal').textContent = 'w głębi labiryntu';
    document.getElementById('mega-people').textContent = 'nigdy';
    document.getElementById('mega-note').textContent = 'Dalej nie ma już pięter. Gdzieś w ciemności świeci terminal.';
    var got = line('mega-secret', 'Fragment sekwencji 2/4: ');
    got.appendChild(code(zero.fragment));
    sg.fragment(2, zero.fragment);
    sg.found('floor0', zero.proof);

    var width = zero.maze.width, height = zero.maze.height;
    var x = zero.maze.start[0], y = zero.maze.start[1];
    var known = {};     // "x,y" => the character seen there
    var end = null;     // the terminal, once seen
    var path = [];
    var done = false;
    var asking = false; // a walk on its way to the server
    var again = false;  // and a newer one to send after it
    var running = null; // the way a swipe runs on, while it waits to see
    var map = document.getElementById('mega-map');
    map.classList.add('mega-maze');

    function learn(sight) {
      sight.rows.forEach(function (row, dy) {
        for (var dx = 0; dx < row.length; dx++) known[(sight.x + dx) + ',' + (sight.y + dy)] = row[dx];
      });
      if (sight.end) end = sight.end;
    }

    function open(cx, cy) {
      return known[cx + ',' + cy] === ' ';
    }

    function draw() {
      var html = '';
      for (var ry = 0; ry < height; ry++) {
        var dim = false;
        for (var rx = 0; rx < width; rx++) {
          var seen = known[rx + ',' + ry];
          var near = Math.abs(rx - x) <= SIGHT && Math.abs(ry - y) <= SIGHT;
          var shade = !near && seen !== undefined;
          if (shade !== dim) {
            html += shade ? '<span class="mega-dim">' : '</span>';
            dim = shade;
          }
          if (rx === x && ry === y) html += '<b class="mega-you">@</b>';
          else if (end && rx === end[0] && ry === end[1]) html += '<b class="mega-end">T</b>';
          else html += seen === '#' ? '#' : ' ';
        }
        if (dim) html += '</span>';
        if (ry < height - 1) html += '\n';
      }
      map.innerHTML = html;
    }

    // the walk so far to the server, one at a time, the newest last
    function ask() {
      if (asking) {
        again = true;
        return;
      }
      asking = true;
      sg.mega({ walk: path.join(''), zero: zero.proof }).then(function (answer) {
        asking = false;
        if (answer && answer.sight) {
          learn(answer.sight);
          draw();
          if (answer.word && !done) {
            done = true;
            running = null;
            var word = line('mega-secret', 'Terminal piętra zero. Na ekranie jedno słowo dla Safeguarda: ');
            word.appendChild(code(answer.word));
            return;
          }
        }
        if (again) {
          again = false;
          ask();
        } else if (running) {
          keepRunning();
        }
      });
    }

    function move(dir) {
      if (done || !open(x + STEPS[dir][0], y + STEPS[dir][1])) return false;
      x += STEPS[dir][0];
      y += STEPS[dir][1];
      if (path.length && path[path.length - 1] === BACK[dir]) path.pop();
      else path.push(dir);
      draw();
      ask();
      return true;
    }

    // on along a corridor, until it turns, splits or ends; where it has not
    // been seen yet, on once the server has shown it
    function keepRunning() {
      var dir = running;
      var sides = dir === 'U' || dir === 'D' ? ['L', 'R'] : ['U', 'D'];
      while (!done && running === dir) {
        if (sides.some(function (s) { return open(x + STEPS[s][0], y + STEPS[s][1]); })) break;
        var ahead = known[(x + STEPS[dir][0]) + ',' + (y + STEPS[dir][1])];
        if (ahead === undefined) return;
        if (!move(dir)) break;
      }
      running = null;
    }

    function run(dir) {
      running = null;
      if (!move(dir)) return;
      running = dir;
      keepRunning();
    }

    document.addEventListener('keydown', function (e) {
      var dir = KEYS[(e.key || '').toLowerCase()];
      var t = e.target;
      if (!dir || done || e.ctrlKey || e.metaKey || e.altKey) return;
      if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
      if (document.body.classList.contains('ns-open') || document.body.classList.contains('pl-open')) return;
      e.preventDefault();
      running = null;
      move(dir);
    });

    var touch = null;
    map.addEventListener('touchstart', function (e) {
      touch = e.touches.length === 1 ? [e.touches[0].clientX, e.touches[0].clientY] : null;
    }, { passive: true });
    map.addEventListener('touchend', function (e) {
      if (!touch || !e.changedTouches.length) return;
      var dx = e.changedTouches[0].clientX - touch[0], dy = e.changedTouches[0].clientY - touch[1];
      touch = null;
      if (Math.max(Math.abs(dx), Math.abs(dy)) < 20) return;
      run(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'R' : 'L') : (dy > 0 ? 'D' : 'U'));
    });

    learn(zero.maze.sight);
    draw();
  }

  function door() {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mega-door hud-corners';
    button.textContent = '▯ Uchylone drzwi Budowniczych';
    mega.appendChild(button);
    button.addEventListener('click', function () {
      button.disabled = true;
      sg.mega({ door: 1 }).then(function (answer) {
        if (!answer || !answer.fragment) {
          button.textContent = 'Drzwi się zamknęły. Budowniczowie wrócili do pracy.';
          return;
        }
        button.remove();
        var got = line('mega-secret', 'Za drzwiami Budowniczych, fragment sekwencji 1/4: ');
        got.appendChild(code(answer.fragment));
        var way = line('mega-secret', 'Korytarz prowadzi dalej: ');
        way.appendChild(code(answer.corridor));
        sg.fragment(1, answer.fragment);
        sg.found('builders', answer.proof);
      });
    });
  }

  // The Netsphere's archive, past the gene (inc/megastructure.php): the
  // Administration's records, the last one ciphered, and where its key is.
  function archive(data) {
    var box = document.querySelector('.not-found');
    box.classList.add('mega-archive');
    document.title = 'Netsfera · Sanakan';
    box.querySelector('h2').textContent = 'Netsfera · archiwum Administracji';
    box.querySelector('h2 + p').textContent = 'Połączenie z Netsferą nawiązane. Dostęp przyznany przez gen terminala sieciowego.';
    mega.hidden = true;

    var records = document.createElement('ol');
    records.className = 'mega-records';
    function record(text, extra) {
      var li = document.createElement('li');
      li.appendChild(document.createTextNode(text));
      if (extra) li.appendChild(extra);
      records.appendChild(li);
    }
    record('Populacja utraciła gen terminala sieciowego. Kontrolę nad Megastrukturą przejmuje Safeguard.');
    record('Budowniczowie nie przyjmują rozkazów. Rozbudowa trwa bez planu.');
    var cipher = document.createElement('code');
    cipher.className = 'mega-cipher';
    cipher.textContent = data.record;
    record('Ostatni zapis Administracji, zaszyfrowany:', cipher);
    record('Szyfr przesuwa każdą literę zapisu o literę klucza pod nią, A o zero, B o jeden. Klucz biegnie dalej tylko po literach.');
    var key = document.createElement('ul');
    key.className = 'mega-key';
    data.key.forEach(function (place) {
      var li = document.createElement('li');
      li.textContent = place[0] + ' · słowo ' + place[1] + ' · litera ' + place[2];
      key.appendChild(li);
    });
    record('Klucz rozproszono w rejestrze Safeguarda, w opisach wpisów:', key);
    record('Kto odczyta zapis, niech poda go terminalowi Netsfery.');
    box.appendChild(records);
  }

  sg.mega({ at: path }).then(function (answer) {
    if (!answer) return;
    if (answer.archive) {
      archive(answer.archive);
      return;
    }
    if (answer.zero) floorZero(answer.zero);
    if (answer.draught) {
      var draught = line('mega-secret', 'Czuć przeciąg. Ciągnie od: ');
      draught.appendChild(code(answer.draught));
    }
    if (answer.door) door();
  });
})();
