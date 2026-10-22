<x-layouts.app title="Sifarişlər">
<div x-data="{ ...poller(@js(route('orders.board')), 8000), pay: null, paymentTypeId: @js($paymentTypes->first()?->id) }">
    <x-page-header title="Sifarişlər" subtitle="Aktiv və ödənilməmiş sifarişlər. Hər 8 saniyədən bir yenilənir.">
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
                                        <p class="font-bold" x-text="o.delivery_type + (o.source === 'qr' ? ' · QR' : '')"></p>
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
                                        <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-500" x-show="['ready','completed'].includes(i.status)" />
                                    </li>
                                </template>
                            </ul>
                            <p class="mt-2 text-xs text-amber-700" x-show="o.notes" x-text="'Qeyd: ' + o.notes"></p>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <button class="btn-secondary btn-sm" @click="printUrl(@js(route('orders.token', '__ID__')).replace('__ID__', o.id))"><x-icon name="printer" class="size-3.5" /> Token</button>
                                <button class="btn-secondary btn-sm" x-show="o.invoice_id" @click="printUrl(@js(route('invoices.receipt', '__ID__')).replace('__ID__', o.invoice_id))"><x-icon name="invoice" class="size-3.5" /> Qəbz</button>
                                <button class="btn-secondary btn-sm" x-show="o.status !== 'completed'" @click="post(@js(route('orders.status', '__ID__')).replace('__ID__', o.id), { status: 'completed' })"><x-icon name="check" class="size-3.5" /> Təhvil verildi</button>
                                <button class="btn-ghost btn-sm text-red-600" x-show="o.payment_status !== 'paid'" @click="confirm('Sifariş #' + o.token_no + ' ləğv edilsin?') && post(@js(route('orders.cancel', '__ID__')).replace('__ID__', o.id), {})">Ləğv</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="border-t border-zinc-100 p-3" x-show="g.unpaid_ids.length && $store.terminal.canPay">
                    <button class="btn-primary w-full" @click="pay = g"><x-icon name="cash" class="size-4" /> Hesabı bağla · <span x-text="money(g.due)"></span></button>
                </div>
            </div>
        </template>
    </div>

    <x-modal show="pay" close="pay = null" title="Hesabı bağla" width="max-w-sm">
        <p class="text-center text-sm text-zinc-500" x-text="pay?.title"></p>
        <p class="mb-4 text-center text-4xl font-black" x-text="money(pay?.due)"></p>
        <p class="label">Ödəniş növü</p>
        <div class="grid grid-cols-2 gap-2">
            @foreach ($paymentTypes as $pt)
                <button class="rounded-xl border px-3 py-3 text-sm font-bold" :class="paymentTypeId === {{ $pt->id }} ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-zinc-200'" @click="paymentTypeId = {{ $pt->id }}">{{ $pt->title }}</button>
            @endforeach
        </div>
        <x-slot:footer>
            <button class="btn-secondary" @click="pay = null">Bağla</button>
            <button class="btn-primary" @click="post(@js(route('orders.pay')), { order_ids: pay.unpaid_ids, payment_type_id: paymentTypeId }).then(r => { if (r) { pay = null; $store.terminal.afterOrder(); printUrl(@js(route('invoices.receipt', '__ID__')).replace('__ID__', r.invoice_id)) } })">Ödənişi təsdiqlə</button>
        </x-slot:footer>
    </x-modal>
</div>
<x-terminal-lock :state="$terminal" />
</x-layouts.app>
