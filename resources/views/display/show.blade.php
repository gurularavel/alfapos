<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $tenant->name }} — {{ $display->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-dvh overflow-hidden bg-ink font-sans text-white antialiased select-none">
<div x-data="customerDisplay(@js($config))" class="relative h-full" @dblclick="document.documentElement.requestFullscreen?.().catch(() => {})">

    {{-- Boş vaxt: ekran qoruyucu --}}
    <div x-show="mode === 'idle'" class="absolute inset-0">
        <template x-for="(img, i) in images" :key="img">
            <img :src="img" alt="" class="absolute inset-0 size-full object-cover transition-opacity duration-1000" :class="slide === i ? 'opacity-100' : 'opacity-0'">
        </template>
        <div x-show="!images.length" class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 p-8 text-center">
            <template x-if="store.logo"><img :src="store.logo" alt="" class="mb-8 size-36 rounded-3xl bg-white object-cover shadow-2xl"></template>
            <p class="text-5xl font-black lg:text-7xl" x-text="store.name"></p>
            <p class="mt-4 text-2xl text-white/80">Xoş gəlmisiniz!</p>
        </div>
        <div class="absolute inset-x-0 bottom-0 flex items-end justify-between bg-gradient-to-t from-black/60 to-transparent p-8">
            <p class="text-6xl font-black tabular-nums" x-text="timeLabel"></p>
            @if ($tenant->is_qr_menu_enabled)
                <div class="flex items-center gap-4 rounded-2xl bg-white p-3 text-ink">
                    <img src="{{ (new \chillerlan\QRCode\QRCode)->render(route('qr.show', $tenant->qr_code)) }}" alt="QR menyu" class="size-24">
                    <p class="max-w-36 text-lg leading-tight font-bold">Menyu və rəy üçün skan edin</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Səbət --}}
    <div x-show="mode === 'cart'" x-cloak class="absolute inset-0 flex flex-col bg-canvas text-ink lg:flex-row">
        <section class="flex min-h-0 flex-1 flex-col p-6 lg:p-10">
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <template x-if="store.logo"><img :src="store.logo" alt="" class="size-14 rounded-2xl object-cover"></template>
                    <p class="text-3xl font-black" x-text="store.name"></p>
                </div>
                <p class="text-xl font-bold text-zinc-500" x-show="state.customer" x-text="'Hörmətli ' + state.customer"></p>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto rounded-3xl bg-white" x-ref="list" x-effect="state.items && $nextTick(() => $refs.list.scrollTop = $refs.list.scrollHeight)">
                <template x-for="(item, i) in state.items" :key="i">
                    <div class="flex items-start justify-between gap-6 border-b border-zinc-100 px-6 py-4 last:border-0" :class="i === state.items.length - 1 && 'bg-brand-50'">
                        <div class="min-w-0">
                            <p class="text-2xl font-bold"><span class="text-brand-600" x-text="item.qty + ' ×'"></span> <span x-text="item.title"></span></p>
                            <p class="text-lg text-zinc-500" x-show="item.subtitle" x-text="item.subtitle"></p>
                        </div>
                        <p class="shrink-0 text-2xl font-black tabular-nums" x-text="money(item.total)"></p>
                    </div>
                </template>
            </div>
        </section>
        <aside class="flex flex-col justify-end bg-ink p-6 text-white lg:w-[38%] lg:p-10">
            <dl class="space-y-3 text-2xl">
                <div class="flex justify-between text-white/70"><dt>Ara cəm</dt><dd class="tabular-nums" x-text="money(state.totals?.sub)"></dd></div>
                <div class="flex justify-between text-brand-300" x-show="state.totals?.discount > 0"><dt>Endirim</dt><dd class="tabular-nums" x-text="'−' + money(state.totals?.discount)"></dd></div>
                <div class="flex justify-between text-white/70"><dt>Vergi</dt><dd class="tabular-nums" x-text="money(state.totals?.tax)"></dd></div>
                <div class="flex justify-between text-white/70" x-show="state.totals?.service > 0"><dt>Servis haqqı</dt><dd class="tabular-nums" x-text="money(state.totals?.service)"></dd></div>
                <div class="flex justify-between text-amber-300" x-show="state.totals?.topup > 0"><dt x-text="'Depozitə qədər fərq (min. ' + money(state.totals?.deposit) + ')'"></dt><dd class="tabular-nums" x-text="money(state.totals?.topup)"></dd></div>
            </dl>
            <div class="mt-6 border-t border-white/20 pt-6">
                <p class="text-2xl text-white/70">Ödəniləcək</p>
                <p class="text-6xl font-black tabular-nums lg:text-7xl" x-text="money(state.totals?.total)"></p>
            </div>
        </aside>
    </div>

    {{-- Təşəkkür --}}
    <div x-show="mode === 'paid' || mode === 'sent'" x-cloak class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-brand-500 to-brand-800 p-8 text-center">
        <div class="mb-8 flex size-28 items-center justify-center rounded-full bg-white/20"><x-icon name="check" class="size-16" /></div>
        <p class="text-6xl font-black" x-text="mode === 'paid' ? 'Təşəkkür edirik!' : 'Sifarişiniz qəbul edildi'"></p>
        <p class="mt-4 text-3xl text-white/80" x-show="mode === 'paid'" x-text="'Ödənildi: ' + money(state.totals?.total)"></p>
        <template x-if="state.token_no">
            <div class="mt-10 rounded-3xl bg-white px-12 py-6 text-ink">
                <p class="text-2xl font-bold text-zinc-500">Sifariş nömrəniz</p>
                <p class="text-8xl font-black text-brand-600" x-text="'#' + state.token_no"></p>
            </div>
        </template>
    </div>

    <span class="absolute top-3 right-3 size-2.5 rounded-full" :class="connected ? 'bg-brand-400' : 'bg-amber-400'" :title="connected ? 'Canlı' : 'Ehtiyat rejim'"></span>
</div>
</body>
</html>
