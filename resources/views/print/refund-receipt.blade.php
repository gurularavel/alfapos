<x-layouts.print :title="'Qaytarma '.$return->invoice?->number" :width="$tenant->printSetting('paper_width', 80)">
    <div class="c">
        <div class="lg b">{{ $tenant->printSetting('header') ?: $tenant->name }}</div>
        @if ($tenant->address)<div class="muted">{{ $tenant->address }}</div>@endif
        <div class="lg b" style="margin-top:6px">QAYTARMA QƏBZİ</div>
    </div>
    <hr>
    <table>
        <tr><td>Qaytarma №:</td><td class="r b">R-{{ str_pad((string) $return->id, 6, '0', STR_PAD_LEFT) }}</td></tr>
        <tr><td>Çek:</td><td class="r">{{ $return->invoice?->number }}</td></tr>
        <tr><td>Tarix:</td><td class="r">{{ $return->created_at->format('d.m.Y H:i') }}</td></tr>
        @if ($return->order->table)<tr><td>Masa:</td><td class="r">{{ $return->order->table->label() }}</td></tr>@endif
    </table>
    <hr>
    <table>
        <tr><td>{{ $return->quantity }} × {{ $return->title }}</td><td class="r"></td></tr>
        <tr><td colspan="2" class="muted">Səbəb: {{ \App\Models\ItemReturn::REASONS[$return->reason] ?? $return->reason }}@if ($return->note) — {{ $return->note }}@endif</td></tr>
    </table>
    <hr>
    <table>
        <tr class="lg b"><td>QAYTARILDI</td><td class="r">{{ money($return->amount) }}</td></tr>
        <tr><td>Ödəniş növü</td><td class="r">{{ $return->paymentType?->title }}</td></tr>
        <tr><td>Kassir</td><td class="r">{{ $return->requester?->name ?? '—' }}</td></tr>
        <tr><td>Təsdiq</td><td class="r">{{ $return->approver?->name }}</td></tr>
    </table>
    <hr>
    <div style="margin-top:18px">Müştərinin imzası: ____________________</div>
    <div style="margin-top:14px">Kassirin imzası: _____________________</div>
</x-layouts.print>
