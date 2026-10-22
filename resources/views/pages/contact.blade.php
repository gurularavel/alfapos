@php($s = site())
<x-layouts.public title="Əlaqə" :description="'AlfaPOS dəstək: '.$s['phone'].', '.$s['email'].'. '.$s['address']">
<section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
    <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">Əlaqə</p>
    <h1 class="mt-2 text-4xl font-black sm:text-5xl">Sizə necə kömək edək?</h1>
    <p class="mt-4 max-w-2xl text-lg text-zinc-600">Demo, qoşulma, qiymətlər və ya texniki suallar üçün bizə yazın, zəng edin və ya ofisimizə gəlin. AlfaPOS — {{ $s['company_name'] }} şirkətinin məhsuludur.</p>

    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (array_filter([
            ['online', 'Mobil telefon', $s['phone'], 'tel:'.$s['phone_intl'], 'Zəng etmək üçün toxunun'],
            $s['city_phone'] ? ['online', 'Şəhər telefonu', $s['city_phone'], 'tel:'.$s['city_phone_intl'], 'Zəng etmək üçün toxunun'] : null,
            ['file', 'E-poçt', $s['email'], 'mailto:'.$s['email'], 'Adətən bir iş günü ərzində cavab veririk'],
            $s['whatsapp'] ? ['note', 'WhatsApp', $s['whatsapp'], 'https://wa.me/'.ltrim($s['whatsapp_intl'], '+'), 'Mesaj yazmaq üçün toxunun'] : null,
        ]) as [$icon, $label, $value, $href, $hint])
            <a href="{{ $href }}" @if (str_starts_with($href, 'http')) target="_blank" rel="noopener" @endif class="card card-pad group transition hover:border-brand-300">
                <div class="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand-100 text-brand-700"><x-icon :name="$icon" class="size-6" /></div>
                <p class="text-sm font-semibold text-zinc-500">{{ $label }}</p>
                <p class="mt-1 text-xl font-black break-all group-hover:text-brand-700">{{ $value }}</p>
                <p class="mt-2 text-sm text-zinc-500">{{ $hint }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
        <div class="card card-pad flex flex-col">
            <p class="text-xl font-black">Ofis</p>
            <dl class="mt-4 space-y-3 text-zinc-700">
                @if ($s['address'])<div><dt class="text-sm font-semibold text-zinc-500">Ünvan</dt><dd class="text-lg font-bold">{{ $s['address'] }}</dd></div>@endif
                @if ($s['work_hours'])<div><dt class="text-sm font-semibold text-zinc-500">İş saatları</dt><dd class="font-semibold">{{ $s['work_hours'] }}</dd></div>@endif
                <div><dt class="text-sm font-semibold text-zinc-500">Şirkət</dt><dd class="font-semibold">{{ $s['company_name'] }}@if ($s['voen']) · VÖEN {{ $s['voen'] }}@endif</dd></div>
            </dl>
            @if ($s['map_url'])
                <a href="{{ $s['map_url'] }}" target="_blank" rel="noopener" class="btn-primary mt-6 self-start"><x-icon name="arrow-right" class="size-4" /> Xəritədə aç / marşrut</a>
            @endif
        </div>
        @if ($s['map_lat'] && $s['map_lng'])
            <div class="overflow-hidden rounded-2xl ring-1 ring-zinc-200">
                <iframe title="Ofisin xəritədə yeri" class="h-80 w-full lg:h-full lg:min-h-80" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                        src="https://maps.google.com/maps?q={{ urlencode($s['map_lat'].','.$s['map_lng']) }}&z=16&hl=az&output=embed"></iframe>
            </div>
        @endif
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="card card-pad">
            <p class="font-extrabold">Artıq müştərisiniz?</p>
            <p class="mt-2 text-sm text-zinc-600">Müraciət edərkən biznesinizin adını və problemin qısa təsvirini yazın; mümkünsə ekran görüntüsü əlavə edin — daha tez kömək edə bilərik.</p>
        </div>
        <div class="card card-pad">
            <p class="font-extrabold">Tez-tez verilən suallar</p>
            <p class="mt-2 text-sm text-zinc-600">Printerlər, internet, cihazlar, abunəlik və məlumatların təhlükəsizliyi haqqında cavablar.</p>
            <a href="{{ route('faq') }}" class="btn-secondary mt-4">Sual-cavaba keç</a>
        </div>
    </div>
</section>
</x-layouts.public>
