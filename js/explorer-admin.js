// Managing the gallery in i/ (loaded only for logged-in admins): adding,
// moving and deleting files and new folders
(function () {
  var data = JSON.parse(document.getElementById('gallery-data').textContent);
  var grid = document.getElementById('grid');
  var gallery = window.SanakanGallery;

  // Polish plural: 1 element, 2-4 elementy, 5+ elementów (but 12-14 elementów)
  function plural(n, one, few, many) {
    if (n === 1) return one;
    var last = n % 10, lastTwo = n % 100;
    return last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14) ? few : many;
  }

  function countLabel(n) {
    return n + ' ' + plural(n, 'element', 'elementy', 'elementów');
  }

  function folderLabel(rel) {
    return rel === '' ? 'i' : 'i/' + rel;
  }

  // POST to the gallery; resolves with {ok, message} also for errors
  function post(fields, file, onProgress) {
    return new Promise(function (resolve) {
      var form = new FormData();
      form.append('csrf', data.csrf);
      Object.keys(fields).forEach(function (key) {
        [].concat(fields[key]).forEach(function (value) {
          form.append(Array.isArray(fields[key]) ? key + '[]' : key, value);
        });
      });
      if (file) form.append('file', file);

      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'index.php');
      if (onProgress) {
        xhr.upload.addEventListener('progress', function (e) {
          if (e.lengthComputable) onProgress(e.loaded / e.total);
        });
      }
      xhr.onload = function () {
        try {
          resolve(JSON.parse(xhr.responseText));
        } catch (err) {
          resolve({ ok: false, message: 'Serwer odpowiedział błędem (' + xhr.status + ').' });
        }
      };
      xhr.onerror = function () {
        resolve({ ok: false, message: 'Brak połączenia z serwerem.' });
      };
      xhr.send(form);
    });
  }

  // the page reloads after a change and shows the result
  function reloadWith(message, error) {
    gallery.flashAfterReload(message, error);
    location.reload();
  }

  function dialogError(dialog, message) {
    var el = dialog.querySelector('.dialog-error');
    el.textContent = message;
    el.hidden = !message;
  }

  function busy(dialog, on) {
    dialog.querySelectorAll('button').forEach(function (button) {
      button.disabled = on;
    });
  }

  document.querySelectorAll('.ex-dialog [data-close]').forEach(function (button) {
    button.addEventListener('click', function () {
      button.closest('dialog').close();
    });
  });

  // ---- Upload ----

  var uploadInput = document.getElementById('upload-input');
  var progress = document.getElementById('progress');
  var progressText = document.getElementById('progress-text');
  var progressFill = document.getElementById('progress-fill');
  var uploading = false;

  function uploadFiles(fileList) {
    var files = Array.prototype.slice.call(fileList);
    if (!files.length || uploading) return;
    uploading = true;

    var added = 0, problems = [], index = 0;
    progress.hidden = false;

    function next() {
      if (index >= files.length) {
        var message = added ? 'Dodano ' + added + ' ' + plural(added, 'plik', 'pliki', 'plików') + '.' : 'Nie dodano żadnego pliku.';
        if (problems.length) message += ' ' + problems.join(' ');
        reloadWith(message, problems.length > 0);
        return;
      }

      var file = files[index++];
      var ext = file.name.split('.').pop().toLowerCase();
      var label = 'Wysyłanie ' + index + '/' + files.length + ': ' + file.name;

      // what the server would refuse anyway is not sent at all
      if (data.types.indexOf(ext) === -1) {
        problems.push(file.name + ': to nie jest obrazek (' + data.types.join(', ') + ').');
        return next();
      }
      if (data.uploadLimit && file.size > data.uploadLimit) {
        problems.push(file.name + ': plik jest za duży (limit serwera: ' + data.uploadLimitLabel + ').');
        return next();
      }

      progressText.textContent = label;
      progressFill.style.width = '0';
      post({ action: 'upload', dir: data.dir }, file, function (part) {
        progressText.textContent = label + ' (' + Math.round(part * 100) + '%)';
        progressFill.style.width = (part * 100) + '%';
      }).then(function (result) {
        if (result.ok) added++;
        else problems.push(result.message);
        next();
      });
    }

    next();
  }

  document.getElementById('act-upload').addEventListener('click', function () {
    uploadInput.click();
  });

  uploadInput.addEventListener('change', function () {
    uploadFiles(uploadInput.files);
  });

  // files dragged from the computer onto the page
  var drop = document.getElementById('drop');
  var dragDepth = 0;

  function draggingFiles(e) {
    return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') !== -1;
  }

  document.addEventListener('dragenter', function (e) {
    if (!draggingFiles(e)) return;
    dragDepth++;
    drop.hidden = false;
  });

  document.addEventListener('dragleave', function (e) {
    if (!draggingFiles(e)) return;
    if (--dragDepth <= 0) {
      dragDepth = 0;
      drop.hidden = true;
    }
  });

  document.addEventListener('dragover', function (e) {
    if (draggingFiles(e)) e.preventDefault();
  });

  document.addEventListener('drop', function (e) {
    if (!draggingFiles(e)) return;
    e.preventDefault();
    dragDepth = 0;
    drop.hidden = true;
    uploadFiles(e.dataTransfer.files);
  });

  // ---- New folder ----

  var mkdirDialog = document.getElementById('dlg-mkdir');
  var mkdirForm = document.getElementById('form-mkdir');

  document.getElementById('act-mkdir').addEventListener('click', function () {
    mkdirForm.reset();
    dialogError(mkdirDialog, '');
    mkdirDialog.showModal();
  });

  mkdirForm.addEventListener('submit', function (e) {
    e.preventDefault();
    busy(mkdirDialog, true);
    post({ action: 'mkdir', dir: data.dir, name: mkdirForm.elements.name.value.trim() }).then(function (result) {
      busy(mkdirDialog, false);
      if (result.ok) reloadWith(result.message);
      else dialogError(mkdirDialog, result.message);
    });
  });

  // ---- Picking items ----

  var selectButton = document.getElementById('act-select');
  var selectionBar = document.getElementById('admin-selection');
  var countEl = document.getElementById('admin-count');
  var selecting = false;

  function pickable() {
    return Array.prototype.slice.call(grid.querySelectorAll('.tile[data-rel]'));
  }

  function picked() {
    return pickable().filter(function (tile) {
      return tile.classList.contains('selected');
    });
  }

  function updateSelection() {
    var n = picked().length;
    countEl.textContent = 'Zaznaczono: ' + n;
    document.getElementById('act-move').disabled = n === 0;
    document.getElementById('act-delete').disabled = n === 0;
  }

  function setSelecting(on) {
    selecting = on;
    document.body.classList.toggle('selecting', on);
    selectButton.setAttribute('aria-pressed', on ? 'true' : 'false');
    selectButton.textContent = on ? 'Anuluj' : 'Zaznacz';
    selectionBar.hidden = !on;
    if (!on) pickable().forEach(function (tile) { tile.classList.remove('selected'); });
    updateSelection();
  }

  selectButton.addEventListener('click', function () {
    setSelecting(!selecting);
  });

  document.getElementById('act-select-all').addEventListener('click', function () {
    var visible = pickable().filter(function (tile) { return !tile.hidden; });
    var all = visible.every(function (tile) { return tile.classList.contains('selected'); });
    visible.forEach(function (tile) { tile.classList.toggle('selected', !all); });
    updateSelection();
  });

  // while picking, a click marks the tile instead of opening it; this runs
  // before the tile's own handlers and stops them
  grid.addEventListener('click', function (e) {
    if (!selecting) return;
    var tile = e.target.closest('.tile');
    if (!tile) return;
    e.preventDefault();
    e.stopPropagation();
    if (tile.hasAttribute('data-rel')) {
      tile.classList.toggle('selected');
      updateSelection();
    }
  }, true);

  function describe(tiles) {
    var names = tiles.slice(0, 5).map(function (tile) {
      return tile.dataset.title || tile.querySelector('.name').textContent.trim();
    });
    return names.join(', ') + (tiles.length > 5 ? ' i ' + (tiles.length - 5) + ' innych' : '');
  }

  // ---- Move ----

  var moveDialog = document.getElementById('dlg-move');
  var moveForm = document.getElementById('form-move');

  document.getElementById('act-move').addEventListener('click', function () {
    var tiles = picked();
    if (!tiles.length) return;
    var rels = tiles.map(function (tile) { return tile.dataset.rel; });

    document.getElementById('move-what').textContent = countLabel(tiles.length) + ': ' + describe(tiles);
    var select = moveForm.elements.target;
    select.innerHTML = '';
    data.folders.forEach(function (rel) {
      var option = document.createElement('option');
      var depth = rel === '' ? 0 : rel.split('/').length;
      option.value = rel;
      option.textContent = '  '.repeat(depth) + folderLabel(rel);
      // a folder cannot go into itself, and the current folder is where they already are
      option.disabled = rel === data.dir || rels.some(function (moving) {
        return rel === moving || rel.indexOf(moving + '/') === 0;
      });
      select.appendChild(option);
    });
    var first = Array.prototype.find.call(select.options, function (option) { return !option.disabled; });
    if (first) first.selected = true;

    dialogError(moveDialog, first ? '' : 'Nie ma innego folderu, do którego można je przenieść.');
    moveDialog.showModal();
  });

  moveForm.addEventListener('submit', function (e) {
    e.preventDefault();
    busy(moveDialog, true);
    post({
      action: 'move',
      target: moveForm.elements.target.value,
      items: picked().map(function (tile) { return tile.dataset.rel; })
    }).then(function (result) {
      busy(moveDialog, false);
      if (result.ok) reloadWith(result.message, result.message.indexOf('Pominięto') !== -1);
      else dialogError(moveDialog, result.message);
    });
  });

  // ---- Delete ----

  var deleteDialog = document.getElementById('dlg-delete');
  var deleteForm = document.getElementById('form-delete');

  function askDelete() {
    var tiles = picked();
    if (!tiles.length) return;
    var folders = tiles.filter(function (tile) { return tile.classList.contains('folder'); }).length;

    document.getElementById('delete-what').textContent = 'Usunąć ' + countLabel(tiles.length) + ': ' + describe(tiles) + '?' +
      (folders ? ' Foldery zostaną usunięte razem z całą zawartością.' : '');
    dialogError(deleteDialog, '');
    deleteDialog.showModal();
  }

  document.getElementById('act-delete').addEventListener('click', askDelete);

  deleteForm.addEventListener('submit', function (e) {
    e.preventDefault();
    busy(deleteDialog, true);
    post({ action: 'delete', items: picked().map(function (tile) { return tile.dataset.rel; }) }).then(function (result) {
      busy(deleteDialog, false);
      if (result.ok) reloadWith(result.message);
      else dialogError(deleteDialog, result.message);
    });
  });

  // Esc leaves picking, Delete asks to delete what is picked
  document.addEventListener('keydown', function (e) {
    if (!selecting || document.querySelector('dialog[open]') || !document.getElementById('viewer').hidden) return;
    var tag = document.activeElement && document.activeElement.tagName;
    if (tag === 'INPUT' || tag === 'SELECT') return;

    if (e.key === 'Escape') setSelecting(false);
    else if (e.key === 'Delete') askDelete();
  });

  updateSelection();
})();
