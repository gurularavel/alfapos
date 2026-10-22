/**
 * Menecer təsdiqi və ləğv / qaytarma pəncərələri (bütün səhifələrdə ortaq Alpine store-lar).
 *
 * withApproval(title, run): run(pin) əvvəlcə PIN-siz çağırılır; server "approver_pin" xətası qaytarsa
 * (işçinin təsdiq icazəsi yoxdur və ya PIN səhvdir), menecer PIN pəncərəsi açılır və sorğu PIN ilə təkrarlanır.
 */
export const APPROVAL_CANCELLED = 'Təsdiq edilmədi';

export function approvalStore() {
    return {
        open: false,
        title: '',
        message: null,
        pin: '',
        resolve: null,

        ask(title, message = null) {
            Object.assign(this, { title, message, pin: '', open: true });
            return new Promise((resolve) => (this.resolve = resolve));
        },
        press(digit) {
            if (this.pin.length < 4) this.pin += digit;
            if (this.pin.length === 4) this.finish(this.pin);
        },
        back() {
            this.pin = this.pin.slice(0, -1);
        },
        finish(value) {
            this.open = false;
            const resolve = this.resolve;
            this.resolve = null;
            resolve?.(value);
        },
    };
}

export async function withApproval(title, run) {
    let pin = null;
    for (;;) {
        try {
            return await run(pin);
        } catch (e) {
            const message = e.errors?.approver_pin?.[0];
            if (!message) throw e;
            pin = await window.Alpine.store('approval').ask(title, pin === null ? null : message);
            if (pin === null) throw new Error(APPROVAL_CANCELLED);
        }
    }
}

/** Ləğv / qaytarma pəncərəsi: sətir (mode 'item') və ya bütün sifariş (mode 'order'). */
export function returnsStore(routes) {
    return {
        routes,
        open: false,
        mode: 'item',
        item: null,
        order: null,
        quantity: 1,
        reason: 'not_liked',
        note: '',
        stockAction: 'waste',
        refundAmount: '',
        busy: false,
        onDone: null,

        /** item: { id, title, qty, price, status }, order: { id, token_no, paid } */
        show({ item, order, onDone = null }) {
            Object.assign(this, {
                mode: 'item', item, order, onDone, quantity: 1, reason: 'not_liked', note: '', refundAmount: '', busy: false, open: true,
                // Mətbəx hələ başlamayıbsa — stoka qaytarılır; hazırlanıbsa — israf
                stockAction: item.status === 'created' ? 'restore' : 'waste',
            });
        },
        showOrder({ order, onDone = null }) {
            Object.assign(this, { mode: 'order', item: null, order, onDone, reason: 'wrong', note: '', busy: false, open: true });
        },
        get amount() {
            return this.item ? this.item.price * this.quantity : 0;
        },
        async submit() {
            if (this.reason === 'other' && !this.note.trim()) return toast('Səbəbi qısa yazın', 'error');
            this.busy = true;
            try {
                const r = this.mode === 'order'
                    ? await withApproval(`Sifariş #${this.order.token_no} ləğvi`, (pin) => api(this.routes.cancel.replace('__ID__', this.order.id), {
                        method: 'POST', body: { reason: this.reason, note: this.note || null, approver_pin: pin },
                    }))
                    : await withApproval(this.order.paid ? 'Qaytarmanı təsdiqləyin' : 'Ləğvi təsdiqləyin', (pin) => api(this.routes.item.replace('__ID__', this.item.id), {
                        method: 'POST',
                        body: {
                            quantity: this.quantity, reason: this.reason, note: this.note || null, stock_action: this.stockAction,
                            refund_amount: this.order.paid && this.refundAmount ? Number(this.refundAmount) : null, approver_pin: pin,
                        },
                    }));
                toast(r.message);
                // Mətbəx hazırlamasın: ləğv qəbzi mətbəxə; pul qaytarılıbsa — müştəri üçün qaytarma qəbzi
                if (r.slip_url && r.kind !== 'refund') printUrl(r.slip_url);
                if (r.receipt_url) printUrl(r.receipt_url);
                this.open = false;
                this.onDone?.(r);
            } catch (e) {
                if (e.message !== APPROVAL_CANCELLED) toast(e.message, 'error');
            } finally {
                this.busy = false;
            }
        },
    };
}
