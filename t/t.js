// Sending files between two devices (t/, inc/transfer.php). The first device
// opens a transfer and shows its address as a QR code; the second opens it
// (#<id>), is asked, and joins. The server only carries the two WebRTC
// messages that connect them (the offer and the answer, with every candidate
// in them, no trickle), asked for by polling; then a data channel opens
// straight between the devices, over their own network when they share one,
// and either side may send files. The server never sees them, unless the
// devices cannot reach each other (no TURN) and the transfer was opened by an
// owner of the site: then they go through it, piece by piece (api.php put and
// get).
//
// On the channel, in order: {t: 'file', n, name, size, type}, its bytes,
// {t: 'done', n}, answered by {t: 'got', n}; {t: 'cancel', n} from the sender
// and {t: 'refuse', n, why} from the receiver stop one; {t: 'bye'} ends.
// What comes in is gathered in Blobs and saved from a link; a file over LIMIT
// is refused. Over the local network a file of DISK_FROM or more goes instead
// to the page's own disk in the browser as it comes (store.js), so it may be
// as big as the room the browser gives the page.
(function () {
  'use strict';

  var API = 'api.php';
  // Cloudflare's STUN only: it carries the site already, so no one else
  // learns the devices' addresses
  var ICE_SERVERS = [{ urls: 'stun:stun.cloudflare.com:3478' }];
  var LIMIT = 2 * 1024 * 1024 * 1024;
  var BLOCK = 1024 * 1024;        // read from a file at once, one piece through the server
  var CHUNK = 64 * 1024;          // one message of the data channel
  var HIGH = 8 * 1024 * 1024;     // the data channel's buffer waited on above this
  var LOW = 1024 * 1024;
  var PART = 16 * 1024 * 1024;    // received bytes made into a Blob, which a browser may keep on disk
  var DISK_FROM = 256 * 1024 * 1024;
  var DISK_SPARE = 64 * 1024 * 1024; // left free on the disk besides the file
  var STORE_LOCK = 'sanakan-transfer/';
  var GATHER_TIME = 4000;
  var CONNECT_TIME = 20000;
  var TOKEN = /^[A-Za-z0-9_-]{22}$/;

  function $(id) { return document.getElementById(id); }
  var steps = { start: $('t-start'), code: $('t-code'), ask: $('t-ask'), link: $('t-link') };
  var messageLine = $('t-message'), againButton = $('t-again');
  var qrCanvas = $('t-qr'), addressField = $('t-address'), ttlLabel = $('t-ttl');
  var stateLabel = $('t-state'), routeLabel = $('t-route'), peerLine = $('t-peer');
  var relayBox = $('t-relay'), dropZone = $('t-drop'), filePicker = $('t-files'), list = $('t-list');

  var encoder = new TextEncoder(), decoder = new TextDecoder();

  // the transfer going on: { id, key, side ('host' or 'guest'), relay (may
  // go through the server), after (the last message read), peer, pc,
  // channel (once open), open, lan (the direct way goes over the local
  // network), ended, timers }
  var s = null;
  var outgoing = [];   // files waiting to go: { n, file, row }
  var sending = null;  // the one going
  var incoming = null; // the one coming: { n, name, size, type, got, parts, partBytes, blobs, row, refused, started,
                       // disk ('opening', 'open' or null), diskId, diskReady }
  var sentFiles = {};  // what was sent, by n, for the answers
  var nextN = 1;
  var unsaved = 0;     // files received and not saved yet

  // ---- the page

  function show(step) {
    Object.keys(steps).forEach(function (name) { steps[name].hidden = name !== step; });
  }

  function say(text) {
    messageLine.textContent = text;
    messageLine.hidden = !text;
  }

  // the transfer ends with $text; what was received stays to be saved
  function stop(text) {
    end();
    say(text);
    againButton.hidden = false;
    dropZone.hidden = true;
    relayBox.hidden = true;
    if (!steps.link.hidden) stateLabel.textContent = 'Rozłączono';
    else show(null);
  }

  againButton.addEventListener('click', function () {
    location.href = location.pathname;
  });

  function api(action, fields) {
    var form = new URLSearchParams();
    Object.keys(fields || {}).forEach(function (name) { form.append(name, fields[name]); });
    return fetch(API + '?a=' + action, { method: 'POST', body: form, cache: 'no-store' }).then(function (response) {
      return response.json().catch(function () { return { ok: false, error: 'server', message: 'Serwer nie odpowiedział jak trzeba.' }; });
    }, function () {
      return { ok: false, error: 'network', message: 'Brak połączenia z serwerem.' };
    }).then(function (data) {
      if (data.ok) return data;
      var error = new Error(data.message || 'Coś poszło nie tak.');
      error.code = data.error;
      throw error;
    });
  }

  function wait(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, ms); });
  }

  function clock(seconds) {
    seconds = Math.max(0, Math.ceil(seconds));
    return Math.floor(seconds / 60) + ':' + (seconds % 60 < 10 ? '0' : '') + seconds % 60;
  }

  function bytes(n) {
    var units = ['B', 'KB', 'MB', 'GB'], unit = 0;
    while (n >= 1024 && unit < units.length - 1) { n /= 1024; unit++; }
    return n.toLocaleString('pl-PL', { maximumFractionDigits: unit ? 1 : 0 }) + ' ' + units[unit];
  }

  // ---- the first device

  var newButton = $('t-new');
  if (newButton) newButton.addEventListener('click', function () {
    newButton.disabled = true;
    say('');
    api('new', { csrf: document.body.dataset.csrf }).then(function (data) {
      s = { id: data.id, key: data.key, side: 'host', relay: data.relay, after: 0, timers: [] };
      showCode(data.ttl);
      poll();
    }, function (error) {
      say(error.message);
    }).then(function () {
      newButton.disabled = false;
    });
  });

  function showCode(ttl) {
    var address = location.origin + location.pathname + '#' + s.id;
    drawQr(address);
    addressField.value = address;
    show('code');
    var until = Date.now() + ttl * 1000, current = s;
    (function tick() {
      if (s !== current || s.peer) return;
      var left = (until - Date.now()) / 1000;
      if (left <= 0) return stop('Kod wygasł, nikt go nie zeskanował.');
      ttlLabel.textContent = 'ważny jeszcze ' + clock(left);
      current.timers.push(setTimeout(tick, 1000));
    })();
  }

  // dark on white with the quiet zone, as every scanner reads it
  function drawQr(text) {
    var qr = qrcode(0, 'M');
    qr.addData(text);
    qr.make();
    var count = qr.getModuleCount(), scale = 8, margin = 4;
    var size = (count + margin * 2) * scale;
    qrCanvas.width = qrCanvas.height = size;
    var context = qrCanvas.getContext('2d');
    context.fillStyle = '#fff';
    context.fillRect(0, 0, size, size);
    context.fillStyle = '#000';
    for (var row = 0; row < count; row++)
      for (var col = 0; col < count; col++)
        if (qr.isDark(row, col)) context.fillRect((col + margin) * scale, (row + margin) * scale, scale, scale);
  }

  $('t-copy').addEventListener('click', function () {
    var button = this;
    var done = function () { button.textContent = 'Skopiowano'; setTimeout(function () { button.textContent = 'Kopiuj'; }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(addressField.value).then(done, function () { addressField.select(); });
    else addressField.select();
  });

  $('t-cancel').addEventListener('click', function () {
    end();
    show('start');
  });

  // ---- the second device

  function join(id) {
    api('peek', { id: id }).then(function (data) {
      var text = $('t-ask-text'), name = document.createElement('strong');
      name.textContent = data.name || 'bez nazwy';
      text.textContent = 'Połączyć to urządzenie z kontem ';
      text.appendChild(name);
      text.appendChild(document.createTextNode('? Oba będą mogły wysyłać sobie pliki.'));
      show('ask');
      $('t-join').addEventListener('click', function () {
        this.disabled = true;
        api('join', { id: id }).then(function (joined) {
          history.replaceState(null, '', location.pathname);
          s = { id: id, key: joined.key, side: 'guest', relay: joined.relay, after: 0, timers: [], peer: true };
          linkStep('Konto: ' + (joined.name || 'bez nazwy'));
          connect(false);
          poll();
        }, function (error) {
          stop(error.message);
        });
      });
    }, function (error) {
      stop(error.code === 'gone' || error.code === 'taken' ? 'Ten kod wygasł albo został już użyty. Otwórz nowe przesyłanie na pierwszym urządzeniu.' : error.message);
    });
  }

  // ---- both: the server's messages until the devices connect

  function poll() {
    var current = s;
    if (!current || current.ended || current.open) return;
    api('poll', { id: current.id, key: current.key, after: current.after }).then(function (data) {
      if (s !== current || current.ended) return;
      if (current.side === 'host' && data.joined && !current.peer) {
        current.peer = true;
        linkStep('Drugie urządzenie: ' + data.joined);
        connect(true);
      }
      data.messages.forEach(function (entry) {
        current.after = Math.max(current.after, entry[0]);
        var message;
        try { message = JSON.parse(entry[1]); } catch (_) { return; }
        signalled(message);
      });
      current.timers.push(setTimeout(poll, current.peer ? 1000 : 1500));
    }, function (error) {
      if (s !== current || current.ended) return;
      if (error.code === 'gone' || error.code === 'denied')
        return stop(current.peer ? 'Drugie urządzenie zakończyło przesyłanie.' : 'Przesyłanie wygasło.');
      current.timers.push(setTimeout(poll, 3000));
    });
  }

  function signal(message) {
    var current = s;
    return api('send', { id: current.id, key: current.key, message: JSON.stringify(message) }).catch(function (error) {
      if (s === current && !current.open) stop(error.message);
    });
  }

  function signalled(message) {
    var current = s, pc = current.pc;
    if (message.t === 'offer' && pc && current.side === 'guest') {
      pc.setRemoteDescription({ type: 'offer', sdp: message.sdp }).then(function () {
        return pc.createAnswer();
      }).then(function (answer) {
        return pc.setLocalDescription(answer);
      }).then(function () {
        return gathered(pc);
      }).then(function () {
        if (s === current) signal({ t: 'answer', sdp: pc.localDescription.sdp });
      }).catch(direct);
    } else if (message.t === 'answer' && pc && current.side === 'host') {
      pc.setRemoteDescription({ type: 'answer', sdp: message.sdp }).catch(direct);
    } else if (message.t === 'relay' && current.relay) {
      relayStart();
    }
  }

  function linkStep(peer) {
    peerLine.textContent = peer;
    stateLabel.textContent = 'Łączenie…';
    routeLabel.textContent = '';
    show('link');
  }

  // ---- the direct way

  function connect(first) {
    var current = s;
    current.timers.push(setTimeout(function () { if (s === current && !current.open) direct(); }, CONNECT_TIME));
    if (!window.RTCPeerConnection) return direct();
    var pc = current.pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    pc.addEventListener('connectionstatechange', function () {
      if (s === current && !current.open && pc.connectionState === 'failed') direct();
    });
    if (first) {
      channelOf(pc.createDataChannel('files', { ordered: true }));
      pc.createOffer().then(function (offer) {
        return pc.setLocalDescription(offer);
      }).then(function () {
        return gathered(pc);
      }).then(function () {
        if (s === current) signal({ t: 'offer', sdp: pc.localDescription.sdp });
      }).catch(direct);
    } else {
      pc.addEventListener('datachannel', function (event) { channelOf(event.channel); });
    }
  }

  // every candidate in the description, or what came in GATHER_TIME
  function gathered(pc) {
    return new Promise(function (resolve) {
      if (pc.iceGatheringState === 'complete') return resolve();
      var timer = setTimeout(resolve, GATHER_TIME);
      pc.addEventListener('icegatheringstatechange', function () {
        if (pc.iceGatheringState === 'complete') { clearTimeout(timer); resolve(); }
      });
    });
  }

  // the direct way did not work: through the server where it may, else the end
  function direct() {
    var current = s;
    if (!current || current.open || current.ended || current.failed) return;
    current.failed = true;
    if (!current.relay)
      return stop('Nie udało się połączyć urządzeń bezpośrednio. Najpewniej zadziała, gdy oba będą w tej samej sieci Wi-Fi.');
    stateLabel.textContent = 'Brak połączenia';
    relayBox.hidden = false;
  }

  $('t-relay-go').addEventListener('click', function () {
    if (!s || !s.relay) return;
    signal({ t: 'relay' });
    relayStart();
  });

  function channelOf(dc) {
    var current = s;
    dc.binaryType = 'arraybuffer';
    dc.bufferedAmountLowThreshold = LOW;
    dc.addEventListener('open', function () {
      if (s !== current || current.channel) return dc.close();
      var max = current.pc.sctp && current.pc.sctp.maxMessageSize;
      opened({
        chunk: Math.min(CHUNK, max || 16 * 1024),
        send: function (data) {
          if (dc.readyState !== 'open') return Promise.reject(new Error('closed'));
          dc.send(data);
          if (dc.bufferedAmount <= HIGH) return Promise.resolve();
          return new Promise(function (resolve, reject) {
            function low() { dc.removeEventListener('close', closed); resolve(); }
            function closed() { dc.removeEventListener('bufferedamountlow', low); reject(new Error('closed')); }
            dc.addEventListener('bufferedamountlow', low, { once: true });
            dc.addEventListener('close', closed, { once: true });
          });
        },
        close: function () { dc.close(); }
      }, 'direct');
    });
    dc.addEventListener('message', function (event) { if (s === current) received(event.data); });
    dc.addEventListener('close', function () {
      if (s === current && current.open) stop('Połączenie zostało przerwane.');
    });
  }

  // which way the bytes go: over the local network when the other end's
  // address is a private one, or an mDNS name (a host candidate whose address
  // the browser does not show is one), which only resolves on the same
  // network, or both are in one IPv6 /64
  function route(pc) {
    if (!pc || !pc.getStats) return;
    pc.getStats().then(function (stats) {
      var pair = null;
      stats.forEach(function (report) {
        if (report.type === 'transport' && report.selectedCandidatePairId) pair = stats.get(report.selectedCandidatePairId);
      });
      if (!pair) stats.forEach(function (report) {
        if (!pair && report.type === 'candidate-pair' && report.state === 'succeeded' && (report.selected || report.nominated)) pair = report;
      });
      if (!pair) return;
      var local = stats.get(pair.localCandidateId) || {}, remote = stats.get(pair.remoteCandidateId) || {};
      var here = String(local.address || local.ip || ''), there = String(remote.address || remote.ip || '');
      var near = privateAddress(there) || (remote.candidateType === 'host' && there === '') || (there.indexOf(':') >= 0 && here.indexOf(':') >= 0 && net64(here) === net64(there));
      routeLabel.textContent = near ? 'sieć lokalna' : 'przez internet, bezpośrednio';
      if (s && s.pc === pc) s.lan = near;
    }).catch(function () { /* the label stays empty */ });
  }

  function privateAddress(address) {
    address = address.toLowerCase();
    return /\.local$/.test(address)
      || /^(?:10\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.|169\.254\.|127\.)/.test(address)
      || /^f[cd][0-9a-f]{2}:/.test(address) || /^fe[89ab][0-9a-f]:/.test(address);
  }

  function net64(address) {
    var halves = address.toLowerCase().split('::');
    var head = halves[0] ? halves[0].split(':') : [], tail = halves[1] ? halves[1].split(':') : [];
    while (head.length + tail.length < 8) head.push('0');
    return head.concat(tail).slice(0, 4).map(function (group) { return parseInt(group, 16); }).join(':');
  }

  // ---- through the server

  function relayStart() {
    var current = s;
    if (!current || current.ended || current.channel) return;
    if (current.pc) current.pc.close();
    var out = 0, inSeq = 0, queue = Promise.resolve(), closed = false;

    function headers(seq) {
      return { 'X-Transfer-Id': current.id, 'X-Transfer-Key': current.key, 'X-Transfer-Seq': String(seq) };
    }

    // a 410: the other side ended it, the server forgot it with any goodbye left
    function lost(gone) {
      if (closed) return;
      closed = true;
      if (s === current) stop(gone === true ? 'Drugie urządzenie zakończyło przesyłanie.' : 'Przesyłanie przez serwer zostało przerwane.');
    }

    // a piece again after a moment while the server is full or away; refused, the end
    function put(frame, seq) {
      if (closed) return Promise.reject(new Error('closed'));
      return fetch(API + '?a=put', { method: 'POST', headers: headers(seq), body: frame, cache: 'no-store' }).then(function (response) {
        if (response.ok) return;
        if (response.status === 429 || response.status >= 500) return wait(300).then(function () { return put(frame, seq); });
        throw new Error('refused');
      }, function () {
        return wait(1500).then(function () { return put(frame, seq); });
      });
    }

    function pull(delay) {
      if (closed) return;
      fetch(API + '?a=get', { method: 'POST', headers: headers(inSeq), cache: 'no-store' }).then(function (response) {
        if (response.status === 204) return wait(delay).then(function () { pull(Math.min(delay * 2, 1500)); });
        if (response.status === 429 || response.status >= 500) return wait(1500).then(function () { pull(delay); });
        if (!response.ok) return lost(response.status === 410);
        return response.arrayBuffer().then(function (buffer) {
          inSeq++;
          var frame = new Uint8Array(buffer);
          if (s === current) received(frame[0] === 0 ? decoder.decode(frame.subarray(1)) : buffer.slice(1));
          pull(100);
        });
      }).catch(function () {
        wait(1500).then(function () { pull(delay); });
      });
    }

    opened({
      chunk: BLOCK,
      send: function (data) {
        var payload = typeof data === 'string' ? encoder.encode(data) : new Uint8Array(data);
        var frame = new Uint8Array(payload.length + 1);
        frame[0] = typeof data === 'string' ? 0 : 1;
        frame.set(payload, 1);
        var seq = out++;
        queue = queue.then(function () { return put(frame, seq); });
        queue.catch(function () { lost(); });
        return queue;
      },
      close: function () { closed = true; }
    }, 'relay');
    pull(100);
  }

  // ---- both: connected

  function opened(channel, way) {
    var current = s;
    current.channel = channel;
    current.open = true;
    relayBox.hidden = true;
    dropZone.hidden = false;
    say('');
    stateLabel.textContent = 'Połączono';
    if (way === 'relay') routeLabel.textContent = 'przez serwer';
    else {
      route(current.pc);
      current.timers.push(setTimeout(function () { if (s === current) route(current.pc); }, 3000));
    }
    pump();
  }

  // ends the transfer here, tells the other side and the server
  function end() {
    var current = s;
    if (!current) return;
    s = null;
    current.ended = true;
    current.timers.forEach(clearTimeout);
    // the server forgets it once the goodbye went, or soon
    var forget = function () {
      if (current.channel) current.channel.close();
      if (current.pc) current.pc.close();
      if (current.key) api('close', { id: current.id, key: current.key }).catch(function () {});
    };
    if (current.channel) {
      var bye;
      try { bye = current.channel.send(JSON.stringify({ t: 'bye' })); } catch (_) { bye = Promise.resolve(); }
      Promise.race([bye.catch(function () {}), wait(2000)]).then(function () { setTimeout(forget, 300); });
    } else {
      forget();
    }
    if (sending) { sending.stopped = true; finished(sending.row, 'Przerwano', true); }
    Object.keys(sentFiles).forEach(function (n) {
      if (sentFiles[n].awaiting) status(sentFiles[n].row, 'Wysłano, bez potwierdzenia odbioru');
    });
    sentFiles = {};
    outgoing.forEach(function (item) { status(item.row, 'Przerwano'); item.row.classList.add('t-failed'); });
    outgoing = [];
    if (incoming && !incoming.refused) { status(incoming.row, 'Przerwano'); incoming.row.classList.add('t-failed'); }
    if (incoming) dropDisk(incoming);
    incoming = null;
    awake();
  }

  $('t-end').addEventListener('click', function () {
    stop('Rozłączono.');
  });

  // the server forgets a transfer left with the page
  window.addEventListener('pagehide', function () {
    if (s && s.key && navigator.sendBeacon) {
      var form = new URLSearchParams();
      form.append('id', s.id);
      form.append('key', s.key);
      navigator.sendBeacon(API + '?a=close', form);
    }
  });

  window.addEventListener('beforeunload', function (event) {
    if (sending || (incoming && !incoming.refused) || unsaved) {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  // ---- the files

  function row(direction, name, size) {
    var item = document.createElement('li');
    item.className = 't-item t-' + direction;
    var mark = document.createElement('span');
    mark.className = 't-mark';
    mark.setAttribute('aria-label', direction === 'out' ? 'Wysyłany' : 'Odbierany');
    mark.textContent = direction === 'out' ? '↑' : '↓';
    var title = document.createElement('span');
    title.className = 't-name';
    title.textContent = name;
    title.title = name;
    var meta = document.createElement('span');
    meta.className = 't-meta';
    meta.textContent = bytes(size);
    var state = document.createElement('span');
    state.className = 't-status';
    var bar = document.createElement('span');
    bar.className = 't-bar';
    bar.appendChild(document.createElement('i'));
    var action = document.createElement('span');
    action.className = 't-action';
    item.append(mark, title, meta, state, action, bar);
    list.prepend(item);
    return item;
  }

  function status(item, text) {
    item.querySelector('.t-status').textContent = text;
  }

  function progress(item, done, total, started) {
    var share = total ? done / total : 1;
    item.querySelector('.t-bar i').style.width = (share * 100).toFixed(1) + '%';
    var seconds = (Date.now() - started) / 1000;
    status(item, Math.floor(share * 100) + '%' + (seconds > 1 ? ' · ' + bytes(done / seconds) + '/s' : ''));
  }

  function action(item, label, handler) {
    var place = item.querySelector('.t-action');
    place.textContent = '';
    if (!label) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'hud-button hud-corners';
    button.textContent = label;
    button.addEventListener('click', handler);
    place.appendChild(button);
  }

  function finished(item, text, failed) {
    status(item, text);
    action(item, null);
    item.classList.add(failed ? 't-failed' : 't-done');
    if (!failed) item.querySelector('.t-bar i').style.width = '100%';
  }

  function queueFiles(files) {
    if (!s || !s.open) return;
    Array.prototype.forEach.call(files, function (file) {
      var item = { n: nextN++, file: file };
      item.row = row('out', file.name, file.size);
      status(item.row, 'Czeka');
      action(item.row, 'Anuluj', function () {
        if (sending === item) item.cancelled = true;
        else {
          outgoing = outgoing.filter(function (other) { return other !== item; });
          finished(item.row, 'Anulowano', true);
        }
      });
      outgoing.push(item);
    });
    pump();
  }

  filePicker.addEventListener('change', function () {
    queueFiles(filePicker.files);
    filePicker.value = '';
  });

  document.addEventListener('dragover', function (event) {
    if (!s || !s.open) return;
    event.preventDefault();
    dropZone.classList.add('t-over');
  });
  document.addEventListener('dragleave', function (event) {
    if (!event.relatedTarget) dropZone.classList.remove('t-over');
  });
  document.addEventListener('drop', function (event) {
    if (!s || !s.open) return;
    event.preventDefault();
    dropZone.classList.remove('t-over');
    queueFiles(event.dataTransfer.files);
  });

  // sends the next file waiting, one at a time
  function pump() {
    if (sending || !s || !s.open) return;
    var item = outgoing.shift();
    if (!item) return awake();
    var channel = s.channel, file = item.file, offset = 0, started = Date.now(), shown = 0;
    sending = item;
    sentFiles[item.n] = item;
    awake();
    channel.send(JSON.stringify({ t: 'file', n: item.n, name: file.name, size: file.size, type: file.type }));

    function next() {
      if (item.stopped) throw new Error('stopped');
      if (item.cancelled || item.refused) {
        channel.send(JSON.stringify({ t: 'cancel', n: item.n }));
        return finished(item.row, item.refused || 'Anulowano', true);
      }
      if (offset >= file.size) {
        item.awaiting = true;
        action(item.row, null);
        return channel.send(JSON.stringify({ t: 'done', n: item.n })).then(function () {
          if (!item.got) status(item.row, 'Wysłano, czeka na potwierdzenie');
        });
      }
      return file.slice(offset, offset + BLOCK).arrayBuffer().then(function (block) {
        var sent = Promise.resolve();
        for (var at = 0; at < block.byteLength; at += channel.chunk)
          sent = sent.then(send.bind(null, block.slice(at, at + channel.chunk)));
        return sent.then(function () {
          offset += block.byteLength;
          if (Date.now() - shown > 150) { shown = Date.now(); progress(item.row, offset, file.size, started); }
          return next();
        });
      });
    }

    function send(piece) {
      if (item.stopped) return Promise.reject(new Error('stopped'));
      return channel.send(piece);
    }

    next().catch(function () {
      if (!item.stopped) finished(item.row, 'Nie udało się wysłać', true);
    }).then(function () {
      sending = null;
      pump();
    });
  }

  function received(data) {
    if (typeof data !== 'string') return bytesIn(data);
    var message;
    try { message = JSON.parse(data); } catch (_) { return; }
    var item = sentFiles[message.n];
    if (message.t === 'file') {
      fileIn(message);
    } else if (message.t === 'done' && incoming && incoming.n === message.n) {
      complete();
    } else if (message.t === 'cancel' && incoming && incoming.n === message.n) {
      if (!incoming.refused) finished(incoming.row, 'Anulowano na drugim urządzeniu', true);
      incoming = null;
      awake();
    } else if (message.t === 'refuse' && item) {
      item.refused = message.why === 'large' ? 'Za duży dla drugiego urządzenia (ponad ' + bytes(LIMIT) + ' poza siecią lokalną)'
        : message.why === 'space' ? 'Za mało miejsca na drugim urządzeniu' : 'Anulowano na drugim urządzeniu';
      if (sending !== item) finished(item.row, item.refused, true);
    } else if (message.t === 'got' && item) {
      item.got = true;
      item.awaiting = false;
      finished(item.row, 'Odebrano');
      delete sentFiles[message.n];
    } else if (message.t === 'bye') {
      stop('Drugie urządzenie się rozłączyło.');
    }
  }

  function fileIn(message) {
    var size = Number(message.size);
    var name = String(message.name || 'plik').replace(/[\\/]/g, '_') || 'plik';
    var item = incoming = {
      n: message.n, name: name, size: size, type: String(message.type || ''),
      got: 0, parts: [], partBytes: 0, blobs: [], started: Date.now(), shown: 0, disk: null
    };
    item.row = row('in', name, isFinite(size) ? size : 0);
    if (!(size >= 0)) return refuse('cancel', 'Nieznany rozmiar');
    if (size >= DISK_FROM && s && s.lan && diskUsable()) toDisk(item);
    else if (size > LIMIT) return refuse('large', 'Za duży (ponad ' + bytes(LIMIT) + ' poza siecią lokalną)');
    status(item.row, 'Odbieranie');
    action(item.row, 'Anuluj', function () {
      if (incoming === item) refuse('cancel', 'Anulowano');
    });
    awake();
  }

  function refuse(why, text) {
    var item = incoming;
    item.refused = true;
    item.parts = item.blobs = [];
    dropDisk(item);
    finished(item.row, text, true);
    if (s && s.channel) s.channel.send(JSON.stringify({ t: 'refuse', n: item.n, why: why }));
    awake();
  }

  function bytesIn(buffer) {
    var item = incoming;
    if (!item || item.refused) return;
    item.got += buffer.byteLength;
    if (item.got > item.size) return refuse('cancel', 'Przyszło więcej, niż zapowiedziano');
    if (item.disk === 'open') {
      storeWorker().postMessage({ op: 'write', id: item.diskId, buffer: buffer }, [buffer]);
    } else {
      item.parts.push(buffer);
      item.partBytes += buffer.byteLength;
      // kept as they are while the disk opens, to be written there in order
      if (item.disk !== 'opening' && item.partBytes >= PART) {
        item.blobs.push(new Blob(item.parts));
        item.parts = [];
        item.partBytes = 0;
      }
    }
    if (Date.now() - item.shown > 150) {
      item.shown = Date.now();
      progress(item.row, item.got, item.size, item.started);
    }
  }

  function complete() {
    var item = incoming;
    incoming = null;
    awake();
    if (item.refused) return;
    if (item.got !== item.size) {
      dropDisk(item);
      return finished(item.row, 'Przyszło mniej, niż zapowiedziano', true);
    }
    status(item.row, 'Zapisywanie');
    (item.diskReady || Promise.resolve()).then(function () {
      if (item.disk !== 'open') return new Blob(item.blobs.concat(item.parts), { type: item.type || 'application/octet-stream' });
      return storeCall(item.diskId, { op: 'close', id: item.diskId }).then(function (reply) {
        if (reply.file.size !== item.size) throw 'failed';
        return reply.file;
      });
    }).then(function (file) {
      item.blobs = item.parts = [];
      if (s && s.channel) s.channel.send(JSON.stringify({ t: 'got', n: item.n }));
      finished(item.row, 'Odebrano');
      saveLink(item, file);
    }, function (error) {
      dropDisk(item);
      finished(item.row, error === 'space' ? 'Zabrakło miejsca na dysku' : 'Nie udało się zapisać', true);
      if (s && s.channel) s.channel.send(JSON.stringify({ t: 'refuse', n: item.n, why: error === 'space' ? 'space' : 'cancel' }));
    });
  }

  // the link that saves a received file; one kept on the page's disk can be
  // removed from there too
  function saveLink(item, file) {
    var place = item.row.querySelector('.t-action'), saved = false;
    // a file read from the disk has no type of its own
    var url = URL.createObjectURL(file.type || !item.type ? file : new Blob([file], { type: item.type }));
    unsaved++;
    var link = document.createElement('a');
    link.className = 'hud-button hud-corners t-save';
    link.href = url;
    link.download = item.name;
    link.textContent = 'Zapisz';
    link.addEventListener('click', function () {
      if (!saved) { saved = true; unsaved--; }
    });
    place.appendChild(link);
    if (item.disk !== 'open') return;
    // the copy stays until removed here or the page is left (sweepDisk() on
    // the next visit)
    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'hud-button hud-corners';
    remove.textContent = 'Usuń';
    remove.title = 'Usuwa kopię z miejsca przeglądarki; najpierw zapisz plik';
    remove.addEventListener('click', function () {
      if (!saved) { saved = true; unsaved--; }
      URL.revokeObjectURL(url);
      dropDisk(item);
      place.textContent = '';
      status(item.row, 'Usunięto z przeglądarki');
    });
    place.appendChild(remove);
  }

  // ---- big files on the page's own disk in the browser (store.js)

  var store = null;
  var storeWaits = {}; // the answer awaited for a file id: { resolve, reject }
  var storeItems = {}; // the incoming file of an id, for a write that failed
  var storeLocks = {}; // what lets go of a file's lock, by id

  function diskUsable() {
    return !!(window.Worker && navigator.storage && navigator.storage.getDirectory && navigator.locks);
  }

  function storeWorker() {
    if (!store) {
      store = new Worker(document.body.dataset.store);
      store.addEventListener('message', function (event) {
        var reply = event.data, waiting = storeWaits[reply.id];
        if (waiting) {
          delete storeWaits[reply.id];
          if (reply.ok) waiting.resolve(reply);
          else waiting.reject(reply.error);
        } else if (!reply.ok && storeItems[reply.id] && storeItems[reply.id] === incoming && !incoming.refused) {
          refuse(reply.error === 'space' ? 'space' : 'cancel', reply.error === 'space' ? 'Zabrakło miejsca na dysku' : 'Nie udało się zapisać na dysku');
        }
      });
      store.addEventListener('error', function () {
        Object.keys(storeWaits).forEach(function (id) { storeWaits[id].reject('failed'); });
        storeWaits = {};
      });
    }
    return store;
  }

  function storeCall(id, message) {
    return new Promise(function (resolve, reject) {
      var waiting = storeWaits[id] = { resolve: resolve, reject: reject };
      storeWorker().postMessage(message);
      setTimeout(function () {
        if (storeWaits[id] === waiting) { delete storeWaits[id]; reject('failed'); }
      }, message.op === 'open' ? 5000 : 60000);
    });
  }

  // a file's lock, held while this page has the file, so no other page
  // sweeps it away
  function hold(id) {
    return new Promise(function (granted) {
      navigator.locks.request(STORE_LOCK + id, function () {
        granted();
        return new Promise(function (release) { storeLocks[id] = release; });
      });
    });
  }

  // Writes $item to the disk once there is room and its file is open; until
  // then what comes waits in memory. Without the disk a file over LIMIT is
  // refused, a smaller one stays in memory.
  function toDisk(item) {
    item.disk = 'opening';
    item.diskId = Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    storeItems[item.diskId] = item;
    var room = navigator.storage.estimate ? navigator.storage.estimate().then(function (estimate) {
      return estimate.quota - estimate.usage;
    }, function () { return Infinity; }) : Promise.resolve(Infinity);
    item.diskReady = room.then(function (free) {
      if (free < item.size + DISK_SPARE) throw 'space';
      return hold(item.diskId);
    }).then(function () {
      return storeCall(item.diskId, { op: 'open', id: item.diskId });
    }).then(function () {
      item.disk = item.dropped ? null : 'open';
      if (item.dropped) return dropDisk(item);
      item.parts.forEach(function (buffer) {
        storeWorker().postMessage({ op: 'write', id: item.diskId, buffer: buffer }, [buffer]);
      });
      item.parts = [];
      item.partBytes = 0;
    }, function (error) {
      item.disk = null;
      dropDisk(item);
      if (item.refused || incoming !== item) return;
      if (item.size > LIMIT || error === 'space')
        refuse(error === 'space' ? 'space' : 'large', error === 'space' ? 'Za mało miejsca na dysku' : 'Nie da się zapisać na dysku, a to ponad ' + bytes(LIMIT));
    });
  }

  // removes $item's copy from the disk, if it had one, and lets go of its
  // lock; one still opening is removed by toDisk() once it opened or failed
  function dropDisk(item) {
    var id = item.diskId;
    if (!id) return;
    delete storeItems[id];
    item.dropped = true;
    if (item.disk === 'opening') return;
    item.diskId = null;
    var release = function () { if (storeLocks[id]) { storeLocks[id](); delete storeLocks[id]; } };
    if (!store) return release();
    storeCall(id, { op: 'remove', id: id }).then(release, release);
  }

  // what pages left behind: every file whose lock no page holds
  function sweepDisk() {
    if (!diskUsable()) return;
    navigator.storage.getDirectory().then(function (root) {
      return root.getDirectoryHandle('sanakan-transfer');
    }).then(function (dir) {
      var names = dir.keys();
      (function next() {
        names.next().then(function (entry) {
          if (entry.done) return;
          navigator.locks.request(STORE_LOCK + entry.value, { ifAvailable: true }, function (lock) {
            return lock ? dir.removeEntry(entry.value).catch(function () {}) : null;
          });
          next();
        }).catch(function () {});
      })();
    }).catch(function () { /* no folder yet */ });
  }

  // ---- the screen kept on while a file goes either way

  var lock = null;
  function awake() {
    var busy = !!(sending || (incoming && !incoming.refused));
    if (!navigator.wakeLock) return;
    if (busy && !lock && document.visibilityState === 'visible') {
      lock = 'asking';
      navigator.wakeLock.request('screen').then(function (sentinel) {
        lock = sentinel;
        sentinel.addEventListener('release', function () { if (lock === sentinel) lock = null; });
        if (!(sending || (incoming && !incoming.refused))) awake();
      }, function () { lock = null; });
    } else if (!busy && lock && lock !== 'asking') {
      lock.release();
      lock = null;
    }
  }
  document.addEventListener('visibilitychange', awake);

  // ---- the start: the scanned address, or the first device

  sweepDisk();
  var hash = location.hash.slice(1);
  if (TOKEN.test(hash)) join(hash);
  else show('start');
})();
