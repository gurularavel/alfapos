<x-layouts.public title="İstifadə şərtləri">
@php
    $c = config('app.company');
    $mail = '<a href="mailto:'.$c['email'].'" class="font-semibold text-brand-700">'.$c['email'].'</a>';
    $trial = \App\Services\TenantProvisioner::TRIAL_DAYS;
@endphp
<x-legal-page title="İstifadə şərtləri" updated="22 oktyabr 2026"
    :intro="'Bu şərtlər <b>'.$c['name'].'</b> şirkətinin <b>AlfaPOS</b> xidmətindən istifadə qaydalarını müəyyən edir. Qeydiyyatdan keçməklə və ya xidmətdən istifadə etməklə bu şərtləri və <a href=\''.route('privacy').'\' class=\'font-semibold text-brand-700\'>Məxfilik siyasəti</a>ni qəbul etmiş olursunuz.'"
    :sections="[
        ['Xidmət', '<p>AlfaPOS restoran, kafe və oxşar müəssisələr üçün bulud əsaslı POS sistemidir: sifariş qəbulu, masalar, mətbəx ekranı, çeklər, QR menyu, anbar, hesabatlar və əlaqəli funksiyalar. Funksiyaların tərkibi seçilmiş plana görə fərqlənə bilər və vaxtaşırı yenilənir.</p>'],
        ['Hesab', '<ul>
            <li>Qeydiyyat zamanı düzgün məlumat verməlisiniz.</li>
            <li>Hesabınızın, işçilərin parollarının və PIN kodlarının məxfiliyinə siz cavabdehsiniz; işçilərin hesab daxilindəki əməliyyatları biznesin əməliyyatları sayılır.</li>
            <li>Hesabınızdan icazəsiz istifadədən şübhələndikdə dərhal '.$mail.' ünvanına bildirin.</li>
        </ul>'],
        ['Sınaq müddəti və ödəniş', '<ul>
            <li>Yeni hesablar '.$trial.' günlük pulsuz sınaq müddəti alır.</li>
            <li>Sınaqdan sonra xidmətdən istifadə seçilmiş planın qiymətinə görə ödənişlidir; qiymətlər saytda göstərilir.</li>
            <li>Abunəlik müddəti bitdikdə panel və POS bağlanır, məlumatlarınız isə saxlanılır və ödəniş edildikdən sonra yenidən əlçatan olur.</li>
        </ul>'],
        ['İstifadə qaydaları', '<p>Xidmətdən qanunsuz məqsədlərlə istifadə etmək, başqa bizneslərin məlumatlarına daxil olmağa cəhd etmək, sistemin təhlükəsizliyini yoxlamaq/pozmaq (icazəsiz), sistemi həddindən artıq yükləmək və ya proqram təminatını kopyalamaq qadağandır. Bu qaydalar pozulduqda hesab dayandırıla bilər.</p>'],
        ['Məlumatlarınız', '<p>Daxil etdiyiniz bütün biznes məlumatları (menyu, sifarişlər, müştərilər və s.) sizə məxsusdur. Biz bu məlumatları yalnız xidməti göstərmək üçün emal edirik. Ətraflı: <a href=\''.route('privacy').'\' class=\'font-semibold text-brand-700\'>Məxfilik siyasəti</a>.</p>'],
        ['Vergi və fiskal tələblər', '<p>Sistemdəki qəbzlər və hesabatlar biznesin daxili uçotu üçündür. Vergi qanunvericiliyinin tələblərinə (o cümlədən nəzarət-kassa aparatı / fiskal çek tələblərinə) əməl edilməsinə biznesin özü cavabdehdir.</p>'],
        ['Xidmətin əlçatanlığı', '<p>Xidmətin fasiləsiz işləməsi üçün səy göstəririk, lakin texniki işlər, internet və ya üçüncü tərəf provayderlərindən asılı fasilələr ola bilər. Planlı texniki işlər barədə mümkün qədər əvvəlcədən məlumat veririk.</p>'],
        ['Məsuliyyətin məhdudlaşdırılması', '<p>Qanunvericiliyin icazə verdiyi həddə, xidmətdən istifadə nəticəsində dolayı zərərlərə (gəlirin itirilməsi və s.) görə məsuliyyət daşımırıq. Hər halda məsuliyyətimiz son 3 ay ərzində xidmət üçün ödədiyiniz məbləğlə məhdudlaşır.</p>'],
        ['Xitam', '<p>Abunəliyi istənilən vaxt yeniləməyərək dayandıra bilərsiniz. Hesabın və məlumatların tam silinməsi üçün '.$mail.' ünvanına müraciət edin. Bu şərtlər ciddi şəkildə pozulduqda hesabı dayandırmaq hüququmuz var.</p>'],
        ['Dəyişikliklər və tətbiq olunan hüquq', '<p>Şərtlər yenilənə bilər; əhəmiyyətli dəyişikliklər barədə məlumat veririk. Bu şərtlər Azərbaycan Respublikasının qanunvericiliyi ilə tənzimlənir.</p>'],
        ['Əlaqə', '<p>'.$c['name'].' · Telefon: <a href=\'tel:'.$c['phone_intl'].'\' class=\'font-semibold text-brand-700\'>'.$c['phone'].'</a> · E-poçt: '.$mail.'</p>'],
    ]" />
</x-layouts.public>
