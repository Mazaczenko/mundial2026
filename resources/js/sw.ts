/// <reference lib="webworker" />
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

declare const self: ServiceWorkerGlobalScope;

cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST);

self.addEventListener('push', (event: PushEvent) => {
    if (!event.data) return;

    const data = event.data.json() as { title: string; body: string; url?: string };

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/android-chrome-192x192.png',
            badge: '/favicon-32x32.png',
            data: { url: data.url ?? '/bets' },
            vibrate: [200, 100, 200],
        })
    );
});

self.addEventListener('notificationclick', (event: NotificationEvent) => {
    event.notification.close();
    const url = (event.notification.data?.url ?? '/bets') as string;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const match = clients.find(c => c.url.includes(url));
            if (match) return match.focus();
            return self.clients.openWindow(url);
        })
    );
});
