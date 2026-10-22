{{-- Menecer təsdiqi (PIN) və ləğv / qaytarma pəncərəsi. Layout-a bir dəfə qoşulur; store-lar resources/js/returns.js-dədir. --}}
@php($canReturn = auth()->user()?->hasScope('ORDERS') || auth()->user()?->hasScope('POS') || auth()->user()?->hasScope('INVOICES'))
@if ($canReturn)
    @push('scripts')
        <script>window.RETURN_ROUTES = @js(['item' => route('orders.items.return', '__ID__'), 'cancel' => route('orders.cancel', '__ID__')]);</script>
    @endpush

    <div x-data x-cloak x-show="$store.returns?.open" class="no-print fixed inset-0 z-[75] flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-6" @click.self="$store.returns.open = false">
        <template x-if="$store.returns?.open">
            <div class="w-full max-w-lg overflow-hidden rounded-t-3xl bg-white sm:rounded-3xl" x-data="{ r: $store.returns }">
                <div class="flex items-start justify-between border-b border-zinc-100 px-6 py-4">
                    <div>
                        <p class="text-xl font-black" x-text="r.mode === 'order' ? 'Sifarişi ləğv et' : (r.order.paid ? 'Qaytarma (pul geri)' : 'Məhsulu ləğv et')"></p>
                        <p class="text-sm text-zinc-500" x-text="'Token #' + r.order.token_no + (r.item ? ' · ' + r.item.title : '')"></p>
                    </div>
                    <button type="button" class="btn-ghost" @click="r.open = false"><x-icon name="x" class="size-5" /></button>
                </div>
                <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-4">
                    <template x-if="r.mode === 'item'">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="label mb-0">Miqdar</span>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn-secondary size-11 p-0 text-xl" @click="r.quantity = Math.max(1, r.quantity - 1)">−</button>
                                    <span class="w-10 text-center text-2xl font-black" x-text="r.quantity"></span>
                                    <button type="button" class="btn-secondary size-11 p-0 text-xl" @click="r.quantity = Math.min(r.item.qty, r.quantity + 1)">+</button>
                                    <span class="text-sm text-zinc-500" x-text="'/ ' + r.item.qty"></span>
                                </div>
                            </div>
                            <div>
                                <p class="label">Stok</p>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach (\App\Models\ItemReturn::STOCK_ACTIONS as $k => $v)
                                        <button type="button" class="rounded-xl border px-3 py-2.5 text-sm font-bold" :class="r.stockAction === '{{ $k }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-zinc-200'" @click="r.stockAction = '{{ $k }}'">{{ $v }}</button>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-zinc-500" x-text="r.stockAction === 'restore' ? 'Məhsul / xammal anbara geri qayıdır (mətbəx hələ başlamayıbsa).' : 'Hazırlanmış yemək israf sayılır, mayası itki kimi yazılır.'"></p>
                            </div>
                            <template x-if="r.order.paid">
                                <div class="rounded-xl bg-amber-50 p-3">
                                    <label class="label text-amber-900">Müştəriyə qaytarılan məbləğ</label>
                                    <input type="number" step="0.01" min="0.01" x-model="r.refundAmount" class="input" :placeholder="'Avtomatik: çekdəki payı (' + money(r.amount) + ' + vergi/servis)'">
                                    <p class="mt-1 text-xs text-amber-800">Boş qalsa, məhsulun çekdəki payı (endirim, vergi, servis daxil) qaytarılır.</p>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div>
                        <p class="label">Səbəb *</p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (\App\Models\ItemReturn::REASONS as $k => $v)
                                <button type="button" class="rounded-xl border px-3 py-2 text-left text-sm font-semibold" :class="r.reason === '{{ $k }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-zinc-200'" @click="r.reason = '{{ $k }}'">{{ $v }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div><label class="label">Qeyd <span x-show="r.reason === 'other'">*</span></label><input x-model="r.note" maxlength="255" class="input" placeholder="Məs. müştəri duzlu olduğunu dedi"></div>
                    <p class="rounded-xl bg-zinc-50 p-3 text-xs text-zinc-600"><x-icon name="alert" class="mr-1 inline size-3.5" /> Menecer təsdiqi tələb olunur. Əməliyyat kimin etdiyi və təsdiqlədiyi ilə qeydə alınır və hesabatda görünür.</p>
                </div>
                <div class="flex gap-3 border-t border-zinc-100 p-4">
                    <button type="button" class="btn-secondary flex-1 py-3" @click="r.open = false">Bağla</button>
                    <button type="button" class="btn-danger flex-1 py-3" :disabled="r.busy" @click="r.submit()"
                            x-text="r.mode === 'order' ? 'Sifarişi ləğv et' : (r.order.paid ? 'Qaytar · ' + money(r.refundAmount || r.amount) : 'Ləğv et · ' + money(r.amount))"></button>
                </div>
            </div>
        </template>
    </div>
@endif

{{-- Menecer PIN-i --}}
<div x-data x-cloak x-show="$store.approval.open" class="no-print fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4" @keydown.window="if ($store.approval.open) { if (/^\d$/.test($event.key)) $store.approval.press($event.key); if ($event.key === 'Backspace') $store.approval.back(); if ($event.key === 'Escape') $store.approval.finish(null) }">
    <div class="w-full max-w-xs rounded-3xl bg-white p-6 text-center">
        <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-amber-100 text-amber-700"><x-icon name="alert" class="size-6" /></div>
        <p class="text-lg font-black" x-text="$store.approval.title"></p>
        <p class="text-sm text-zinc-500">Menecer PIN-ini daxil etsin</p>
        <p class="mt-2 min-h-5 text-sm font-semibold text-red-600" x-text="$store.approval.message"></p>
        <div class="my-4 flex justify-center gap-3">
            <template x-for="i in 4"><span class="size-4 rounded-full" :class="$store.approval.pin.length >= i ? 'bg-ink' : 'bg-zinc-200'"></span></template>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <template x-for="d in ['1','2','3','4','5','6','7','8','9']"><button type="button" class="rounded-2xl bg-zinc-100 py-4 text-2xl font-black active:bg-zinc-200" @click="$store.approval.press(d)" x-text="d"></button></template>
            <button type="button" class="rounded-2xl py-4 text-sm font-bold text-zinc-500" @click="$store.approval.finish(null)">Ləğv</button>
            <button type="button" class="rounded-2xl bg-zinc-100 py-4 text-2xl font-black active:bg-zinc-200" @click="$store.approval.press('0')">0</button>
            <button type="button" class="rounded-2xl py-4 text-xl font-bold text-zinc-500" @click="$store.approval.back()">⌫</button>
        </div>
    </div>
</div>
