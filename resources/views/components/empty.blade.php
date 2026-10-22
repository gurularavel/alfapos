@props(['icon' => 'box', 'title' => 'Hələ heç nə yoxdur'])
<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <div class="mb-3 flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500"><x-icon :name="$icon" class="size-6" /></div>
    <p class="font-bold text-ink">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-1 max-w-sm text-sm text-zinc-500">{{ $slot }}</div>
    @endif
</div>
