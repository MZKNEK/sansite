// The worker that writes a big incoming file of the transfer page (t.js) to
// the page's own disk in the browser (the origin private file system), piece
// by piece, so it never has to fit in memory. Synchronous access handles,
// which every browser with that disk gives only to a worker, write fast and
// in Safari too. Each file, named by the page's random id in the folder
// sanakan-transfer, gets in order: {op: 'open'}, {op: 'write', buffer}...,
// {op: 'close'}, answered with the File read from the disk, or
// {op: 'remove'}. Every answer is {id, ok, op, file} or {id, ok: false,
// error: 'space' or 'failed'}; after a failure only a removal is done.
'use strict';

var DIR = 'sanakan-transfer';
var folder = null;
var files = {}; // by id: { chain, entry, handle, at, failed }

function storeFolder() {
  if (!folder) folder = navigator.storage.getDirectory().then(function (root) {
    return root.getDirectoryHandle(DIR, { create: true });
  });
  return folder;
}

function run(message, file) {
  if (message.op === 'open') {
    return storeFolder().then(function (dir) {
      return dir.getFileHandle(message.id, { create: true });
    }).then(function (entry) {
      file.entry = entry;
      return entry.createSyncAccessHandle();
    }).then(function (handle) {
      file.handle = handle;
      handle.truncate(0);
      return {};
    });
  }
  if (message.op === 'write') {
    var bytes = new Uint8Array(message.buffer);
    var written = file.handle.write(bytes, { at: file.at });
    file.at += written;
    if (written !== bytes.length) throw new DOMException('short write', 'QuotaExceededError');
    return null;
  }
  if (message.op === 'close') {
    file.handle.flush();
    file.handle.close();
    file.handle = null;
    return file.entry.getFile().then(function (found) { return { file: found }; });
  }
  if (message.op === 'remove') {
    if (file.handle) file.handle.close();
    delete files[message.id];
    return storeFolder().then(function (dir) {
      return dir.removeEntry(message.id);
    }).catch(function () { /* gone already */ }).then(function () { return {}; });
  }
  return null;
}

self.addEventListener('message', function (event) {
  var message = event.data;
  var file = files[message.id] || (files[message.id] = { chain: Promise.resolve(), at: 0, failed: false });
  file.chain = file.chain.then(function () {
    if (file.failed && message.op !== 'remove') return;
    return Promise.resolve(run(message, file)).then(function (answer) {
      if (answer) self.postMessage({ id: message.id, ok: true, op: message.op, file: answer.file });
    });
  }).catch(function (error) {
    file.failed = true;
    if (file.handle) {
      try { file.handle.close(); } catch (_) { /* closed already */ }
      file.handle = null;
    }
    self.postMessage({ id: message.id, ok: false, error: error && error.name === 'QuotaExceededError' ? 'space' : 'failed' });
  });
});
