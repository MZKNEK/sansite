// WAP (wap/), the Weeaboo Animu Playlist: the songs of wap/.mu/ (listed by
// songs.php, given out by song.php) one after another, in their order or
// shuffled, with the gallery player's controls. A folder or files of the
// visitor's own join the list for this tab only: played from blob: addresses,
// their tags read in the browser (tags.js), never sent anywhere; where the
// browser can (showDirectoryPicker), a folder can also be remembered and comes
// back on the next visit. The volume,
// the mute and the shuffle stay in localStorage; nothing plays before the
// visitor asks.
(function () {
  'use strict';

  var VOLUME_KEY = 'wap-volume', MUTED_KEY = 'wap-muted', SHUFFLE_KEY = 'wap-shuffle';
  var BASE_TITLE = document.title;
  var AUDIO_FILE = /\.(?:mp3|ogg|oga|opus|m4a|aac|flac|wav|webm)$/i;

  function $(id) { return document.getElementById(id); }
  var list = $('wap-list'), status = $('wap-status'), count = $('wap-count');
  var stateLabel = $('wap-state'), position = $('wap-position');
  var titleLine = $('wap-title'), artistLine = $('wap-artist');
  var timeLabel = $('wap-time'), durationLabel = $('wap-duration');
  var seek = $('wap-seek'), volumeRange = $('wap-volume');
  var playButton = $('wap-play'), prevButton = $('wap-prev'), nextButton = $('wap-next');
  var shuffleButton = $('wap-shuffle'), muteButton = $('wap-mute');
  var filePicker = $('wap-files'), addLabel = $('wap-add-label'), clearButton = $('wap-clear');
  var rememberButton = $('wap-remember'), folderRow = $('wap-folders');

  var audio = new Audio();
  audio.preload = 'metadata';

  // { file (its address), title, artist, local (from the visitor's disk, with
  // key, source, the File, and folder, the remembered folder's id, if from
  // one), duration and broken once known; row, titleLine, artistLine and
  // length, its parts in the list }
  var songs = [];
  var current = -1;    // the song in the player
  var order = [];      // the songs' indices in the order they play
  var shuffle = false;
  var started = false; // played at all, so the first song waits as "Gotowy", not "Pauza"
  var dragging = false;
  var failures = 0;    // songs that would not play in a row, so a list of broken files stops

  function read(key) {
    try { return localStorage.getItem(key); } catch (_) { return null; }
  }
  function keep(key, value) {
    try { localStorage.setItem(key, String(value)); } catch (_) { /* this visit only */ }
  }

  function clock(seconds) {
    if (!isFinite(seconds) || seconds < 0) return '0:00';
    seconds = Math.floor(seconds);
    var h = Math.floor(seconds / 3600), m = Math.floor(seconds / 60) % 60, s = seconds % 60;
    return (h ? h + ':' + (m < 10 ? '0' : '') : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function two(n) {
    return (n < 10 ? '0' : '') + n;
  }

  // 1 utwór, 2-4 utwory, 5-21 utworów, 22-24 utwory...
  function songsWord(n) {
    if (n === 1) return 'utwór';
    var last = n % 10, tens = n % 100;
    return last >= 2 && last <= 4 && (tens < 12 || tens > 14) ? 'utwory' : 'utworów';
  }

  // a range shows its value as the filled part of its line
  function fill(range) {
    var span = range.max - range.min;
    range.style.setProperty('--player-fill', (span ? (range.value - range.min) / span * 100 : 0) + '%');
  }

  // ---- the order

  function shuffled(items) {
    for (var i = items.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = items[i]; items[i] = items[j]; items[j] = t;
    }
    return items;
  }

  // the list's order, or a shuffle that starts with the given song
  function buildOrder(first) {
    order = songs.map(function (_, i) { return i; });
    if (!shuffle) return;
    shuffled(order);
    var at = order.indexOf(first);
    if (at > 0) order.unshift(order.splice(at, 1)[0]);
  }

  // the next or the previous song; past the end the list starts again, and a
  // shuffle is dealt anew (never with the song just heard first)
  function step(direction) {
    if (!songs.length) return;
    var next = order.indexOf(current) + direction;
    if (next >= order.length) {
      if (shuffle) {
        buildOrder(-1);
        if (order.length > 1 && order[0] === current) order.push(order.shift());
      }
      next = 0;
    } else if (next < 0) {
      next = order.length - 1;
    }
    load(order[next], true);
  }

  // ---- the player

  function showPosition() {
    position.textContent = current < 0 ? '' : two(order.indexOf(current) + 1) + ' / ' + two(songs.length);
  }

  // the title and the artist of the song in the player
  function showSong(song) {
    titleLine.textContent = song ? song.title : '—';
    titleLine.title = song ? song.title : '';
    artistLine.textContent = song ? song.artist : '';
    if ('mediaSession' in navigator && window.MediaMetadata) {
      navigator.mediaSession.metadata = song ? new MediaMetadata({ title: song.title, artist: song.artist, album: 'Weeaboo Animu Playlist' }) : null;
    }
  }

  function load(index, play) {
    var song = songs[index];
    if (!song) return;
    if (current >= 0) {
      songs[current].row.classList.remove('wap-current');
      songs[current].row.removeAttribute('aria-current');
    }
    current = index;
    song.row.classList.add('wap-current');
    song.row.setAttribute('aria-current', 'true');
    // the list follows the song while it is in sight, never at the start
    var box = list.getBoundingClientRect();
    if (play && box.top < window.innerHeight && box.bottom > 0) song.row.scrollIntoView({ block: 'nearest' });

    showSong(song);
    showPosition();
    seek.value = 0;
    seek.disabled = true;
    fill(seek);
    timeLabel.textContent = '0:00';
    durationLabel.textContent = clock(song.duration);

    audio.src = song.file;
    if (play) start();
    else paint();
  }

  // the player empty again, when the song in it left the list
  function unload() {
    audio.pause();
    audio.removeAttribute('src');
    audio.load();
    current = -1;
    started = false;
    showSong(null);
    showPosition();
    seek.value = 0;
    seek.disabled = true;
    fill(seek);
    timeLabel.textContent = '0:00';
    durationLabel.textContent = '0:00';
    paint();
  }

  function start() {
    if (current < 0) {
      if (!songs.length) return;
      buildOrder(-1);
      load(order[0], true);
      return;
    }
    var playing = audio.play();
    if (playing && playing.catch) playing.catch(function () { paint(); });
  }

  function toggle() {
    if (current >= 0 && !audio.paused) audio.pause();
    else start();
  }

  function paint() {
    var on = current >= 0 && !audio.paused;
    playButton.setAttribute('aria-pressed', on ? 'true' : 'false');
    playButton.setAttribute('aria-label', on ? 'Pauza' : 'Odtwórz');
    list.classList.toggle('wap-playing', on);
    stateLabel.textContent = on ? 'Teraz gra' : started ? 'Pauza' : 'Gotowy';
    document.title = !started || current < 0 ? BASE_TITLE : (on ? '▶ ' : '') + songs[current].title + ' · WAP';
    if ('mediaSession' in navigator) navigator.mediaSession.playbackState = !started ? 'none' : on ? 'playing' : 'paused';
  }

  audio.addEventListener('play', function () {
    started = true;
    paint();
  });
  audio.addEventListener('pause', paint);
  audio.addEventListener('playing', function () { failures = 0; });
  audio.addEventListener('ended', function () { step(1); });

  // an Ogg served without ranges tells its length only once it has been read
  // through, so the length is taken whenever it comes
  function lengthKnown() {
    seek.disabled = !isFinite(audio.duration);
    durationLabel.textContent = clock(audio.duration);
    known(songs[current], audio.duration);
  }
  audio.addEventListener('loadedmetadata', lengthKnown);
  audio.addEventListener('durationchange', lengthKnown);

  audio.addEventListener('timeupdate', function () {
    if (dragging || !isFinite(audio.duration) || !audio.duration) return;
    seek.value = Math.round(audio.currentTime / audio.duration * 1000);
    fill(seek);
    timeLabel.textContent = clock(audio.currentTime);
  });

  // a file that will not play is marked and skipped, unless none would
  audio.addEventListener('error', function () {
    if (current < 0 || !audio.getAttribute('src')) return;
    broken(songs[current]);
    failures++;
    if (failures < songs.length) step(1);
    else {
      failures = 0;
      paint();
    }
  });

  // dragging moves the time shown; letting go moves the song there
  seek.addEventListener('input', function () {
    dragging = true;
    fill(seek);
    timeLabel.textContent = clock(seek.value / 1000 * audio.duration);
  });
  seek.addEventListener('change', function () {
    dragging = false;
    if (isFinite(audio.duration)) audio.currentTime = seek.value / 1000 * audio.duration;
  });

  function seekBy(seconds) {
    if (current < 0 || !isFinite(audio.duration)) return;
    audio.currentTime = Math.max(0, Math.min(audio.duration, audio.currentTime + seconds));
  }

  // past the first seconds "previous" goes back to the start of the song
  function previous() {
    if (current >= 0 && audio.currentTime > 3) audio.currentTime = 0;
    else step(-1);
  }

  playButton.addEventListener('click', toggle);
  prevButton.addEventListener('click', previous);
  nextButton.addEventListener('click', function () { step(1); });

  // ---- the shuffle

  function setShuffle(on) {
    shuffle = on;
    shuffleButton.setAttribute('aria-pressed', on ? 'true' : 'false');
    buildOrder(current);
    showPosition();
    keep(SHUFFLE_KEY, on ? 1 : 0);
  }
  shuffleButton.addEventListener('click', function () { setShuffle(!shuffle); });

  // ---- the volume

  function setVolume(percent) {
    percent = Math.max(0, Math.min(100, Math.round(percent)));
    volumeRange.value = percent;
    audio.volume = percent / 100;
    fill(volumeRange);
    keep(VOLUME_KEY, percent);
    if (percent > 0 && audio.muted) setMuted(false);
  }

  function setMuted(on) {
    audio.muted = on;
    muteButton.setAttribute('aria-pressed', on ? 'true' : 'false');
    muteButton.setAttribute('aria-label', on ? 'Włącz dźwięk' : 'Wycisz');
    keep(MUTED_KEY, on ? 1 : 0);
  }

  volumeRange.addEventListener('input', function () { setVolume(volumeRange.value); });
  muteButton.addEventListener('click', function () { setMuted(!audio.muted); });

  // ---- the keys

  // as in the gallery and 0x40: space pauses, N and P change the song, the
  // arrows wind and set the volume, S shuffles, M mutes; a focused range keeps
  // its arrows, a focused button its Enter (space pauses even there, or the
  // song just clicked in the list would start again)
  document.addEventListener('keydown', function (event) {
    if (event.ctrlKey || event.metaKey || event.altKey) return;
    var target = event.target;
    var tag = target && target.tagName;
    if (tag === 'INPUT' && target.type !== 'range' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable) return;
    if (tag === 'INPUT' && /^Arrow/.test(event.key)) return;
    var key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
    var actions = {
      ' ': toggle,
      k: toggle,
      n: function () { step(1); },
      p: previous,
      s: function () { setShuffle(!shuffle); },
      m: function () { setMuted(!audio.muted); },
      ArrowUp: function () { setVolume(+volumeRange.value + 5); },
      ArrowDown: function () { setVolume(+volumeRange.value - 5); },
      ArrowRight: function () { seekBy(5); },
      ArrowLeft: function () { seekBy(-5); }
    };
    if (!actions[key]) return;
    event.preventDefault();
    actions[key]();
  });

  // a button takes its space on the key's release
  document.addEventListener('keyup', function (event) {
    if (event.key === ' ' && event.target && event.target.tagName === 'BUTTON') event.preventDefault();
  });

  // the keys of the system and the headphones
  if ('mediaSession' in navigator) {
    var handlers = {
      play: start,
      pause: function () { audio.pause(); },
      previoustrack: previous,
      nexttrack: function () { step(1); },
      seekto: function (details) { if (isFinite(audio.duration)) audio.currentTime = details.seekTime; }
    };
    Object.keys(handlers).forEach(function (action) {
      try { navigator.mediaSession.setActionHandler(action, handlers[action]); } catch (_) { /* not supported here */ }
    });
  }

  // ---- the list

  function known(song, duration) {
    if (!song || !isFinite(duration) || song.duration) return;
    song.duration = duration;
    song.length.textContent = clock(duration);
    total();
  }

  // a file whose length cannot be read: marked, and left out of the sum
  function broken(song) {
    if (!song || song.broken) return;
    song.broken = true;
    song.row.classList.add('wap-broken');
    if (!song.duration) song.length.textContent = '—';
    total();
  }

  // how many songs and, once every length is known, how long all of them last;
  // the removal of the own songs shows while there are any
  function total() {
    var sum = 0, all = true, own = false;
    songs.forEach(function (song) {
      if (song.duration) sum += song.duration;
      else if (!song.broken) all = false;
      if (song.local) own = true;
    });
    count.textContent = songs.length + ' ' + songsWord(songs.length) + (all && sum ? ' · ' + clock(sum) : '');
    clearButton.hidden = !own;
    if (songs.length) status.hidden = true;
  }

  function render() {
    list.textContent = '';
    songs.forEach(function (song, i) {
      var row = document.createElement('li');
      if (song.local) row.className = 'wap-local';
      if (song.broken) row.classList.add('wap-broken');
      if (i === current) {
        row.classList.add('wap-current');
        row.setAttribute('aria-current', 'true');
      }
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'wap-song';

      var number = document.createElement('span');
      number.className = 'wap-no';
      number.textContent = two(i + 1);
      var bars = document.createElement('span');
      bars.className = 'wap-bars';
      bars.setAttribute('aria-hidden', 'true');
      bars.innerHTML = '<i></i><i></i><i></i>';
      number.appendChild(bars);

      var name = document.createElement('span');
      name.className = 'wap-name';
      song.titleLine = document.createElement('b');
      song.artistLine = document.createElement('small');
      name.appendChild(song.titleLine);
      name.appendChild(song.artistLine);

      song.length = document.createElement('span');
      song.length.className = 'wap-len';
      song.length.textContent = song.duration ? clock(song.duration) : song.broken ? '—' : '';

      button.appendChild(number);
      button.appendChild(name);
      button.appendChild(song.length);
      button.addEventListener('click', function () {
        var index = songs.indexOf(song);
        if (index === current) {
          toggle();
          return;
        }
        if (shuffle) buildOrder(index);
        load(index, true);
      });
      row.appendChild(button);
      list.appendChild(row);
      song.row = row;
      showNames(song);
    });
    total();
  }

  function showNames(song) {
    song.titleLine.textContent = song.title;
    song.artistLine.textContent = song.artist;
    song.artistLine.hidden = !song.artist;
  }

  // A new list (the server's, own songs added or taken away): the song in the
  // player stays in it, or leaves the player empty; an empty player takes the
  // first song, without playing it.
  function setSongs(next) {
    var playing = songs[current] || null;
    songs = next;
    current = playing ? songs.indexOf(playing) : -1;
    if (playing && current < 0) unload();
    render();
    buildOrder(current);
    if (current < 0 && songs.length) load(order[0], false);
    else showPosition();
    probeLengths();
  }

  // The lengths of the list, one song at a time through a second, silent
  // element that reads only the start of each file; started again when songs
  // come.
  var probe = new Audio(), probing = null;
  probe.preload = 'metadata';
  probe.muted = true;
  function probeLengths() {
    if (probing) return;
    probing = songs.filter(function (song) { return !song.probed && !song.duration && !song.broken; })[0] || null;
    if (!probing) {
      probe.removeAttribute('src');
      probe.load();
      return;
    }
    probing.probed = true;
    probe.src = probing.file;
  }
  probe.addEventListener('loadedmetadata', function () {
    var song = probing;
    probing = null;
    known(song, probe.duration);
    probeLengths();
  });
  probe.addEventListener('error', function () {
    if (!probing) return;
    var song = probing;
    probing = null;
    broken(song);
    probeLengths();
  });

  // ---- the visitor's own songs

  // the title and the artist from a file's name, as songs.php reads them:
  // "Artist - Title", without a leading track number ("01. ", "01 - ", "04 ")
  function fromName(name) {
    var label = name.replace(/\.[^.]*$/, '').replace(/_/g, ' ').trim()
      .replace(/^(?:\d{1,3}\s*[.\-]|\d{2})\s+(?=\S)/, '');
    var dash = label.indexOf(' - ');
    return dash >= 0
      ? { title: label.slice(dash + 3).trim(), artist: label.slice(0, dash).trim() }
      : { title: label, artist: '' };
  }

  // The audio files of a folder or a drop ({ file, path }), in the order of
  // their paths, after the list; a file already there (path, size and time)
  // comes once. Named after the file first, then after its tags, one file at
  // a time. folder: the remembered folder they come from, if any.
  function addFiles(items, folder) {
    var seen = {};
    songs.forEach(function (song) { if (song.key) seen[song.key] = true; });
    var added = items.filter(function (item) {
      item.key = item.path + '|' + item.file.size + '|' + item.file.lastModified;
      if (seen[item.key] || !(AUDIO_FILE.test(item.file.name) || /^audio\//.test(item.file.type))) return false;
      seen[item.key] = true;
      return true;
    }).sort(function (a, b) {
      return a.path.localeCompare(b.path, 'pl', { numeric: true, sensitivity: 'base' });
    }).map(function (item) {
      var name = fromName(item.file.name);
      return { file: URL.createObjectURL(item.file), title: name.title, artist: name.artist, local: true, key: item.key, source: item.file, folder: folder || null };
    });
    if (!added.length) return;
    setSongs(songs.concat(added));
    added.reduce(function (done, song) {
      return done.then(function () {
        return window.wapTags(song.source).then(function (tags) {
          if (!tags.title && !tags.artist) return;
          song.title = tags.title || song.title;
          song.artist = tags.artist || song.artist;
          showNames(song);
          if (song === songs[current]) {
            showSong(song);
            paint();
          }
        });
      });
    }, Promise.resolve());
  }

  function clearOwn() {
    songs.forEach(function (song) { if (song.local) URL.revokeObjectURL(song.file); });
    setSongs(songs.filter(function (song) { return !song.local; }));
    if (!songs.length) {
      status.textContent = 'Lista jest pusta.';
      status.hidden = false;
    }
  }

  // a computer picks a whole folder; a phone, which cannot, picks files
  if (!window.matchMedia || !window.matchMedia('(pointer: fine)').matches) {
    filePicker.removeAttribute('webkitdirectory');
    addLabel.textContent = 'Dodaj pliki';
  }
  filePicker.addEventListener('change', function () {
    addFiles(Array.prototype.map.call(filePicker.files, function (file) {
      return { file: file, path: file.webkitRelativePath || file.name };
    }));
    filePicker.value = '';
  });
  clearButton.addEventListener('click', clearOwn);

  // every file of a dropped folder, however deep
  function entryFiles(entry) {
    if (entry.isFile) {
      return new Promise(function (resolve) {
        entry.file(function (file) { resolve([{ file: file, path: entry.fullPath }]); }, function () { resolve([]); });
      });
    }
    if (!entry.isDirectory) return Promise.resolve([]);
    var reader = entry.createReader(), found = [];
    return new Promise(function (resolve) {
      (function more() {
        reader.readEntries(function (batch) {
          if (!batch.length) return resolve(found);
          found = found.concat(Array.prototype.slice.call(batch));
          more();
        }, function () { resolve(found); });
      })();
    }).then(function (children) {
      return Promise.all(children.map(entryFiles)).then(function (lists) { return [].concat.apply([], lists); });
    });
  }

  document.addEventListener('dragover', function (event) {
    if (event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') >= 0) event.preventDefault();
  });
  document.addEventListener('drop', function (event) {
    var transfer = event.dataTransfer;
    if (!transfer || !transfer.files.length) return;
    event.preventDefault();
    // the entries only while the drop lasts, so taken at once
    var entries = Array.prototype.map.call(transfer.items || [], function (item) {
      return item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
    }).filter(Boolean);
    if (entries.length) {
      Promise.all(entries.map(entryFiles)).then(function (lists) { addFiles([].concat.apply([], lists)); });
    } else {
      addFiles(Array.prototype.map.call(transfer.files, function (file) { return { file: file, path: file.name }; }));
    }
  });

  // ---- remembered folders

  // Where the browser hands out a folder for good (Chrome, Edge, Opera on a
  // computer), its handle is kept in IndexedDB ("wap", "folders": { id,
  // handle, name }) and read again on the next visit. Reading it needs the
  // visitor's leave: given for good, the folder comes at once; asked again
  // on each visit, its chip waits for a click. Elsewhere none of this shows.
  var folders = [];   // { id, handle, name, state: 'ready', 'waiting' or 'gone' }
  var canRemember = typeof window.showDirectoryPicker === 'function' && !!window.indexedDB;

  // one transaction on the folders; work gets the store and may return a
  // request, whose result comes out
  function folderStore(mode, work) {
    return new Promise(function (resolve, reject) {
      var open = indexedDB.open('wap', 1);
      open.onupgradeneeded = function () { open.result.createObjectStore('folders', { keyPath: 'id', autoIncrement: true }); };
      open.onerror = function () { reject(open.error); };
      open.onsuccess = function () {
        var db = open.result, result;
        var transaction = db.transaction('folders', mode);
        var request = work(transaction.objectStore('folders'));
        if (request) request.onsuccess = function () { result = request.result; };
        transaction.oncomplete = function () { db.close(); resolve(result); };
        transaction.onerror = transaction.onabort = function () { db.close(); reject(transaction.error); };
      };
    });
  }

  // every file under a folder handle, its path from the folder's name, as a
  // picked folder gives it
  function folderFiles(handle, path) {
    var found = [], entries = handle.values();
    function next() {
      return entries.next().then(function (step) {
        if (step.done) return found;
        var entry = step.value, inside = path + '/' + entry.name;
        var more = entry.kind === 'file'
          ? entry.getFile().then(function (file) { found.push({ file: file, path: inside }); })
          : folderFiles(entry, inside).then(function (deeper) { found = found.concat(deeper); });
        return more.then(next);
      });
    }
    return next();
  }

  // reads a folder (again): its songs gone from the disk leave the list, the
  // new ones join it
  function loadFolder(folder) {
    return folderFiles(folder.handle, folder.name).then(function (items) {
      var there = {};
      items.forEach(function (item) { there[item.path + '|' + item.file.size + '|' + item.file.lastModified] = true; });
      var gone = songs.filter(function (song) { return song.folder === folder.id && !there[song.key]; });
      if (gone.length) {
        gone.forEach(function (song) { URL.revokeObjectURL(song.file); });
        setSongs(songs.filter(function (song) { return gone.indexOf(song) < 0; }));
      }
      addFiles(items, folder.id);
      folder.state = 'ready';
    }, function () {
      folder.state = 'gone';
    }).then(paintFolders);
  }

  // a click on a chip: the leave asked for if needed (a click lets the
  // browser ask), then the folder read
  function openFolder(folder) {
    var asked = folder.handle.requestPermission ? folder.handle.requestPermission({ mode: 'read' }) : Promise.resolve('granted');
    asked.then(function (leave) {
      if (leave === 'granted') return loadFolder(folder);
    }, function () { /* the chip stays as it was */ });
  }

  function forgetFolder(folder) {
    folderStore('readwrite', function (store) { return store.delete(folder.id); }).catch(function () { /* gone already */ });
    folders.splice(folders.indexOf(folder), 1);
    var leaving = songs.filter(function (song) { return song.folder === folder.id; });
    leaving.forEach(function (song) { URL.revokeObjectURL(song.file); });
    if (leaving.length) setSongs(songs.filter(function (song) { return song.folder !== folder.id; }));
    paintFolders();
  }

  var FOLDER_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5A1.5 1.5 0 0 1 4.5 5h4.2l2 2.5h8.8A1.5 1.5 0 0 1 21 9v8.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5z" /></svg>';
  var CROSS_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>';
  var FOLDER_NOTES = { ready: 'Wczytaj ponownie', waiting: 'kliknij, aby wczytać', gone: 'Nie ma go już na dysku' };

  function paintFolders() {
    folderRow.textContent = '';
    folderRow.hidden = !folders.length;
    folders.forEach(function (folder) {
      var chip = document.createElement('span');
      chip.className = 'wap-folder' + (folder.state === 'waiting' ? ' wap-waiting' : folder.state === 'gone' ? ' wap-gone' : '');
      var load = document.createElement('button');
      load.type = 'button';
      load.className = 'wap-folder-load';
      load.title = FOLDER_NOTES[folder.state];
      load.innerHTML = FOLDER_ICON;
      load.appendChild(document.createTextNode(folder.name));
      if (folder.state === 'waiting') {
        var note = document.createElement('small');
        note.textContent = FOLDER_NOTES.waiting;
        load.appendChild(note);
      }
      load.addEventListener('click', function () { openFolder(folder); });
      var forget = document.createElement('button');
      forget.type = 'button';
      forget.className = 'wap-folder-forget';
      forget.title = 'Zapomnij ten folder';
      forget.setAttribute('aria-label', 'Zapomnij folder ' + folder.name);
      forget.innerHTML = CROSS_ICON;
      forget.addEventListener('click', function () { forgetFolder(folder); });
      chip.appendChild(load);
      chip.appendChild(forget);
      folderRow.appendChild(chip);
    });
  }

  // picks a folder and keeps it; the same folder picked again is only read again
  function rememberFolder() {
    window.showDirectoryPicker({ id: 'wap', mode: 'read', startIn: 'music' }).then(function (handle) {
      return Promise.all(folders.map(function (folder) { return folder.handle.isSameEntry(handle); })).then(function (same) {
        var known = folders[same.indexOf(true)];
        if (known) return loadFolder(known);
        return folderStore('readwrite', function (store) { return store.add({ handle: handle, name: handle.name }); }).then(function (id) {
          var folder = { id: id, handle: handle, name: handle.name, state: 'ready' };
          folders.push(folder);
          return loadFolder(folder);
        });
      });
    }).catch(function () { /* none picked, or none kept */ });
  }

  // the folders of the last visits, each read at once where the leave stands
  function restoreFolders() {
    if (!canRemember) return;
    folderStore('readonly', function (store) { return store.getAll(); }).then(function (kept) {
      folders = (kept || []).map(function (entry) { return { id: entry.id, handle: entry.handle, name: entry.name, state: 'waiting' }; });
      paintFolders();
      folders.forEach(function (folder) {
        var leave = folder.handle.queryPermission ? folder.handle.queryPermission({ mode: 'read' }) : Promise.resolve('prompt');
        leave.then(function (state) {
          if (state === 'granted') loadFolder(folder);
        }, function () { /* waits for a click */ });
      });
    }).catch(function () { /* no storage: nothing remembered */ });
  }

  if (canRemember) {
    rememberButton.hidden = false;
    $('wap-remember-hint').hidden = false;
    rememberButton.addEventListener('click', rememberFolder);
  }

  // ---- the start

  var keptVolume = parseInt(read(VOLUME_KEY), 10);
  setVolume(keptVolume >= 0 && keptVolume <= 100 ? keptVolume : 70);
  setMuted(read(MUTED_KEY) === '1');
  shuffle = read(SHUFFLE_KEY) === '1';
  shuffleButton.setAttribute('aria-pressed', shuffle ? 'true' : 'false');
  fill(seek);
  paint();

  // the server's songs first, before any own ones added meanwhile, then the
  // remembered folders
  fetch('songs.php', { cache: 'no-cache' })
    .then(function (response) {
      if (!response.ok) throw new Error(response.status);
      return response.json();
    })
    .then(function (data) {
      var shared = (Array.isArray(data) ? data : []).filter(function (song) { return song && song.file; });
      if (!shared.length) status.textContent = 'Lista jest pusta.';
      setSongs(shared.concat(songs));
    })
    .catch(function () {
      status.textContent = 'Nie udało się wczytać listy.';
    })
    .then(restoreFolders);
})();
