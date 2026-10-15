// The site's service worker, registered by js/safeguard.js on every page. When
// a page cannot be reached because the browser has no network, it answers with
// offline.html instead of the browser's own error. Nothing else is kept or
// served from a cache: with a network every request goes on as before.
//
// The ?v= below is the hash of each file (tools/asset-versions.php), so a
// change to the offline page changes this file too; the browser then installs
// it anew, with a cache of its own, and the old one is removed.
var OFFLINE = '/offline.html?v=85f0e271fb';
var FILES = [OFFLINE, '/fonts/sanakan-mono-400.woff2?v=68de4eecad', '/favicon.svg?v=3dcb782c4b'];
var PREFIX = 'sanakan-offline ';
var CACHE = PREFIX + FILES.join(' ');
// left behind when the offline page was shown; js/safeguard.js notes it as a
// find of the Safeguard's register on the next page and removes it
var SEEN = 'sanakan-offline-seen';
var CHOICE_CACHE = 'sanakan-discovery-choice';
var CHOICE_URL = new URL('sw.js?discovery-choice', self.registration.scope).href;
var discoveryPermission = caches.match(CHOICE_URL, { cacheName: CHOICE_CACHE })
  .then(function (response) { return response ? response.text().then(function (value) { return value === 'yes'; }) : false; })
  .catch(function () { return false; });
var permissionWrites = Promise.resolve();

self.addEventListener('message', function (event) {
  if (!event.data || event.data.type !== 'sanakan-discovery-storage') return;
  var allowed = event.data.enabled === true;
  discoveryPermission = Promise.resolve(allowed);
  permissionWrites = permissionWrites.catch(function () {}).then(function () {
    return caches.open(CHOICE_CACHE).then(function (cache) { return cache.put(CHOICE_URL, new Response(allowed ? 'yes' : 'no')); });
  });
  event.waitUntil(Promise.all([permissionWrites, allowed ? Promise.resolve() : caches.delete(SEEN)]));
});

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
        return discoveryPermission.then(function (allowed) {
          if (!allowed) return page;
          return caches.open(SEEN).then(function () { return page; }, function () { return page; });
        });
      });
    }));
    return;
  }

  // what the offline page itself loads
  var url = new URL(request.url);
  if (url.origin === self.location.origin && FILES.indexOf(url.pathname + url.search) >= 0)
    event.respondWith(caches.match(request).then(function (hit) { return hit || fetch(request); }));
});
