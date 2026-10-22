import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import pos from './pos';
import terminal from './terminal';
import { startRealtime } from './echo';

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
    if (res.status === 423) window.dispatchEvent(new CustomEvent('terminal-locked'));
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

/**
 * Yenilənən siyahılar (Sifarişlər, Mətbəx). Reverb bağlıdırsa siqnal gələn kimi yenilənir,
 * ehtiyat olaraq 60 saniyədə bir; bağlantı yoxdursa hər `interval` ms-dən bir yoxlayır.
 */
const poller = (url, interval = 8000) => ({
    rows: [],
    loading: true,
    timer: null,
    lastLoad: 0,
    debounce: null,
    async init() {
        await this.load();
        this.timer = setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            const wait = Alpine.store('rt').connected ? 60000 : interval;
            if (Date.now() - this.lastLoad >= wait) this.load();
        }, 1000);
        this.onRealtime = (e) => {
            this.realtime?.(e.detail);
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => this.load(), 250);
        };
        window.addEventListener('rt:order', this.onRealtime);
    },
    destroy() {
        clearInterval(this.timer);
        window.removeEventListener('rt:order', this.onRealtime);
    },
    async load() {
        this.lastLoad = Date.now();
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

// POS və Sifarişlər səhifələri terminal vəziyyətini window.TERMINAL_STATE ilə ötürür
if (window.TERMINAL_STATE) Alpine.store('terminal', terminal(window.TERMINAL_STATE));

// Reverb bağlantısının vəziyyəti (ekranlarda "Canlı" göstəricisi və polling tezliyi üçün)
Alpine.store('rt', { connected: false });
startRealtime(Alpine);

/**
 * Səslər (fayl tələb etmir, WebAudio ilə yaradılır). Brauzer səsə yalnız istifadəçi ekrana
 * ən azı bir dəfə toxunandan sonra icazə verir.
 *  - add:   məhsul səbətə əlavə olunanda qısa, yumşaq "klik"
 *  - alert: yeni sifariş üçün ikili siqnal
 */
const SOUNDS = {
    add: [[0, 1320, 0.06, 0.18]],
    alert: [[0, 880, 0.15, 0.25], [0.18, 880, 0.15, 0.25]],
};
window.playSound = (kind = 'alert') => {
    try {
        const ctx = (window.__audio ??= new AudioContext());
        if (ctx.state === 'suspended') ctx.resume();
        for (const [at, freq, length, volume] of SOUNDS[kind] ?? SOUNDS.alert) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = kind === 'add' ? 'triangle' : 'sine';
            o.frequency.value = freq;
            g.gain.setValueAtTime(volume, ctx.currentTime + at);
            g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + at + length);
            o.connect(g).connect(ctx.destination);
            o.start(ctx.currentTime + at);
            o.stop(ctx.currentTime + at + length + 0.01);
        }
    } catch {}
};
window.beep = () => window.playSound('alert');

window.Alpine = Alpine;
Alpine.start();
