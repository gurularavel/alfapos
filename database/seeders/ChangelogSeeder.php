<?php

namespace Database\Seeders;

use App\Models\ChangelogEntry;
use Illuminate\Database\Seeder;

/** AlfaPOS-un versiya tarixçəsi. Təkrar işə salına bilər: versiya üzrə yenilənir, superadmin dəyişiklikləri saxlanılır. */
class ChangelogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->releases() as [$version, $date, $title, $items]) {
            ChangelogEntry::firstOrCreate(['version' => $version], [
                'released_at' => $date,
                'title' => $title,
                'items' => collect($items)->flatMap(fn ($lines, $type) => collect($lines)->map(fn ($text) => ['type' => $type, 'text' => $text]))->values()->all(),
            ]);
        }
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: array<string, list<string>>}> */
    protected function releases(): array
    {
        return [
            ['1.0.0', '2026-10-22', 'İlk buraxılış', [
                'new' => [
                    'Bulud əsaslı restoran POS-u: hər restoran öz məlumatları ilə ayrıca işləyir',
                    'POS ekranı, sifarişlər, mətbəx ekranı, fakturalar və müştərilər',
                    'Menyu: kateqoriyalar, variantlar, əlavələr, vergilər və ödəniş növləri',
                    'Anbar: stok qalıqları, reseptlə avtomatik silinmə, stok hərəkətləri',
                    'QR menyu ilə masadan sifariş və rəylər, rezervlər',
                    '20-dən çox hesabat, dashboard, işçi icazələri və audit jurnalı',
                    'POS-u tam ekran rejimində işlətmək',
                ],
            ]],
            ['1.1.0', '2026-10-22', 'Terminal rejimi və canlı yenilənmə', [
                'new' => [
                    'Terminal rejimi: hər işçi öz PIN-i ilə daxil olur, ofisiant rolu (ödəniş qəbul etmir)',
                    'Ekran qoruyucu restoran şəkilləri ilə, fəaliyyətsizlikdə avtomatik kilid (vaxtı ayarlanır)',
                    'Realtime: sifarişlər bütün ekranlarda dərhal yenilənir',
                    'Məhsul əlavə edəndə və yeni sifarişdə səs siqnalı (söndürmək olar)',
                    'Müştəri ekranı (ikinci monitor): səbət və cəm müştəriyə görünür',
                    'Demo menyu üçün məhsul şəkilləri',
                ],
                'improved' => ['PIN klaviaturası sensor ekran üçün böyüdülüb və tam ekran edilib'],
                'fixed' => ['Terminal kilidlənəndə yarımçıq səbət təmizlənir'],
            ]],
            ['1.2.0', '2026-10-22', 'Masalar və zal planı', [
                'new' => [
                    'Kabinet / VIP masalar üçün minimum depozit, qəbzdə göstərilir',
                    'Yuxarıdan baxış masa xəritəsi: oturacaqlar, dolu masalar, toxunanda POS həmin masa ilə açılır',
                    'Zal planı redaktoru: masaları sürüşdürüb yerləşdirmək',
                    'Masanın hesab çeki və hesabın bağlanması, əlavə sifariş (mətbəxə ayrıca çek)',
                    'Masanı başqa masaya köçürmək və birləşdirmək, mətbəxə köçürmə çeki',
                    'Vergi və servis haqqını açıb-söndürmək',
                    'Çap ayarlarında qəbzin canlı önizləməsi və sınaq çapı',
                ],
                'improved' => ['Masa siyahısında "Bütün masalar" görünüşü; əlavə sifarişdə əvvəlki sifarişlər fərqli rənglə'],
            ]],
            ['1.3.0', '2026-10-22', 'Sayt və şirkət məlumatları', [
                'new' => [
                    'Haqqımızda, Əlaqə, Sual-cavab, Məxfilik siyasəti və İstifadə şərtləri səhifələri',
                    'Saytın məzmunu və rekvizitləri superadmin panelindən idarə olunur',
                    'Ekran qoruyucuda məhsulun versiyası',
                ],
            ]],
            ['1.4.0', '2026-10-22', 'Filiallar, terminallar və yeni dashboard', [
                'new' => [
                    'Filiallar və terminallar: hər satış hansı kassadan vurulduğu ilə yazılır',
                    'Ofisiantı terminala bağlamaq: PIN yalnız öz terminalında işləyir',
                    'Hesabatlarda filial və terminal filtrləri, terminallar və filiallar üzrə satış',
                    'Dashboard: terminallar, filiallar, işçilər, saatlar, kateqoriyalar, sifariş və ödəniş növləri, masalar üzrə satış',
                ],
                'fixed' => ['"Sifariş tipi üzrə" və "Masalar üzrə" hesabatlarında əlavə sifarişli masanın çeki iki-üç dəfə sayılırdı'],
            ]],
            ['1.5.0', '2026-10-23', 'Hazır yemək stoku və personal yeməyi', [
                'new' => [
                    'Hazır yemək stoku: porsiya ilə hazırlama, xammal reseptlə anbardan silinir, stok bitəndə satış bağlanır',
                    'İsraf və sayım, hazır stok hərəkətləri jurnalı',
                    'Personal yeməyi: işçilərə verilən yemək stokdan silinir və uçota düşür',
                ],
                'improved' => ['POS kartında qalan porsiya sayı, QR menyuda bitmiş yemək gizlənir'],
            ]],
            ['1.6.0', '2026-10-23', 'Alış qiyməti və maya dəyəri', [
                'new' => [
                    'Anbarda alış qiyməti, girişdə orta çəkili maya, anbarın dəyəri',
                    'Yeməyin maya dəyəri və marjası (menyu formasında canlı)',
                    '"Məhsullar üzrə mənfəət" hesabatı',
                ],
                'improved' => ['Personal yeməyi və hazır yemək hesabatlarında maya sütunu'],
            ]],
            ['1.7.0', '2026-10-23', 'Qaytarma və fırıldağa nəzarət', [
                'new' => [
                    'Məhsulun ləğvi (ödənişdən əvvəl) və qaytarılması (pul geri), mətbəxə ləğv çeki, qaytarma qəbzi',
                    'Menecer təsdiqi (PIN): ləğv, qaytarma, sifariş və çek ləğvi, endirim, servis imtinası',
                    '"Ləğv və qaytarma" jurnalı, işçilər üzrə ləğv faizi, endirimlər hesabatı',
                    'Təchizatçıya qaytarma (anbar)',
                ],
                'improved' => ['Qəbz və hesab çekinin təkrar çapı "NÜSXƏ" kimi işarələnir'],
                'fixed' => [
                    'Ofisiant mətbəxə getmiş sifarişi səbəbsiz ləğv edə bilirdi',
                    'Ödənilmiş çek menecer təsdiqi olmadan ləğv edilə bilirdi',
                    'Terminal PIN-ini təxmin etmə cəhdləri məhdudlaşdırılmırdı',
                ],
            ]],
            ['1.8.0', '2026-10-23', 'Bildirişlər və yeniliklər', [
                'new' => [
                    'Paneldə bildirişlər: anbarda və hazır stokda bitən / azalan məhsullar dərhal görünür',
                    'Saytda "Yeniliklər" bölməsi: versiyalar, əlavələr və düzəlişlər tarixlə',
                ],
            ]],
        ];
    }
}
