<x-layouts.print :title="'Token #'.$order->token_no" :width="$tenant->printSetting('paper_width', 80)">
    <div class="c">
        <div class="b">{{ $tenant->name }}</div>
        <div class="muted">MƏTBƏX ÇEKİ (KOT)</div>
        <div class="big">#{{ $order->token_no }}</div>
        <div>{{ \App\Models\Order::DELIVERY_TYPES[$order->delivery_type] }}@if ($order->table) · {{ $order->table->label() }}@endif</div>
        <div class="muted">{{ $order->created_at->format('d.m.Y H:i') }}</div>
    </div>
    <hr>
    <table>
        @foreach ($order->items->where('status', '!=', 'cancelled') as $item)
            <tr>
                <td class="lg b" style="width:34px">{{ $item->quantity }}×</td>
                <td class="lg">
                    {{ $item->title }}@if ($item->variant_title) ({{ $item->variant_title }})@endif
                    @foreach ($item->addons ?? [] as $addon)<br><span class="muted">+ {{ $addon['title'] }}</span>@endforeach
                    @if ($item->notes)<br><span class="b">* {{ $item->notes }}</span>@endif
                </td>
                @if ($tenant->printSetting('kot_show_prices'))<td class="r">{{ number_format($item->lineTotal(), 2) }}</td>@endif
            </tr>
        @endforeach
    </table>
    @if ($order->notes)<hr><div class="b">Qeyd: {{ $order->notes }}</div>@endif
</x-layouts.print>
