/**
 * POS ekranı. Məlumat serverdən bir dəfə (window.POS_DATA) gəlir; qiymətlər burada yalnız göstərmək üçün
 * hesablanır — server sifarişi bazadakı qiymətlərlə yenidən hesablayır.
 */
export default (data) => ({
    ...data,
    category: null,
    search: '',
    cart: [],
    customer: null,
    customerQuery: '',
    customerResults: [],
    deliveryType: 'dinein',
    tableId: '',
    discountType: 'percentage',
    discountValue: 0,
    notes: '',
    draftId: null,

    // modallar
    picker: null, // { item, variantId, addonIds, quantity, notes }
    modal: null, // 'pay' | 'discount' | 'drafts' | 'qr' | 'customer' | 'done' | 'draft'
    paymentTypeId: null,
    drafts: [],
    qrOrders: [],
    newCustomer: { name: '', phone: '', email: '', birth_date: '', gender: '' },
    draftReference: '',
    result: null,
    busy: false,
    full: false,

    init() {
        window.APP_CURRENCY = this.currency;
        // Esc ilə brauzer tam ekranından çıxanda rejimi də söndürürük
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && this.full) this.setFull(false);
        });
        try {
            if (localStorage.getItem('pos-full') === '1') this.setFull(true);
        } catch {}
        this.paymentTypeId = this.paymentTypes[0]?.id ?? null;
        this.$watch('customerQuery', () => this.findCustomers());
        try {
            const saved = JSON.parse(localStorage.getItem('pos-cart') || 'null');
            if (saved?.cart?.length) Object.assign(this, saved);
        } catch {}
        this.$watch('cart', () => this.persist());
        // Terminal kilidlənəndə səbət və açıq pəncərələr təmizlənir
        window.addEventListener('terminal-lock', () => this.resetForLock());
        const terminal = Alpine.store('terminal');
        if (terminal?.active && terminal.locked) this.resetForLock();
        this.refreshQrCount();
        // Canlı bağlantı varsa QR sifarişlər siqnalla gəlir; yoxdursa 20 saniyədə bir yoxlanılır
        setInterval(() => !Alpine.store('rt').connected && this.refreshQrCount(), 20000);
        window.addEventListener('rt:order', ({ detail }) => {
            if (detail.source === 'qr' && detail.type === 'created') {
                this.refreshQrCount();
                beep();
                toast(`Yeni QR sifariş #${detail.token_no}`);
            }
        });
    },

    // ---- tam ekran ----
    // Sidebar və topbar gizlənir; brauzer icazə verirsə ekran da tam ekrana keçir.
    // Brauzer tam ekranı yalnız istifadəçi kliki ilə açılır, ona görə səhifə yenilənəndə yalnız fokus rejimi bərpa olunur.
    toggleFull() {
        if (this.full) {
            if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
            this.setFull(false);
        } else {
            this.setFull(true);
            document.documentElement.requestFullscreen?.().catch(() => {});
        }
    },

    setFull(value) {
        this.full = value;
        // Terminal rejimində menyu həmişə gizli qalır
        document.body.classList.toggle('pos-full', value || document.body.classList.contains('terminal-mode'));
        try {
            localStorage.setItem('pos-full', value ? '1' : '0');
        } catch {}
    },

    persist() {
        try {
            localStorage.setItem('pos-cart', JSON.stringify({ cart: this.cart, deliveryType: this.deliveryType, tableId: this.tableId, customer: this.customer }));
        } catch {}
    },

    get filtered() {
        const q = this.search.trim().toLowerCase();
        return this.items.filter((i) => (!this.category || i.category_id === this.category) && (!q || i.title.toLowerCase().includes(q)));
    },

    // ---- səbət ----
    open(item) {
        if (!item.variants.length && !item.addons.length) return this.add(item, null, [], 1, '');
        this.picker = { item, variantId: item.variants[0]?.id ?? null, addonIds: [], quantity: 1, notes: '' };
    },

    get pickerPrice() {
        if (!this.picker) return 0;
        const { item, variantId, addonIds } = this.picker;
        const base = variantId ? item.variants.find((v) => v.id === variantId).price : item.price;
        return base + item.addons.filter((a) => addonIds.includes(a.id)).reduce((s, a) => s + a.price, 0);
    },

    confirmPicker() {
        const p = this.picker;
        this.add(p.item, p.variantId, p.addonIds, p.quantity, p.notes);
        this.picker = null;
    },

    add(item, variantId, addonIds, quantity, notes) {
        const key = `${item.id}|${variantId ?? ''}|${[...addonIds].sort().join(',')}|${notes}`;
        const existing = this.cart.find((l) => l.key === key);
        if (existing) {
            existing.quantity += quantity;
            return;
        }
        const variant = item.variants.find((v) => v.id === variantId);
        const addons = item.addons.filter((a) => addonIds.includes(a.id));
        this.cart.push({
            key,
            menu_item_id: item.id,
            variant_id: variantId,
            addon_ids: addonIds,
            title: item.title,
            subtitle: [variant?.title, ...addons.map((a) => '+ ' + a.title)].filter(Boolean).join(', '),
            price: (variant ? variant.price : item.price) + addons.reduce((s, a) => s + a.price, 0),
            tax_rate: item.tax_rate,
            tax_type: item.tax_type,
            quantity,
            notes,
        });
    },

    inc(line) {
        line.quantity++;
    },
    dec(line) {
        line.quantity > 1 ? line.quantity-- : this.cart.splice(this.cart.indexOf(line), 1);
    },
    editNote(line) {
        const v = prompt('Qeyd (maks. 100 simvol)', line.notes || '');
        if (v !== null) line.notes = v.slice(0, 100);
    },

    clear() {
        Object.assign(this, { cart: [], customer: null, tableId: '', discountValue: 0, notes: '', draftId: null, deliveryType: 'dinein' });
        this.persist();
    },

    resetForLock() {
        this.clear();
        Object.assign(this, { picker: null, modal: null, search: '', category: null, customerQuery: '', customerResults: [], draftReference: '' });
    },

    // ---- cəmlər (server ilə eyni formul) ----
    get totals() {
        let sub = 0, tax = 0, exclusive = 0;
        for (const l of this.cart) {
            const amount = l.price * l.quantity;
            sub += amount;
            if (l.tax_type === 'inclusive') tax += amount - amount / (1 + l.tax_rate / 100);
            else exclusive += (amount * l.tax_rate) / 100;
        }
        tax += exclusive;
        const dv = Number(this.discountValue) || 0;
        const discount = this.discountType === 'percentage' ? (sub * Math.min(100, dv)) / 100 : Math.min(sub, dv);
        const service = ((sub - discount) * this.serviceCharge) / 100;
        return { sub, discount, tax, service, total: sub - discount + exclusive + service };
    },

    get count() {
        return this.cart.reduce((s, l) => s + l.quantity, 0);
    },

    // ---- müştəri ----
    async findCustomers() {
        if (this.customerQuery.length < 2) return (this.customerResults = []);
        this.customerResults = await api(`${this.routes.customers}?q=${encodeURIComponent(this.customerQuery)}`).catch(() => []);
    },
    pickCustomer(c) {
        this.customer = c;
        this.customerQuery = '';
        this.customerResults = [];
        this.persist();
    },
    async saveCustomer() {
        try {
            this.pickCustomer(await api(this.routes.customerStore, { method: 'POST', body: this.newCustomer }));
            this.newCustomer = { name: '', phone: '', email: '', birth_date: '', gender: '' };
            this.modal = null;
            toast('Müştəri əlavə edildi');
        } catch (e) {
            toast(e.message, 'error');
        }
    },

    // ---- göndərmə ----
    payload(action) {
        return {
            action,
            cart: this.cart.map(({ menu_item_id, variant_id, addon_ids, quantity, notes }) => ({ menu_item_id, variant_id, addon_ids, quantity, notes })),
            delivery_type: this.deliveryType,
            customer_id: this.customer?.id ?? null,
            store_table_id: this.deliveryType === 'dinein' && this.tableId ? Number(this.tableId) : null,
            discount_type: Number(this.discountValue) > 0 ? this.discountType : null,
            discount_value: Number(this.discountValue) || 0,
            notes: this.notes || null,
            draft_id: this.draftId,
            draft_reference: action === 'draft' ? this.draftReference : null,
            payment_type_id: action === 'pay' ? this.paymentTypeId : null,
        };
    },

    async submit(action) {
        if (!this.cart.length) return toast('Səbət boşdur', 'error');
        this.busy = true;
        try {
            const r = await api(this.routes.store, { method: 'POST', body: this.payload(action) });
            if (action === 'draft') {
                toast(r.message);
                this.modal = null;
                this.draftReference = '';
            } else {
                this.result = r;
                this.modal = 'done';
            }
            const table = this.tables.find((t) => t.id === Number(this.tableId));
            if (table && action !== 'draft') table.busy = true;
            this.clear();
            if (action !== 'draft') {
                Alpine.store('terminal')?.afterOrder();
            }
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            this.busy = false;
        }
    },

    printToken() {
        printUrl(this.routes.token.replace('__ID__', this.result.order.id));
    },
    printReceipt() {
        printUrl(this.routes.receipt.replace('__ID__', this.result.invoice_id));
    },

    // ---- qaralamalar / QR ----
    async openDrafts() {
        this.modal = 'drafts';
        this.drafts = await api(this.routes.drafts).catch(() => []);
    },
    loadDraft(d) {
        this.clear();
        for (const row of d.items) {
            const item = this.items.find((i) => i.id === row.menu_item_id);
            if (item) this.add(item, row.variant_id, row.addon_ids.map(Number), row.quantity, row.notes || '');
        }
        Object.assign(this, {
            draftId: d.id,
            deliveryType: d.delivery_type,
            tableId: d.store_table_id ?? '',
            customer: d.customer,
            discountType: d.discount_type || 'percentage',
            discountValue: d.discount_value,
            notes: d.notes || '',
            modal: null,
        });
    },
    async deleteDraft(d) {
        await api(`${this.routes.drafts}/${d.id}`, { method: 'DELETE' }).catch((e) => toast(e.message, 'error'));
        this.drafts = this.drafts.filter((x) => x.id !== d.id);
    },
    async refreshQrCount() {
        this.qrOrders = await api(this.routes.qr).catch(() => this.qrOrders);
    },
});
