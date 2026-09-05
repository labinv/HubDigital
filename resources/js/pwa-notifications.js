const SELECTOR = '[data-hub-notification-id]';
const STORAGE_KEY = 'hubdigital:last-browser-notification';
const TOAST_STORAGE_KEY = 'hubdigital:last-in-app-notification';
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
    const id = element?.dataset.hubNotificationId;
    const body = element?.dataset.hubNotificationBody;
    if (!id || !body) return;

    // Cuando la aplicación está abierta, el curador recibe un aviso discreto
    // en el borde inferior, similar a una conversación de mensajería. No depende
    // de permisos del navegador y conserva la misma ruta accionable del push.
    showInAppToast(element);

    if (!('Notification' in window) || Notification.permission !== 'granted') return;
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

function showInAppToast(element) {
    const id = element?.dataset.hubNotificationId;
    const body = element?.dataset.hubNotificationBody;
    if (!id || !body || localStorage.getItem(TOAST_STORAGE_KEY) === id) return;

    document.querySelector(`[data-hub-in-app-toast-id="${CSS.escape(id)}"]`)?.remove();

    const url = safeSameOriginUrl(element.dataset.hubNotificationUrl || '/dashboard');
    const toast = document.createElement('aside');
    toast.dataset.hubInAppToastId = id;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.style.cssText = [
        'position:fixed', 'right:16px', 'bottom:16px', 'z-index:2147483647',
        'display:flex', 'width:min(390px,calc(100vw - 32px))', 'gap:12px',
        'border:1px solid rgba(23,55,94,.16)', 'border-radius:16px',
        'background:#fff', 'box-shadow:0 18px 45px rgba(15,35,60,.24)',
        'padding:14px', 'color:#15253a', 'font-family:Inter,system-ui,sans-serif',
    ].join(';');

    const emblem = document.createElement('div');
    emblem.setAttribute('aria-hidden', 'true');
    emblem.textContent = '●';
    emblem.style.cssText = 'display:grid;place-items:center;flex:0 0 34px;height:34px;border-radius:50%;background:#eaf4ef;color:#167247;font-size:18px';

    const content = document.createElement('div');
    content.style.cssText = 'min-width:0;flex:1';
    const title = document.createElement('p');
    title.textContent = element.dataset.hubNotificationTitle || 'HubDigital · Curaduría';
    title.style.cssText = 'margin:0 26px 3px 0;font-size:13px;font-weight:700;color:#17375e';
    const message = document.createElement('p');
    message.textContent = body;
    message.style.cssText = 'margin:0;font-size:13px;line-height:1.45;color:#435366';
    const action = document.createElement('button');
    action.type = 'button';
    action.textContent = element.dataset.hubNotificationAction || 'Abrir expediente';
    action.style.cssText = 'margin-top:9px;border:0;background:transparent;padding:0;color:#1265a8;font-size:13px;font-weight:700;cursor:pointer';
    action.addEventListener('click', () => navigateToNotification(url));
    content.append(title, message, action);

    const close = document.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', 'Cerrar aviso');
    close.textContent = '×';
    close.style.cssText = 'position:absolute;right:10px;top:7px;border:0;background:transparent;color:#607083;font-size:23px;line-height:1;cursor:pointer';
    close.addEventListener('click', () => toast.remove());

    toast.append(emblem, content, close);
    document.body.append(toast);
    localStorage.setItem(TOAST_STORAGE_KEY, id);
    window.setTimeout(() => toast.remove(), 12000);
}

function safeSameOriginUrl(value) {
    const destination = new URL(value, window.location.origin);

    return destination.origin === window.location.origin ? destination.href : `${window.location.origin}/dashboard`;
}

function navigateToNotification(url) {
    // Livewire conserva la navegación fluida cuando está disponible; el enlace
    // clásico garantiza acceso también desde una página cargada sin Livewire.
    if (window.Livewire?.navigate) {
        window.Livewire.navigate(url);

        return;
    }

    window.location.assign(url);
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
            'data-hub-notification-action',
        ],
    });
}

window.hubPwaNotifications = { enable, disable, status };
document.addEventListener('DOMContentLoaded', () => {
    observe();
    status().catch(() => emitStatus('error', 'No fue posible consultar el estado de los avisos.'));
}, { once: true });
