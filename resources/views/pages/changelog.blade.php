@php($entries = \App\Models\ChangelogEntry::published()->get())
<x-layouts.public title="Yeniliklər" description="AlfaPOS-un versiyaları: yeni imkanlar, təkmilləşdirmələr və düzəlişlər tarixlə.">
<section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
    <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">Yeniliklər</p>
    <h1 class="mt-2 text-4xl font-black sm:text-5xl">Nə dəyişdi?</h1>
    <p class="mt-3 text-lg text-zinc-600">AlfaPOS mütəmadi yenilənir. Hər versiyada nə əlavə olunduğu və nə düzəldildiyi burada, tarixi ilə.</p>

    <div class="relative mt-10 space-y-6 border-l-2 border-zinc-200 pl-6 sm:pl-8">
        @forelse ($entries as $entry)
            <div class="relative">
                <span @class(['absolute top-6 -left-[33px] size-4 rounded-full border-4 border-canvas sm:-left-[41px]', 'bg-brand-500' => $loop->first, 'bg-zinc-300' => ! $loop->first])></span>
                <x-changelog-release :entry="$entry" :latest="$loop->first" />
            </div>
        @empty
            <p class="text-zinc-500">Hələ qeyd yoxdur.</p>
        @endforelse
    </div>
</section>
</x-layouts.public>
