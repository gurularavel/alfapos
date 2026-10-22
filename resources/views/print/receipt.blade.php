<x-layouts.print :title="'Qəbz '.$invoice->number" :width="$tenant->printSetting('paper_width', 80)">
    @if ($tenant->printSetting('show_store_details', true))
        <div class="c">
            @if ($tenant->logo)<img src="{{ Storage::disk('public')->url($tenant->logo) }}" alt="" style="max-height:40px"><br>@endif
            <div class="lg b">{{ $tenant->printSetting('header') ?: $tenant->name }}</div>
            @if ($tenant->address)<div class="muted">{{ $tenant->address }}</div>@endif
            @if ($tenant->phone)<div class="muted">{{ $tenant->phone }}</div>@endif
        </div>
        <hr>
    @endif

    <table>
        <tr><td>Faktura:</td><td class="r b">{{ $invoice->number }}</td></tr>
        <tr><td>Tarix:</td><td class="r">{{ $invoice->created_at->format('d.m.Y H:i') }}</td></tr>
        <tr><td>Token:</td><td class="r">{{ $invoice->orders->pluck('token_no')->map(fn ($t) => '#'.$t)->implode(', ') }}</td></tr>
        @if ($table = $invoice->orders->first()?->table)<tr><td>Masa:</td><td class="r">{{ $table->label() }}</td></tr>@endif
        @if ($waiter = $invoice->orders->first()?->creator)<tr><td>Ofisiant:</td><td class="r">{{ $waiter->name }}</td></tr>@endif
        @if ($invoice->creator)<tr><td>Kassir:</td><td class="r">{{ $invoice->creator->name }}</td></tr>@endif
        @if ($invoice->customer && $tenant->printSetting('show_customer_details', true))
            <tr><td>Müştəri:</td><td class="r">{{ $invoice->customer->name }}<br>{{ $invoice->customer->phone }}</td></tr>
        @endif
    </table>
    <hr>
    <table>
        @foreach ($invoice->orders as $order)
            @foreach ($order->items->where('status', '!=', 'cancelled') as $item)
                <tr>
                    <td>
                        {{ $item->quantity }} × {{ $item->title }}@if ($item->variant_title) ({{ $item->variant_title }})@endif
                        @foreach ($item->addons ?? [] as $addon)<br><span class="muted">+ {{ $addon['title'] }}</span>@endforeach
                        @if ($item->notes && $tenant->printSetting('show_notes', true))<br><span class="muted">* {{ $item->notes }}</span>@endif
                    </td>
                    <td class="r">{{ number_format($item->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Ara cəm</td><td class="r">{{ money($invoice->sub_total) }}</td></tr>
        @if ($invoice->discount_total > 0)<tr><td>Endirim</td><td class="r">−{{ money($invoice->discount_total) }}</td></tr>@endif
        <tr><td>Vergi</td><td class="r">{{ money($invoice->tax_total) }}</td></tr>
        @if ($invoice->service_charge_total > 0)<tr><td>Servis haqqı</td><td class="r">{{ money($invoice->service_charge_total) }}</td></tr>@endif
        @if ($invoice->deposit_amount > 0)
            <tr><td colspan="2"><hr></td></tr>
            <tr><td>Sifariş cəmi</td><td class="r">{{ money($invoice->total - $invoice->deposit_topup) }}</td></tr>
            <tr><td>Kabinet depoziti (min.)</td><td class="r">{{ money($invoice->deposit_amount) }}</td></tr>
            @if ($invoice->deposit_topup > 0)
                <tr class="b"><td>Depozitə qədər fərq</td><td class="r">{{ money($invoice->deposit_topup) }}</td></tr>
            @else
                <tr><td colspan="2" class="muted">Depozit qarşılanıb</td></tr>
            @endif
        @endif
        <tr class="lg b"><td>CƏM</td><td class="r">{{ money($invoice->total) }}</td></tr>
        <tr><td>Ödəniş</td><td class="r">{{ $invoice->paymentType?->title }}</td></tr>
    </table>
    @if ($invoice->status === 'void')<hr><div class="c b lg">LƏĞV EDİLİB</div>@endif
    <hr>
    <div class="c muted">{{ $tenant->printSetting('footer') }}</div>
    @if ($tenant->is_feedback_enabled && $tenant->is_qr_menu_enabled)
        <div class="c" style="margin-top:6px">
            <img src="{{ (new \chillerlan\QRCode\QRCode)->render(route('qr.show', $tenant->qr_code).'#feedback') }}" alt="QR" style="width:90px">
            <div class="muted">Rəyinizi bildirin</div>
        </div>
    @endif
</x-layouts.print>
