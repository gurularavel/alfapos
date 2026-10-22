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
        {{-- Məhsulun versiyası və dəstək --}}
        <p class="pointer-events-none absolute inset-x-0 bottom-2 text-center text-xs font-semibold tracking-wide text-white/50">
            AlfaPOS v{{ config('app.version') }} · {{ site('company_name') }} · Dəstək: {{ site('phone') }}
        </p>
    </div>

    {{--
        PIN paneli — tam ekran, flat. Üfüqi ekranda solda başlıq/nöqtələr, sağda klaviatura bütün hündürlüyü tutur;
        şaquli ekranda üst-üstə. Düymələr ekranın ölçüsünə görə böyüyür (sensor ekran üçün), kölgə/çərçivə yoxdur.
        touch-manipulation: iki dəfə toxunuşda zoom olmur.
    --}}
    <div x-show="$store.terminal.mode === 'pin'" class="absolute inset-0 flex flex-col bg-ink [touch-action:manipulation] lg:flex-row">
        <section class="flex shrink-0 flex-col justify-between gap-6 bg-brand-700 p-6 sm:p-10 lg:w-[38%] lg:p-14">
            <div class="flex items-center gap-4">
                <template x-if="$store.terminal.store.logo"><img :src="$store.terminal.store.logo" alt="" class="size-14 rounded-xl bg-white object-cover"></template>
                <div>
                    <p class="text-xl font-black" x-text="$store.terminal.store.name"></p>
                    <p class="text-sm font-semibold text-white/60 tabular-nums" x-text="$store.terminal.timeLabel + ' · ' + $store.terminal.dateLabel"></p>
                </div>
            </div>

            <div>
                <p class="text-3xl leading-tight font-black sm:text-4xl lg:text-5xl" x-text="$store.terminal.purpose === 'exit' ? 'Terminaldan çıxış' : 'PIN kodunuzu daxil edin'"></p>
                <p class="mt-2 text-lg text-white/70" x-text="$store.terminal.purpose === 'exit' ? 'Admin PIN-i tələb olunur' : 'Sifarişlər sizin adınıza yazılacaq'"></p>
                <div class="mt-8 flex gap-4" :class="$store.terminal.error && 'animate-[shake_.3s]'">
                    <template x-for="n in 4">
                        <span class="size-7 rounded-full transition-colors sm:size-9" :class="$store.terminal.pin.length >= n ? 'bg-white' : 'bg-white/20'"></span>
                    </template>
                </div>
                <p class="mt-5 min-h-7 text-xl font-bold text-red-200" x-text="$store.terminal.error"></p>
            </div>

            <button type="button" class="flex items-center gap-3 self-start rounded-2xl bg-white/10 px-6 py-4 text-lg font-bold transition-colors select-none active:bg-white/25" @click="$store.terminal.mode = 'saver'">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Geri
            </button>
        </section>

        <section class="grid min-h-0 flex-1 grid-cols-3 grid-rows-4 gap-2 p-2 sm:gap-3 sm:p-4 lg:p-6">
            <template x-for="d in ['1','2','3','4','5','6','7','8','9']">
                <button type="button" class="rounded-2xl bg-white/[0.07] text-[clamp(2.5rem,7vh,4.5rem)] font-bold tabular-nums transition-colors select-none active:bg-brand-500" @click="$store.terminal.press(d)" x-text="d"></button>
            </template>
            <button type="button" class="rounded-2xl text-[clamp(1.1rem,2.6vh,1.6rem)] font-bold text-white/60 transition-colors select-none active:bg-white/10" @click="$store.terminal.pin = ''; $store.terminal.error = ''">Təmizlə</button>
            <button type="button" class="rounded-2xl bg-white/[0.07] text-[clamp(2.5rem,7vh,4.5rem)] font-bold transition-colors select-none active:bg-brand-500" @click="$store.terminal.press('0')">0</button>
            <button type="button" class="flex items-center justify-center rounded-2xl text-white/70 transition-colors select-none active:bg-white/10" @click="$store.terminal.back()" aria-label="Sil">
                <svg class="size-[clamp(2.25rem,6vh,3.5rem)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z"/><path d="m18 9-6 6M12 9l6 6"/></svg>
            </button>
        </section>
    </div>

    <button type="button" x-show="$store.terminal.mode === 'saver'" class="absolute top-4 right-4 rounded-full bg-black/40 px-5 py-3 text-sm font-semibold text-white/80 backdrop-blur active:bg-black/60" @click.stop="$store.terminal.openPin('exit')">Terminaldan çıx</button>
</div>
