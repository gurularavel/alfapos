<x-layouts.app title="Sifarişlər">
<div x-data="{
    ...poller(@js(route('orders.board')), 8000),
    {{-- Hesabı bağlama: tam ekran, bölünmüş ödəniş (checkout.js) --}}
    pay(g) {
        $store.checkout.show({
            title: g.title, subtitle: g.orders.length + ' sifariş', due: g.due, types: @js($paymentTypes),
            submit: async (payments) => {
                const r = await api(@js(route('orders.pay')), { method: 'POST', body: { order_ids: g.unpaid_ids, payments } });
                toast(r.message);
                $store.terminal.afterOrder();
                if (@js($autoPrintReceipt)) printUrl(@js(route('invoices.receipt', '__ID__')).replace('__ID__', r.invoice_id));
                await this.load();
            },
        });
    },
}">
    <x-page-header title="Sifarişlər" subtitle="Aktiv və ödənilməmiş sifarişlər.">
        <x-live-badge />
        <template x-if="$store.terminal.active && $store.terminal.operator"><span class="badge-green py-1.5 text-sm" x-text="$store.terminal.operator.name + ' · ' + $store.terminal.operator.role"></span></template>
        <a href="{{ route('pos') }}" class="btn-secondary"><x-icon name="pos" class="size-4" /> POS</a>
        <button class="btn-secondary" @click="load()"><x-icon name="refresh" class="size-4" /> Yenilə</button>
    </x-page-header>

    <div x-show="loading" class="py-16 text-center text-zinc-500">Yüklənir…</div>
    <div x-show="!loading && !rows.length" class="card"><x-empty icon="orders" title="Aktiv sifariş yoxdur">Yeni sifarişlər POS-dan və ya QR menyudan buraya düşür.</x-empty></div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <template x-for="g in rows" :key="g.key">
            <div class="card flex flex-col">
                <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="table" /></div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-extrabold" x-text="g.title"></p>
                        <p class="text-xs text-zinc-500" x-text="g.floor || (g.orders.length + ' sifariş')"></p>
                    </div>
                    <p class="text-right" x-show="g.due > 0"><span class="block text-[11px] text-zinc-500">Ödəniləcək</span><span class="font-black" x-text="money(g.due)"></span></p>
                </div>

                <div class="flex-1 space-y-3 p-3">
                    <template x-for="o in g.orders" :key="o.id">
                        <div class="rounded-xl bg-zinc-50 p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-ink text-xs font-black text-white" x-text="o.token_no"></span>
                                    <div class="text-xs leading-tight">
                                        <p class="font-bold"><span x-text="o.delivery_type + (o.source === 'qr' ? ' · QR' : '')"></span> <span x-show="o.is_addition" class="badge-amber">Əlavə</span></p>
                                        <p class="text-zinc-500" x-text="o.time + (o.waiter ? ' · ' + o.waiter : '') + (o.customer ? ' · ' + o.customer : '')"></p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span class="badge" :class="{ 'badge-blue': o.status === 'created', 'badge-amber': o.status === 'preparing', 'badge-green': o.status === 'ready', 'badge-gray': o.status === 'completed' }" x-text="o.status_label"></span>
                                    <span class="badge" :class="o.payment_status === 'paid' ? 'badge-green' : 'badge-red'" x-text="o.payment_status === 'paid' ? 'Ödənilib' : 'Ödənilməyib'"></span>
                                </div>
                            </div>
                            <ul class="space-y-1 text-sm">
                                <template x-for="i in o.items">
                                    <li class="flex items-start justify-between gap-2" :class="i.status === 'cancelled' && 'line-through text-zinc-400'">
                                        <span>
                                            <span class="font-semibold" x-text="i.qty + ' × ' + i.title"></span>
                                            <span class="text-zinc-500" x-show="i.variant" x-text="'(' + i.variant + ')'"></span>
                                            <span class="block text-xs text-zinc-500" x-show="i.addons" x-text="'+ ' + i.addons"></span>
                                            <span class="block text-xs text-amber-700" x-show="i.notes" x-text="i.notes"></span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1">
                                            <x-icon name="check" class="mt-0.5 size-4 text-brand-500" x-show="['ready','completed'].includes(i.status)" />
                                            <button type="button" x-show="i.status !== 'cancelled'" class="rounded px-1.5 text-xs font-bold text-red-600 no-underline hover:bg-red-50"
                                                    @click="$store.returns.show({ item: { id: i.id, title: i.title, qty: i.qty, price: i.price, status: i.status }, order: { id: o.id, token_no: o.token_no, paid: o.payment_status === 'paid' }, onDone: () => load() })"
                                                    x-text="o.payment_status === 'paid' ? 'Qaytar' : 'Ləğv'"></button>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                            <p class="mt-2 text-xs text-amber-700" x-show="o.notes" x-text="'Qeyd: ' + o.notes"></p>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <button class="btn-secondary btn-sm" @click="printUrl(@js(route('orders.token', '__ID__')).replace('__ID__', o.id))"><x-icon name="printer" class="size-3.5" /> Token</button>
                                <button class="btn-secondary btn-sm" x-show="o.invoice_id" @click="printUrl(@js(route('invoices.receipt', '__ID__')).replace('__ID__', o.invoice_id))"><x-icon name="invoice" class="size-3.5" /> Qəbz</button>
                                <button class="btn-secondary btn-sm" x-show="o.status !== 'completed'" @click="post(@js(route('orders.status', '__ID__')).replace('__ID__', o.id), { status: 'completed' })"><x-icon name="check" class="size-3.5" /> Təhvil verildi</button>
                                <button class="btn-ghost btn-sm text-red-600" x-show="o.payment_status !== 'paid'" @click="$store.returns.showOrder({ order: { id: o.id, token_no: o.token_no, paid: false }, onDone: () => load() })">Sifarişi ləğv et</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex items-center gap-2 border-t border-zinc-100 px-3 py-2" x-show="g.is_table && g.unpaid_ids.length">
                    <span class="text-sm text-zinc-500">Köçür:</span>
                    <select class="input py-1.5 text-sm" @change="const s = $event.target, to = s.value, name = s.selectedOptions[0]?.text; s.value = ''; if (to && confirm(g.title + ' → ' + name + ' köçürülsün? Dolu masadırsa hesablar birləşəcək.')) post(@js(route('orders.transfer')), { from_table_id: Number(g.key.slice(1)), to_table_id: Number(to) }).then(r => r && @js($autoPrintKot) && printUrl(r.slip_url))">
                        <option value="">Masa seçin…</option>
                        @foreach ($tables as $t)
                            <option value="{{ $t->id }}" :disabled="g.key === 't{{ $t->id }}'">{{ $t->title }}{{ $t->floor ? ' · '.$t->floor : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center justify-between border-t border-amber-100 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-show="g.deposit > 0 && g.unpaid_ids.length">
                    <span x-text="'Depozit (min.) ' + money(g.deposit) + ' · sifariş ' + money(g.orders_total)"></span>
                    <span class="font-bold" x-text="g.deposit_topup > 0 ? '+' + money(g.deposit_topup) : 'qarşılanıb'"></span>
                </div>
                {{-- Hesab çeki: müştəriyə aparmaq üçün (ödəniş icazəsi olmayan ofisiant / barmen də çap edə bilər) --}}
                <div class="border-t border-zinc-100 px-3 pt-3" x-show="g.unpaid_ids.length">
                    <button class="btn-secondary w-full" @click="printUrl(@js(route('orders.bill')) + '?ids=' + g.unpaid_ids.join(','))"><x-icon name="printer" class="size-4" /> Hesab çeki · <span x-text="money(g.due)"></span></button>
                </div>
                {{-- Onlayn ödəniş (Kapital Bank): müştəri masadakı ödəniş QR-ı ilə ödəyir --}}
                <div class="border-t border-sky-100 bg-sky-50 px-4 py-2 text-sm text-sky-900" x-show="g.online">
                    <p class="flex justify-between gap-2 font-bold"><span><x-icon name="online" class="inline size-4" /> Onlayn ödəniş gözlənilir</span><span x-text="money(g.online?.amount)"></span></p>
                    <p class="text-xs text-sky-800" x-text="g.online?.opened ? 'Müştəri bank səhifəsini açıb · ' + g.online?.bank_status : 'QR hələ skan edilməyib'"></p>
                    <div class="mt-1.5 flex gap-2">
                        <button class="btn-secondary btn-sm" @click="api(@js(route('orders.online.show', '__ID__')).replace('__ID__', g.online.id)).then(r => toast(r.message, r.payment.note ? 'error' : 'success')).catch(e => toast(e.message, 'error')).finally(() => load())"><x-icon name="refresh" class="size-3.5" /> Yoxla</button>
                        <button class="btn-ghost btn-sm text-red-600" x-show="$store.terminal.canPay" @click="confirm('Onlayn ödəniş sorğusu ləğv edilsin?') && post(@js(route('orders.online.cancel', '__ID__')).replace('__ID__', g.online.id), {})">Ləğv et</button>
                    </div>
                </div>
                <div class="grid gap-2 border-t border-zinc-100 p-3" x-show="g.unpaid_ids.length && $store.terminal.canPay">
                    @if ($onlinePayments)
                        <button class="btn-secondary w-full border-sky-300 text-sky-800" x-show="g.is_table" @click="post(@js(route('orders.online.store')), { store_table_id: Number(g.key.slice(1)) })"><x-icon name="online" class="size-4" /> <span x-text="g.online ? 'Onlayn ödənişə yenidən göndər' : 'Onlayn ödənişə göndər (QR)'"></span></button>
                    @endif
                    <button class="btn-primary w-full" @click="pay(g)"><x-icon name="cash" class="size-4" /> Hesabı bağla · <span x-text="money(g.due)"></span></button>
                </div>
            </div>
        </template>
    </div>

</div>
<x-checkout-screen />
<x-terminal-lock :state="$terminal" />
</x-layouts.app>
