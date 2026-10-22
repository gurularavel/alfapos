import { createEcho, trackConnection } from './echo';

/**
 * Müştəriyə baxan ekran. POS səbəti dəyişdikcə Reverb ilə vəziyyət gəlir;
 * bağlantı yoxdursa hər 3 saniyədən bir serverdən son vəziyyət soruşulur.
 * Rejimlər: idle (ekran qoruyucu), cart (səbət), paid / sent (təşəkkür + token).
 */
export default (config) => ({
    ...config,
    slide: 0,
    now: new Date(),
    connected: false,
    thanksTimer: null,

    init() {
        const echo = createEcho();
        if (echo) {
            trackConnection(echo, this);
            echo.channel(this.channel).listen('.display.updated', (state) => this.apply(state));
        }
        // Ehtiyat: Reverb bağlı deyilsə vəziyyəti soruşuruq; bağlıdırsa da ara-sıra (ekranın "online" görünməsi üçün)
        let tick = 0;
        setInterval(() => {
            tick++;
            if (!this.connected || tick % 20 === 0) this.poll();
        }, 3000);
        setInterval(() => (this.now = new Date()), 1000);
        setInterval(() => this.images.length > 1 && (this.slide = (this.slide + 1) % this.images.length), 8000);
        this.apply(this.state, true);
    },

    async poll() {
        try {
            const r = await fetch(this.stateUrl, { headers: { Accept: 'application/json' } });
            if (r.ok) this.apply(await r.json());
        } catch {}
    },

    apply(state, initial = false) {
        if (!state || (this.state?.at && state.at && state.at < this.state.at && !initial)) return;
        this.state = state;
        clearTimeout(this.thanksTimer);
        // Təşəkkür ekranı bir müddət görünür, sonra ekran qoruyucuya qayıdır
        if (state.mode === 'paid' || state.mode === 'sent') {
            this.thanksTimer = setTimeout(() => (this.state = { ...this.state, mode: 'idle' }), 10000);
        }
    },

    get mode() {
        return this.state?.mode === 'cart' && !this.state.items?.length ? 'idle' : this.state?.mode ?? 'idle';
    },

    money(v) {
        return `${Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/,/g, ' ')} ${this.currency}`;
    },

    get timeLabel() {
        return `${String(this.now.getHours()).padStart(2, '0')}:${String(this.now.getMinutes()).padStart(2, '0')}`;
    },
});
