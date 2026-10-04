// Picture explorer in i/: messages, search, sorting and the viewer

// Short messages at the bottom of the page. A message can also wait for the
// next page view, e.g. the result of a change before the page reloads.
window.SanakanGallery = (function () {
  var toast = document.getElementById('toast');
  var timer;
  var KEY = 'gallery-flash';

  function show(message, error) {
    toast.textContent = message;
    toast.classList.toggle('error', !!error);
    toast.hidden = false;
    clearTimeout(timer);
    timer = setTimeout(function () { toast.hidden = true; }, error ? 12000 : 6000);
  }

  function flashAfterReload(message, error) {
    try {
      sessionStorage.setItem(KEY, JSON.stringify({ message: message, error: !!error }));
    } catch (err) {
      // without storage the message is just lost
    }
  }

  if (toast) {
    toast.addEventListener('click', function () { toast.hidden = true; });
    if (!toast.hidden) show(toast.textContent);

    try {
      var waiting = JSON.parse(sessionStorage.getItem(KEY) || 'null');
      sessionStorage.removeItem(KEY);
      if (waiting) show(waiting.message, waiting.error);
    } catch (err) {
      // nothing waiting
    }
  }

  // logging out works for everyone logged in, also without the admin script
  var logout = document.getElementById('act-logout');
  if (logout) {
    logout.addEventListener('click', function () {
      var form = new FormData();
      form.append('action', 'logout');
      form.append('csrf', logout.dataset.csrf);
      fetch('index.php', { method: 'POST', body: form }).then(function (res) {
        return res.json();
      }).then(function (result) {
        flashAfterReload(result.message, !result.ok);
      }).catch(function () {
        flashAfterReload('Nie udało się wylogować.', true);
      }).then(function () {
        location.reload();
      });
    });
  }

  return { toast: show, flashAfterReload: flashAfterReload };
})();

