<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\SitePage;
use Illuminate\Database\Seeder;

/**
 * Saytın ilkin məzmunu (Haqqımızda, Məxfilik siyasəti, İstifadə şərtləri, Sual-cavab).
 * Yalnız boşdursa yaradır — superadminin redaktələrinin üstündən yazmır.
 * Mətndəki {şirkət}, {telefon}, {email} və s. yer tutucular sayt ayarlarından doldurulur.
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        SitePage::firstOrCreate(['slug' => 'about'], [
            'title' => 'Restoranlar üçün sadə və etibarlı POS',
            'description' => 'AlfaPOS — {şirkət} şirkətinin restoran və kafelər üçün bulud əsaslı POS sistemi.',
            'body' => <<<'MD'
**AlfaPOS** — **{şirkət}** şirkəti tərəfindən hazırlanmış bulud əsaslı satış nöqtəsi (POS) sistemidir. Restoran, kafe, bar, otel restoranları və fast-food müəssisələri üçün nəzərdə tutulub: kassa, masalar, mətbəx, QR menyu, anbar və hesabatlar bir yerdə, istənilən cihazdan.

## Məqsədimiz

Kiçik və orta müəssisələrə bahalı avadanlıq və mürəkkəb proqramlar olmadan müasir idarəetmə imkanı vermək.

## Necə işləyirik

Sistem buludda işləyir: quraşdırma, server və ya texniki xidmət tələb etmir. Yeniləmələr avtomatik gəlir. Qoşulma, menyunun daxil edilməsi, printer və cihazların qurulmasında kömək edirik.

## Niyə AlfaPOS?

- Azərbaycan dilində interfeys və ₼ ilə hesablamalar
- ƏDV (qiymətə daxil və ya üstə), servis haqqı və kabinet depoziti
- PIN ilə işləyən POS terminalı, kassir və ofisiant rolları
- Zal planı, əlavə sifariş, masanı köçürmək
- Canlı mətbəx ekranı və mətbəx çekləri
- QR menyu, QR sifariş və müştəri rəyləri
- Planşet, kompüter və telefonda işləyir
MD,
        ]);

        SitePage::firstOrCreate(['slug' => 'privacy'], [
            'title' => 'Məxfilik siyasəti',
            'description' => 'AlfaPOS xidmətində fərdi məlumatların toplanması, istifadəsi və qorunması.',
            'body' => <<<'MD'
Bu siyasət **{şirkət}** şirkətinin (bundan sonra — “biz”) **AlfaPOS** xidmətindən (alfapos.app saytı, idarəetmə paneli, POS, QR menyu və müştəri ekranı) istifadə zamanı fərdi məlumatların necə toplandığını, istifadə edildiyini və qorunduğunu izah edir. Fərdi məlumatların emalı Azərbaycan Respublikasının “Fərdi məlumatlar haqqında” Qanununa uyğun aparılır.

## Hansı məlumatları toplayırıq

- **Hesab məlumatları:** biznesin adı, sahibin və işçilərin adı, e-poçt ünvanı, telefon nömrəsi, parol və POS PIN kodu (yalnız şifrələnmiş formada).
- **Biznes məlumatları:** menyu, qiymətlər, masalar, sifarişlər, fakturalar, anbar, rezervlər və hesabatlar.
- **Biznesin müştəriləri haqqında məlumatlar:** biznesin özünün daxil etdiyi ad, telefon, e-poçt, doğum tarixi; QR menyu ilə göndərilən sifarişlər və rəylər.
- **Texniki məlumatlar:** IP ünvanı (audit qeydlərində), brauzer sessiyası üçün zəruri cookie-lər, xəta qeydləri.

## Məlumatlardan necə istifadə edirik

- Xidməti göstərmək: sifarişlərin qəbulu, çeklərin çapı, hesabatların hazırlanması.
- Hesabı qorumaq: giriş, PIN yoxlaması, şübhəli əməliyyatların aşkarlanması, audit qeydləri.
- Abunəlik və dəstək: ödənişlərin uçotu, müraciətlərə cavab vermək, vacib xidməti bildirişlər göndərmək.

Məlumatlarınızı reklam məqsədilə satmırıq və üçüncü şəxslərə ötürmürük.

## Biznesin müştərilərinin məlumatları

Restoran (biznes) öz müştəriləri haqqında daxil etdiyi məlumatların **sahibi və operatorudur**; biz bu məlumatları yalnız biznesin tapşırığı ilə və xidməti göstərmək üçün saxlayırıq. Müştərinin öz məlumatları ilə bağlı müraciəti ilk növbədə həmin biznesə ünvanlanmalıdır.

## Məlumatların saxlanması və təhlükəsizliyi

- Hər biznesin məlumatları digər bizneslərin məlumatlarından məntiqi olaraq tam ayrılır.
- Brauzer ilə server arasında əlaqə şifrələnmiş (HTTPS) kanalla aparılır.
- Parollar və PIN kodlar geri qaytarılmayan şəkildə (hash) saxlanılır; işçilər rollarına uyğun yalnız lazımi bölmələrə daxil olur.
- Mühüm dəyişikliklərdən əvvəl verilənlər bazasının ehtiyat nüsxəsi götürülür.

Heç bir sistem tam risksiz deyil; təhlükəsizlik insidenti baş verərsə, təsirlənən bizneslərə məlumat veririk.

## Cookie-lər

Yalnız sistemin işləməsi üçün zəruri cookie-lərdən istifadə edirik (sessiya və CSRF qorunması). Reklam və ya üçüncü tərəf analitika cookie-ləri istifadə olunmur.

## Məlumatların ötürülməsi

Məlumatlar yalnız xidməti təmin edən infrastruktur (server/hosting) provayderlərinə — xidmət göstərmək üçün, və qanunla nəzərdə tutulmuş hallarda səlahiyyətli dövlət orqanlarına ötürülə bilər.

## Saxlama müddəti

Məlumatlar hesab aktiv olduğu müddətdə saxlanılır. Abunəlik bitdikdə məlumatlar silinmir ki, yenidən aktivləşdirmədə itməsin. Hesabın və məlumatların silinməsi üçün {email} ünvanına müraciət edə bilərsiniz.

## Hüquqlarınız

Öz fərdi məlumatlarınızla tanış olmaq, onların düzəldilməsini, silinməsini və ya emalının məhdudlaşdırılmasını tələb etmək hüququnuz var. Müraciətlər üçün: {email}.

## Əlaqə

{şirkət} · VÖEN {voen} · {ünvan} · Telefon: {telefon}, {şəhər_telefonu} · E-poçt: {email}
MD,
        ]);

        SitePage::firstOrCreate(['slug' => 'terms'], [
            'title' => 'İstifadə şərtləri',
            'description' => 'AlfaPOS xidmətindən istifadə qaydaları.',
            'body' => <<<'MD'
Bu şərtlər **{şirkət}** şirkətinin **AlfaPOS** xidmətindən istifadə qaydalarını müəyyən edir. Qeydiyyatdan keçməklə və ya xidmətdən istifadə etməklə bu şərtləri və Məxfilik siyasətini qəbul etmiş olursunuz.

## Xidmət

AlfaPOS restoran, kafe və oxşar müəssisələr üçün bulud əsaslı POS sistemidir: sifariş qəbulu, masalar, mətbəx ekranı, çeklər, QR menyu, anbar, hesabatlar və əlaqəli funksiyalar. Funksiyaların tərkibi seçilmiş plana görə fərqlənə bilər və vaxtaşırı yenilənir.

## Hesab

- Qeydiyyat zamanı düzgün məlumat verməlisiniz.
- Hesabınızın, işçilərin parollarının və PIN kodlarının məxfiliyinə siz cavabdehsiniz.
- Hesabınızdan icazəsiz istifadədən şübhələndikdə dərhal {email} ünvanına bildirin.

## Sınaq müddəti və ödəniş

- Yeni hesablar pulsuz sınaq müddəti alır.
- Sınaqdan sonra xidmətdən istifadə seçilmiş planın qiymətinə görə ödənişlidir.
- Abunəlik bitdikdə panel və POS bağlanır, məlumatlarınız isə saxlanılır və ödənişdən sonra yenidən əlçatan olur.

## İstifadə qaydaları

Xidmətdən qanunsuz məqsədlərlə istifadə etmək, başqa bizneslərin məlumatlarına daxil olmağa cəhd etmək, sistemin təhlükəsizliyini icazəsiz yoxlamaq/pozmaq, sistemi həddindən artıq yükləmək və ya proqram təminatını kopyalamaq qadağandır.

## Məlumatlarınız

Daxil etdiyiniz bütün biznes məlumatları sizə məxsusdur. Biz bu məlumatları yalnız xidməti göstərmək üçün emal edirik.

## Vergi və fiskal tələblər

Sistemdəki qəbzlər və hesabatlar biznesin daxili uçotu üçündür. Vergi qanunvericiliyinin tələblərinə (o cümlədən nəzarət-kassa aparatı / fiskal çek tələblərinə) əməl edilməsinə biznesin özü cavabdehdir.

## Xidmətin əlçatanlığı

Xidmətin fasiləsiz işləməsi üçün səy göstəririk, lakin texniki işlər, internet və ya üçüncü tərəf provayderlərindən asılı fasilələr ola bilər.

## Məsuliyyətin məhdudlaşdırılması

Qanunvericiliyin icazə verdiyi həddə, xidmətdən istifadə nəticəsində dolayı zərərlərə görə məsuliyyət daşımırıq. Məsuliyyətimiz son 3 ay ərzində xidmət üçün ödədiyiniz məbləğlə məhdudlaşır.

## Xitam

Abunəliyi istənilən vaxt yeniləməyərək dayandıra bilərsiniz. Hesabın və məlumatların tam silinməsi üçün {email} ünvanına müraciət edin.

## Tətbiq olunan hüquq

Bu şərtlər Azərbaycan Respublikasının qanunvericiliyi ilə tənzimlənir.

## Əlaqə

{şirkət} · VÖEN {voen} · {ünvan} · Telefon: {telefon}, {şəhər_telefonu} · E-poçt: {email}
MD,
        ]);

        if (Faq::exists()) {
            return;
        }

        $faqs = [
            'Ümumi' => [
                ['AlfaPOS nədir?', 'Restoran, kafe və barlar üçün bulud əsaslı POS sistemidir: sifariş qəbulu, masalar, mətbəx ekranı, ödəniş və çeklər, QR menyu, anbar və hesabatlar. {şirkət} şirkətinin məhsuludur.'],
                ['Pulsuz sınamaq olarmı?', 'Bəli. Qeydiyyatdan sonra bütün imkanlardan pulsuz sınaq müddəti ərzində istifadə edirsiniz, kart tələb olunmur.'],
                ['Hansı cihazlarda işləyir?', 'Brauzeri olan istənilən cihazda: Windows/Mac kompüter, Android planşet, iPad, sensor POS monitorları və telefon. Ayrıca proqram quraşdırmaq lazım deyil.'],
                ['İnternet mütləqdirmi?', 'Bəli, sistem buludda işləyir. Sabit internet (Wi-Fi və ya kabel) tövsiyə edirik; ehtiyat üçün mobil internet də işləyir.'],
            ],
            'Kassa və masalar' => [
                ['Kassir və ofisiantlar necə daxil olur?', 'Cihaz bir dəfə "Terminal rejimi"nə keçirilir. Hər işçinin 4 rəqəmli PIN-i olur: PIN yazanda ekran açılır və sifarişlər avtomatik onun adına yazılır. Hərəkətsizlikdən sonra ekran özü kilidlənir.'],
                ['Masanın hesabını necə bağlayıram?', 'POS → Masalar → dolu masaya toxunun → "Hesab çeki" (müştəriyə göstərmək üçün) → "Hesabı bağla" → ödəniş növü. Qəbz çap olunur, masa boşalır.'],
                ['Masa əlavə sifariş versə nə olur?', 'Yeni sifariş mövcud hesabın üstünə gəlir, mətbəxə isə yalnız yeni məhsullarla "ƏLAVƏ SİFARİŞ" çeki gedir. Hesab bağlananda hamısı bir qəbzdə olur.'],
                ['Masanı başqa masaya köçürmək olarmı?', 'Bəli: Masalar → dolu masa → "Masanı köçür / birləşdir" → hədəf masa. Dolu masaya köçürəndə hesablar birləşir, mətbəxə "MASA KÖÇÜRÜLDÜ" çeki gedir.'],
                ['Kabinet depoziti necə işləyir?', 'Masaya minimum məbləğ (depozit) təyin edirsiniz. Hesab bundan az olarsa, fərq qəbzdə ayrıca sətirdə göstərilir və ödənişə əlavə olunur.'],
                ['Vergi və servis haqqını söndürmək olarmı?', 'Bəli: Ayarlar → Vergi və servis haqqı. Servis haqqını yalnız zalda oturanlara tətbiq etmək və kassada konkret sifariş üçün ləğv etmək də mümkündür.'],
            ],
            'Avadanlıq' => [
                ['Hansı printerlər dəstəklənir?', '58 mm və 80 mm termal çek printerləri (brauzerdən çap). Ayarlar → Çap ayarlarında nümunə qəbzi görüb test çapı edə bilərsiniz.'],
                ['Mətbəx ekranı üçün nə lazımdır?', 'Mətbəxdə planşet və ya monitorlu kompüter kifayətdir. Yeni sifariş gələndə ekran dərhal yenilənir və səs siqnalı verir.'],
                ['Müştəri ekranı (ikinci ekran) necə qoşulur?', 'Ayarlar → Müştəri ekranlarında ekran yaradın, linki planşetdə və ya ikinci monitorda açın, POS-da həmin ekranı seçin.'],
            ],
            'Abunəlik və təhlükəsizlik' => [
                ['Ödəniş necə aparılır?', 'Aylıq və ya illik plan seçirsiniz. Abunəliyi uzatmaq üçün bizimlə əlaqə saxlayın: {telefon}, {email}.'],
                ['Abunəlik bitsə məlumatlarım silinəcək?', 'Xeyr. Sistem bağlanır, amma menyu, sifarişlər və hesabatlar qorunur; abunəliyi yeniləyən kimi hər şey əvvəlki kimi işləyir.'],
                ['Məlumatlarım təhlükəsizdirmi?', 'Hər biznesin məlumatları digərlərindən tam ayrılır, əlaqə şifrələnmiş (HTTPS) kanalla aparılır, giriş parol və PIN ilə qorunur. Ətraflı: Məxfilik siyasəti.'],
                ['Dəstəyə necə müraciət edim?', 'Mobil: {telefon} · Şəhər: {şəhər_telefonu} · E-poçt: {email} · WhatsApp eyni mobil nömrə ilə.'],
            ],
        ];

        $sort = 0;
        foreach ($faqs as $group => $items) {
            foreach ($items as [$question, $answer]) {
                Faq::create(['group' => $group, 'question' => $question, 'answer' => $answer, 'sort' => ++$sort]);
            }
        }
    }
}
