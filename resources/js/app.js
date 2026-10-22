import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import pos from './pos';

Alpine.plugin(collapse);

const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

/** JSON sorğu köməkçisi: xətada Laravel validasiya mesajını atır. */
window.api = async (url, { method = 'GET', body } = {}) => {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (res.status === 204) return null;
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const first = data.errors ? Object.values(data.errors)[0][0] : null;
        throw new Error(first || data.message || 'Xəta baş verdi');
    }
    return data;
};

/** Ekranın aşağısında qısa bildiriş. */
window.toast = (message, type = 'success') => window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));

window.money = (v, currency = window.APP_CURRENCY || '₼') =>
    `${Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/,/g, ' ')} ${currency}`;

/** Yeni pəncərədə çap səhifəsi açır (qəbz, token). */
window.printUrl = (url) => {
    const w = window.open(url, 'print', 'width=420,height=640');
    if (!w) window.location.href = url;
};

/** Periodik yenilənən siyahılar üçün (Orders, Kitchen). */
const poller = (url, interval = 8000) => ({
    rows: [],
    loading: true,
    timer: null,
    async init() {
        await this.load();
        this.timer = setInterval(() => document.visibilityState === 'visible' && this.load(), interval);
    },
    destroy() {
        clearInterval(this.timer);
    },
    async load() {
        try {
            this.rows = await api(url);
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            this.loading = false;
        }
    },
    async post(postUrl, body, reload = true) {
        try {
            const r = await api(postUrl, { method: 'POST', body });
            if (r?.message) toast(r.message);
            if (reload) await this.load();
            return r;
        } catch (e) {
            toast(e.message, 'error');
        }
    },
});
window.poller = poller;
Alpine.data('poller', poller);

Alpine.data('pos', pos);

window.Alpine = Alpine;
Alpine.start();
