<x-layouts.public title="Haqqımızda" description="AlfaPOS — Kvadrat şirkətinin restoran və kafelər üçün bulud əsaslı POS sistemi.">
@php($company = config('app.company'))
<section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
    <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">Haqqımızda</p>
    <h1 class="mt-2 text-4xl font-black sm:text-5xl">Restoranlar üçün sadə və etibarlı POS</h1>
    <p class="mt-6 text-lg text-zinc-600">
        <b>AlfaPOS</b> — <b>{{ $company['name'] }}</b> şirkəti tərəfindən hazırlanmış bulud əsaslı satış nöqtəsi (POS) sistemidir.
        Restoran, kafe, bar, otel restoranları və fast-food müəssisələri üçün nəzərdə tutulub: kassa, masalar, mətbəx,
        QR menyu, anbar və hesabatlar bir yerdə, istənilən cihazdan.
    </p>

    <div class="mt-12 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Məqsədimiz', 'Kiçik və orta müəssisələrə bahalı avadanlıq və mürəkkəb proqramlar olmadan müasir idarəetmə imkanı vermək.'],
            ['Necə işləyirik', 'Sistem buludda işləyir: quraşdırma, server və ya texniki xidmət tələb etmir. Yeniləmələr avtomatik gəlir.'],
            ['Dəstək', 'Qoşulma, menyunun daxil edilməsi, printer və cihazların qurulmasında kömək edirik.'],
        ] as [$title, $text])
            <div class="card card-pad"><p class="font-extrabold">{{ $title }}</p><p class="mt-2 text-sm text-zinc-600">{{ $text }}</p></div>
        @endforeach
    </div>

    <h2 class="mt-14 text-2xl font-black">Niyə AlfaPOS?</h2>
    <ul class="mt-4 grid gap-3 text-zinc-700 sm:grid-cols-2">
        @foreach ([
            'Azərbaycan dilində interfeys və ₼ ilə hesablamalar',
            'ƏDV (qiymətə daxil və ya üstə), servis haqqı və kabinet depoziti',
            'PIN ilə işləyən POS terminalı, kassir və ofisiant rolları',
            'Canlı mətbəx ekranı və mətbəx çekləri',
            'QR menyu, QR sifariş və müştəri rəyləri',
            'Planşet, kompüter və telefonda işləyir',
        ] as $point)
            <li class="flex gap-3"><x-icon name="check" class="mt-0.5 size-5 shrink-0 text-brand-500" /> {{ $point }}</li>
        @endforeach
    </ul>

    <div class="mt-14 rounded-3xl bg-white p-8 ring-1 ring-zinc-200">
        <p class="text-xl font-black">Bizimlə əlaqə</p>
        <p class="mt-2 text-zinc-600">Telefon: <a href="tel:{{ $company['phone_intl'] }}" class="font-bold text-brand-700">{{ $company['phone'] }}</a> · E-poçt: <a href="mailto:{{ $company['email'] }}" class="font-bold text-brand-700">{{ $company['email'] }}</a></p>
        <a href="{{ route('contact') }}" class="btn-primary mt-5">Əlaqə səhifəsi</a>
    </div>
</section>
</x-layouts.public>
