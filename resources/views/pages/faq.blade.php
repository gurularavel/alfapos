<x-layouts.public title="Sual-cavab" description="AlfaPOS haqqında tez-tez verilən suallar: cihazlar, printer, internet, abunəlik, təhlükəsizlik.">
@php
    $company = config('app.company');
    $trial = \App\Services\TenantProvisioner::TRIAL_DAYS;
    $groups = [
        'Ümumi' => [
            ['AlfaPOS nədir?', 'Restoran, kafe və barlar üçün bulud əsaslı POS sistemidir: sifariş qəbulu, masalar, mətbəx ekranı, ödəniş və çeklər, QR menyu, anbar və hesabatlar. '.$company['name'].' şirkətinin məhsuludur.'],
            ['Pulsuz sınamaq olarmı?', "Bəli. Qeydiyyatdan sonra {$trial} gün bütün imkanlardan pulsuz istifadə edirsiniz, kart tələb olunmur."],
            ['Hansı cihazlarda işləyir?', 'Brauzeri olan istənilən cihazda: Windows/Mac kompüter, Android planşet, iPad, sensor POS monitorları və telefon. Ayrıca proqram quraşdırmaq lazım deyil.'],
            ['İnternet mütləqdirmi?', 'Bəli, sistem buludda işləyir. Sabit internet (Wi-Fi və ya kabel) tövsiyə edirik; ehtiyat üçün mobil internet də işləyir.'],
        ],
        'Kassa və masalar' => [
            ['Kassir və ofisiantlar necə daxil olur?', 'Cihaz bir dəfə "Terminal rejimi"nə keçirilir. Hər işçinin 4 rəqəmli PIN-i olur: PIN yazanda ekran açılır və sifarişlər avtomatik onun adına yazılır. Hərəkətsizlikdən sonra ekran özü kilidlənir.'],
            ['Masanın hesabını necə bağlayıram?', 'POS → Masalar → dolu masaya toxunun → "Hesab çeki" (müştəriyə göstərmək üçün) → "Hesabı bağla" → ödəniş növü. Qəbz çap olunur, masa boşalır.'],
            ['Masa əlavə sifariş versə nə olur?', 'Yeni sifariş mövcud hesabın üstünə gəlir, mətbəxə isə yalnız yeni məhsullarla "ƏLAVƏ SİFARİŞ" çeki gedir. Hesab bağlananda hamısı bir qəbzdə olur.'],
            ['Kabinet depoziti necə işləyir?', 'Masaya minimum məbləğ (depozit) təyin edirsiniz. Hesab bundan az olarsa, fərq qəbzdə ayrıca sətirdə göstərilir və ödənişə əlavə olunur.'],
            ['Vergi və servis haqqını söndürmək olarmı?', 'Bəli: Ayarlar → Vergi və servis haqqı. Servis haqqını yalnız zalda oturanlara tətbiq etmək və kassada konkret sifariş üçün ləğv etmək də mümkündür.'],
        ],
        'Avadanlıq' => [
            ['Hansı printerlər dəstəklənir?', '58 mm və 80 mm termal çek printerləri (brauzerdən çap). Ayarlar → Çap ayarlarında nümunə qəbzi görüb test çapı edə bilərsiniz.'],
            ['Mətbəx ekranı üçün nə lazımdır?', 'Mətbəxdə planşet və ya monitorlu kompüter kifayətdir. Yeni sifariş gələndə ekran dərhal yenilənir və səs siqnalı verir.'],
            ['Müştəri ekranı (ikinci ekran) necə qoşulur?', 'Ayarlar → Müştəri ekranlarında ekran yaradın, linki planşetdə və ya ikinci monitorda açın, POS-da həmin ekranı seçin.'],
        ],
        'Abunəlik və təhlükəsizlik' => [
            ['Ödəniş necə aparılır?', 'Aylıq və ya illik plan seçirsiniz. Abunəliyi uzatmaq üçün bizimlə əlaqə saxlayın: '.$company['phone'].', '.$company['email'].'.'],
            ['Abunəlik bitsə məlumatlarım silinəcək?', 'Xeyr. Sistem bağlanır, amma menyu, sifarişlər və hesabatlar qorunur; abunəliyi yeniləyən kimi hər şey əvvəlki kimi işləyir.'],
            ['Məlumatlarım təhlükəsizdirmi?', 'Hər biznesin məlumatları digərlərindən tam ayrılır, əlaqə şifrələnmiş (HTTPS) kanalla aparılır, giriş parol və PIN ilə qorunur. Ətraflı: Məxfilik siyasəti.'],
            ['Dəstəyə necə müraciət edim?', 'Telefon: '.$company['phone'].' · E-poçt: '.$company['email'].' · WhatsApp eyni nömrə ilə.'],
        ],
    ];
@endphp
<section class="mx-auto max-w-4xl px-4 py-14 sm:px-6" x-data="{ q: '' }">
    <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">Sual-cavab</p>
    <h1 class="mt-2 text-4xl font-black sm:text-5xl">Tez-tez verilən suallar</h1>
    <div class="relative mt-8">
        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-zinc-400" />
        <input x-model="q" class="input py-3.5 pl-12 text-base" placeholder="Sual axtarın…">
    </div>

    @foreach ($groups as $group => $items)
        <h2 class="mt-10 mb-3 text-xl font-black">{{ $group }}</h2>
        <div class="divide-y divide-zinc-100 overflow-hidden rounded-2xl bg-white ring-1 ring-zinc-200">
            @foreach ($items as [$question, $answer])
                <details class="group px-5" x-show="!q || @js(mb_strtolower($question.' '.$answer)).includes(q.toLowerCase())">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 font-bold">
                        {{ $question }}
                        <x-icon name="chevron-down" class="size-5 shrink-0 text-zinc-400 transition group-open:rotate-180" />
                    </summary>
                    <p class="pb-5 text-zinc-600">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    @endforeach

    <div class="mt-12 rounded-3xl bg-brand-50 p-8">
        <p class="text-xl font-black">Cavabı tapmadınız?</p>
        <p class="mt-1 text-zinc-600">Bizə zəng edin: <a href="tel:{{ $company['phone_intl'] }}" class="font-bold text-brand-700">{{ $company['phone'] }}</a> və ya yazın: <a href="mailto:{{ $company['email'] }}" class="font-bold text-brand-700">{{ $company['email'] }}</a></p>
    </div>
</section>
</x-layouts.public>
