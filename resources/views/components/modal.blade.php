{{-- Alpine modal. İstifadə: <x-modal show="modal === 'pay'" close="modal = null" title="Ödəniş"> ... </x-modal> --}}
@props(['show', 'close', 'title' => null, 'width' => 'max-w-lg'])
<div x-cloak x-show="{{ $show }}" x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" @keydown.escape.window="{{ $close }}">
    <div @click.outside="{{ $close }}" class="flex max-h-[92vh] w-full {{ $width }} flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
        @if ($title)
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
                <h3 class="text-lg font-bold text-ink">{!! $title !!}</h3>
                <button type="button" class="btn-ghost" @click="{{ $close }}" aria-label="Bağla"><x-icon name="x" /></button>
            </div>
        @endif
        <div class="overflow-y-auto px-5 py-4">{{ $slot }}</div>
        @isset($footer)
            <div class="flex justify-end gap-2 border-t border-zinc-100 bg-zinc-50 px-5 py-3">{{ $footer }}</div>
        @endisset
    </div>
</div>
