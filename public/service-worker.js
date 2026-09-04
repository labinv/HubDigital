function readPushPayload(data) {
    if (!data) return {};

    try {
        return data.json();
    } catch {
        return { body: data.text() };
    }
}

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    const payload = readPushPayload(event.data);
    event.waitUntil(self.registration.showNotification(payload.title ?? 'HubDigital', {
        body: payload.body ?? 'Tienes una nueva notificación.',
        icon: payload.icon ?? '/images/hub-icon.png',
        badge: payload.badge ?? '/images/hub-icon.png',
        tag: payload.tag ?? 'hubdigital',
        renotify: true,
        data: { url: payload.data?.url ?? payload.url ?? '/dashboard' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destino = new URL(event.notification.data?.url ?? '/dashboard', self.location.origin);
    const url = destino.origin === self.location.origin
        ? destino.href
        : new URL('/dashboard', self.location.origin).href;
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const existing = windows.find((client) => client.url === url);
        return existing ? existing.focus() : clients.openWindow(url);
    }));
});

// Deliberadamente no se intercepta `fetch`: los expedientes y PDF privados nunca
// deben quedar en una caché offline del service worker.
