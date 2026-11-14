/* ============================================================
   Service worker - Rent Management System (PWA layer)

   Caching policy is deliberately conservative because this app
   serves private, session-authenticated HTML:

   * navigations  -> network first, never cached, offline.html on failure
   * /ajax/, file.php, install.php, manifest.php, cron/ -> not touched
   * same-origin assets under assets/ -> cache first (+ background refresh)
   * CDN (Tailwind, jsDelivr, Google Fonts) -> stale-while-revalidate
   * non-GET -> not touched, so payments/saves always hit the server

   Result: the installed app opens instantly and shows a proper
   "you're offline" screen instead of a browser error, while tenant
   data never leaks from a cache on a shared device.
   ============================================================ */

const VERSION = 'rms-pwa-v2';
const STATIC_CACHE = VERSION + '-static';
const CDN_CACHE = VERSION + '-cdn';

// self.registration.scope is .../oop-rms/ on this host and works the
// same at a domain root, so all URLs below stay sub-folder safe.
const BASE = new URL(self.registration.scope).pathname;
const OFFLINE_URL = BASE + 'offline.html';

const PRECACHE = [
    OFFLINE_URL,
    BASE + 'assets/css/style.css',
    BASE + 'assets/js/app.js',
    BASE + 'assets/js/pwa.js',
    BASE + 'assets/icons/icon-192.png',
    BASE + 'assets/icons/icon-512.png',
    BASE + 'assets/icons/favicon-32.png',
    BASE + 'assets/images/favicon.svg',
];

// Endpoints that must never be served from (or written to) a cache.
const BYPASS = /(\/ajax\/|\/file\.php|\/install\.php|\/manifest\.php|\/cron\/)/i;
const ASSET = /\/assets\//i;

const CDN_HOSTS = [
    'cdn.tailwindcss.com',
    'cdn.jsdelivr.net',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'unpkg.com',
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        // addAll() is all-or-nothing; one 404 would abort the install.
        await Promise.all(PRECACHE.map((url) =>
            cache.add(new Request(url, { cache: 'reload' })).catch(() => {})
        ));
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(
            names
                .filter((n) => n.startsWith('rms-pwa-') && n !== STATIC_CACHE && n !== CDN_CACHE)
                .map((n) => caches.delete(n))
        );
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

/* ---------- due-date reminders (Web Push) ---------- */

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (err) {
        data = { body: event.data ? event.data.text() : '' };
    }

    const scope = self.registration.scope;
    const title = data.title || 'Rent reminder';

    event.waitUntil(self.registration.showNotification(title, {
        body: data.body || '',
        icon: data.icon || scope + 'assets/icons/icon-192.png',
        badge: data.badge || scope + 'assets/icons/icon-192.png',
        // Same tag = a newer reminder replaces the older one instead of stacking.
        tag: data.tag || 'rms-reminder',
        renotify: true,
        data: { url: data.url || scope },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || self.registration.scope;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        // Reuse an open tab when there is one, so the tenant doesn't end up
        // with five copies of the portal after five reminders.
        for (const client of windows) {
            if ('focus' in client) {
                await client.focus();
                if ('navigate' in client) {
                    try { await client.navigate(target); } catch (e) { /* cross-origin */ }
                }
                return;
            }
        }
        if (self.clients.openWindow) {
            await self.clients.openWindow(target);
        }
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return; // never intercept writes

    const url = new URL(request.url);

    if (url.origin === self.location.origin && BYPASS.test(url.pathname)) return;

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    if (url.origin === self.location.origin) {
        if (ASSET.test(url.pathname)) {
            event.respondWith(cacheFirst(request));
        }
        return;
    }

    if (CDN_HOSTS.includes(url.hostname)) {
        event.respondWith(staleWhileRevalidate(request));
    }
});

/* ---------- strategies ---------- */

async function networkFirstNavigation(request) {
    try {
        // cache: 'no-store' keeps a stale authenticated page out of the
        // HTTP cache after logout / role change.
        return await fetch(request, { cache: 'no-store' });
    } catch (err) {
        const cached = await caches.match(OFFLINE_URL);
        if (cached) return cached;
        return new Response(
            '<!doctype html><meta charset="utf-8"><title>Offline</title>' +
            '<body style="font-family:system-ui;padding:2rem;text-align:center">' +
            '<h1>You are offline</h1><p>Reconnect and try again.</p></body>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request, { ignoreSearch: true });
    if (cached) {
        // Refresh in the background so a deploy is picked up next visit.
        fetch(request).then((res) => {
            if (res && res.ok) cache.put(request, res.clone());
        }).catch(() => {});
        return cached;
    }
    const response = await fetch(request);
    if (response && response.ok) cache.put(request, response.clone());
    return response;
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(CDN_CACHE);
    const cached = await cache.match(request);
    const network = fetch(request).then((res) => {
        // Opaque (no-cors) responses can still be stored; status 0 is normal.
        if (res && (res.ok || res.type === 'opaque')) {
            cache.put(request, res.clone());
        }
        return res;
    }).catch(() => null);
    return cached || (await network) || Response.error();
}
