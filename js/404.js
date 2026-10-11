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

  if (window.SanakanSafeguard) SanakanSafeguard.found('lost');
})();
