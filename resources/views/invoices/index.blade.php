<x-layouts.app title="Fakturalar">
<div x-data="{ voiding: null }">
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
                    <td class="text-right font-bold">{{ money($invoice->total) }}</td>
                    <td>
                        @if ($invoice->status === 'paid')<span class="badge-green">Ödənilib</span>
                        @else<span class="badge-red" title="{{ $invoice->void_reason }}">Ləğv</span>@endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-ghost btn-sm" onclick="printUrl('{{ route('invoices.receipt', $invoice) }}')"><x-icon name="printer" class="size-4" /></button>
                        @if ($invoice->status === 'paid')
                            <button class="btn-ghost btn-sm text-red-600" @click="voiding = @js(['id' => $invoice->id, 'number' => $invoice->number])">Ləğv</button>
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
        </form>
        <x-slot:footer>
            <button class="btn-secondary" @click="voiding = null">Bağla</button>
            <button class="btn-danger" form="void-form">Ləğv et</button>
        </x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
