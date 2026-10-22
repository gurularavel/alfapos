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
    {{-- Sensor ekran üçün: düymələr ~96px (ekran hündürlüyünə görə 72–104px), touch-manipulation ilə iki dəfə toxunuşda zoom olmur --}}
    <div x-show="$store.terminal.mode === 'pin'" class="absolute inset-0 flex items-center justify-center bg-black/70 p-3 backdrop-blur-sm [touch-action:manipulation]" @click.self="$store.terminal.mode = 'saver'">
        <div class="w-full max-w-md rounded-[2rem] bg-white p-5 text-ink shadow-2xl sm:p-7">
            <p class="text-center text-xl font-black sm:text-2xl" x-text="$store.terminal.purpose === 'exit' ? 'Terminaldan çıxış — admin PIN' : 'PIN kodunuzu daxil edin'"></p>
            <div class="my-5 flex justify-center gap-5" :class="$store.terminal.error && 'animate-[shake_.3s]'">
                <template x-for="n in 4">
                    <span class="size-6 rounded-full border-[3px] transition" :class="$store.terminal.pin.length >= n ? 'scale-110 border-brand-500 bg-brand-500' : 'border-zinc-300'"></span>
                </template>
            </div>
            <p class="mb-3 min-h-6 text-center text-base font-bold text-red-600" x-text="$store.terminal.error"></p>
            <div class="grid grid-cols-3 gap-3">
                <template x-for="d in ['1','2','3','4','5','6','7','8','9']">
                    <button type="button" class="h-[clamp(4.5rem,11vh,6.5rem)] rounded-3xl bg-zinc-100 text-4xl font-black tabular-nums shadow-[inset_0_-3px_0_rgb(0_0_0/0.06)] transition select-none active:scale-95 active:bg-brand-500 active:text-white" @click="$store.terminal.press(d)" x-text="d"></button>
                </template>
                <button type="button" class="h-[clamp(4.5rem,11vh,6.5rem)] rounded-3xl text-lg font-bold text-zinc-500 transition select-none active:scale-95 active:bg-zinc-100" @click="$store.terminal.mode = 'saver'">Ləğv</button>
                <button type="button" class="h-[clamp(4.5rem,11vh,6.5rem)] rounded-3xl bg-zinc-100 text-4xl font-black shadow-[inset_0_-3px_0_rgb(0_0_0/0.06)] transition select-none active:scale-95 active:bg-brand-500 active:text-white" @click="$store.terminal.press('0')">0</button>
                <button type="button" class="flex h-[clamp(4.5rem,11vh,6.5rem)] items-center justify-center rounded-3xl text-zinc-600 transition select-none active:scale-95 active:bg-zinc-100" @click="$store.terminal.back()" aria-label="Sil">
                    <svg class="size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z"/><path d="m18 9-6 6M12 9l6 6"/></svg>
                </button>
            </div>
        </div>
    </div>

    <button type="button" x-show="$store.terminal.mode === 'saver'" class="absolute top-4 right-4 rounded-full bg-black/40 px-5 py-3 text-sm font-semibold text-white/80 backdrop-blur active:bg-black/60" @click.stop="$store.terminal.openPin('exit')">Terminaldan çıx</button>
</div>
