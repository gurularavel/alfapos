<x-layouts.print title="Hesab" :width="$tenant->printSetting('paper_width', 80)">
    <div class="c">
        <div class="lg b">{{ $tenant->printSetting('header') ?: $tenant->name }}</div>
        @if ($tenant->address)<div class="muted">{{ $tenant->address }}</div>@endif
        <div class="lg b" style="margin-top:6px">HESAB</div>
        <div class="muted">Ödəniş çeki deyil</div>
    </div>
    <hr>
    <table>
        @if ($table = $orders->first()->table)<tr><td>Masa:</td><td class="r b">{{ $table->label() }}</td></tr>@endif
        <tr><td>Token:</td><td class="r">{{ $orders->pluck('token_no')->map(fn ($t) => '#'.$t)->implode(', ') }}</td></tr>
        <tr><td>Tarix:</td><td class="r">{{ now()->format('d.m.Y H:i') }}</td></tr>
        @if ($waiter = $orders->first()->creator)<tr><td>Ofisiant:</td><td class="r">{{ $waiter->name }}</td></tr>@endif
    </table>
    <hr>
    <table>
        @foreach ($orders as $order)
            @foreach ($order->items->where('status', '!=', 'cancelled') as $item)
                <tr>
                    <td>{{ $item->quantity }} × {{ $item->title }}@if ($item->variant_title) ({{ $item->variant_title }})@endif
                        @foreach ($item->addons ?? [] as $addon)<br><span class="muted">+ {{ $addon['title'] }}</span>@endforeach
                    </td>
                    <td class="r">{{ number_format($item->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Ara cəm</td><td class="r">{{ money($totals['sub_total']) }}</td></tr>
        @if ($totals['discount_total'] > 0)<tr><td>Endirim</td><td class="r">−{{ money($totals['discount_total']) }}</td></tr>@endif
        @if ($totals['tax_total'] > 0)<tr><td>Vergi</td><td class="r">{{ money($totals['tax_total']) }}</td></tr>@endif
        @if ($totals['service_charge_total'] > 0)<tr><td>Servis haqqı</td><td class="r">{{ money($totals['service_charge_total']) }}</td></tr>@endif
        @if ($deposit > 0)
            <tr><td>Sifariş cəmi</td><td class="r">{{ money($totals['total']) }}</td></tr>
            <tr><td>Kabinet depoziti (min.)</td><td class="r">{{ money($deposit) }}</td></tr>
            @if ($topup > 0)<tr class="b"><td>Depozitə qədər fərq</td><td class="r">{{ money($topup) }}</td></tr>@endif
        @endif
        <tr class="lg b"><td>ÖDƏNİLƏCƏK</td><td class="r">{{ money($totals['total'] + $topup) }}</td></tr>
    </table>
    <hr>
    <div class="c muted">{{ $tenant->printSetting('footer') }}</div>
</x-layouts.print>
