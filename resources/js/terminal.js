/**
 * POS terminal rejimi (Alpine store "terminal").
 * Kilidli ekranda ekran qoruyucu göstərilir; toxunanda PIN soruşulur. Hərəkətsizlikdən
 * (lockTimeout saniyə) sonra ekran yenidən kilidlənir. Server tərəfi də eyni qaydanı yoxlayır.
 */
export default (state) => ({
    ...state,
    mode: 'saver', // saver | pin
    purpose: 'unlock', // unlock | exit
    pin: '',
    error: '',
    busy: false,
    slide: 0,
    now: new Date(),
    lastActivity: Date.now(),
    lastPing: Date.now(),

    init() {
        const activity = () => this.activity();
        ['pointerdown', 'keydown', 'touchstart', 'wheel'].forEach((e) => document.addEventListener(e, activity, { passive: true }));
        window.addEventListener('terminal-locked', () => this.markLocked());
        document.addEventListener('keydown', (e) => {
            if (!this.active || !this.locked || this.mode !== 'pin') return;
            if (/^\d$/.test(e.key)) this.press(e.key);
            else if (e.key === 'Backspace') this.back();
            else if (e.key === 'Escape') this.mode = 'saver';
        });
        setInterval(() => this.tick(), 1000);
        setInterval(() => this.images.length > 1 && (this.slide = (this.slide + 1) % this.images.length), 7000);
    },

    apply(next) {
        for (const key of ['active', 'locked', 'operator', 'canPay', 'lockTimeout', 'lockAfterOrder', 'images']) {
            if (key in next) this[key] = next[key];
        }
    },

    activity() {
        this.lastActivity = Date.now();
        // Serverə "hələ işləyirəm" siqnalı (ən çox 10 saniyədə bir)
        if (this.active && !this.locked && Date.now() - this.lastPing > 10000) {
            this.lastPing = Date.now();
            api(this.routes.ping).then((r) => r?.locked && this.markLocked()).catch(() => {});
        }
    },

    tick() {
        this.now = new Date();
        if (this.active && !this.locked && Date.now() - this.lastActivity > this.lockTimeout * 1000) this.lock();
    },

    // Brauzerlərin çoxunda "az" Intl lokalı yoxdur — tarixi özümüz formatlayırıq
    get timeLabel() {
        return `${String(this.now.getHours()).padStart(2, '0')}:${String(this.now.getMinutes()).padStart(2, '0')}`;
    },

    get dateLabel() {
        const days = ['Bazar', 'Bazar ertəsi', 'Çərşənbə axşamı', 'Çərşənbə', 'Cümə axşamı', 'Cümə', 'Şənbə'];
        const months = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avqust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'];
        return `${days[this.now.getDay()]}, ${this.now.getDate()} ${months[this.now.getMonth()]}`;
    },

    get idleLeft() {
        return Math.max(0, Math.ceil(this.lockTimeout - (this.now - this.lastActivity) / 1000));
    },

    markLocked() {
        this.locked = true;
        this.operator = null;
        this.mode = 'saver';
        this.pin = '';
    },

    async lock() {
        this.markLocked();
        await api(this.routes.lock, { method: 'POST' }).catch(() => {});
    },

    async enter() {
        try {
            const r = await api(this.routes.enter, { method: 'POST' });
            this.apply(r.state);
            document.body.classList.add('pos-full', 'terminal-mode');
            this.markLocked();
            toast(r.message);
        } catch (e) {
            toast(e.message, 'error');
        }
    },

    openPin(purpose = 'unlock') {
        this.purpose = purpose;
        this.mode = 'pin';
        this.pin = '';
        this.error = '';
    },

    press(digit) {
        if (this.busy || this.pin.length >= 4) return;
        this.error = '';
        this.pin += digit;
        if (this.pin.length === 4) this.submit();
    },

    back() {
        this.pin = this.pin.slice(0, -1);
    },

    async submit() {
        this.busy = true;
        try {
            if (this.purpose === 'exit') {
                await api(this.routes.exit, { method: 'POST', body: { pin: this.pin } });
                window.location.reload();
                return;
            }
            const r = await api(this.routes.unlock, { method: 'POST', body: { pin: this.pin } });
            this.apply(r.state);
            this.lastActivity = this.lastPing = Date.now();
            this.mode = 'saver';
            toast(r.message);
        } catch (e) {
            this.error = e.message;
            if (navigator.vibrate) navigator.vibrate(150);
        } finally {
            this.pin = '';
            this.busy = false;
        }
    },

    /** Sifariş/ödənişdən sonra (ayara görə) kilidləyir ki, növbəti işçi öz PIN-i ilə davam etsin. */
    afterOrder() {
        if (this.active && this.lockAfterOrder) setTimeout(() => this.lock(), 2500);
    },
});
