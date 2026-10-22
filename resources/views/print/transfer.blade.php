<x-layouts.print title="Masa köçürüldü" :width="$tenant->printSetting('paper_width', 80)">
    @php($first = $orders->first())
    <div class="c">
        <div class="b">{{ $tenant->name }}</div>
        <div class="muted">MƏTBƏX ÇEKİ</div>
        <div class="lg b" style="border:2px solid #000;margin:6px 0;padding:3px">MASA KÖÇÜRÜLDÜ</div>
        <div class="big" style="font-size:20px">{{ $first->transferred_from }} → {{ $first->table?->label() }}</div>
        <div class="muted">{{ $first->transferred_at?->format('d.m.Y H:i') }}@if ($by) · {{ $by }}@endif</div>
    </div>
    <hr>
    @foreach ($orders as $order)
        <div class="b">Token #{{ $order->token_no }} <span class="muted">({{ $order->created_at->format('H:i') }})</span></div>
        <table>
            @foreach ($order->items as $item)
                <tr>
                    <td class="b" style="width:34px">{{ $item->quantity }}×</td>
                    <td>{{ $item->title }}@if ($item->variant_title) ({{ $item->variant_title }})@endif</td>
                </tr>
            @endforeach
        </table>
        @unless ($loop->last)<hr>@endunless
    @endforeach
    <hr>
    <div class="c b">Yeməkləri {{ $first->table?->title }} masasına verin</div>
</x-layouts.print>
