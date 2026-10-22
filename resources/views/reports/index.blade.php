<x-layouts.app title="Hesabatlar">
<div x-data="{ q: '' }">
    <x-page-header title="Hesabatlar" subtitle="{{ count(\App\Http\Controllers\ReportController::REPORTS) }} hesabat. İstənilən tarix aralığı üçün açın və çap edin.">
        <div class="relative w-64">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
            <input x-model="q" class="input pl-9" placeholder="Hesabat axtar">
        </div>
    </x-page-header>

    @foreach ($groups as $group => $reports)
        <section class="mb-6" x-show="!q || @js(collect($reports)->map(fn ($r) => mb_strtolower($r[1].' '.$r[2]))->implode('|')).includes(q.toLowerCase())">
            <h2 class="mb-3 font-extrabold text-zinc-700">{{ $group }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($reports as $key => [, $title, $desc])
                    <a href="{{ route('reports.show', $key) }}" class="card card-pad group transition hover:border-brand-300"
                       x-show="!q || @js(mb_strtolower($title.' '.$desc)).includes(q.toLowerCase())">
                        <div class="mb-3 flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-icon name="chart" class="size-4" /></div>
                        <p class="font-bold group-hover:text-brand-700">{{ $title }}</p>
                        <p class="mt-1 text-sm text-zinc-500">{{ $desc }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
</x-layouts.app>
