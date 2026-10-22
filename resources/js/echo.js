import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Canlı yeniləmələr (Laravel Reverb). Yalnız biznes istifadəçisinin səhifələrində qoşulur
 * (<meta name="tenant-id">). Bağlantı olmasa ekranlar ehtiyat polling ilə işləməyə davam edir.
 */
export function startRealtime(Alpine) {
    const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!tenantId || !key) return;

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

    const store = Alpine.store('rt');
    window.Echo.connector.pusher.connection.bind('state_change', ({ current }) => {
        store.connected = current === 'connected';
    });

    window.Echo.private(`tenant.${tenantId}`).listen('.order.changed', (e) => {
        window.dispatchEvent(new CustomEvent('rt:order', { detail: e }));
    });
}
