const SELECTOR = '[data-hub-notification-id]';
const STORAGE_KEY = 'hubdigital:last-browser-notification';

async function registration() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return null;
    return navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
}

async function showLatest(element) {
    if (!('Notification' in window)) return;
    const id = element?.dataset.hubNotificationId;
    const body = element?.dataset.hubNotificationBody;
    if (!id || !body || Notification.permission !== 'granted') return;
    if (localStorage.getItem(STORAGE_KEY) === id) return;

    const serviceWorker = await registration();
    await serviceWorker?.showNotification(
        element.dataset.hubNotificationTitle || 'HubDigital',
        {
            body,
            icon: '/images/hub-icon.png',
            badge: '/images/hub-icon.png',
            tag: `hubdigital-${id}`,
            renotify: true,
            data: { url: element.dataset.hubNotificationUrl || '/dashboard' },
        },
    );
    localStorage.setItem(STORAGE_KEY, id);
}

async function enable() {
    if (!('Notification' in window)) return;
    const permission = await Notification.requestPermission();
    if (permission === 'granted') await showLatest(document.querySelector(SELECTOR));
}

function observe() {
    const scan = () => document.querySelectorAll(SELECTOR).forEach(showLatest);
    scan();
    new MutationObserver(scan).observe(document.body, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: [
            'data-hub-notification-id',
            'data-hub-notification-body',
            'data-hub-notification-url',
        ],
    });
}

window.hubPwaNotifications = { enable };
registration().catch(() => null);
document.addEventListener('DOMContentLoaded', observe, { once: true });
