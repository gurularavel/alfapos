/**
 * Hesabı bağlama ekranı (Alpine store "checkout") — PIN pəncərəsi kimi tam ekran.
 * Bölünmüş ödəniş: məbləği rəqəm klaviaturasında yazıb ödəniş növünə toxunun (məs. 20 → Nağd, qalanı → Kart).
 * Məbləğ yazılmayıbsa qalan məbləğin hamısı götürülür. Nağdda artıq verilən pul qalıq kimi göstərilir.
 *
 * checkout.open({ title, subtitle, due, types, submit: async (payments) => bool })
 * payments: [{ payment_type_id, amount, tendered? }]
 */
const round = (v) => Math.round(v * 100) / 100;

export default () => ({
    open: false,
    title: '',
    subtitle: '',
    due: 0,
    types: [],
    lines: [],
    entry: '',
    busy: false,
    error: '',
    submitFn: null,

    init() {
        document.addEventListener('keydown', (e) => {
            if (!this.open || this.busy) return;
            if (/^[0-9]$/.test(e.key) || e.key === '.' || e.key === ',') this.press(e.key === ',' ? '.' : e.key);
            else if (e.key === 'Backspace') this.back();
            else if (e.key === 'Escape') this.close();
            else if (e.key === 'Enter' && this.ready) this.confirm();
            else return;
            e.preventDefault();
        });
    },

    show({ title = '', subtitle = '', due, types, submit }) {
        Object.assign(this, { open: true, title, subtitle, due: round(Number(due) || 0), types, lines: [], entry: '', busy: false, error: '', submitFn: submit });
    },

    close() {
        if (!this.busy) this.open = false;
    },

    get paid() {
        return round(this.lines.reduce((s, l) => s + l.amount, 0));
    },

    get remaining() {
        return round(Math.max(0, this.due - this.paid));
    },

    get change() {
        return round(this.lines.reduce((s, l) => s + (l.tendered ? l.tendered - l.amount : 0), 0));
    },

    get ready() {
        return this.lines.length > 0 && this.remaining <= 0.004;
    },

    get entryValue() {
        return Number(this.entry) || 0;
    },

    press(key) {
        this.error = '';
        if (key === '.' && this.entry.includes('.')) return;
        if (/\.\d{2}$/.test(this.entry)) return; // qəpik 2 rəqəm
        if (this.entry.replace('.', '').length >= 7) return;
        this.entry = this.entry === '' && key === '.' ? '0.' : this.entry === '0' && key !== '.' ? key : this.entry + key;
    },

    back() {
        this.entry = this.entry.slice(0, -1);
    },

    /** Əskinas düymələri: məbləğ sahəsinə yazılır (sonra "Nağd" basılır). */
    note(value) {
        this.error = '';
        this.entry = String(round(this.entryValue + value));
    },

    isCash(type) {
        return type.icon === 'cash';
    },

    /** Ödəniş növünə toxunanda: yazılmış məbləğ (yoxdursa qalanın hamısı) həmin növə əlavə olunur. */
    add(type) {
        if (this.remaining <= 0.004) return (this.error = 'Məbləğ artıq tam qarşılanıb.');
        const amount = this.entry === '' ? this.remaining : this.entryValue;
        if (amount <= 0) return (this.error = 'Məbləği daxil edin.');

        let line = { payment_type_id: type.id, title: type.title, icon: type.icon, amount: round(Math.min(amount, this.remaining)), tendered: null };
        if (amount > this.remaining + 0.004) {
            if (!this.isCash(type)) return (this.error = `${type.title}: məbləğ qalandan (${money(this.remaining)}) çox ola bilməz.`);
            line.tendered = round(amount); // nağd: artıq verilən pul qalıq kimi qaytarılır
        }
        const same = this.lines.find((l) => l.payment_type_id === type.id && !l.tendered && !line.tendered);
        if (same) same.amount = round(same.amount + line.amount);
        else this.lines.push(line);
        this.entry = '';
        this.error = '';
    },

    remove(index) {
        this.lines.splice(index, 1);
        this.error = '';
    },

    async confirm() {
        if (!this.ready || this.busy) return;
        this.busy = true;
        this.error = '';
        try {
            const ok = await this.submitFn(this.lines.map(({ payment_type_id, amount, tendered }) => ({ payment_type_id, amount, tendered })));
            if (ok !== false) this.open = false;
        } catch (e) {
            this.error = e.message;
        } finally {
            this.busy = false;
        }
    },
});