(function () {
  var grid = document.getElementById('grid');
  if (!grid) return;

  // the ".." tile stays first and is not searched or sorted
  var tiles = Array.prototype.slice.call(grid.querySelectorAll('.tile:not(.up)'));
  var up = document.getElementById('ex-up');

  // Polish plural: 1 plik, 2-4 pliki, 5+ plików (but 12-14 plików)
  function plural(n, one, few, many) {
    if (n === 1) return one;
    var last = n % 10, lastTwo = n % 100;
    return last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14) ? few : many;
  }

  // Search by name
  var input = document.getElementById('ex-search');
  var info = document.getElementById('ex-search-info');
  var noResults = document.getElementById('ex-no-results');

  function filter() {
    var words = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    var found = 0;

    tiles.forEach(function (tile) {
      var match = words.every(function (word) {
        return tile.dataset.name.indexOf(word) !== -1;
      });
      tile.hidden = !match;
      if (match) found++;
    });

    noResults.hidden = found > 0;
    info.textContent = words.length ? 'Znaleziono ' + found + ' ' + plural(found, 'element', 'elementy', 'elementów') : '';
  }

  if (input) {
    input.addEventListener('input', filter);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        input.value = '';
        filter();
      }
    });
  }

  // Sorting: folders always first; names A-Z, newest first, biggest first
  var collator = new Intl.Collator('pl', { numeric: true, sensitivity: 'base' });
  var sortButtons = document.querySelectorAll('.sort button');

  function sortBy(key) {
    tiles.sort(function (a, b) {
      var folderA = a.classList.contains('folder'), folderB = b.classList.contains('folder');
      if (folderA !== folderB) return folderA ? -1 : 1;
      if (key === 'date' && a.dataset.date !== b.dataset.date) return b.dataset.date - a.dataset.date;
      if (key === 'size' && a.dataset.size !== b.dataset.size) return b.dataset.size - a.dataset.size;
      return collator.compare(a.dataset.name, b.dataset.name);
    });
    tiles.forEach(function (tile) {
      grid.appendChild(tile);
    });

    sortButtons.forEach(function (button) {
      button.setAttribute('aria-pressed', button.dataset.sort === key ? 'true' : 'false');
    });
  }

  sortButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      sortBy(button.dataset.sort);
    });
  });

  // Viewer: pictures open in place, other files open in a new tab
  var viewer = document.getElementById('viewer');
  var image = document.getElementById('viewer-img');
  var loading = document.getElementById('viewer-loading');
  var nameEl = document.getElementById('viewer-name');
  var detailsEl = document.getElementById('viewer-details');
  var openLink = document.getElementById('viewer-open');
  var copyButton = document.getElementById('viewer-copy');
  var current = null;

  // the pictures in their current order, without the ones the search hides
  function pictures() {
    return tiles.filter(function (tile) {
      return !tile.hidden && tile.dataset.image === '1';
    });
  }

  function show(tile) {
    current = tile;
    nameEl.textContent = tile.dataset.title;
    detailsEl.textContent = tile.dataset.details;
    openLink.href = tile.getAttribute('href');

    loading.hidden = false;
    image.hidden = true;
    image.src = tile.getAttribute('href');
    image.alt = tile.dataset.title;
  }

  image.addEventListener('load', function () {
    loading.hidden = true;
    image.hidden = false;
  });

  image.addEventListener('error', function () {
    loading.textContent = 'Nie udało się wczytać obrazka';
  });

  function open(tile) {
    loading.textContent = 'Wczytywanie…';
    show(tile);
    viewer.hidden = false;
    document.body.classList.add('viewer-open');
    document.getElementById('viewer-close').focus();
  }

  function close() {
    viewer.hidden = true;
    document.body.classList.remove('viewer-open');
    image.removeAttribute('src');
    if (current) current.focus();
  }

  function step(delta) {
    var list = pictures();
    var index = list.indexOf(current);
    if (index === -1 || list.length < 2) return;
    loading.textContent = 'Wczytywanie…';
    show(list[(index + delta + list.length) % list.length]);
  }

  tiles.forEach(function (tile) {
    if (tile.dataset.image !== '1') return;
    tile.addEventListener('click', function (e) {
      // ctrl/cmd/middle click still opens the file in a new tab
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      open(tile);
    });
  });

  document.getElementById('viewer-close').addEventListener('click', close);
  document.getElementById('viewer-prev').addEventListener('click', function () { step(-1); });
  document.getElementById('viewer-next').addEventListener('click', function () { step(1); });

  // a click on the dark background closes the viewer
  document.getElementById('viewer-stage').addEventListener('click', function (e) {
    if (e.target === this) close();
  });

  // full link to the picture, e.g. to paste on Discord
  if (navigator.clipboard && window.isSecureContext) {
    copyButton.addEventListener('click', function () {
      navigator.clipboard.writeText(new URL(current.getAttribute('href'), location.href).href).then(function () {
        copyButton.textContent = 'Skopiowano';
        copyButton.classList.add('done');
        clearTimeout(copyButton.resetTimer);
        copyButton.resetTimer = setTimeout(function () {
          copyButton.textContent = 'Kopiuj link';
          copyButton.classList.remove('done');
        }, 1500);
      });
    });
  } else {
    copyButton.hidden = true;
  }

  document.addEventListener('keydown', function (e) {
    if (!viewer.hidden) {
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowLeft') step(-1);
      else if (e.key === 'ArrowRight') step(1);
      return;
    }

    // "/" jumps to the search, Backspace or Alt+Up goes one folder up,
    // unless the user is typing somewhere
    var tag = document.activeElement && document.activeElement.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;

    if (input && e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
      e.preventDefault();
      input.focus();
    } else if (up && ((e.key === 'Backspace' && !e.altKey) || (e.key === 'ArrowUp' && e.altKey))) {
      e.preventDefault();
      location.href = up.href;
    }
  });
})();
