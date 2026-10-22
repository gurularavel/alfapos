@php
    $s = site();
    $groups = \App\Models\Faq::where('is_active', true)->orderBy('sort')->orderBy('id')->get()->groupBy('group');
@endphp
<x-layouts.public title="Sual-cavab" description="AlfaPOS haqqında tez-tez verilən suallar: cihazlar, printer, internet, abunəlik, təhlükəsizlik.">
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
            @foreach ($items as $faq)
                @php($answer = \App\Models\SitePage::placeholders($faq->answer))
                <details class="group px-5" x-show="!q || @js(mb_strtolower($faq->question.' '.$answer)).includes(q.toLowerCase())">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 font-bold">
                        {{ $faq->question }}
                        <x-icon name="chevron-down" class="size-5 shrink-0 text-zinc-400 transition group-open:rotate-180" />
                    </summary>
                    <p class="pb-5 whitespace-pre-line text-zinc-600">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    @endforeach

    <div class="mt-12 rounded-3xl bg-brand-50 p-8">
        <p class="text-xl font-black">Cavabı tapmadınız?</p>
        <p class="mt-1 text-zinc-600">Bizə zəng edin: <a href="tel:{{ $s['phone_intl'] }}" class="font-bold text-brand-700">{{ $s['phone'] }}</a>@if ($s['city_phone']), <a href="tel:{{ $s['city_phone_intl'] }}" class="font-bold text-brand-700">{{ $s['city_phone'] }}</a>@endif və ya yazın: <a href="mailto:{{ $s['email'] }}" class="font-bold text-brand-700">{{ $s['email'] }}</a></p>
    </div>
</section>
</x-layouts.public>
