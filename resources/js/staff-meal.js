/**
 * Personal yeməyi formu: yemək seçimi və səbət. Hazır stokla izlənən yeməklər (stock !== null)
 * qalan porsiyadan çox seçilə bilməz.
 */
export default (menu = [], userId = '') => ({
    menu,
    userId,
    cart: [],
    search: '',
    category: null,

    get filtered() {
        const q = this.search.trim().toLowerCase();
        return this.menu.filter((m) => (!this.category || m.category_id === this.category) && (!q || m.title.toLowerCase().includes(q)));
    },
    get total() {
        return this.cart.reduce((s, l) => s + l.item.price * l.quantity, 0);
    },
    left(m) {
        return m.stock - (this.cart.find((l) => l.id === m.id)?.quantity ?? 0);
    },
    add(m) {
        if (m.stock !== null && this.left(m) <= 0) return toast(`${m.title}: hazır stokda qalmayıb`, 'error');
        const line = this.cart.find((l) => l.id === m.id);
        line ? line.quantity++ : this.cart.push({ id: m.id, title: m.title, item: m, quantity: 1 });
    },
    dec(line) {
        line.quantity > 1 ? line.quantity-- : this.cart.splice(this.cart.indexOf(line), 1);
    },
});
