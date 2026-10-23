/**
 * Kassir kalkulyatoru (Alpine store "calc"): + − × ÷, %, ±. Klaviatura ilə də işləyir.
 * Hesablama addım-addım aparılır (adi kalkulyator kimi), qəpik dəqiqliyi üçün nəticə 10 rəqəmə yuvarlaqlaşdırılır.
 */
const OPS = { '+': (a, b) => a + b, '−': (a, b) => a - b, '×': (a, b) => a * b, '÷': (a, b) => (b === 0 ? NaN : a / b) };
const KEY_OPS = { '+': '+', '-': '−', '*': '×', x: '×', '/': '÷' };
const tidy = (n) => (Number.isFinite(n) ? String(parseFloat(n.toPrecision(10))) : 'Xəta');

export default () => ({
    open: false,
    display: '0',
    acc: null,
    op: null,
    fresh: true,
    history: '',

    init() {
        document.addEventListener('keydown', (e) => {
            if (!this.open) return;
            if (/^[0-9]$/.test(e.key)) this.digit(e.key);
            else if (e.key === '.' || e.key === ',') this.dot();
            else if (KEY_OPS[e.key]) this.operator(KEY_OPS[e.key]);
            else if (e.key === 'Enter' || e.key === '=') this.equals();
            else if (e.key === 'Backspace') this.back();
            else if (e.key === 'Escape') this.open = false;
            else if (e.key === '%') this.percent();
            else return;
            e.preventDefault();
        });
    },

    toggle() {
        this.open = !this.open;
    },

    digit(d) {
        if (this.fresh || this.display === '0' || this.display === 'Xəta') {
            this.display = d;
            this.fresh = false;
        } else if (this.display.replace(/[-.]/g, '').length < 12) {
            this.display += d;
        }
    },

    dot() {
        if (this.fresh || this.display === 'Xəta') {
            this.display = '0.';
            this.fresh = false;
        } else if (!this.display.includes('.')) {
            this.display += '.';
        }
    },

    operator(op) {
        if (this.op && !this.fresh) this.compute();
        else this.acc = Number(this.display);
        this.op = op;
        this.fresh = true;
        this.history = `${tidy(this.acc)} ${op}`;
    },

    compute() {
        const result = OPS[this.op](this.acc, Number(this.display));
        this.display = tidy(result);
        this.acc = Number.isFinite(result) ? result : null;
    },

    equals() {
        if (!this.op) return;
        this.history = `${tidy(this.acc)} ${this.op} ${this.display} =`;
        this.compute();
        this.op = null;
        this.fresh = true;
    },

    percent() {
        // 50 + 10% → 50-nin 10%-i (5); tək ədəddə 10% → 0.1
        const value = Number(this.display);
        this.display = tidy(this.op && this.acc !== null ? (this.acc * value) / 100 : value / 100);
        this.fresh = false;
    },

    negate() {
        if (this.display !== '0' && this.display !== 'Xəta') this.display = this.display.startsWith('-') ? this.display.slice(1) : '-' + this.display;
    },

    back() {
        if (this.fresh) return;
        this.display = this.display.length > 1 ? this.display.slice(0, -1) : '0';
    },

    clear() {
        Object.assign(this, { display: '0', acc: null, op: null, fresh: true, history: '' });
    },
});
