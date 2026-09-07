const SELECTOR = '[data-hub-notification-id]';
const STORAGE_KEY = 'hubdigital:browser-notifications-v2';
const TOAST_STORAGE_KEY = 'hubdigital:in-app-notifications-v2';
const MAX_REMEMBERED_NOTIFICATIONS = 80;
const CONFIG_URL = '/pwa/configuracion';
const SUBSCRIPTIONS_URL = '/pwa/suscripciones';
const TOAST_TRAY_ID = 'hub-in-app-toast-tray';
let observerInitialized = false;
const presentingNatively = new Set();
const enMemoria = new Set();
let confirmacionPendiente = new Set();
let temporizadorConfirmacion = null;
let entregaActiva = null;

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

async function showLatest(element, entrega) {
    const id = element?.dataset.hubNotificationId;
    const body = element?.dataset.hubNotificationBody;
    if (!id || !body) return;

    // Cuando la aplicación está abierta, el curador recibe un aviso discreto
    // en el borde inferior, similar a una conversación de mensajería. No depende
    // de permisos del navegador y conserva la misma ruta accionable del push.
    showInAppToast(element);
    confirmDelivery(entrega);

    // La campana es la única responsable cuando la aplicación está abierta.
    // El service worker presenta Web Push cuando no existe una ventana operativa.
}

function confirmDelivery(entrega) {
    if (!entrega?.lote || !entrega?.componentId) return;
    entrega.ids.forEach((id) => confirmacionPendiente.add(id));
    if (temporizadorConfirmacion) return;
    temporizadorConfirmacion = window.setTimeout(() => {
        const ids = [...confirmacionPendiente];
        confirmacionPendiente = new Set();
        temporizadorConfirmacion = null;
        const actual = entregaActiva;
        if (!actual || !window.Livewire?.find) return;
        window.Livewire.find(actual.componentId)?.call('confirmarEntrega', actual.lote, ids).then(() => {
            entregaActiva = null;
        }).catch(() => {
            ids.forEach((id) => enMemoria.delete(id));
            entregaActiva = null;
        });
    }, 150);
}

function showInAppToast(element) {
    const id = element?.dataset.hubNotificationId;
    const body = element?.dataset.hubNotificationBody;
    if (!id || !body || wasRemembered(TOAST_STORAGE_KEY, id)) return;

    document.querySelector(`[data-hub-in-app-toast-id="${CSS.escape(id)}"]`)?.remove();

    const url = safeSameOriginUrl(element.dataset.hubNotificationUrl || '/dashboard');
    const toast = document.createElement('aside');
    toast.dataset.hubInAppToastId = id;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.className = 'hub-in-app-toast';

    const emblem = document.createElement('div');
    emblem.setAttribute('aria-hidden', 'true');
    emblem.textContent = '●';
    emblem.className = 'hub-in-app-toast__emblem';

    const content = document.createElement('div');
    content.className = 'hub-in-app-toast__content';
    const title = document.createElement('p');
    title.textContent = element.dataset.hubNotificationTitle || 'HubDigital · Curaduría';
    title.className = 'hub-in-app-toast__title';
    const message = document.createElement('p');
    message.textContent = body;
    message.className = 'hub-in-app-toast__message';
    const action = document.createElement('button');
    action.type = 'button';
    action.textContent = element.dataset.hubNotificationAction || 'Abrir expediente';
    action.className = 'hub-in-app-toast__action';
    action.addEventListener('click', () => navigateToNotification(url));
    content.append(title, message, action);

    const close = document.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', 'Cerrar aviso');
    close.textContent = '×';
    close.className = 'hub-in-app-toast__close';
    close.addEventListener('click', () => toast.remove());

    toast.append(emblem, content, close);
    toastTray().append(toast);
    remember(TOAST_STORAGE_KEY, id);
    window.setTimeout(() => toast.remove(), 12000);
}

function toastTray() {
    let tray = document.getElementById(TOAST_TRAY_ID);
    if (tray) return tray;

    tray = document.createElement('div');
    tray.id = TOAST_TRAY_ID;
    tray.className = 'hub-in-app-toast-tray';
    tray.setAttribute('aria-label', 'Avisos operativos');
    document.body.append(tray);

    return tray;
}

function rememberedIds(key) {
    try {
        const saved = JSON.parse(localStorage.getItem(key) ?? '[]');
        return Array.isArray(saved) ? saved.filter((id) => typeof id === 'string') : [];
    } catch {
        return [];
    }
}

function wasRemembered(key, id) {
    return rememberedIds(key).includes(id);
}

function remember(key, id) {
    const ids = rememberedIds(key).filter((saved) => saved !== id);
    ids.push(id);
    try {
        localStorage.setItem(key, JSON.stringify(ids.slice(-MAX_REMEMBERED_NOTIFICATIONS)));
    } catch {
        // Un almacenamiento local no disponible no debe bloquear la bandeja ni el push.
    }
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
    if (observerInitialized) return;
    observerInitialized = true;

    const scan = () => {
        const elementos = [...document.querySelectorAll(SELECTOR)].filter((element) => {
            const id = element.dataset.hubNotificationRecordId;
            if (!id || enMemoria.has(id)) return false;
            enMemoria.add(id);
            return true;
        });
        if (elementos.length === 0) return;
        const componente = elementos[0].closest('[wire\\:id]');
        const componentId = componente?.getAttribute('wire:id');
        const ids = elementos.map((element) => element.dataset.hubNotificationRecordId);
        const mostrar = (lote = '') => {
            const entrega = { componentId, lote, ids };
            entregaActiva = entrega;
            elementos.forEach((element) => showLatest(element, entrega).catch(() => enMemoria.delete(element.dataset.hubNotificationRecordId)));
        };
        if (!componentId || !window.Livewire?.find) {
            mostrar();
            return;
        }
        window.Livewire.find(componentId).call('registrarLoteEnviado', ids).then(mostrar).catch(() => ids.forEach((id) => enMemoria.delete(id)));
    };
    scan();
    new MutationObserver(scan).observe(document.body, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: [
            'data-hub-notification-id',
            'data-hub-notification-record-id',
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

document.addEventListener('livewire:navigated', () => {
    observe();
});

navigator.serviceWorker?.addEventListener('message', (event) => {
    if (event.data?.type !== 'hubdigital-push-pendiente') return;
    const component = document.querySelector('[data-hub-notification-record-id]')?.closest('[wire\\:id]');
    const componentId = component?.getAttribute('wire:id');
    window.Livewire?.find(componentId)?.$refresh();
});
