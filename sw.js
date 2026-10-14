// The site's service worker, registered by js/safeguard.js on every page. When
// a page cannot be reached because the browser has no network, it answers with
// offline.html instead of the browser's own error. Nothing else is kept or
// served from a cache: with a network every request goes on as before.
//
// The ?v= below is the hash of each file (tools/asset-versions.php), so a
// change to the offline page changes this file too; the browser then installs
// it anew, with a cache of its own, and the old one is removed.
var OFFLINE = '/offline.html?v=e845940e41';
var FILES = [OFFLINE, '/fonts/sanakan-mono-400.woff2?v=68de4eecad', '/favicon.svg?v=3dcb782c4b'];
var PREFIX = 'sanakan-offline ';
var CACHE = PREFIX + FILES.join(' ');
// left behind when the offline page was shown; js/safeguard.js notes it as a
// find of the Safeguard's register on the next page and removes it
var SEEN = 'sanakan-offline-seen';

self.addEventListener('install', function (event) {
  event.waitUntil(caches.open(CACHE)
    .then(function (cache) { return cache.addAll(FILES); })
    .then(function () { return self.skipWaiting(); }));
});

// only the caches of this worker's older versions go; others on the site (the
// croppers' libraries may keep their own) stay
self.addEventListener('activate', function (event) {
  event.waitUntil(caches.keys()
    .then(function (names) {
      return Promise.all(names.filter(function (name) {
        return name.indexOf(PREFIX) === 0 && name !== CACHE;
      }).map(function (name) { return caches.delete(name); }));
    })
    .then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') return;

  // a page: from the network, the offline page only when that fails
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(function () {
      return caches.match(OFFLINE).then(function (page) {
        if (!page) return Response.error();
        return caches.open(SEEN).then(function () { return page; }, function () { return page; });
      });
    }));
    return;
  }

  // what the offline page itself loads
  var url = new URL(request.url);
  if (url.origin === self.location.origin && FILES.indexOf(url.pathname + url.search) >= 0)
    event.respondWith(caches.match(request).then(function (hit) { return hit || fetch(request); }));
});
