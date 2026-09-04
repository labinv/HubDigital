const SELECTOR = '[data-hub-notification-id]';
const STORAGE_KEY = 'hubdigital:last-browser-notification';
const CONFIG_URL = '/pwa/configuracion';
const SUBSCRIPTIONS_URL = '/pwa/suscripciones';

function emitStatus(status, message) {
    window.dispatchEvent(new CustomEvent('hub-pwa-status', {
        detail: { status, message },
    }));
}

async function registration() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return null;
    await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
    return navigator.serviceWorker.ready;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function urlBase64ToUint8Array(value) {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
}

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
        ...options,
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
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

async function status() {
    if (!('Notification' in window) || !('PushManager' in window) || !window.isSecureContext) {
        emitStatus('unsupported', 'Este navegador no admite avisos push seguros.');
        return;
    }

    const serviceWorker = await registration();
    const subscription = await serviceWorker?.pushManager.getSubscription();
    emitStatus(
        subscription ? 'enabled' : Notification.permission === 'denied' ? 'denied' : 'disabled',
        subscription ? 'Avisos activos en este dispositivo.' : null,
    );
}

async function enable() {
    try {
        if (!('Notification' in window) || !('PushManager' in window) || !window.isSecureContext) {
            emitStatus('unsupported', 'Los avisos requieren HTTPS y un navegador compatible.');
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            emitStatus('denied', 'El navegador no autorizó las notificaciones.');
            return;
        }

        const configuration = await jsonRequest(CONFIG_URL);
        if (!configuration.enabled || !configuration.publicKey) {
            // El aviso en primer plano sigue disponible, pero nunca simulamos
            // que existe push si el servidor no tiene VAPID configurado.
            await showLatest(document.querySelector(SELECTOR));
            emitStatus('unavailable', 'Push pendiente de configuración en este ambiente.');
            return;
        }

        const serviceWorker = await registration();
        let subscription = await serviceWorker.pushManager.getSubscription();
        if (!subscription) {
            subscription = await serviceWorker.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(configuration.publicKey),
            });
        }

        const payload = subscription.toJSON();
        await jsonRequest(SUBSCRIPTIONS_URL, {
            method: 'POST',
            body: JSON.stringify({
                endpoint: subscription.endpoint,
                keys: payload.keys,
                contentEncoding: PushManager.supportedContentEncodings?.[0] ?? 'aes128gcm',
            }),
        });

        emitStatus('enabled', 'Avisos activos en este dispositivo.');
    } catch {
        emitStatus('error', 'No fue posible activar los avisos. Inténtalo nuevamente.');
    }
}

async function disable() {
    try {
        const serviceWorker = await registration();
        const subscription = await serviceWorker?.pushManager.getSubscription();
        if (subscription) {
            await jsonRequest(SUBSCRIPTIONS_URL, {
                method: 'DELETE',
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });
            await subscription.unsubscribe();
        }
        emitStatus('disabled', 'Avisos desactivados en este dispositivo.');
    } catch {
        emitStatus('error', 'No fue posible desactivar los avisos.');
    }
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

window.hubPwaNotifications = { enable, disable, status };
document.addEventListener('DOMContentLoaded', () => {
    observe();
    status().catch(() => emitStatus('error', 'No fue posible consultar el estado de los avisos.'));
}, { once: true });
