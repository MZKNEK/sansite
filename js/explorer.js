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

  return { toast: show, flashAfterReload: flashAfterReload };
})();

(function () {
  var grid = document.getElementById('grid');
  if (!grid) return;

  // the ".." tile stays first and is not searched or sorted
  var tiles = Array.prototype.slice.call(grid.querySelectorAll('.tile:not(.up)'));
  var up = document.getElementById('ex-up');

  var plural = window.SanakanUtil.plural;

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
  var sortKey = 'name';

  function sortBy(key) {
    sortKey = key;
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

  // A thumbnail the server did not make in time (it makes one at a time and
  // says 503 while busy) is asked for again, a few times, later each time.
  // Errors do not bubble, so they are caught on the way down; the ones from
  // before this script ran are found by the empty pictures they left.
  function retryThumb(img) {
    var tries = Number(img.dataset.tries || 0);
    if (tries >= 4 || img.src.indexOf('thumb=') === -1) return;
    img.dataset.tries = tries + 1;
    setTimeout(function () {
      img.src = img.src.replace(/&retry=\d+$/, '') + '&retry=' + (tries + 1);
    }, 1500 * Math.pow(2, tries));
  }

  document.addEventListener('error', function (e) {
    if (e.target.tagName === 'IMG' && e.target.closest('.thumb')) retryThumb(e.target);
  }, true);
  document.querySelectorAll('.thumb img').forEach(function (img) {
    if (img.complete && img.getAttribute('src') && img.naturalWidth === 0) retryThumb(img);
  });

  // Viewer: pictures and videos open in place, other files open in a new tab
  var viewer = document.getElementById('viewer');
  var image = document.getElementById('viewer-img');
  var video = document.getElementById('viewer-video');
  var loading = document.getElementById('viewer-loading');

  // The volume set in the viewer is kept, so a film does not blast at full
  // volume every time; half is a calmer start than the browser's full.
  var VOLUME_KEY = 'gallery-volume';

  function savedVolume() {
    try {
      var value = parseFloat(localStorage.getItem(VOLUME_KEY));
      if (isFinite(value) && value >= 0 && value <= 1) return value;
    } catch (err) {
      // no storage, the default stands
    }
    return 0.5;
  }

  video.volume = savedVolume();
  video.addEventListener('volumechange', function () {
    try {
      localStorage.setItem(VOLUME_KEY, String(video.volume));
    } catch (err) {
      // not remembered then
    }
  });
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

  // Zoom of the picture or film open in the viewer: the wheel zooms towards the
  // pointer, once zoomed a drag moves it, and a double click goes back to fit.
  var ZOOM_MAX = 8;
  var zoom = { scale: 1, x: 0, y: 0, baseW: 0, baseH: 0 };
  var stageEl = document.getElementById('viewer-stage');
  // what the viewer shows now, the picture or the film
  var media = image;
  var panStart = null;
  var panMoved = false;
  var panStartedOnBackground = false;

  // the box the media is centred in, without the stage's padding
  function stageBox() {
    var cs = getComputedStyle(stageEl);
    var r = stageEl.getBoundingClientRect();
    var left = parseFloat(cs.paddingLeft);
    var top = parseFloat(cs.paddingTop);
    var w = stageEl.clientWidth - left - parseFloat(cs.paddingRight);
    var h = stageEl.clientHeight - top - parseFloat(cs.paddingBottom);

    return { w: w, h: h, cx: r.left + left + w / 2, cy: r.top + top + h / 2 };
  }

  function applyZoom() {
    media.style.transform = 'translate(' + zoom.x + 'px, ' + zoom.y + 'px) scale(' + zoom.scale + ')';
    viewer.classList.toggle('zoomed', zoom.scale > 1);
  }

  function resetZoom() {
    zoom.scale = 1;
    zoom.x = 0;
    zoom.y = 0;
    zoom.baseW = 0;
    zoom.baseH = 0;
    image.style.transform = '';
    video.style.transform = '';
    viewer.classList.remove('zoomed', 'panning');
    panStart = null;
    panMoved = false;
  }

  // the size the media has when it fits, before any zoom
  function rememberBase() {
    media.style.transform = '';
    var r = media.getBoundingClientRect();
    zoom.baseW = r.width;
    zoom.baseH = r.height;
  }

  function clampZoom() {
    var box = stageBox();
    var maxX = Math.max(0, (zoom.baseW * zoom.scale - box.w) / 2);
    var maxY = Math.max(0, (zoom.baseH * zoom.scale - box.h) / 2);
    zoom.x = Math.max(-maxX, Math.min(maxX, zoom.x));
    zoom.y = Math.max(-maxY, Math.min(maxY, zoom.y));
  }

  stageEl.addEventListener('wheel', function (e) {
    if (media.hidden) return;
    e.preventDefault();
    if (!zoom.baseW) rememberBase();

    var dy = e.deltaMode === 1 ? e.deltaY * 16 : e.deltaY;
    var next = Math.max(1, Math.min(ZOOM_MAX, zoom.scale * Math.exp(-dy * 0.0015)));
    if (next === zoom.scale) return;

    // the point under the pointer stays where it is
    var box = stageBox();
    var dx = e.clientX - box.cx;
    var dyy = e.clientY - box.cy;
    var ratio = next / zoom.scale;
    zoom.x = dx - ratio * (dx - zoom.x);
    zoom.y = dyy - ratio * (dyy - zoom.y);
    zoom.scale = next;
    if (zoom.scale === 1) { zoom.x = 0; zoom.y = 0; }
    clampZoom();
    applyZoom();
  }, { passive: false });

  // Once zoomed, dragging moves the media. The pointer capture and the default
  // stop wait for a real movement, so a click on the film's own controls still
  // works; a press on the background (and any drag) never closes the viewer.
  stageEl.addEventListener('pointerdown', function (e) {
    panStartedOnBackground = e.target === stageEl;
    if (e.target.closest('button') || zoom.scale <= 1 || e.button !== 0) return;
    panStart = { x: e.clientX, y: e.clientY, ox: zoom.x, oy: zoom.y, id: e.pointerId };
    panMoved = false;
  });

  stageEl.addEventListener('pointermove', function (e) {
    if (!panStart) return;
    if (!panMoved) {
      if (Math.abs(e.clientX - panStart.x) + Math.abs(e.clientY - panStart.y) <= 3) return;
      panMoved = true;
      viewer.classList.add('panning');
      try {
        stageEl.setPointerCapture(panStart.id);
      } catch (err) {
        // no capture, the drag still works
      }
    }
    e.preventDefault();
    zoom.x = panStart.ox + (e.clientX - panStart.x);
    zoom.y = panStart.oy + (e.clientY - panStart.y);
    clampZoom();
    applyZoom();
  });

  function endPan() {
    if (!panStart) return;
    panStart = null;
    viewer.classList.remove('panning');
  }

  stageEl.addEventListener('pointerup', endPan);
  stageEl.addEventListener('pointercancel', endPan);

  // A drag must not reach the film's own play/pause (its click toggles it); a
  // plain click still does. The press on the media is stopped too, so it does
  // not close the viewer.
  [image, video].forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!panMoved) return;
      panMoved = false;
      e.preventDefault();
      e.stopPropagation();
    });
  });

  // a double click on the media goes back to fit
  stageEl.addEventListener('dblclick', function (e) {
    if (media.hidden) return;
    e.preventDefault();
    resetZoom();
    rememberBase();
  });

  function show(tile) {
    current = tile;
    image.hidden = true;
    video.hidden = true;
    media = tile.dataset.kind === 'video' ? video : image;
    resetZoom();
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
    if (zoom.scale === 1) rememberBase();
  });

  // the player shows as soon as the size is known, so big videos show their controls while loading
  video.addEventListener('loadedmetadata', function () {
    loading.hidden = true;
    video.hidden = false;
    if (zoom.scale === 1) rememberBase();
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
    resetZoom();
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

  // a click on the dark background closes the viewer, unless it ended a drag or
  // began on the picture (setPointerCapture moves its click onto the stage)
  stageEl.addEventListener('click', function (e) {
    if (panMoved) { panMoved = false; return; }
    if (panStartedOnBackground && e.target === this) close();
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
    // a dialog over the viewer (rename, delete) gets the keys for itself
    if (document.querySelector('dialog[open]')) return;

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

  // A change (explorer-admin.js) reloads the page; what was typed into the
  // search, the sorting and the picture to show in the viewer ($openRel, or
  // none) wait for it, so the visitor is back where they were
  var VIEW_KEY = 'gallery-view';

  function keepView(openRel) {
    try {
      sessionStorage.setItem(VIEW_KEY, JSON.stringify({
        url: location.href,
        search: input ? input.value : '',
        sort: sortKey,
        open: openRel || null
      }));
    } catch (err) {
      // without storage the page comes back as it loads
    }
  }

  try {
    var kept = JSON.parse(sessionStorage.getItem(VIEW_KEY) || 'null');
    sessionStorage.removeItem(VIEW_KEY);
    if (kept && kept.url === location.href) {
      if (input && kept.search) {
        input.value = kept.search;
        filter();
      }
      if (kept.sort && kept.sort !== 'name') sortBy(kept.sort);
      var reopen = kept.open && pictures().filter(function (tile) { return tile.dataset.rel === kept.open; })[0];
      if (reopen) open(reopen);
    }
  } catch (err) {
    // nothing kept
  }

  window.SanakanGallery.keepView = keepView;
  window.SanakanGallery.viewing = function () {
    return viewer.hidden ? null : current;
  };
  window.SanakanGallery.pictures = pictures;

})();

// A picture or film uploaded with "change to WebP/WebM" is converted on the
// server after the upload (inc/gallery.php, inc/check-bot.php). The gallery
// panel and the tasks page (i/?zadania=1) carry data-media-jobs; while a job is
// still going its status is asked for again and the rows update; on the gallery
// the page comes back once nothing is left, showing the changed files.
(function () {
  var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-media-jobs]'));
  if (!boxes.length) return;

  var STATUSES = ['pending', 'converting', 'done', 'skipped', 'failed'];

  function setStatus(el, status) {
    STATUSES.forEach(function (name) { el.classList.remove(name); });
    el.classList.add(status);
  }

  function anyActive() {
    return boxes.some(function (box) { return box.dataset.active === '1'; });
  }

  function paintJobs(data) {
    var jobs = data.jobs || [];
    var active = false;
    jobs.forEach(function (job) {
      if (job.active) active = true;
      boxes.forEach(function (box) {
        box.querySelectorAll('.media-job').forEach(function (row) {
          if (row.dataset.job !== job.rel) return;
          setStatus(row, job.status);
          row.dataset.status = job.status;
          row.title = job.text;
          var short = row.querySelector('.media-job-state');
          if (short) short.textContent = job.short;
          var full = row.querySelector('.media-task-state');
          if (full) full.textContent = job.text;
        });
      });
      document.querySelectorAll('.job-badge').forEach(function (badge) {
        if (badge.dataset.job !== job.rel) return;
        setStatus(badge, job.status);
        badge.textContent = job.label;
        badge.hidden = job.status !== 'pending' && job.status !== 'converting' && job.status !== 'failed';
      });
    });
    boxes.forEach(function (box) { box.dataset.active = active ? '1' : '0'; });
    var summary = document.querySelector('[data-media-jobs-summary]');
    if (summary && data.summary) summary.textContent = data.summary;
    return active;
  }

  function pollJobs() {
    fetch('?mediajobs=1', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        var wasActive = anyActive();
        var active = paintJobs(data || {});
        if (wasActive && !active && document.getElementById('grid')) {
          if (window.SanakanGallery && window.SanakanGallery.keepView) window.SanakanGallery.keepView(null);
          location.reload();
          return;
        }
        setTimeout(pollJobs, active ? 5000 : 15000);
      })
      .catch(function () { setTimeout(pollJobs, 15000); });
  }

  if (anyActive()) pollJobs();
})();

