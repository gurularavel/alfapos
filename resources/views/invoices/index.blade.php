<x-layouts.app title="Fakturalar">
<div x-data="{ voiding: null, refunding: null }">
    <x-page-header title="Fakturalar" subtitle="Bütün ödənilmiş və ləğv edilmiş çeklər." />

    <form class="card mb-4 grid gap-2 p-3 sm:grid-cols-[1fr_auto_auto_auto_auto_auto]">
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Faktura №, müştəri adı və ya telefon">
        <input type="date" name="from" value="{{ request('from') }}" class="input">
        <input type="date" name="to" value="{{ request('to') }}" class="input">
        <select name="terminal_id" class="input"><option value="">Bütün terminallar</option>@foreach ($terminals as $tm)<option value="{{ $tm->id }}" @selected(request('terminal_id') == $tm->id)>{{ $tm->name }}</option>@endforeach</select>
        <select name="status" class="input"><option value="">Bütün statuslar</option><option value="paid" @selected(request('status') === 'paid')>Ödənilib</option><option value="void" @selected(request('status') === 'void')>Ləğv</option></select>
        <button class="btn-primary"><x-icon name="search" class="size-4" /> Axtar</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Faktura</th><th>Tarix</th><th>Token</th><th>Müştəri</th><th>Ödəniş</th><th>Terminal</th><th class="text-right">Cəm</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td class="font-bold">{{ $invoice->number }}</td>
                    <td class="whitespace-nowrap text-zinc-500">{{ $invoice->created_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $invoice->orders->pluck('token_no')->map(fn ($t) => '#'.$t)->implode(', ') }}</td>
                    <td>{{ $invoice->customer?->name ?? 'Gəlmə müştəri' }}</td>
                    <td>{{ $invoice->paymentType?->title }}</td>
                    <td class="text-zinc-500">{{ $invoice->terminal?->name ?? '—' }}</td>
                    <td class="text-right font-bold">{{ money($invoice->total) }}@if ($invoice->refunded_total > 0)<span class="block text-xs font-semibold text-red-600">−{{ money($invoice->refunded_total) }} qaytarılıb</span>@endif</td>
                    <td>
                        @if ($invoice->status === 'paid')<span class="badge-green">Ödənilib</span>
                        @else<span class="badge-red" title="{{ $invoice->void_reason }}">Ləğv</span>@endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" onclick="printUrl('{{ route('invoices.receipt', $invoice) }}')"><x-icon name="printer" class="size-4" /></button>
                        @if ($invoice->status === 'paid')
                            @if ($canRefund)
                                <button class="btn-ghost btn-sm" @click="refunding = @js(['number' => $invoice->number, 'refundable' => (float) $invoice->total - (float) $invoice->refunded_total, 'items' => $invoice->orders->flatMap(fn ($o) => $o->items->where('status', '!=', 'cancelled')->map(fn ($i) => ['id' => $i->id, 'title' => $i->title.($i->variant_title ? ' ('.$i->variant_title.')' : ''), 'qty' => $i->quantity, 'price' => (float) $i->price, 'status' => $i->status, 'order' => ['id' => $o->id, 'token_no' => $o->token_no, 'paid' => true]]))->values()])">Qaytar</button>
                            @endif
                            @if (! $invoice->refunded_total)<button class="btn-ghost btn-sm text-red-600" @click="voiding = @js(['id' => $invoice->id, 'number' => $invoice->number])">Ləğv</button>@endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty icon="invoice" title="Faktura tapılmadı" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>

    <x-modal show="voiding" close="voiding = null" title="Fakturanı ləğv et" width="max-w-sm">
        <form method="POST" :action="@js(route('invoices.void', '__ID__')).replace('__ID__', voiding?.id)" id="void-form">
            @csrf
            <p class="mb-3 text-sm text-zinc-600">Faktura <b x-text="voiding?.number"></b> ləğv ediləcək, sifarişlər ləğv statusuna keçəcək və stok geri qaytarılacaq.</p>
            <label class="label">Səbəb</label>
            <input name="reason" class="input" required maxlength="255">
            @unless (auth()->user()->hasScope('APPROVE'))
                <label class="label mt-3">Menecer PIN-i</label>
                <input type="password" name="approver_pin" inputmode="numeric" maxlength="4" pattern="\d{4}" class="input tracking-[0.5em]" required autocomplete="off">
                <p class="mt-1 text-xs text-zinc-500">Çeki ləğv etmək üçün menecer təsdiqi lazımdır.</p>
            @endunless
        </form>
        <x-slot:footer>
            <button class="btn-secondary" @click="voiding = null">Bağla</button>
            <button class="btn-danger" form="void-form">Ləğv et</button>
        </x-slot:footer>
    </x-modal>

    <x-modal show="refunding" close="refunding = null" title="Çekdən qaytarma" width="max-w-md">
        <template x-if="refunding"><div>
            <p class="mb-1 text-sm text-zinc-600">Faktura <b x-text="refunding.number"></b> · qaytarıla bilər: <b x-text="money(refunding.refundable)"></b></p>
            <p class="mb-3 text-xs text-zinc-500">Qaytarılan məhsulu seçin.</p>
            <template x-for="i in refunding.items" :key="i.id">
                <button type="button" class="mb-2 flex w-full items-center justify-between rounded-xl border border-zinc-200 px-3 py-2.5 text-left hover:border-brand-300"
                        @click="$store.returns.show({ item: i, order: i.order, onDone: () => location.reload() }); refunding = null">
                    <span class="font-semibold" x-text="i.qty + ' × ' + i.title"></span><span class="text-sm text-zinc-500" x-text="money(i.price * i.qty)"></span>
                </button>
            </template>
            <p x-show="!refunding.items.length" class="text-sm text-zinc-500">Qaytarılacaq məhsul qalmayıb.</p>
        </div></template>
        <x-slot:footer><button class="btn-secondary" @click="refunding = null">Bağla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
