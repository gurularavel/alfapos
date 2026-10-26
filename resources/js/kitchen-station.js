/**
 * Mətbəx ekranının şöbə rejimi: cihaz öz şöbəsini seçir (bar, mətbəx) — yalnız həmin şöbənin məhsulları görünür.
 * "Çap stansiyası" açıqdırsa, şöbəyə yeni sifariş gələndə çeki bu cihaz avtomatik çap edir (bir dəfə).
 * x-data-da poller ilə birləşdirilir (...spread), ona görə getter yox, metodlardır.
 */
const KEY = { department: 'kitchen-department', station: 'kitchen-station', printed: 'kitchen-printed' };
const MAX_AGE_MINUTES = 15; // cihaz uzun müddət söndürülübsə köhnə sifarişlər çap olunmasın

const load = (key, fallback) => {
    try {
        const v = localStorage.getItem(key);
        return v === null ? fallback : JSON.parse(v);
    } catch {
        return fallback;
    }
};
const save = (key, value) => {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {}
};

export default (departments, tokenUrl) => ({
    departments,
    department: departments.some((d) => d.id === load(KEY.department, null)) ? load(KEY.department, null) : null,
    station: load(KEY.station, false),
    printed: new Set(load(KEY.printed, [])),

    currentDepartment() {
        return this.departments.find((d) => d.id === this.department) ?? null;
    },

    /** Seçilmiş şöbənin sifarişləri (yalnız onun məhsulları ilə). */
    visible() {
        if (!this.department) return this.rows;
        return this.rows
            .map((o) => ({ ...o, items: o.items.filter((i) => i.department_id === this.department) }))
            .filter((o) => o.items.length);
    },

    setDepartment(id) {
        this.department = id;
        save(KEY.department, id);
        if (!id) this.setStation(false);
        else if (this.station) this.markSeen();
    },

    setStation(on) {
        this.station = on;
        save(KEY.station, on);
        if (on) this.markSeen(); // açılan anda növbədəki sifarişlər çap olunmur — yalnız yenilər
    },

    key(order) {
        return `${order.id}:${this.department}`;
    },

    markSeen() {
        this.visible().forEach((o) => this.printed.add(this.key(o)));
        this.persistPrinted();
    },

    persistPrinted() {
        save(KEY.printed, [...this.printed].slice(-300));
    },

    /** x-effect: sifariş siyahısı yenilənəndə şöbənin yeni sifarişlərini çap edir. */
    autoPrint(rows) {
        if (!this.station || !this.department || !rows) return;
        for (const o of this.visible()) {
            if (this.printed.has(this.key(o)) || o.minutes > MAX_AGE_MINUTES) continue;
            if (!o.items.some((i) => i.status === 'created')) continue;
            this.printed.add(this.key(o));
            printUrl(`${tokenUrl.replace('__ID__', o.id)}?department=${this.department}`);
        }
        this.persistPrinted();
    },
});
