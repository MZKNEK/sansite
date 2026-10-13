// The 403 page, shown by nginx for any address it refuses (and by the gallery
// for a folder the account may not see).

// the address that was refused
var path = location.pathname;
try {
  path = decodeURIComponent(path);
} catch (err) {
  // a broken %-code stays as it is
}
document.getElementById('refused').textContent = path + location.search;

// a browser that found the Net Terminal Gene (js/safeguard.js) is recognised,
// though the door stays shut all the same
if (window.SanakanSafeguard && SanakanSafeguard.finds().gene) {
  var gene = document.getElementById('gene');
  gene.textContent = 'rozpoznany';
  gene.className = 'gene';
}

// Whoever stands at the door three minutes is shot at: the Safeguard aims
// from beyond the edge of the screen and fires at the lock. The first time it
// is a find of the register, which the server gives only that long after a
// ticket of when the wait began (megaWitness() in inc/megastructure.php).
(function () {
  var WAIT = 180000;
  // localStorage "safeguard-denied" in seconds tries it out sooner (the
  // server still waits, but in the preview)
  try {
    WAIT = 1000 * Number(localStorage.getItem('safeguard-denied')) || WAIT;
  } catch (err) {
    // no storage: the full wait
  }
  var sg = window.SanakanSafeguard;
  var lock = document.querySelector('.denied .lock-icon');
  if (!lock) return;
  var ticket = null;
  if (sg && !sg.finds().denied)
    sg.ticket('denied').then(function (got) { ticket = got; });

  function fire() {
    // not at a tab nobody looks at: when it is looked at again
    if (document.hidden) {
      document.addEventListener('visibilitychange', function again() {
        if (document.hidden) return;
        document.removeEventListener('visibilitychange', again);
        setTimeout(fire, 1200);
      });
      return;
    }

    var w = document.documentElement.clientWidth;
    var h = document.documentElement.clientHeight;
    var r = lock.getBoundingClientRect();
    var x = r.left + r.width / 2;
    var y = r.top + r.height / 2;
    if (y < 0 || y > h) {
      x = w / 2;
      y = h / 2;
    }
    // from above the right edge of the screen
    var fromX = w + 40;
    var fromY = -40;
    var dx = x - fromX;
    var dy = y - fromY;

    var laser = document.createElement('div');
    laser.className = 'sg-laser';
    laser.style.left = fromX + 'px';
    laser.style.top = fromY + 'px';
    laser.style.width = Math.sqrt(dx * dx + dy * dy) + 'px';
    laser.style.transform = 'rotate(' + Math.atan2(dy, dx) + 'rad)';
    var dot = document.createElement('div');
    dot.className = 'sg-laser-dot';
    dot.style.left = x + 'px';
    dot.style.top = y + 'px';
    document.body.appendChild(laser);
    document.body.appendChild(dot);

    setTimeout(function () {
      dot.remove();
      laser.className = 'sg-laser fire';
      document.body.classList.add('sg-hit');
      lock.classList.add('scorched');
      var verdict = document.querySelectorAll('.denied .scan dd')[1];
      if (verdict) verdict.textContent = 'ogień';
      if (sg && ticket) sg.witness('denied', { ticket: ticket });
      setTimeout(function () {
        laser.remove();
        document.body.classList.remove('sg-hit');
      }, 1500);
    }, 1600);
  }

  setTimeout(fire, WAIT);
})();
