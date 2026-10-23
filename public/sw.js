/*
 * The service worker behind the installed app.
 *
 * It keeps a deliberately small footprint: pages are always fetched from the
 * network, since every one of them is rendered for the signed-in user and
 * carries a CSRF token, and only when the network is unreachable does a
 * navigation fall back to the offline page cached at install time. Built
 * assets under /build are content-hashed, so they are served from the cache
 * first and fetched once per deploy.
 *
 * The page registers this file with the current asset version in its query
 * string, so every deploy installs a fresh worker, refreshes the offline page,
 * and drops the caches of the previous build.
 */

const version = new URL(self.location.href).searchParams.get('v') ?? 'dev';
const offlineCache = `offline-${version}`;
const assetCache = `assets-${version}`;
const offlineUrl = '/offline.html';
const offlineFiles = [offlineUrl, '/favicon.ico', '/favicon.svg'];

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url ?? '/', self.location.origin);

    if (url.origin !== self.location.origin) return;

    event.waitUntil(self.clients.matchAll({ type: 'window' }).then(async (clients) => {
        const client = clients[0];

        if (client) {
            await client.navigate(url.href);
            await client.focus();
        } else {
            await self.clients.openWindow(url.href);
        }
    }));
});

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(offlineCache)
            .then((cache) => cache.addAll(offlineFiles.map((file) => new Request(file, { cache: 'reload' }))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== offlineCache && key !== assetCache)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(navigate(request));

        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(asset(request));
    } else if (offlineFiles.includes(url.pathname)) {
        event.respondWith(offlineFile(request));
    }
});

async function navigate(request) {
    try {
        return await fetch(request);
    } catch {
        const cached = await caches.match(offlineUrl, { cacheName: offlineCache });

        return cached ?? new Response('You are offline.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }
}

async function offlineFile(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cached = await caches.match(request, { cacheName: offlineCache, ignoreSearch: true });

        if (cached) {
            return cached;
        }

        throw error;
    }
}

async function asset(request) {
    const cache = await caches.open(assetCache);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok && response.type === 'basic') {
        await cache.put(request, response.clone());
    }

    return response;
}