// The line of tips in the toolbar takes a lot of room on a phone. A small
// button folds it away, and the choice is kept in this browser so it stays
// folded (or shown) on the next visit. Before any choice a phone starts with
// it folded: dragging files and Ctrl+V do not apply there.
(function () {
  var HINT_KEY = 'gallery-hint';

  document.querySelectorAll('.admin-hint').forEach(function (hint) {
    var content = document.createElement('span');
    content.className = 'admin-hint-content';
    while (hint.firstChild) content.appendChild(hint.firstChild);

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'admin-hint-toggle';

    function setCollapsed(collapsed) {
      hint.classList.toggle('collapsed', collapsed);
      toggle.textContent = collapsed ? '?' : '\u00d7';
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggle.title = collapsed ? 'Pokaż podpowiedź' : 'Ukryj podpowiedź';
      toggle.setAttribute('aria-label', toggle.title);
    }

    var collapsed = window.matchMedia('(max-width: 720px)').matches;
    try {
      var saved = localStorage.getItem(HINT_KEY);
      if (saved) collapsed = saved === 'hidden';
    } catch (err) {}

    toggle.addEventListener('click', function () {
      collapsed = !collapsed;
      setCollapsed(collapsed);
      try { localStorage.setItem(HINT_KEY, collapsed ? 'hidden' : 'shown'); } catch (err) {}
    });

    hint.appendChild(content);
    hint.appendChild(toggle);
    setCollapsed(collapsed);
  });
})();
