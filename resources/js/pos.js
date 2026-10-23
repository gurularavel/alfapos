import { chairs, floorOf, withPositions } from './floorplan';

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
    noServiceCharge: false,
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

    // masa planı
    view: null,
    board: {},
    mapFloor: null,
    tableSheet: null, // { table, group, paying }
    transferFrom: null, // köçürülən masa (zal planında hədəf seçilir)

    // müştəri ekranı (bu cihazda seçilmiş kassa ekranı)
    displayId: null,
    displayTimer: null,
    displayHoldUntil: 0,

    init() {
        try {
            const saved = Number(localStorage.getItem('pos-display'));
            if (this.displays.some((d) => d.id === saved)) this.displayId = saved;
        } catch {}
        this.$watch('displayPayload', () => this.scheduleDisplay());
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
        // PIN-dən sonra "POS / Masalar" seçimi; masalar açıqdırsa siqnalla yenilənir
        window.addEventListener('terminal-unlocked', () => (this.view = 'home'));
        window.addEventListener('rt:order', () => this.loadBoard());
        this.$watch('tableId', () => this.loadBoard());
        this.loadBoard();
        const terminal = Alpine.store('terminal');
        if (terminal?.active && terminal.locked) this.resetForLock();
        this.refreshQrCount();
        // Canlı bağlantı varsa QR sifarişlər siqnalla gəlir; yoxdursa 20 saniyədə bir yoxlanılır
        setInterval(() => !Alpine.store('rt').connected && this.refreshQrCount(), 20000);
        window.addEventListener('rt:order', ({ detail }) => {
            if (detail.source === 'qr' && detail.type === 'created') {
                this.refreshQrCount();
                if (this.sounds?.newOrder) playSound('alert');
                toast(`Yeni QR sifariş #${detail.token_no}`);
            }
        });
    },

    // ---- masa planı ----
    // view: null (POS) | 'home' (PIN-dən sonra POS/Masalar seçimi) | 'tables' (zal planı)
    chairs,

    openTables() {
        this.view = 'tables';
        // Default: "Bütün masalar" (mapFloor = null); seçilmiş mərtəbə silinibsə ona qayıdırıq
        if (this.mapFloor && !this.floors.includes(this.mapFloor)) this.mapFloor = null;
        this.loadBoard();
    },

    async loadBoard() {
        try {
            const rows = await api(this.routes.board);
            this.board = Object.fromEntries(rows.filter((g) => g.is_table).map((g) => [g.key, g]));
            // Açıq olan masa paneli varsa onu da yeniləyirik
            if (this.tableSheet) this.tableSheet.group = this.board[`t${this.tableSheet.table.id}`] ?? null;
        } catch {}
    },

    /** Masadakı sifarişin məhsulunu ləğv et (ödənilibsə — qaytar). Menecer təsdiqi pəncərədə istənir. */
    returnItem(item, order) {
        Alpine.store('returns').show({
            item: { id: item.id, title: item.title, qty: item.qty, price: item.price, status: item.status },
            order: { id: order.id, token_no: order.token_no, paid: order.payment_status === 'paid' },
            onDone: () => this.loadBoard(),
        });
    },

    get floors() {
        return [...new Set(this.tables.map(floorOf))];
    },

    /** "Bütün masalar" görünüşü: mərtəbələr üzrə qruplar (zal planı mövqeləri olmadan). */
    get tablesByFloor() {
        return this.floors.map((floor) => ({ floor, tables: this.tables.filter((t) => floorOf(t) === floor) }));
    },

    /** Seçilmiş mərtəbənin masaları — Ayarlar → Zal planındakı mövqelərlə (yoxdursa avtomatik). */
    get mapTables() {
        return withPositions(this.tables.filter((t) => floorOf(t) === this.mapFloor));
    },

    /** Masanın canlı vəziyyəti (Sifarişlər lövhəsindən): dolu, məbləğ, vaxt, ofisiant. */
    tableState(t) {
        const g = this.board[`t${t.id}`];
        if (!g || !g.unpaid_ids.length) return null;
        const first = g.orders[0];
        return {
            orders: g.orders.length,
            due: g.due,
            since: first.time,
            waiter: first.waiter,
            ready: g.orders.some((o) => o.status === 'ready'),
        };
    },

    /** Boş masa → POS; dolu masa → əməliyyat paneli (əlavə sifariş / hesab çeki / hesabı bağla). */
    tapTable(t) {
        if (this.transferFrom) return this.transferTo(t);
        const group = this.board[`t${t.id}`];
        if (group?.unpaid_ids.length) {
            this.tableSheet = { table: t, group, paying: false };
            return;
        }
        this.pickTable(t);
    },

    pickTable(t) {
        this.deliveryType = 'dinein';
        this.tableId = String(t.id);
        this.view = null;
        this.tableSheet = null;
        toast(`${t.label} seçildi`);
    },

    /** Masanı köçür: panel bağlanır, zal planında hədəf masa seçilir (dolu masa → hesablar birləşir). */
    startTransfer() {
        this.transferFrom = this.tableSheet.table;
        this.tableSheet = null;
    },

    async transferTo(t) {
        const from = this.transferFrom;
        if (t.id === from.id) return;
        if (this.tableState(t) && !confirm(`${t.title} masasında açıq hesab var. Hesablar birləşdirilsin?`)) return;
        try {
            const r = await api(this.routes.transfer, { method: 'POST', body: { from_table_id: from.id, to_table_id: t.id } });
            toast(r.message);
            // Mətbəxə "MASA KÖÇÜRÜLDÜ" çeki (Çap ayarları → mətbəx çekini avtomatik çap et)
            if (this.autoPrintKot) printUrl(r.slip_url);
            this.transferFrom = null;
            if (String(this.tableId) === String(from.id)) this.tableId = String(t.id);
            await this.loadBoard();
        } catch (e) {
            toast(e.message, 'error');
        }
    },

    printBill() {
        printUrl(`${this.routes.bill}?ids=${this.tableSheet.group.unpaid_ids.join(',')}`);
    },

    async closeBill(paymentTypeId) {
        const sheet = this.tableSheet;
        try {
            const r = await api(this.routes.pay, { method: 'POST', body: { order_ids: sheet.group.unpaid_ids, payment_type_id: paymentTypeId } });
            toast(`${sheet.table.title}: hesab bağlandı`);
            this.tableSheet = null;
            if (this.autoPrintReceipt) printUrl(this.routes.receipt.replace('__ID__', r.invoice_id));
            const table = this.tables.find((t) => t.id === sheet.table.id);
            if (table) table.busy = false;
            await this.loadBoard();
            Alpine.store('terminal')?.afterOrder();
        } catch (e) {
            toast(e.message, 'error');
        }
    },

    /** Əvvəlki sifarişlər + yeni səbət; depozit (varsa) ümumi məbləğə tətbiq olunur — server ilə eyni qayda. */
    get tableTotalWithNew() {
        const g = this.tableOpenOrders;
        if (!g) return this.totals.total;
        const sum = g.orders_total + this.totals.total;
        return Math.max(sum, g.deposit || 0);
    },

    /** Seçilmiş masada artıq ödənilməmiş sifariş varsa — yeni sifariş "əlavə" kimi gedir. */
    get tableOpenOrders() {
        if (this.deliveryType !== 'dinein' || !this.tableId) return null;
        const g = this.board[`t${this.tableId}`];
        return g?.unpaid_ids.length ? g : null;
    },

    // ---- müştəri ekranı ----
    get display() {
        return this.displays.find((d) => d.id === this.displayId) ?? null;
    },

    /** Müştəriyə göstərilən hissə: yalnız məhsul adları, miqdar və cəmlər (qeydlər daxili məlumatdır). */
    get displayPayload() {
        const t = this.totals;
        return {
            mode: this.cart.length ? 'cart' : 'idle',
            items: this.cart.map((l) => ({ title: l.title, subtitle: l.subtitle || null, qty: l.quantity, total: Math.round(l.price * l.quantity * 100) / 100 })),
            totals: { sub: t.sub, discount: t.discount, tax: t.tax, service: t.service, deposit: t.deposit, topup: t.topup, total: t.total },
            customer: this.customer?.name ?? null,
        };
    },

    selectDisplay(id) {
        this.displayId = id;
        try {
            localStorage.setItem('pos-display', id ?? '');
        } catch {}
        if (id) this.pushDisplay(this.displayPayload);
    },

    openDisplayWindow() {
        if (this.display) window.open(this.display.url, 'customer-display', 'width=1280,height=800');
    },

    scheduleDisplay() {
        if (!this.display) return;
        // Ödənişdən sonra "Təşəkkür" ekranı bir müddət qalsın — boş səbət onu dərhal əvəz etməsin
        if (!this.cart.length && Date.now() < this.displayHoldUntil) return;
        clearTimeout(this.displayTimer);
        this.displayTimer = setTimeout(() => this.pushDisplay(this.displayPayload), 250);
    },

    pushDisplay(payload) {
        if (!this.display) return;
        api(this.display.push, { method: 'POST', body: payload }).catch(() => {});
    },

    /** Sifariş göndəriləndə / ödəniləndə müştəri ekranında təşəkkür + token. */
    finishDisplay(action, tokenNo, totals) {
        if (!this.display) return;
        clearTimeout(this.displayTimer);
        this.displayHoldUntil = Date.now() + 10000;
        this.pushDisplay({ mode: action === 'pay' ? 'paid' : 'sent', items: [], totals, token_no: tokenNo, customer: this.customer?.name ?? null });
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
    /** Hazır stokla izlənən yeməyin səbətdən sonra qalan porsiyası (izlənmirsə Infinity). */
    stockLeft(item) {
        if (item.stock === null || item.stock === undefined) return Infinity;
        return item.stock - this.cart.filter((l) => l.menu_item_id === item.id).reduce((s, l) => s + l.quantity, 0);
    },

    hasStock(item, quantity = 1) {
        if (this.stockLeft(item) >= quantity) return true;
        const left = Math.max(0, this.stockLeft(item));
        // Qalıq sayı yalnız admin / kassirə göstərilir (ofisiant yalnız "bitib" xəbərini görür)
        const showCount = left && Alpine.store('terminal')?.canSeeStock;
        toast(showCount ? `${item.title}: hazır stokda cəmi ${left} porsiya qalıb` : `${item.title}: hazır stokda ${left ? 'kifayət qədər yoxdur' : 'bitib'}`, 'error');
        return false;
    },

    open(item) {
        if (!this.hasStock(item)) return;
        if (!item.variants.length && !item.addons.length) {
            this.addSound();
            return this.add(item, null, [], 1, '');
        }
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
        if (!this.hasStock(p.item, p.quantity)) return;
        this.addSound();
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

    addSound() {
        if (this.sounds?.addItem) playSound('add');
    },

    inc(line) {
        const item = this.items.find((i) => i.id === line.menu_item_id);
        if (item && !this.hasStock(item)) return;
        this.addSound();
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
        Object.assign(this, { cart: [], customer: null, tableId: '', discountValue: 0, notes: '', draftId: null, deliveryType: 'dinein', noServiceCharge: false });
        this.persist();
    },

    resetForLock() {
        this.clear();
        Object.assign(this, { picker: null, modal: null, search: '', category: null, customerQuery: '', customerResults: [], draftReference: '', view: null, tableSheet: null, transferFrom: null });
    },

    /** Servis haqqı bu sifarişə aiddirmi (ayara və xidmət növünə görə) — kassir onu ləğv edə bilər. */
    get serviceApplies() {
        return this.service.enabled && (!this.service.dineinOnly || this.deliveryType === 'dinein');
    },

    get serviceRate() {
        return this.serviceApplies && !this.noServiceCharge ? this.service.rate : 0;
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
        const service = ((sub - discount) * this.serviceRate) / 100;
        // Kabinet/VIP depoziti: hesab minimum məbləğdən azdırsa fərq əlavə olunur (server ilə eyni qayda)
        const beforeDeposit = sub - discount + exclusive + service;
        // Açıq hesabı olan masaya əlavə sifarişdə depozit ayrıca yox, masanın ümumi hesabına tətbiq olunur
        const deposit = this.deliveryType === 'dinein' && !this.tableOpenOrders ? this.selectedTable?.deposit || 0 : 0;
        const topup = deposit > 0 ? Math.max(0, Math.round((deposit - beforeDeposit) * 100) / 100) : 0;
        return { sub, discount, tax, service, deposit, topup, total: beforeDeposit + topup };
    },

    get selectedTable() {
        return this.tables.find((t) => String(t.id) === String(this.tableId)) ?? null;
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
            no_service_charge: this.serviceApplies && this.noServiceCharge,
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
            const totalsBefore = this.displayPayload.totals;
            // Endirim / servis haqqından imtina menecer təsdiqi istəyə bilər (server approver_pin xətası qaytarır)
            const r = await withApproval('Endirimi təsdiqləyin', (pin) => api(this.routes.store, { method: 'POST', body: { ...this.payload(action), approver_pin: pin } }));
            if (action !== 'draft') this.finishDisplay(action, r.order.token_no, totalsBefore);
            // Mətbəxə ayrıca çek: hər göndərilən sifariş (əlavə sifariş də) yalnız öz məhsulları ilə çap olunur
            for (const [id, stock] of Object.entries(r.stock ?? {})) {
                const item = this.items.find((i) => i.id === Number(id));
                if (item) item.stock = stock;
            }
            if (action !== 'draft' && this.autoPrintKot) printUrl(this.routes.token.replace('__ID__', r.order.id));
            // Ödənişdən sonra qəbz dərhal çap olunur — əlavə "Qəbz" düyməsi gözlənilmir
            if (action === 'pay' && r.invoice_id && this.autoPrintReceipt) printUrl(this.routes.receipt.replace('__ID__', r.invoice_id));
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
