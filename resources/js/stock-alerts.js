/**
 * Paneldəki stok bildirişləri (Alpine store): zəng, açılan siyahı və header altındakı yanıb-sönən zolaq eyni məlumatı oxuyur.
 * Hər dəqiqə yoxlanılır; Reverb bağlıdırsa da eyni tezlik (bildiriş qalıqdan hesablanır, ucuz sorğudur).
 */
export default (urls) => ({
    open: false,
    items: [],
    unread: 0,
    out: 0,

    init() {
        this.load();
        setInterval(() => document.hidden || this.load(), 60000);
        document.addEventListener('visibilitychange', () => document.hidden || this.load());
    },

    async load() {
        try {
            const r = await api(urls.index);
            Object.assign(this, { items: r.items, unread: r.unread, out: r.out });
        } catch {}
    },

    get banner() {
        const unread = this.items.filter((i) => i.unread);
        if (!unread.length) return null;
        const names = unread.slice(0, 3).map((i) => i.title).join(', ') + (unread.length > 3 ? ` və daha ${unread.length - 3}` : '');
        const outCount = unread.filter((i) => i.level === 'out').length;
        return outCount ? `Stokda bitdi: ${names}` : `Stok azalır: ${names}`;
    },

    async markRead() {
        this.items.forEach((i) => (i.unread = false));
        this.unread = 0;
        await api(urls.read, { method: 'POST' }).catch(() => {});
    },
});
