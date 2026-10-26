{{-- POS terminalının kilid ekranı. Səhifədə $terminal (PosTerminal::state()) olmalıdır. --}}
@props(['state'])
@push('scripts')
    <script>window.TERMINAL_STATE = @js($state);</script>
@endpush

{{--
    İki hissə: solda (şaquli ekranda yuxarıda) restoranın fonu, sağda AlfaPOS və PIN paneli.
    Sensor ekran üçün: düymələr ekranın hündürlüyünə görə böyüyür, kölgə/çərçivə yoxdur,
    touch-manipulation — iki dəfə toxunuşda zoom olmur.
--}}
<div x-data x-cloak x-show="$store.terminal.active && $store.terminal.locked" x-transition.opacity.duration.300ms
     class="fixed inset-0 z-[70] flex flex-col overflow-hidden bg-ink text-white select-none [touch-action:manipulation] landscape:flex-row">

    {{-- Restoranın fonu (ekran qoruyucu şəkilləri) --}}
    <section class="relative h-[32vh] shrink-0 overflow-hidden landscape:h-auto landscape:flex-1">
        <template x-for="(img, i) in $store.terminal.images" :key="img">
            <img :src="img" alt="" class="absolute inset-0 size-full object-cover transition-opacity duration-1000" :class="$store.terminal.slide === i ? 'opacity-100' : 'opacity-0'">
        </template>
        <div x-show="!$store.terminal.images.length" class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 p-6 text-center">
            <template x-if="$store.terminal.store.logo"><img :src="$store.terminal.store.logo" alt="" class="mb-6 size-20 rounded-3xl bg-white object-cover landscape:size-28"></template>
            <p class="text-3xl font-black landscape:text-5xl" x-text="$store.terminal.store.name"></p>
        </div>
        <div class="absolute inset-x-0 bottom-0 flex items-end gap-4 bg-gradient-to-t from-black/75 to-transparent p-5 pt-16 landscape:p-10">
            <template x-if="$store.terminal.store.logo && $store.terminal.images.length"><img :src="$store.terminal.store.logo" alt="" class="size-14 shrink-0 rounded-2xl bg-white object-cover landscape:size-16"></template>
            <div class="min-w-0">
                <p class="text-4xl font-black tabular-nums landscape:text-7xl" x-text="$store.terminal.timeLabel"></p>
                <p class="mt-1 truncate text-base text-white/80 landscape:text-lg" x-text="($store.terminal.images.length ? $store.terminal.store.name + ' · ' : '') + $store.terminal.dateLabel"></p>
            </div>
        </div>
        <button type="button" x-show="$store.terminal.purpose !== 'exit'" class="absolute top-4 left-4 rounded-full bg-black/40 px-5 py-3 text-sm font-semibold text-white/85 backdrop-blur active:bg-black/60" @click="$store.terminal.openPin('exit')">Terminaldan çıx</button>
    </section>

    {{-- AlfaPOS + PIN paneli --}}
    <section class="flex min-h-0 flex-1 flex-col gap-[clamp(.5rem,2vh,1.5rem)] p-[clamp(.75rem,2.5vh,2rem)] landscape:w-[min(42%,34rem)] landscape:flex-none">
        <div class="text-center">
            <p class="text-[clamp(1.75rem,5vh,3rem)] leading-none font-black tracking-tight">Alfa<span class="text-brand-400">POS</span></p>
            <p class="mt-[clamp(.5rem,2.5vh,1.75rem)] text-[clamp(1.1rem,3vh,1.6rem)] font-bold" x-text="$store.terminal.purpose === 'exit' ? 'Terminaldan çıxış — admin PIN-i' : 'PIN kodunuzu daxil edin'"></p>
            <div class="mt-[clamp(.5rem,2vh,1.25rem)] flex justify-center gap-4" :class="$store.terminal.error && 'animate-[shake_.3s]'">
                <template x-for="n in 4">
                    <span class="size-[clamp(1rem,2.6vh,1.6rem)] rounded-full transition-colors" :class="$store.terminal.pin.length >= n ? 'bg-white' : 'bg-white/20'"></span>
                </template>
            </div>
            <p class="mt-2 min-h-[1.75rem] text-lg font-bold text-red-300" x-text="$store.terminal.error"></p>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-3 grid-rows-4 gap-[clamp(.4rem,1.2vh,.75rem)]">
            <template x-for="d in ['1','2','3','4','5','6','7','8','9']">
                <button type="button" class="rounded-2xl bg-white/[0.08] text-[clamp(1.75rem,5.5vh,3.5rem)] font-bold tabular-nums transition-colors active:bg-brand-500" @click="$store.terminal.press(d)" x-text="d"></button>
            </template>
            <button type="button" class="rounded-2xl text-[clamp(1rem,2.4vh,1.4rem)] font-bold text-white/60 transition-colors active:bg-white/10"
                    @click="$store.terminal.purpose === 'exit' ? $store.terminal.openPin('unlock') : ($store.terminal.pin = '', $store.terminal.error = '')"
                    x-text="$store.terminal.purpose === 'exit' ? 'Ləğv et' : 'Təmizlə'"></button>
            <button type="button" class="rounded-2xl bg-white/[0.08] text-[clamp(1.75rem,5.5vh,3.5rem)] font-bold transition-colors active:bg-brand-500" @click="$store.terminal.press('0')">0</button>
            <button type="button" class="flex items-center justify-center rounded-2xl text-white/70 transition-colors active:bg-white/10" @click="$store.terminal.back()" aria-label="Sil">
                <svg class="size-[clamp(1.75rem,5vh,3rem)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z"/><path d="m18 9-6 6M12 9l6 6"/></svg>
            </button>
        </div>

        {{-- Məhsulun versiyası və dəstək --}}
        <p class="text-center text-xs font-semibold tracking-wide text-white/45">
            v{{ config('app.version') }} · {{ site('company_name') }} · Dəstək: {{ site('phone') }}
        </p>
    </section>
</div>

{{-- Hərəkətsizlik xəbərdarlığı: kilidə 5 saniyə qalmış (seçim edilməsə vaxt bitəndə kilidlənir) --}}
<div x-data x-cloak x-show="$store.terminal.warning" x-transition.opacity.duration.150ms class="no-print fixed inset-0 z-[90] flex items-center justify-center bg-black/60 p-4" role="alertdialog" aria-modal="true" aria-labelledby="idle-title">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 text-center shadow-2xl sm:p-8">
        <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-amber-100 text-4xl font-black text-amber-700 tabular-nums" x-text="$store.terminal.idleLeft"></div>
        <p id="idle-title" class="mt-4 text-2xl font-black">Sistemdə qalırsınız?</p>
        <p class="mt-1 text-zinc-500"><span x-text="$store.terminal.operator?.name ?? ''"></span>, ekran <span class="font-bold" x-text="$store.terminal.idleLeft"></span> saniyə sonra kilidlənəcək.</p>
        <div class="mt-6 grid grid-cols-2 gap-3">
            <button type="button" class="btn-secondary py-4 text-lg" @click="$store.terminal.lock()"><x-icon name="logout" class="size-5" /> Blokla</button>
            <button type="button" class="btn-primary py-4 text-lg" @click="$store.terminal.stay()" x-init="$watch('$store.terminal.warning', (v) => v && $nextTick(() => $el.focus()))">Qalıram</button>
        </div>
    </div>
</div>
