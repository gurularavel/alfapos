/**
 * Axtarış açarı: "Çay", "cay", "chay", "ÇAY" eyni nəticə versin (etiketsiz).
 * Kiçik hərf → diakritikalar (ç ş ğ ö ü) atılır → ı → i → latın klaviaturasında yazılan cütlər (ch sh gh kh zh).
 * "ə" həm "e", həm "a" kimi yazılır ("xemir" / "xamir"), ona görə mətnin hər iki variantı yoxlanılır.
 * Serverdə eyni qayda: app/helpers.php → search_key().
 */
export const searchKey = (value, schwa = 'e') =>
    String(value ?? '')
        .replace(/İ/g, 'i')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/ı/g, 'i')
        .replace(/ə/g, schwa)
        .replace(/ch/g, 'c')
        .replace(/sh/g, 's')
        .replace(/gh/g, 'g')
        .replace(/kh/g, 'x')
        .replace(/zh/g, 'j')
        .trim();

/** Mətn axtarış sorğusuna uyğundurmu (boş sorğu — hamısı). */
export const matchesSearch = (text, query) => {
    const q = searchKey(query);
    return !q || searchKey(text).includes(q) || searchKey(text, 'a').includes(q);
};
