<x-layouts.public title="Məxfilik siyasəti">
@php
    $c = config('app.company');
    $mail = '<a href="mailto:'.$c['email'].'" class="font-semibold text-brand-700">'.$c['email'].'</a>';
@endphp
<x-legal-page title="Məxfilik siyasəti" updated="22 oktyabr 2026"
    :intro="'Bu siyasət <b>'.$c['name'].'</b> şirkətinin (bundan sonra — “biz”) <b>AlfaPOS</b> xidmətindən (alfapos.app saytı, idarəetmə paneli, POS, QR menyu və müştəri ekranı) istifadə zamanı fərdi məlumatların necə toplandığını, istifadə edildiyini və qorunduğunu izah edir. Fərdi məlumatların emalı Azərbaycan Respublikasının “Fərdi məlumatlar haqqında” Qanununa uyğun aparılır.'"
    :sections="[
        ['Hansı məlumatları toplayırıq', '<ul>
            <li><b>Hesab məlumatları:</b> biznesin adı, sahibin və işçilərin adı, e-poçt ünvanı, telefon nömrəsi, parol (yalnız şifrələnmiş formada) və POS PIN kodu (yalnız şifrələnmiş formada).</li>
            <li><b>Biznes məlumatları:</b> menyu, qiymətlər, masalar, sifarişlər, fakturalar, anbar, rezervlər və hesabatlar.</li>
            <li><b>Biznesin müştəriləri haqqında məlumatlar:</b> biznesin özünün daxil etdiyi ad, telefon, e-poçt, doğum tarixi; QR menyu ilə göndərilən sifarişlər və rəylər.</li>
            <li><b>Texniki məlumatlar:</b> IP ünvanı (audit qeydlərində), brauzer sessiyası üçün zəruri cookie-lər, xəta qeydləri.</li>
        </ul>'],
        ['Məlumatlardan necə istifadə edirik', '<ul>
            <li>Xidməti göstərmək: sifarişlərin qəbulu, çeklərin çapı, hesabatların hazırlanması.</li>
            <li>Hesabı qorumaq: giriş, PIN yoxlaması, şübhəli əməliyyatların aşkarlanması, audit qeydləri.</li>
            <li>Abunəlik və dəstək: ödənişlərin uçotu, müraciətlərə cavab vermək, vacib xidməti bildirişlər göndərmək.</li>
        </ul><p>Məlumatlarınızı reklam məqsədilə satmırıq və üçüncü şəxslərə ötürmürük.</p>'],
        ['Biznesin müştərilərinin məlumatları', '<p>Restoran (biznes) öz müştəriləri haqqında daxil etdiyi məlumatların <b>sahibi və operatorudur</b>; biz bu məlumatları yalnız biznesin tapşırığı ilə və xidməti göstərmək üçün saxlayırıq. Müştərinin öz məlumatları ilə bağlı müraciəti ilk növbədə həmin biznesə ünvanlanmalıdır; lazım olduqda biznesə texniki kömək edirik.</p>'],
        ['Məlumatların saxlanması və təhlükəsizliyi', '<ul>
            <li>Hər biznesin məlumatları digər bizneslərin məlumatlarından məntiqi olaraq tam ayrılır.</li>
            <li>Brauzer ilə server arasında əlaqə şifrələnmiş (HTTPS) kanalla aparılır.</li>
            <li>Parollar və PIN kodlar geri qaytarılmayan şəkildə (hash) saxlanılır; işçilər öz rollarına uyğun yalnız lazımi bölmələrə daxil olur.</li>
            <li>Mühüm dəyişikliklərdən əvvəl verilənlər bazasının ehtiyat nüsxəsi götürülür.</li>
        </ul><p>Heç bir sistem tam risksiz deyil; təhlükəsizlik insidenti baş verərsə, təsirlənən bizneslərə məlumat veririk.</p>'],
        ['Cookie-lər', '<p>Yalnız sistemin işləməsi üçün zəruri cookie-lərdən istifadə edirik (sessiya və CSRF qorunması). Reklam və ya üçüncü tərəf analitika cookie-ləri istifadə olunmur. Brauzerin yerli yaddaşında POS üçün kiçik ayarlar (məsələn, tam ekran rejimi, seçilmiş müştəri ekranı) saxlanıla bilər.</p>'],
        ['Məlumatların ötürülməsi', '<p>Məlumatlar yalnız aşağıdakı hallarda ötürülə bilər: xidməti təmin edən infrastruktur (server/hosting) provayderlərinə — yalnız xidmət göstərmək üçün; qanunla nəzərdə tutulmuş hallarda səlahiyyətli dövlət orqanlarına.</p>'],
        ['Saxlama müddəti', '<p>Məlumatlar hesab aktiv olduğu müddətdə saxlanılır. Abunəlik bitdikdə məlumatlar silinmir ki, yenidən aktivləşdirmədə itməsin. Hesabın və məlumatların silinməsi üçün '.$mail.' ünvanına müraciət edə bilərsiniz; qanunvericiliyin saxlanmasını tələb etdiyi məlumatlar istisna olmaqla, məlumatlar silinir.</p>'],
        ['Hüquqlarınız', '<p>Qanunvericiliyə uyğun olaraq öz fərdi məlumatlarınızla tanış olmaq, onların düzəldilməsini, silinməsini və ya emalının məhdudlaşdırılmasını tələb etmək hüququnuz var. Profil məlumatlarınızı paneldə özünüz dəyişə bilərsiniz; digər müraciətlər üçün '.$mail.' ünvanına yazın.</p>'],
        ['Dəyişikliklər', '<p>Bu siyasət yenilənə bilər. Əhəmiyyətli dəyişikliklər barədə sayt və ya panel vasitəsilə məlumat veririk; “Son yenilənmə” tarixi hər dəfə dəyişdirilir.</p>'],
        ['Əlaqə', '<p>'.$c['name'].' · Telefon: <a href=\'tel:'.$c['phone_intl'].'\' class=\'font-semibold text-brand-700\'>'.$c['phone'].'</a> · E-poçt: '.$mail.'</p>'],
    ]" />
</x-layouts.public>
