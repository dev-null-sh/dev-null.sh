// Take over right away, otherwise an updated worker waits until every tab is closed.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    const { title, ...options } = event.data.json();

    event.waitUntil(
        Promise.all([
            self.registration.showNotification(title, options),
            // Open tabs refresh their badge and list without a page load.
            self.clients
                .matchAll({ type: 'window', includeUncontrolled: true })
                .then((clients) => clients.forEach((client) => client.postMessage({ type: 'notification' }))),
        ]),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    event.waitUntil(self.clients.openWindow(event.notification.data?.url ?? '/notifications'));
});
