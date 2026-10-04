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
    info.textContent = words.length
      ? 'Znaleziono ' + found + ' ' + plural(found, 'element', 'elementy', 'elementów')
        + (input.dataset.searching === '1' ? ' wśród wyników' : ' w tym folderze') + ' · Enter: szukaj w całej galerii'
      : '';
  }

  // typing filters what is shown; Enter searches the whole gallery on the server,
  // and Enter on an empty box goes back from the results to the folder
  function searchEverywhere() {
    var query = input.value.trim();
    var dir = input.dataset.dir;
    if (!query && input.dataset.searching !== '1') return;

    location.href = query
      ? '?q=' + encodeURIComponent(query) + (dir ? '&p=' + encodeURIComponent(dir) : '')
      : (dir ? '?p=' + encodeURIComponent(dir) : './');
  }

  if (input) {
    input.addEventListener('input', filter);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchEverywhere();
      } else if (e.key === 'Escape') {
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

  // Video tiles show their first frame, loaded only when the tile comes into
  // view, and play without sound while the mouse is over them
  var tileVideos = Array.prototype.slice.call(grid.querySelectorAll('.tile-video'));

  function loadTileVideo(video) {
    if (video.src) return;
    video.preload = 'metadata';
    video.src = video.dataset.src;
  }

  if ('IntersectionObserver' in window) {
    var watcher = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        loadTileVideo(entry.target);
        watcher.unobserve(entry.target);
      });
    }, { rootMargin: '200px' });
    tileVideos.forEach(function (video) { watcher.observe(video); });
  } else {
    tileVideos.forEach(loadTileVideo);
  }

  tileVideos.forEach(function (video) {
    var tile = video.closest('.tile');
    tile.addEventListener('mouseenter', function () {
      loadTileVideo(video);
      video.play().catch(function () {});
    });
    tile.addEventListener('mouseleave', function () {
      video.pause();
    });
  });

  // Viewer: pictures and videos open in place, other files open in a new tab
  var viewer = document.getElementById('viewer');
  var image = document.getElementById('viewer-img');
  var video = document.getElementById('viewer-video');
  var loading = document.getElementById('viewer-loading');
  var nameEl = document.getElementById('viewer-name');
  var detailsEl = document.getElementById('viewer-details');
  var openLink = document.getElementById('viewer-open');
  var copyButton = document.getElementById('viewer-copy');
  var current = null;

  function viewable(tile) {
    return tile.dataset.kind === 'image' || tile.dataset.kind === 'video';
  }

  // the pictures and videos in their current order, without the ones the search hides
  function pictures() {
    return tiles.filter(function (tile) {
      return !tile.hidden && viewable(tile);
    });
  }

  function stopVideo() {
    video.pause();
    video.removeAttribute('src');
    video.load();
  }

  function show(tile) {
    current = tile;
    nameEl.textContent = tile.dataset.title;
    detailsEl.textContent = tile.dataset.details;
    openLink.href = tile.getAttribute('href');

    loading.hidden = false;
    image.hidden = true;
    video.hidden = true;

    if (tile.dataset.kind === 'video') {
      image.removeAttribute('src');
      video.src = tile.getAttribute('href');
      video.play().catch(function () {});
    } else {
      stopVideo();
      image.src = tile.getAttribute('href');
      image.alt = tile.dataset.title;
    }
  }

  image.addEventListener('load', function () {
    loading.hidden = true;
    image.hidden = false;
  });

  // the player shows as soon as the size is known, so big videos show their controls while loading
  video.addEventListener('loadedmetadata', function () {
    loading.hidden = true;
    video.hidden = false;
  });

  function loadFailed() {
    loading.textContent = 'Nie udało się wczytać pliku';
  }

  image.addEventListener('error', loadFailed);
  video.addEventListener('error', function () {
    if (video.getAttribute('src')) loadFailed();
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
    stopVideo();
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
    if (!viewable(tile)) return;
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
