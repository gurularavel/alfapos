<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tenant->name }} — Menyu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $menu = $items->map(fn ($m) => [
        'id' => $m->id, 'title' => $m->title, 'description' => $m->description, 'price' => (float) $m->price,
        'image' => $m->imageUrl(), 'category_id' => $m->category_id,
        'variants' => $m->variants->map(fn ($v) => ['id' => $v->id, 'title' => $v->title, 'price' => (float) $v->price]),
        'addons' => $m->addons->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'price' => (float) $a->price]),
    ]);
@endphp
<body class="bg-canvas font-sans text-ink antialiased">
<div class="mx-auto min-h-screen max-w-xl bg-white pb-28"
     x-data="{
        items: @js($menu), category: null, cart: [], picker: null, sheet: false, notes: '', done: null, busy: false,
        get filtered() { return this.items.filter(i => !this.category || i.category_id === this.category) },
        get total() { return this.cart.reduce((s, l) => s + l.price * l.quantity, 0) },
        get count() { return this.cart.reduce((s, l) => s + l.quantity, 0) },
        open(item) { this.picker = { item, variantId: item.variants[0]?.id ?? null, addonIds: [], quantity: 1 } },
        add() {
            const { item, variantId, addonIds, quantity } = this.picker;
            const v = item.variants.find(x => x.id === variantId);
            const adds = item.addons.filter(a => addonIds.includes(a.id));
            this.cart.push({ menu_item_id: item.id, variant_id: variantId, addon_ids: addonIds, quantity, title: item.title,
                sub: [v?.title, ...adds.map(a => '+ ' + a.title)].filter(Boolean).join(', '),
                price: (v ? v.price : item.price) + adds.reduce((s, a) => s + a.price, 0) });
            this.picker = null;
        },
        async send() {
            this.busy = true;
            try {
                const r = await api(@js(route('qr.order', $tenant->qr_code)), { method: 'POST', body: {
                    t: @js(request('t')), notes: this.notes,
                    cart: this.cart.map(({ menu_item_id, variant_id, addon_ids, quantity }) => ({ menu_item_id, variant_id, addon_ids, quantity })) } });
                this.done = r; this.cart = []; this.sheet = false; this.notes = '';
            } catch (e) { toast(e.message, 'error') } finally { this.busy = false }
        },
     }" x-init="window.APP_CURRENCY = @js($tenant->currency)">

    <header class="bg-brand-500 px-5 pt-8 pb-6 text-white">
        <div class="flex items-center gap-3">
            @if ($tenant->logo)<img src="{{ Storage::disk('public')->url($tenant->logo) }}" alt="" class="size-14 rounded-2xl bg-white object-cover">@endif
            <div>
                <h1 class="text-2xl font-black">{{ $tenant->name }}</h1>
                <p class="text-sm text-white/80">{{ $tenant->address }}</p>
            </div>
        </div>
        @if ($table)<p class="mt-4 inline-flex rounded-full bg-white/20 px-3 py-1 text-sm font-bold">{{ $table->label() }}</p>@endif
    </header>

    <nav class="sticky top-0 z-10 flex gap-2 overflow-x-auto border-b border-zinc-100 bg-white px-4 py-3">
        <button class="chip" :class="!category && 'active'" @click="category = null">Hamısı</button>
        @foreach ($categories as $c)
            <button class="chip" :class="category === {{ $c->id }} && 'active'" @click="category = {{ $c->id }}">{{ $c->title }}</button>
        @endforeach
    </nav>

    <div class="divide-y divide-zinc-100">
        <template x-for="item in filtered" :key="item.id">
            <div class="flex gap-3 px-4 py-4">
                <div class="min-w-0 flex-1">
                    <p class="font-extrabold" x-text="item.title"></p>
                    <p class="mt-0.5 line-clamp-2 text-sm text-zinc-500" x-text="item.description"></p>
                    <p class="mt-1.5 font-black text-brand-600" x-text="money(item.price)"></p>
                </div>
                <div class="relative shrink-0">
                    <template x-if="item.image"><img :src="item.image" :alt="item.title" class="size-24 rounded-xl object-cover" loading="lazy"></template>
                    <template x-if="!item.image"><div class="flex size-24 items-center justify-center rounded-xl bg-brand-50 text-3xl font-black text-brand-300" x-text="item.title.slice(0, 1)"></div></template>
                    @if ($canOrder)
                        <button class="absolute -right-1 -bottom-1 flex size-9 items-center justify-center rounded-full bg-brand-500 text-white shadow" @click="open(item)" aria-label="Əlavə et"><x-icon name="plus" class="size-5" /></button>
                    @endif
                </div>
            </div>
        </template>
    </div>

    @if ($tenant->is_feedback_enabled)
        <section id="feedback" class="m-4 rounded-2xl bg-zinc-50 p-5" x-data="{ rating: 5 }">
            <h2 class="text-lg font-black">Rəyinizi bildirin</h2>
            @if (session('success'))<p class="mt-2 rounded-xl bg-brand-100 px-3 py-2 text-sm font-semibold text-brand-800">{{ session('success') }}</p>@endif
            <form method="POST" action="{{ route('qr.feedback', $tenant->qr_code) }}" class="mt-3 space-y-3">
                @csrf
                <div class="flex gap-1 text-3xl">
                    <template x-for="n in 5"><button type="button" @click="rating = n" :class="n <= rating ? 'text-amber-400' : 'text-zinc-300'">★</button></template>
                    <input type="hidden" name="rating" :value="rating">
                </div>
                <div class="grid grid-cols-2 gap-2"><input name="name" class="input" placeholder="Adınız"><input name="phone" class="input" placeholder="Telefon"></div>
                <textarea name="comment" rows="3" class="input" placeholder="Şərhiniz"></textarea>
                <button class="btn-primary w-full">Göndər</button>
            </form>
        </section>
    @endif

    @if ($canOrder)
        {{-- Səbət düyməsi --}}
        <div x-show="count" x-cloak class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-xl p-4">
            <button class="btn-primary w-full justify-between py-4 text-base shadow-lg" @click="sheet = true">
                <span x-text="count + ' məhsul'"></span><span>Səbət · <span x-text="money(total)"></span></span>
            </button>
        </div>

        {{-- Variant seçimi --}}
        <x-modal show="picker" close="picker = null" title="<span x-text='picker?.item.title'></span>">
            <template x-if="picker">
                <div class="space-y-4">
                    <div x-show="picker.item.variants.length" class="space-y-1.5">
                        <template x-for="v in picker.item.variants" :key="v.id">
                            <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <span class="flex items-center gap-2"><input type="radio" :value="v.id" x-model.number="picker.variantId" class="text-brand-500"> <span x-text="v.title"></span></span><span x-text="money(v.price)"></span>
                            </label>
                        </template>
                    </div>
                    <div x-show="picker.item.addons.length" class="space-y-1.5">
                        <p class="label">Əlavələr</p>
                        <template x-for="a in picker.item.addons" :key="a.id">
                            <label class="flex items-center justify-between rounded-xl border border-zinc-200 px-3 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <span class="flex items-center gap-2"><input type="checkbox" :value="a.id" x-model.number="picker.addonIds" class="rounded text-brand-500"> <span x-text="a.title"></span></span><span x-text="'+' + money(a.price)"></span>
                            </label>
                        </template>
                    </div>
                    <div class="flex items-center justify-center gap-4">
                        <button class="btn-secondary" @click="picker.quantity > 1 && picker.quantity--"><x-icon name="minus" class="size-4" /></button>
                        <span class="text-xl font-black" x-text="picker.quantity"></span>
                        <button class="btn-secondary" @click="picker.quantity++"><x-icon name="plus" class="size-4" /></button>
                    </div>
                    <button class="btn-primary w-full py-3" @click="add()">Səbətə əlavə et</button>
                </div>
            </template>
        </x-modal>

        {{-- Səbət --}}
        <x-modal show="sheet" close="sheet = false" title="Sifarişiniz">
            <template x-for="(l, i) in cart" :key="i">
                <div class="flex items-start justify-between gap-3 border-b border-zinc-100 py-2.5">
                    <div><p class="font-bold" x-text="l.quantity + ' × ' + l.title"></p><p class="text-xs text-zinc-500" x-text="l.sub"></p></div>
                    <div class="flex items-center gap-2"><span class="font-bold" x-text="money(l.price * l.quantity)"></span><button class="text-red-500" @click="cart.splice(i, 1)"><x-icon name="trash" class="size-4" /></button></div>
                </div>
            </template>
            <textarea x-model="notes" rows="2" class="input mt-3" placeholder="Qeyd (məs. allergiya)"></textarea>
            <p class="mt-3 flex justify-between text-lg font-black"><span>Cəm</span><span x-text="money(total)"></span></p>
            <x-slot:footer><button class="btn-primary w-full py-3" :disabled="busy || !cart.length" @click="send()">Sifarişi göndər</button></x-slot:footer>
        </x-modal>

        <x-modal show="done" close="done = null" width="max-w-sm">
            <div class="py-6 text-center">
                <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-brand-100 text-brand-600"><x-icon name="check" class="size-7" /></div>
                <p class="text-lg font-black" x-text="done?.message"></p>
                <p class="mt-2 text-sm text-zinc-500">Sifariş nömrəniz</p>
                <p class="text-5xl font-black text-brand-600" x-text="'#' + done?.token_no"></p>
            </div>
        </x-modal>
    @endif

    <div x-data="{ items: [] }" @toast.window="const t = { id: Date.now(), ...$event.detail }; items.push(t); setTimeout(() => items = items.filter(i => i.id !== t.id), 3500)" class="fixed inset-x-4 top-4 z-[60] flex flex-col gap-2">
        <template x-for="t in items" :key="t.id"><div class="rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg" :class="t.type === 'error' ? 'bg-red-600' : 'bg-ink'" x-text="t.message"></div></template>
    </div>
</div>
</body>
</html>
