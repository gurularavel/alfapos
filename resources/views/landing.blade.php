<x-layouts.public>
<section class="mx-auto max-w-6xl px-4 pt-12 pb-20 sm:px-6 lg:pt-20">
    <div class="max-w-3xl">
        <span class="badge-green mb-5">{{ \App\Services\TenantProvisioner::TRIAL_DAYS }} gün pulsuz</span>
        <h1 class="text-4xl leading-[1.05] font-black sm:text-6xl">Restoranınızı bir ekrandan idarə edin.</h1>
        <p class="mt-6 max-w-xl text-lg text-zinc-600">Kassa, mətbəx, masalar, QR menyu, anbar və hesabatlar. Planşetdə, kompüterdə və telefonda işləyir — quraşdırma tələb etmir.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="btn-primary px-6 py-3 text-base">Hesab yarat <x-icon name="arrow-right" class="size-4" /></a>
            <a href="{{ route('contact') }}" class="btn-secondary px-6 py-3 text-base">Demo üçün əlaqə</a>
        </div>
    </div>

    <div id="imkanlar" class="mt-16 grid scroll-mt-24 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['pos', 'Sürətli POS', 'Kateqoriyalar, variantlar, əlavələr, endirim, vergi və servis haqqı ilə saniyələr içində sifariş.'],
            ['table', 'Masalar və zal planı', 'Zalın real planı, dolu/boş masalar, əlavə sifariş, masanı köçürmək, kabinet depoziti.'],
            ['chef', 'Mətbəx ekranı', 'Sifarişlər canlı olaraq mətbəxə düşür, aşpaz hər məhsulu "hazırlanır → hazırdır" kimi işarələyir.'],
            ['user-cog', 'PIN ilə terminal', 'Kassir və ofisiant öz PIN-i ilə daxil olur, sifariş avtomatik onun adına yazılır.'],
            ['qr', 'QR menyu və sifariş', 'Hər masanın öz QR kodu. Müştəri menyuya baxır, sifariş verir, rəy yazır.'],
            ['online', 'Müştəri ekranı', 'Kassada ikinci ekran: müştəri səbəti və cəmi canlı görür.'],
            ['box', 'Anbar və resept', 'Satışda inqrediyentlər avtomatik silinir, az qalan stok üçün xəbərdarlıq.'],
            ['chart', 'Hesabatlar', 'Günlük, saatlıq, məhsul, kateqoriya, ödəniş növü, işçi və vergi hesabatları.'],
            ['printer', 'Çek və mətbəx çeki', '58/80 mm qəbz, mətbəx çeki, hesab çeki — nümunəni çap edib yoxlamaq imkanı.'],
        ] as [$icon, $title, $text])
            <div class="card card-pad">
                <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-100 text-brand-700"><x-icon :name="$icon" /></div>
                <h3 class="font-extrabold">{{ $title }}</h3>
                <p class="mt-1.5 text-sm text-zinc-600">{{ $text }}</p>
            </div>
        @endforeach
    </div>
</section>

<section id="qiymetler" class="scroll-mt-20 border-t border-zinc-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-3xl font-black">Qiymətlər</h2>
        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach (\App\Models\Plan::where('is_active', true)->orderBy('sort')->get() as $plan)
                <div @class(['card card-pad', 'ring-2 ring-brand-500' => $loop->iteration === 2])>
                    <p class="font-bold text-zinc-500">{{ $plan->name }}</p>
                    <p class="mt-2 text-4xl font-black">{{ rtrim(rtrim($plan->price, '0'), '.') }} <span class="text-lg font-bold text-zinc-500">{{ $plan->currency }}/{{ $plan->interval === 'year' ? 'il' : 'ay' }}</span></p>
                    <ul class="mt-5 space-y-2 text-sm text-zinc-600">
                        <li>{{ $plan->max_users ? $plan->max_users.' istifadəçi' : 'Limitsiz istifadəçi' }}</li>
                        <li>{{ $plan->max_menu_items ? $plan->max_menu_items.' menyu məhsulu' : 'Limitsiz menyu' }}</li>
                        <li>{{ $plan->max_tables ? $plan->max_tables.' masa' : 'Limitsiz masa' }}</li>
                        @foreach ($plan->features ?? [] as $f)<li class="flex gap-2"><x-icon name="check" class="size-4 text-brand-500" />{{ $f }}</li>@endforeach
                    </ul>
                    <a href="{{ route('register') }}" class="btn-primary mt-6 w-full">Başla</a>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <div class="flex flex-col items-start justify-between gap-6 rounded-3xl bg-brand-700 p-8 text-white sm:p-12 lg:flex-row lg:items-center">
        <div>
            <h2 class="text-3xl font-black">Sualınız var?</h2>
            <p class="mt-2 text-white/80">Ən çox verilən suallara baxın və ya bizə zəng edin — quraşdırmada kömək edirik.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('faq') }}" class="btn bg-white px-6 py-3 text-base text-brand-800 hover:bg-brand-50">Sual-cavab</a>
            <a href="tel:{{ site('phone_intl') }}" class="btn bg-white/15 px-6 py-3 text-base text-white hover:bg-white/25">{{ site('phone') }}</a>
        </div>
    </div>
</section>
</x-layouts.public>
