import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import pos from './pos';
import terminal from './terminal';
import { startRealtime } from './echo';
import customerDisplay from './display';
import { planEditor } from './floorplan';
import staffMeal from './staff-meal';
import stockAlerts from './stock-alerts';
import checkout from './checkout';
import calculator from './calculator';
import { approvalStore, returnsStore, withApproval } from './returns';

Alpine.plugin(collapse);

/**
 * Telefon maskası (x-phone): yalnız rəqəm və əvvəldə "+". Azərbaycan nömrəsi +994 50 123 45 67 kimi qruplaşdırılır,
 * 0 ilə başlayan yerli nömrə (050...) +994-ə çevrilir. Server də eyni qaydanı yoxlayır.
 */
const formatPhone = (value) => {
    // Hazır "+994 "-dən sonra yeni "+" yazılıbsa (xarici nömrə) nömrə oradan başlayır
    if (value.lastIndexOf('+') > 0) value = value.slice(value.lastIndexOf('+'));
    let digits = value.replace(/\D/g, '');
    if (!digits) return value.trim().startsWith('+') ? '+' : '';
    if (digits.startsWith('0')) digits = '994' + digits.slice(1);
    // Fokusda "+994 " hazır gəlir: istifadəçi yenə +994 və ya 0 ilə yazsa təkrarlanmasın
    if (digits.startsWith('994994')) digits = digits.slice(3);
    if (digits.startsWith('9940')) digits = '994' + digits.slice(4);
    if (!digits.startsWith('994')) return '+' + digits.slice(0, 15);
    const groups = [3, 2, 3, 2, 2];
    const parts = [];
    let rest = digits.slice(0, 12);
    for (const size of groups) {
        if (!rest) break;
        parts.push(rest.slice(0, size));
        rest = rest.slice(size);
    }
    return '+' + parts.join(' ');
};
Alpine.directive('phone', (el) => {
    const apply = () => {
        const formatted = formatPhone(el.value);
        if (formatted !== el.value) el.value = formatted;
    };
    el.addEventListener('input', apply);
    el.addEventListener('focus', () => el.value || (el.value = '+994 '));
    el.addEventListener('blur', () => el.value.replace(/\D/g, '') === '994' && (el.value = ''));
    apply();
});

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
        const error = new Error(first || data.message || 'Xəta baş verdi');
        error.errors = data.errors ?? null; // withApproval() approver_pin xətasını buradan tanıyır
        error.status = res.status;
        throw error;
    }
    return data;
};

/** Ekranın aşağısında qısa bildiriş. */
window.toast = (message, type = 'success') => window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));

window.money = (v, currency = window.APP_CURRENCY || '₼') =>
    `${Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/,/g, ' ')} ${currency}`;

/** Yeni pəncərədə çap səhifəsi açır (qəbz, token). */
/**
 * Sürətli çap: sənəd görünməyən iframe-də açılır və dərhal çap olunur (ayrıca pəncərə yoxdur).
 * Növbə ilə: mətbəx çeki və qəbz eyni anda gəlsə, biri bitəndən sonra digəri çap olunur.
 * Brauzer --kiosk-printing ilə açılıbsa, dialoq da çıxmır — sənəd birbaşa defolt printerə gedir.
 */
const printQueue = [];
let printing = false;
const nextPrint = () => {
    if (printing || !printQueue.length) return;
    printing = true;
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:80mm;height:200mm;border:0';
    let finished = false;
    const done = () => {
        if (finished) return;
        finished = true;
        printing = false;
        setTimeout(() => frame.remove(), 2000);
        nextPrint();
    };
    frame.addEventListener('load', () => {
        frame.contentWindow.addEventListener('afterprint', done);
        setTimeout(done, 60000); // dialoq açıq qalıbsa növbə ilişməsin
    });
    frame.src = printQueue.shift();
    document.body.appendChild(frame);
};
window.printUrl = (url) => {
    printQueue.push(url);
    nextPrint();
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
Alpine.data('customerDisplay', customerDisplay);
Alpine.data('planEditor', planEditor);
Alpine.data('staffMeal', staffMeal);

// POS və Sifarişlər səhifələri terminal vəziyyətini window.TERMINAL_STATE ilə ötürür
if (window.TERMINAL_STATE) Alpine.store('terminal', terminal(window.TERMINAL_STATE));

// Reverb bağlantısının vəziyyəti (ekranlarda "Canlı" göstəricisi və polling tezliyi üçün)
Alpine.store('rt', { connected: false });

// Menecer təsdiqi (PIN) və ləğv / qaytarma pəncərələri — layout-dakı komponentlər istifadə edir
window.withApproval = withApproval;
Alpine.store('approval', approvalStore());
// Hesabı bağlama ekranı (bölünmüş ödəniş) — POS və Sifarişlər səhifələrindəki <x-checkout-screen />
Alpine.store('checkout', checkout());
Alpine.store('calc', calculator());
if (window.RETURN_ROUTES) Alpine.store('returns', returnsStore(window.RETURN_ROUTES));
// Stok bildirişləri (anbar / mətbəx icazəsi olanlar üçün layout ünvanları ötürür)
if (window.STOCK_ALERT_URLS) Alpine.store('alerts', stockAlerts(window.STOCK_ALERT_URLS));
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
