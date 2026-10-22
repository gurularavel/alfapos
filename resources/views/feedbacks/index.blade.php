<x-layouts.app title="Rəylər">
    <x-page-header title="Rəylər" subtitle="QR menyu vasitəsilə müştərilərdən gələn rəylər.">
        <span class="badge-amber text-sm">★ {{ $average }} · {{ $count }} rəy</span>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('feedbacks.index') }}" @class(['chip', 'active' => ! request('rating')])>Hamısı</a>
        @foreach ([5, 4, 3, 2, 1] as $r)
            <a href="{{ route('feedbacks.index', ['rating' => $r]) }}" @class(['chip', 'active' => request('rating') == $r])>{{ $r }} ★</a>
        @endforeach
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($feedbacks as $f)
            <div class="card card-pad">
                <div class="flex items-start justify-between">
                    <div><p class="font-bold">{{ $f->name ?: 'Anonim' }}</p><p class="text-xs text-zinc-500">{{ $f->phone }} · {{ $f->created_at->format('d.m.Y H:i') }}</p></div>
                    <span class="text-amber-500">{{ str_repeat('★', $f->rating) }}<span class="text-zinc-200">{{ str_repeat('★', 5 - $f->rating) }}</span></span>
                </div>
                @if ($f->comment)<p class="mt-3 text-sm text-zinc-700">{{ $f->comment }}</p>@endif
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty icon="star" title="Hələ rəy yoxdur">Ayarlarda rəy funksiyasını aktiv edin; müştərilər QR menyudan rəy yaza bilər.</x-empty></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $feedbacks->links() }}</div>
</x-layouts.app>
