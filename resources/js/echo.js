let echoPromise = null;

/**
 * Reverb bağlantısı (Promise); açar build-də yoxdursa (Reverb söndürülüb) null.
 * Echo + Pusher ayrıca chunk-dır: yalnız canlı yeniləmə lazım olan ekranlarda yüklənir
 * (landing, giriş, QR menyu kimi ictimai səhifələr onu endirmir).
 */
export function createEcho() {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) return Promise.resolve(null);

    return (echoPromise ??= Promise.all([import('laravel-echo'), import('pusher-js')])
        .then(([{ default: Echo }, { default: Pusher }]) => connect(Echo, Pusher, key))
        .catch(() => null));
}

function connect(Echo, Pusher, key) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
}

/** Bağlantı vəziyyətini Alpine store-a yazır (ekranlarda "Canlı" göstəricisi və polling tezliyi üçün). */
export function trackConnection(echo, store) {
    echo.connector.pusher.connection.bind('state_change', ({ current }) => {
        store.connected = current === 'connected';
    });
}

/**
 * Canlı yeniləmələr (Laravel Reverb). Yalnız biznes istifadəçisinin səhifələrində qoşulur
 * (<meta name="tenant-id">). Bağlantı olmasa ekranlar ehtiyat polling ilə işləməyə davam edir.
 */
export function startRealtime(Alpine) {
    const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
    if (!tenantId) return;
    createEcho().then((echo) => {
        if (!echo) return;
        trackConnection(echo, Alpine.store('rt'));
        echo.private(`tenant.${tenantId}`).listen('.order.changed', (e) => {
            window.dispatchEvent(new CustomEvent('rt:order', { detail: e }));
        });
    });
}
