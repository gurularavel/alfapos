/**
 * Zal planı köməkçiləri (POS masa planı və Ayarlardakı redaktor üçün ortaq).
 * Mövqelər mərtəbə kətanının faizi ilə saxlanılır (0–100), masa mərkəzi həmin nöqtədədir.
 */

/** Yuxarıdan görünüş: oturacaqların yarısı yuxarıda, yarısı aşağıda (max 12 göstərilir). */
export function chairs(t) {
    const n = Math.min(Math.max(t.seats, 1), 12);
    return { top: Math.ceil(n / 2), bottom: Math.floor(n / 2) };
}

/** Yeri təyin olunmamış masalar üçün avtomatik şəbəkə mövqeyi (mərtəbə daxilində). */
export function withPositions(tables) {
    const placed = tables.filter((t) => t.x != null && t.y != null);
    const free = tables.filter((t) => t.x == null || t.y == null);
    const cols = Math.max(1, Math.ceil(Math.sqrt(free.length * 1.6)));
    const rows = Math.max(1, Math.ceil(free.length / cols));
    return [
        ...placed.map((t) => ({ ...t, auto: false })),
        ...free.map((t, i) => ({
            ...t,
            x: Math.round(((i % cols) + 0.5) * (100 / cols) * 100) / 100,
            y: Math.round((Math.floor(i / cols) + 0.5) * (100 / rows) * 100) / 100,
            auto: true,
        })),
    ];
}

export const floorOf = (t) => t.floor || 'Ümumi';

/**
 * Ayarlar → Zal planı redaktoru: masaları barmaqla/siçanla sürüşdürmək (pointer events),
 * mövqeləri bir dəfə "Yadda saxla" ilə göndərmək.
 */
export function planEditor({ tables, saveUrl }) {
    const floors = [...new Set(tables.map(floorOf))];
    return {
        floors,
        floor: floors[0] ?? null,
        tables: withPositions(tables),
        dragging: null,
        dirty: false,
        saving: false,
        chairs,

        get floorTables() {
            return this.tables.filter((t) => floorOf(t) === this.floor);
        },

        start(e, t) {
            this.dragging = t;
            e.target.setPointerCapture?.(e.pointerId);
        },

        move(e) {
            if (!this.dragging) return;
            const rect = this.$refs.canvas.getBoundingClientRect();
            const clamp = (v) => Math.min(96, Math.max(4, v));
            this.dragging.x = Math.round(clamp(((e.clientX - rect.left) / rect.width) * 100) * 100) / 100;
            this.dragging.y = Math.round(clamp(((e.clientY - rect.top) / rect.height) * 100) * 100) / 100;
            this.dragging.auto = false;
            this.dirty = true;
        },

        end() {
            this.dragging = null;
        },

        resetFloor() {
            const others = this.tables.filter((t) => floorOf(t) !== this.floor);
            const reset = withPositions(this.floorTables.map((t) => ({ ...t, x: null, y: null })));
            this.tables = [...others, ...reset];
            this.dirty = true;
        },

        async save() {
            this.saving = true;
            try {
                const r = await api(saveUrl, {
                    method: 'PUT',
                    body: { positions: this.tables.map((t) => ({ id: t.id, x: t.auto ? null : t.x, y: t.auto ? null : t.y })) },
                });
                toast(r.message);
                this.dirty = false;
            } catch (e) {
                toast(e.message, 'error');
            } finally {
                this.saving = false;
            }
        },
    };
}
