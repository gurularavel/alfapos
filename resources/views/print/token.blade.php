<x-layouts.print :title="'Token #'.$order->token_no" :width="$tenant->printSetting('paper_width', 80)" :preview="$preview ?? false" :autoprint="! ($preview ?? false)">
    <div class="c">
        <div class="b">{{ $tenant->name }}</div>
        <div class="b">{{ $title ?? 'MƏTBƏX ÇEKİ (KOT)' }}</div>
        @if ($order->transferred_from)
            <div class="b" style="border:1px dashed #000;margin:4px 0;padding:2px">Köçürülüb: {{ $order->transferred_from }} → {{ $order->table?->label() }}</div>
        @endif
        @if ($order->is_addition)
            <div class="lg b" style="border:2px solid #000;margin:4px 0;padding:2px">ƏLAVƏ SİFARİŞ</div>
            @php($previous = \App\Models\Order::where('store_table_id', $order->store_table_id)->where('payment_status', 'pending')->whereNotIn('status', ['draft', 'cancelled'])->where('id', '<', $order->id)->pluck('token_no'))
            @if ($previous->isNotEmpty())<div class="muted">Əvvəlki: {{ $previous->map(fn ($t) => '#'.$t)->implode(', ') }}</div>@endif
        @endif
        <div class="big">#{{ $order->token_no }}</div>
        <div>{{ \App\Models\Order::DELIVERY_TYPES[$order->delivery_type] }}@if ($order->table) · {{ $order->table->label() }}@endif</div>
        <div class="muted">{{ $order->created_at->format('d.m.Y H:i') }}@if ($order->creator) · {{ $order->creator->name }}@endif</div>
    </div>
    <hr>
    @php($groups ??= collect([null => $order->items->where('status', '!=', 'cancelled')]))
    @foreach ($groups as $departmentId => $items)
    {{-- Bir neçə şöbə bir çekdədirsə (ümumi çek) hər şöbə ayrıca başlıqla --}}
    @if ($groups->count() > 1)<div class="b" style="margin-top:6px;border-bottom:1px solid #000">{{ mb_strtoupper($departments[$departmentId]->name ?? 'Mətbəx', 'UTF-8') }}</div>@endif
    <table>
        @foreach ($items as $item)
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
    @endforeach
    @if ($order->notes)<hr><div class="b">Qeyd: {{ $order->notes }}</div>@endif
</x-layouts.print>
