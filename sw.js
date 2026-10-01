/**
 * Prismatch - Service Worker (PWA)
 * Complete offline support, static asset caching (Stale-While-Revalidate),
 * network-first navigation with offline fallback, and background sync.
 */

const CACHE_NAME = 'prismatch-pwa-v2';

const PRECACHE_ASSETS = [
  '/',
  '/play.php',
  '/rooms.php',
  '/daily_leaderboard.php',
  '/offline.html',
  '/site.webmanifest',
  '/css/style.css',
  '/js/app.js',
  '/js/pwa.js',
  '/js/offline-store.js',
  '/logo.svg',
  '/logo.png',
  '/favicon.svg',
  '/favicon.ico',
  '/google.svg',
  '/error-x.svg',
  '/success-checkmark.svg',
  '/bootstrap-icons/play-fill.svg',
  '/bootstrap-icons/people-fill.svg',
  '/bootstrap-icons/trophy-fill.svg',
  '/bootstrap-icons/calendar2-check.svg',
  '/bootstrap-icons/list.svg',
  '/bootstrap-icons/search.svg',
  '/bootstrap-icons/translate.svg',
  '/bootstrap-icons/arrow-left.svg',
  '/bootstrap-icons/trophy.svg',
];

// Install: Pre-cache critical application shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[SW] Pre-caching core application shell');
      return cache.addAll(PRECACHE_ASSETS).catch((err) => {
        console.warn('[SW] Some precache assets failed to load:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate: Clean up older cache versions
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            console.log('[SW] Removing deprecated cache:', key);
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch: Smart caching strategies based on request type
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Skip non-GET requests (POST / PUT / DELETE)
  if (request.method !== 'GET') {
    return;
  }

  // 1. API Requests: Network-first
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request).catch(() => {
        return new Response(JSON.stringify({ ok: false, offline: true, error: 'Offline' }), {
          headers: { 'Content-Type': 'application/json' }
        });
      })
    );
    return;
  }

  // 2. HTML Navigation Requests: Network-First with Cache Fallback and Offline Page
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(async () => {
          // Check if the requested URL exists in cache
          const cached = await caches.match(request);
          if (cached) return cached;

          // If looking for play.php, serve cached play.php
          if (url.pathname.includes('play.php')) {
            const playCache = await caches.match('/play.php');
            if (playCache) return playCache;
          }

          // Fallback to offline fallback page
          const offlinePage = await caches.match('/offline.html');
          if (offlinePage) return offlinePage;

          return new Response('Offline - Prismatch', {
            status: 503,
            headers: { 'Content-Type': 'text/plain' }
          });
        })
    );
    return;
  }

  // 3. Static Assets (CSS, JS, Fonts, Flags, SVGs, Images): Cache-First with Stale-While-Revalidate
  const isStatic =
    url.pathname.match(/\.(css|js|svg|png|jpg|jpeg|gif|webp|ico|woff2|woff|ttf|webmanifest)$/i) ||
    url.hostname.includes('fonts.googleapis.com') ||
    url.hostname.includes('fonts.gstatic.com') ||
    url.pathname.startsWith('/flags/');

  if (isStatic) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        // Fetch from network to update cache in background
        const networkFetch = fetch(request)
          .then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
              const clone = networkResponse.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
            }
            return networkResponse;
          })
          .catch(() => cachedResponse);

        // Return cached immediately if available, otherwise wait for network
        return cachedResponse || networkFetch;
      })
    );
    return;
  }

  // 4. Default: Network with Cache Fallback
  event.respondWith(
    fetch(request)
      .then((response) => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
        }
        return response;
      })
      .catch(() => caches.match(request))
  );
});

// Background Sync for offline score sync
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-scores') {
    event.waitUntil(
      self.clients.matchAll().then((clients) => {
        clients.forEach((client) => {
          client.postMessage({ type: 'TRIGGER_SCORE_SYNC' });
        });
      })
    );
  }
});
