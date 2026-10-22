@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-extrabold text-ink">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-zinc-500">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
