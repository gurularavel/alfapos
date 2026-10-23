{{-- Bir versiyanın "Yeniliklər" kartı (ana səhifə və /yenilikler). --}}
@props(['entry', 'latest' => false])
<article {{ $attributes->class(['relative rounded-2xl bg-white p-6 ring-1 ring-zinc-200', 'ring-2 ring-brand-300' => $latest]) }} id="v{{ $entry->version }}">
    <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1">
        <span class="rounded-lg bg-ink px-2.5 py-1 font-mono text-sm font-bold text-white">v{{ $entry->version }}</span>
        @if ($latest)<span class="badge-green">Son versiya</span>@endif
        <time datetime="{{ $entry->released_at->toDateString() }}" class="text-sm text-zinc-500">{{ $entry->released_at->translatedFormat('d F Y') }}</time>
    </div>
    <h3 class="text-xl font-black">{{ $entry->title }}</h3>
    @foreach ($entry->grouped() as $type => $lines)
        <p class="mt-4 mb-2"><span class="{{ \App\Models\ChangelogEntry::TYPES[$type][1] }}">{{ \App\Models\ChangelogEntry::TYPES[$type][0] }}</span></p>
        <ul class="space-y-1.5 text-zinc-700">
            @foreach ($lines as $line)
                <li class="flex gap-2"><span class="mt-2 size-1.5 shrink-0 rounded-full {{ ['new' => 'bg-brand-500', 'improved' => 'bg-sky-500', 'fixed' => 'bg-amber-500'][$type] }}"></span>{{ $line }}</li>
            @endforeach
        </ul>
    @endforeach
</article>
