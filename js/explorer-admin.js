// Managing the gallery in i/ (loaded for the gallery admins, and for an
// account in its own folder, where the server allows less): adding, moving
// and deleting files, new folders and shared links
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

  // "i/..." as the gallery names a path; links have u/<token> for the folder of an account
  function folderLabel(rel) {
    Object.keys(data.labels).forEach(function (link) {
      if (rel === link || rel.indexOf(link + '/') === 0) rel = data.labels[link] + rel.slice(link.length);
    });
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

  // the page reloads after a change and shows the result, with the search and
  // sorting kept, and the picture $openRel (if any) open in the viewer again
  function reloadWith(message, error, openRel) {
    gallery.flashAfterReload(message, error);
    gallery.keepView(openRel);
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

  // "change to WebP" (only when the server can write WebP): ticked unless it was
  // unticked before in this browser
  var webpBox = document.getElementById('upload-webp');
  if (webpBox) {
    webpBox.checked = true;
    try {
      webpBox.checked = localStorage.getItem('gallery-webp') !== '0';
    } catch (err) {
      // no storage, the box stays ticked
    }
    webpBox.addEventListener('change', function () {
      try {
        localStorage.setItem('gallery-webp', webpBox.checked ? '1' : '0');
      } catch (err) {
        // not remembered then
      }
    });
  }
  var progress = document.getElementById('progress');
  var progressText = document.getElementById('progress-text');
  var progressFill = document.getElementById('progress-fill');
  var uploading = false;

  // places in the gallery with the same content, by SHA-256; none when the browser
  // cannot hash (crypto.subtle needs https) or the check fails, the upload goes on then
  function findDuplicates(file) {
    if (!window.crypto || !crypto.subtle || !file.arrayBuffer) return Promise.resolve([]);

    return file.arrayBuffer().then(function (buffer) {
      return crypto.subtle.digest('SHA-256', buffer);
    }).then(function (digest) {
      var hash = Array.prototype.map.call(new Uint8Array(digest), function (byte) {
        return ('0' + byte.toString(16)).slice(-2);
      }).join('');
      return post({ action: 'duplicates', size: String(file.size), hash: hash });
    }).then(function (result) {
      return result.ok && result.matches ? result.matches : [];
    }).catch(function () {
      return [];
    });
  }

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
        problems.push(file.name + ': można dodawać tylko ' + data.types.join(', ') + '.');
        return next();
      }
      if (data.uploadLimit && file.size > data.uploadLimit) {
        problems.push(file.name + ': plik jest za duży (limit serwera: ' + data.uploadLimitLabel + ').');
        return next();
      }

      progressText.textContent = label + ' (sprawdzanie, czy już jest w galerii)';
      progressFill.style.width = '0';

      // the same content already in the gallery? asked before sending, so a big file is not sent twice
      findDuplicates(file).then(function (matches) {
        if (matches.length && !window.confirm(file.name + ' już jest w galerii:\n' + matches.join('\n') + '\n\nDodać mimo to?')) {
          problems.push(file.name + ': pominięto, już jest w galerii (' + matches[0] + ').');
          return next();
        }

        post({ action: 'upload', dir: data.dir, webp: webpBox && webpBox.checked ? '1' : '0' }, file, function (part) {
          progressText.textContent = label + ' (' + Math.round(part * 100) + '%)';
          progressFill.style.width = (part * 100) + '%';
        }).then(function (result) {
          if (result.ok) added++;
          else problems.push(result.message);
          next();
        });
      });
    }

    next();
  }

  // the search results have no single folder to add to
  if (data.dir !== null) {
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

    // a picture pasted with Ctrl+V, e.g. a screenshot; text pasted into a field stays text
    document.addEventListener('paste', function (e) {
      var files = e.clipboardData ? Array.prototype.slice.call(e.clipboardData.files) : [];
      if (!files.length || document.querySelector('dialog[open]')) return;
      e.preventDefault();
      uploadFiles(files.map(pastedFile));
    });

    // browsers call every pasted screenshot "image.png", so it gets the time instead
    function pastedFile(file) {
      if (!/^image\.\w+$/i.test(file.name)) return file;

      var now = new Date();
      function two(n) { return ('0' + n).slice(-2); }
      var name = 'wklejone ' + now.getFullYear() + '-' + two(now.getMonth() + 1) + '-' + two(now.getDate())
        + ' ' + two(now.getHours()) + '-' + two(now.getMinutes()) + '-' + two(now.getSeconds())
        + '.' + file.name.split('.').pop().toLowerCase();
      try {
        return new File([file], name, { type: file.type, lastModified: now.getTime() });
      } catch (err) {
        return file;
      }
    }

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
  }

  // ---- Share ----

  // a link that opens the folder without a login; the second press of the
  // button copies the link the first one made
  var shareButton = document.getElementById('act-share');
  if (shareButton) {
    var shareDialog = document.getElementById('dlg-share');
    var shareForm = document.getElementById('form-share');
    var shareSubmit = shareForm.querySelector('button[type="submit"]');

    shareButton.addEventListener('click', function () {
      shareForm.reset();
      shareForm.elements.link.hidden = true;
      shareForm.elements.days.hidden = false;
      shareSubmit.textContent = 'Utwórz link';
      dialogError(shareDialog, '');
      shareDialog.showModal();
    });

    shareForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var link = shareForm.elements.link;
      if (!link.hidden) {
        link.select();
        var copied = navigator.clipboard && window.isSecureContext
          ? navigator.clipboard.writeText(link.value)
          : Promise.resolve(document.execCommand('copy'));
        copied.then(function () {
          shareSubmit.textContent = 'Skopiowano';
        });
        return;
      }

      busy(shareDialog, true);
      post({ action: 'share', dir: data.dir, days: shareForm.elements.days.value }).then(function (result) {
        busy(shareDialog, false);
        if (!result.ok) return dialogError(shareDialog, result.message);
        link.value = data.shareUrl + result.token;
        link.hidden = false;
        shareForm.elements.days.hidden = true;
        shareSubmit.textContent = 'Kopiuj link';
        link.focus();
        link.select();
      });
    });
  }

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
    document.getElementById('act-rename').disabled = n !== 1;
    document.getElementById('act-delete').disabled = n === 0;
    var images = picked().filter(rotatable).length;
    document.getElementById('act-rotate-left').disabled = images === 0;
    document.getElementById('act-rotate-right').disabled = images === 0;
    if (webpButton) webpButton.disabled = picked().filter(webpable).length === 0;
    if (zipButton) zipButton.disabled = n === 0;
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

  // ---- Rotate ----

  // what GD can turn: PNG, JPG and WebP pictures (an animated WebP the server skips)
  function rotatable(tile) {
    return tile.classList.contains('file') && /\.(png|jpe?g|webp)$/i.test(tile.dataset.rel);
  }

  function rotate(direction) {
    var tiles = picked().filter(rotatable);
    if (!tiles.length) return;
    post({ action: 'rotate', direction: direction, items: tiles.map(function (tile) { return tile.dataset.rel; }) }).then(function (result) {
      if (result.ok) reloadWith(result.message, result.message.indexOf('Pominięto') !== -1);
      else gallery.toast(result.message, true);
    });
  }

  document.getElementById('act-rotate-left').addEventListener('click', function () { rotate('left'); });
  document.getElementById('act-rotate-right').addEventListener('click', function () { rotate('right'); });

  // ---- Change to WebP (gallery admins) ----
  // One picture per request, as a big GIF takes a while; the server keeps the
  // WebP only when it is smaller and moves the original to the trash.

  var webpButton = document.getElementById('act-webp');

  function webpable(tile) {
    return tile.classList.contains('file') && /\.(png|jpe?g|gif)$/i.test(tile.dataset.rel);
  }

  if (webpButton) {
    webpButton.addEventListener('click', function () {
      var tiles = picked().filter(webpable);
      if (!tiles.length) return;
      if (!window.confirm('Zamienić na WebP: ' + describe(tiles) + '? Oryginały trafią do kosza, a ich stare linki będą otwierać WebP. '
        + 'Te, które jako WebP nie wyjdą wyraźnie mniejsze, zostaną bez zmian.')) return;

      var changed = 0;
      var skipped = [];
      webpButton.disabled = true;
      tiles.reduce(function (previous, tile, i) {
        return previous.then(function () {
          gallery.toast('Zamieniam na WebP ' + (i + 1) + ' z ' + tiles.length + '…');
          return post({ action: 'webp', items: [tile.dataset.rel] }).then(function (result) {
            if (!result.ok) skipped.push((tile.dataset.title || tile.dataset.rel) + ' (' + result.message + ')');
            changed += result.changed || 0;
            skipped = skipped.concat(result.skipped || []);
          });
        });
      }, Promise.resolve()).then(function () {
        var message = changed ? 'Zamieniono na WebP ' + countLabel(changed) + ', oryginały są w koszu.' : 'Nic nie zamieniono.';
        if (skipped.length) message += ' Bez zmian: ' + skipped.join(', ') + '.';
        if (changed) reloadWith(message, skipped.length > 0);
        else {
          gallery.toast(message, true);
          webpButton.disabled = false;
        }
      });
    });
  }

  // ---- ZIP of the picked items ----

  // a real form, so the browser saves the answer as a file
  var zipButton = document.getElementById('act-zip');
  if (zipButton) {
    zipButton.addEventListener('click', function () {
      var tiles = picked();
      if (!tiles.length) return;

      var form = document.createElement('form');
      form.method = 'POST';
      form.action = 'index.php';
      form.hidden = true;
      var fields = [['csrf', data.csrf], ['action', 'zip'], ['dir', data.dir || ''], ['back', location.search || './']];
      tiles.forEach(function (tile) { fields.push(['items[]', tile.dataset.rel]); });
      fields.forEach(function (field) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = field[0];
        input.value = field[1];
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
      form.remove();
      gallery.toast('Pakowanie ' + countLabel(tiles.length) + ', pobieranie zaraz się zacznie.');
    });
  }

  // ---- Rename ----

  var renameDialog = document.getElementById('dlg-rename');
  var renameForm = document.getElementById('form-rename');
  // what the dialog renames, and whether the viewer shows it again afterwards
  var renaming = null;
  var renameReopens = false;

  function askRename(tile, reopen) {
    renaming = tile;
    renameReopens = reopen;
    var current = tile.dataset.rel.split('/').pop();

    document.getElementById('rename-what').textContent = folderLabel(tile.dataset.rel);
    renameForm.elements.name.value = current;
    dialogError(renameDialog, '');
    renameDialog.showModal();

    // the name without the extension is selected, ready to type over
    var dot = tile.classList.contains('folder') ? -1 : current.lastIndexOf('.');
    renameForm.elements.name.setSelectionRange(0, dot > 0 ? dot : current.length);
  }

  document.getElementById('act-rename').addEventListener('click', function () {
    var tiles = picked();
    if (tiles.length === 1) askRename(tiles[0], false);
  });

  renameForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var rel = renaming.dataset.rel;
    var name = renameForm.elements.name.value.trim();
    busy(renameDialog, true);
    post({ action: 'rename', items: [rel], name: name }).then(function (result) {
      busy(renameDialog, false);
      if (result.ok) reloadWith(result.message, false, renameReopens ? rel.slice(0, rel.lastIndexOf('/') + 1) + name : null);
      else dialogError(renameDialog, result.message);
    });
  });

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
  // what the dialog deletes, and the picture the viewer shows afterwards
  var deleting = [];
  var deleteThenOpen = null;

  function askDelete(tiles, thenOpen) {
    if (!tiles.length) return;
    deleting = tiles;
    deleteThenOpen = thenOpen;
    var folders = tiles.filter(function (tile) { return tile.classList.contains('folder'); }).length;

    document.getElementById('delete-what').textContent = 'Usunąć ' + countLabel(tiles.length) + ': ' + describe(tiles) + '?' +
      (folders ? ' Foldery razem z całą zawartością.' : '');
    dialogError(deleteDialog, '');
    deleteDialog.showModal();
  }

  document.getElementById('act-delete').addEventListener('click', function () {
    askDelete(picked(), null);
  });

  deleteForm.addEventListener('submit', function (e) {
    e.preventDefault();
    busy(deleteDialog, true);
    post({ action: 'delete', items: deleting.map(function (tile) { return tile.dataset.rel; }) }).then(function (result) {
      busy(deleteDialog, false);
      if (result.ok) reloadWith(result.message, false, deleteThenOpen);
      else dialogError(deleteDialog, result.message);
    });
  });

  // ---- The picture open in the viewer ----
  // renamed or deleted on its own, without picking it; the viewer comes back
  // with it renamed, or with the next picture after a delete

  function renameViewed() {
    var tile = gallery.viewing();
    if (tile) askRename(tile, true);
  }

  function deleteViewed() {
    var tile = gallery.viewing();
    if (!tile) return;
    var list = gallery.pictures();
    var index = list.indexOf(tile);
    var next = list[index + 1] || list[index - 1];
    askDelete([tile], next ? next.dataset.rel : null);
  }

  document.getElementById('viewer-rename').addEventListener('click', renameViewed);
  document.getElementById('viewer-delete').addEventListener('click', deleteViewed);

  // Esc leaves picking, Delete asks to delete what is picked; in the viewer
  // Delete and F2 delete or rename the picture shown
  document.addEventListener('keydown', function (e) {
    if (document.querySelector('dialog[open]')) return;
    var tag = document.activeElement && document.activeElement.tagName;
    if (tag === 'INPUT' || tag === 'SELECT') return;

    if (!document.getElementById('viewer').hidden) {
      if (e.key === 'Delete') deleteViewed();
      else if (e.key === 'F2') {
        e.preventDefault();
        renameViewed();
      }
      return;
    }
    if (!selecting) return;
    if (e.key === 'Escape') setSelecting(false);
    else if (e.key === 'Delete') askDelete(picked(), null);
  });

  updateSelection();
})();
