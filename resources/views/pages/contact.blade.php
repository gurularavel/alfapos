<x-layouts.public title="Əlaqə" description="AlfaPOS dəstək xidməti: telefon 055 703 80 08, e-poçt support@kvadrat.az.">
@php($company = config('app.company'))
<section class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
    <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">Əlaqə</p>
    <h1 class="mt-2 text-4xl font-black sm:text-5xl">Sizə necə kömək edək?</h1>
    <p class="mt-4 max-w-2xl text-lg text-zinc-600">Demo, qoşulma, qiymətlər və ya texniki suallar üçün bizə yazın və ya zəng edin. AlfaPOS — {{ $company['name'] }} şirkətinin məhsuludur.</p>

    <div class="mt-10 grid gap-4 md:grid-cols-3">
        <a href="tel:{{ $company['phone_intl'] }}" class="card card-pad group transition hover:border-brand-300">
            <div class="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand-100 text-brand-700"><x-icon name="online" class="size-6" /></div>
            <p class="text-sm font-semibold text-zinc-500">Telefon</p>
            <p class="mt-1 text-2xl font-black group-hover:text-brand-700">{{ $company['phone'] }}</p>
            <p class="mt-2 text-sm text-zinc-500">Zəng etmək üçün toxunun</p>
        </a>
        <a href="mailto:{{ $company['email'] }}" class="card card-pad group transition hover:border-brand-300">
            <div class="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand-100 text-brand-700"><x-icon name="file" class="size-6" /></div>
            <p class="text-sm font-semibold text-zinc-500">E-poçt</p>
            <p class="mt-1 text-2xl font-black break-all group-hover:text-brand-700">{{ $company['email'] }}</p>
            <p class="mt-2 text-sm text-zinc-500">Adətən bir iş günü ərzində cavab veririk</p>
        </a>
        <a href="https://wa.me/{{ ltrim($company['phone_intl'], '+') }}" target="_blank" rel="noopener" class="card card-pad group transition hover:border-brand-300">
            <div class="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand-100 text-brand-700"><x-icon name="note" class="size-6" /></div>
            <p class="text-sm font-semibold text-zinc-500">WhatsApp</p>
            <p class="mt-1 text-2xl font-black group-hover:text-brand-700">{{ $company['phone'] }}</p>
            <p class="mt-2 text-sm text-zinc-500">Mesaj yazmaq üçün toxunun</p>
        </a>
    </div>

    <div class="mt-10 grid gap-4 md:grid-cols-2">
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
