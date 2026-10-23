/**
 * Hesabı bağlama ekranı (Alpine store "checkout") — PIN pəncərəsi kimi tam ekran.
 * Bölünmüş ödəniş: məbləği rəqəm klaviaturasında yazıb ödəniş növünə toxunun (məs. 20 → Nağd, qalanı → Kart).
 * Məbləğ yazılmayıbsa qalan məbləğin hamısı götürülür. Nağdda artıq verilən pul qalıq kimi göstərilir.
 * Nağd qalıq: "Müştəri verir" düymələri (hesab 22 → 25, 30, 50, 100 ...) bir toxunuşla nağd ödənişi və qaytarılacaq
 * məbləği hesablayır; təsdiqdən sonra ekran qalığı iri göstərir ki, kassir pulu sayarkən unutmasın.
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
    // Təsdiqdən sonra qalıq ekranı: { change, tendered, due }
    done: null,

    init() {
        document.addEventListener('keydown', (e) => {
            if (!this.open || this.busy) return;
            if (this.done) {
                if (e.key === 'Enter' || e.key === 'Escape') this.finish();
                return e.preventDefault();
            }
            if (/^[0-9]$/.test(e.key) || e.key === '.' || e.key === ',') this.press(e.key === ',' ? '.' : e.key);
            else if (e.key === 'Backspace') this.back();
            else if (e.key === 'Escape') this.close();
            else if (e.key === 'Enter' && this.ready) this.confirm();
            else return;
            e.preventDefault();
        });
    },

    show({ title = '', subtitle = '', due, types, submit }) {
        Object.assign(this, { open: true, title, subtitle, due: round(Number(due) || 0), types, lines: [], entry: '', busy: false, error: '', submitFn: submit, done: null });
    },

    close() {
        if (!this.busy) this.open = false;
    },

    /** Qalıq ekranında "Hazır". */
    finish() {
        this.done = null;
        this.open = false;
    },

    get cashType() {
        return this.types.find((t) => t.icon === 'cash') ?? null;
    },

    /** Müştərinin verə biləcəyi məbləğlər: qalanı yuxarı yuvarlaqlaşdırıb (5, 10, 20, 50 ...) və iri əskinaslar. */
    get suggestions() {
        const r = this.remaining;
        if (r <= 0.004 || !this.cashType) return [];
        const values = new Set();
        for (const step of [1, 5, 10, 20, 50, 100]) {
            const v = Math.ceil(r / step) * step;
            if (v > r + 0.004) values.add(v);
        }
        for (const note of [50, 100, 200]) if (note > r + 0.004) values.add(note);
        return [...values].sort((a, b) => a - b).slice(0, 6);
    },

    /** Klaviaturada yazılan məbləğ qalandan çoxdursa — nağd veriləndə qaytarılacaq (önizləmə). */
    get preview() {
        return this.cashType && this.entry !== '' && this.entryValue > this.remaining + 0.004 && this.remaining > 0.004 ? round(this.entryValue - this.remaining) : 0;
    },

    /** "Müştəri verir" düyməsi: nağd ödəniş + qalıq bir toxunuşla. */
    cashGiven(value) {
        this.entry = String(value);
        this.add(this.cashType);
    },

    /** Nağd, dəqiq məbləğ (qalıqsız). */
    cashExact() {
        this.entry = '';
        this.add(this.cashType);
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
            if (ok === false) return;
            // Qalıq varsa ekran bağlanmır — kassir qaytarılacaq məbləği iri görür, "Hazır" basır
            if (this.change > 0) {
                const tendered = round(this.lines.reduce((s, l) => s + (l.tendered ?? 0), 0));
                this.done = { change: this.change, tendered, due: this.due };
            } else {
                this.open = false;
            }
        } catch (e) {
            this.error = e.message;
        } finally {
            this.busy = false;
        }
    },
});
