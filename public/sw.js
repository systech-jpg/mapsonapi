const CACHE_VERSION = 'v1';
const CACHE_NAME = `app-shell-${CACHE_VERSION}`;

const PRECACHE = [
  '/offline.html',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Jangan pernah layani data API dari cache — isinya harus selalu segar.
  if (url.pathname.startsWith('/api/')) return;

  // Halaman: network-first, fallback ke halaman offline
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  // Aset statis: cache-first
  event.respondWith(caches.match(req).then((cached) => cached || fetch(req)));
});

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data.json();
  } catch (e) {
    payload = { title: 'Notifikasi', body: event.data ? event.data.text() : '' };
  }

  event.waitUntil(
    self.registration.showNotification(payload.title || 'Notifikasi', {
      body: payload.body || '',
      icon: payload.icon || '/icons/icon-192.png',
      badge: '/badge-72.png',
      tag: payload.tag || undefined,
      data: { url: (payload.data && payload.data.url) || '/' },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
      for (const win of wins) {
        if (win.url.includes(target) && 'focus' in win) return win.focus();
      }
      return clients.openWindow(target);
    })
  );
});
