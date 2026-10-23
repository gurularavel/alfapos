{{--
    Hesabı bağlama ekranı (tam ekran, PIN pəncərəsi kimi). Store: resources/js/checkout.js
    Solda: ödəniləcək, əlavə olunmuş ödənişlər, qalan / qalıq. Sağda: məbləğ, rəqəm klaviaturası, əskinaslar, ödəniş növləri.
--}}
<div x-data x-cloak x-show="$store.checkout.open" x-transition.opacity.duration.150ms
     @click.stop {{-- altındakı pəncərələr (click.outside) bağlanmasın --}}
     class="no-print fixed inset-0 z-[70] flex flex-col bg-ink text-white [touch-action:manipulation] lg:flex-row" role="dialog" aria-modal="true" aria-label="Hesabı bağla">
    <section class="flex shrink-0 flex-col gap-4 bg-brand-700 p-5 sm:p-8 lg:w-[38%] lg:p-10">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-bold tracking-wide text-white/60 uppercase">Hesabı bağla</p>
                <p class="text-2xl font-black sm:text-3xl" x-text="$store.checkout.title"></p>
                <p class="text-sm text-white/70" x-text="$store.checkout.subtitle"></p>
            </div>
            <button type="button" class="rounded-2xl bg-white/10 px-4 py-3 font-bold active:bg-white/25" :disabled="$store.checkout.busy" @click="$store.checkout.close()">Bağla</button>
        </div>

        <div>
            <p class="text-white/70">Ödəniləcək</p>
            <p class="text-5xl font-black tabular-nums sm:text-6xl" x-text="money($store.checkout.due)"></p>
        </div>

        <div class="min-h-0 flex-1 space-y-2 overflow-y-auto">
            <template x-for="(l, i) in $store.checkout.lines" :key="i">
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/10 px-4 py-3">
                    <div>
                        <p class="text-lg font-bold" x-text="l.title"></p>
                        <p class="text-sm text-white/70" x-show="l.tendered" x-text="'Alınıb ' + money(l.tendered) + ' · qalıq ' + money(l.tendered - l.amount)"></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xl font-black tabular-nums" x-text="money(l.amount)"></span>
                        <button type="button" class="rounded-xl px-2 py-1 text-white/60 active:bg-white/20" :disabled="$store.checkout.busy" @click="$store.checkout.remove(i)" aria-label="Sil">✕</button>
                    </div>
                </div>
            </template>
            <p x-show="!$store.checkout.lines.length" class="rounded-2xl border-2 border-dashed border-white/20 px-4 py-5 text-white/70">
                Nağd: müştərinin verdiyi məbləğə toxunun ("Müştəri verir") — qaytarılacaq məbləğ hesablanır.
                Kart və digər növlər: növə toxunun. Bölmək üçün əvvəlcə məbləği yazın (məs. 10 → Nağd), qalanı başqa növlə.
            </p>
        </div>

        <div class="space-y-1 text-lg">
            <p class="flex justify-between" x-show="$store.checkout.remaining > 0.004"><span class="text-white/70">Qalan</span><span class="font-black tabular-nums text-amber-200" x-text="money($store.checkout.remaining)"></span></p>
            <div x-show="$store.checkout.change > 0" class="rounded-2xl bg-amber-300 px-4 py-3 text-ink">
                <p class="text-sm font-black tracking-wide uppercase">Qaytarılacaq</p>
                <p class="text-4xl font-black tabular-nums" x-text="money($store.checkout.change)"></p>
            </div>
            <p class="min-h-7 font-bold text-red-200" x-text="$store.checkout.error"></p>
        </div>
        <button type="button" class="rounded-2xl py-5 text-2xl font-black transition-colors" :class="$store.checkout.ready ? 'bg-white text-brand-700 active:bg-brand-50' : 'bg-white/15 text-white/40'"
                :disabled="!$store.checkout.ready || $store.checkout.busy" @click="$store.checkout.confirm()"
                x-text="$store.checkout.busy ? 'Gözləyin…' : 'Ödənişi təsdiqlə'"></button>
    </section>

    <section class="flex min-h-0 flex-1 flex-col gap-2 p-2 sm:gap-3 sm:p-4 lg:p-6">
        <div class="rounded-2xl bg-white/[0.07] px-5 py-3">
            <div class="flex items-center justify-between">
                <span class="text-white/60">Məbləğ</span>
                <span class="text-4xl font-black tabular-nums" x-text="$store.checkout.entry === '' ? money($store.checkout.remaining) : $store.checkout.entry + ' ₼'" :class="$store.checkout.entry === '' && 'text-white/40'"></span>
            </div>
            <p x-show="$store.checkout.preview > 0" class="mt-1 text-right text-lg font-bold text-amber-200">Nağd veriləndə qaytarılacaq: <span class="tabular-nums" x-text="money($store.checkout.preview)"></span></p>
        </div>
        {{-- Müştəri verir: bir toxunuşla nağd ödəniş və qalıq (hesab 22 → 25, 30, 50, 100 ...) --}}
        <div x-show="$store.checkout.suggestions.length" class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-bold text-white/60">Müştəri verir:</span>
            <button type="button" class="rounded-xl bg-white/15 px-4 py-3 text-lg font-bold active:bg-white/30" @click="$store.checkout.cashExact()">Dəqiq</button>
            <template x-for="n in $store.checkout.suggestions" :key="n">
                <button type="button" class="min-w-16 rounded-xl bg-white/[0.07] px-4 py-3 text-lg font-bold tabular-nums active:bg-white/20" @click="$store.checkout.cashGiven(n)" x-text="n + ' ₼'"></button>
            </template>
        </div>
        <div class="grid min-h-0 flex-1 grid-cols-3 grid-rows-4 gap-2 sm:gap-3">
            <template x-for="d in ['1','2','3','4','5','6','7','8','9']">
                <button type="button" class="rounded-2xl bg-white/[0.07] text-[clamp(1.8rem,5.5vh,3.5rem)] font-bold tabular-nums select-none active:bg-brand-500" @click="$store.checkout.press(d)" x-text="d"></button>
            </template>
            <button type="button" class="rounded-2xl bg-white/[0.07] text-[clamp(1.8rem,5.5vh,3.5rem)] font-bold select-none active:bg-brand-500" @click="$store.checkout.press('.')">.</button>
            <button type="button" class="rounded-2xl bg-white/[0.07] text-[clamp(1.8rem,5.5vh,3.5rem)] font-bold select-none active:bg-brand-500" @click="$store.checkout.press('0')">0</button>
            <button type="button" class="rounded-2xl text-lg font-bold text-white/70 select-none active:bg-white/10" @click="$store.checkout.entry ? $store.checkout.back() : null" @dblclick="$store.checkout.entry = ''">⌫ Sil</button>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <template x-for="t in $store.checkout.types" :key="t.id">
                <button type="button" class="rounded-2xl bg-brand-500 py-5 text-xl font-black select-none active:bg-brand-700 disabled:opacity-40" :disabled="$store.checkout.busy || $store.checkout.remaining <= 0.004"
                        @click="$store.checkout.add(t)" x-text="t.title"></button>
            </template>
        </div>
    </section>

    {{-- Təsdiqdən sonra: qaytarılacaq məbləğ iri (kassir pulu sayarkən) --}}
    <div x-show="$store.checkout.done" x-transition.opacity class="absolute inset-0 flex flex-col items-center justify-center gap-6 bg-ink p-6 text-center">
        <p class="text-2xl font-bold text-brand-300">✓ Ödəniş qəbul edildi</p>
        <div>
            <p class="text-xl font-black tracking-wide text-amber-200 uppercase">Qaytarılacaq</p>
            <p class="text-[clamp(4rem,16vh,9rem)] leading-none font-black tabular-nums" x-text="money($store.checkout.done?.change)"></p>
        </div>
        <p class="text-xl text-white/70">Müştəri verdi <b class="text-white" x-text="money($store.checkout.done?.tendered)"></b> · Hesab <b class="text-white" x-text="money($store.checkout.done?.due)"></b></p>
        <button type="button" class="min-w-64 rounded-2xl bg-white px-10 py-5 text-2xl font-black text-brand-700 active:bg-brand-50" @click="$store.checkout.finish()">Hazır</button>
    </div>
</div>
