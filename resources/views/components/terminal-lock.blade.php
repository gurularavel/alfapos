{{-- POS terminalının kilid ekranı. Səhifədə $terminal (PosTerminal::state()) olmalıdır. --}}
@props(['state'])
@push('scripts')
    <script>window.TERMINAL_STATE = @js($state);</script>
@endpush

<div x-data x-cloak x-show="$store.terminal.active && $store.terminal.locked" x-transition.opacity.duration.300ms
     class="fixed inset-0 z-[70] overflow-hidden bg-ink text-white select-none">

    {{-- Ekran qoruyucu --}}
    <div class="absolute inset-0 cursor-pointer" @click="$store.terminal.openPin('unlock')">
        <template x-for="(img, i) in $store.terminal.images" :key="img">
            <img :src="img" alt="" class="absolute inset-0 size-full object-cover transition-opacity duration-1000" :class="$store.terminal.slide === i ? 'opacity-100' : 'opacity-0'">
        </template>
        <div x-show="!$store.terminal.images.length" class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900">
            <template x-if="$store.terminal.store.logo"><img :src="$store.terminal.store.logo" alt="" class="mb-6 size-28 rounded-3xl bg-white object-cover shadow-xl"></template>
            <p class="text-4xl font-black sm:text-6xl" x-text="$store.terminal.store.name"></p>
        </div>
        <div class="absolute inset-x-0 bottom-0 flex items-end justify-between bg-gradient-to-t from-black/70 to-transparent p-6 sm:p-10">
            <div>
                <p class="text-5xl font-black tabular-nums sm:text-7xl" x-text="$store.terminal.timeLabel"></p>
                <p class="mt-1 text-lg text-white/80" x-text="$store.terminal.dateLabel"></p>
            </div>
            <p class="animate-pulse rounded-full bg-white/15 px-5 py-2.5 text-lg font-bold backdrop-blur">Başlamaq üçün toxunun</p>
        </div>
    </div>

    {{-- PIN paneli --}}
    <div x-show="$store.terminal.mode === 'pin'" x-transition.opacity class="absolute inset-0 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="$store.terminal.mode = 'saver'">
        <div class="w-full max-w-xs rounded-3xl bg-white p-6 text-ink shadow-2xl">
            <p class="text-center text-lg font-black" x-text="$store.terminal.purpose === 'exit' ? 'Terminaldan çıxış — admin PIN' : 'PIN kodunuzu daxil edin'"></p>
            <div class="my-5 flex justify-center gap-3" :class="$store.terminal.error && 'animate-[shake_.3s]'">
                <template x-for="n in 4">
                    <span class="size-4 rounded-full border-2 transition" :class="$store.terminal.pin.length >= n ? 'border-brand-500 bg-brand-500' : 'border-zinc-300'"></span>
                </template>
            </div>
            <p class="mb-3 h-5 text-center text-sm font-semibold text-red-600" x-text="$store.terminal.error"></p>
            <div class="grid grid-cols-3 gap-2.5">
                <template x-for="d in ['1','2','3','4','5','6','7','8','9']">
                    <button type="button" class="h-16 rounded-2xl bg-zinc-100 text-2xl font-black transition active:scale-95 active:bg-brand-100" @click="$store.terminal.press(d)" x-text="d"></button>
                </template>
                <button type="button" class="h-16 rounded-2xl text-sm font-bold text-zinc-500" @click="$store.terminal.mode = 'saver'">Ləğv</button>
                <button type="button" class="h-16 rounded-2xl bg-zinc-100 text-2xl font-black transition active:scale-95 active:bg-brand-100" @click="$store.terminal.press('0')">0</button>
                <button type="button" class="flex h-16 items-center justify-center rounded-2xl text-zinc-500" @click="$store.terminal.back()" aria-label="Sil">⌫</button>
            </div>
        </div>
    </div>

    <button type="button" x-show="$store.terminal.mode === 'saver'" class="absolute top-4 right-4 rounded-full bg-black/30 px-3 py-1.5 text-xs font-semibold text-white/70 backdrop-blur hover:text-white" @click.stop="$store.terminal.openPin('exit')">Terminaldan çıx</button>
</div>
