<x-layouts.print title="Ləğv" :width="$tenant->printSetting('paper_width', 80)">
    <div class="c">
        <div class="b">{{ $tenant->name }}</div>
        <div class="muted">MƏTBƏX ÇEKİ</div>
        <div class="lg b" style="border:2px solid #000;margin:6px 0;padding:3px">{{ $return->kind === 'refund' ? 'QAYTARMA' : 'LƏĞV — HAZIRLAMAYIN' }}</div>
        <div class="muted">{{ $return->created_at->format('d.m.Y H:i') }}</div>
    </div>
    <hr>
    <table>
        <tr><td>Token:</td><td class="r b">#{{ $return->order->token_no }}</td></tr>
        @if ($return->order->table)<tr><td>Masa:</td><td class="r b">{{ $return->order->table->label() }}</td></tr>@endif
    </table>
    <hr>
    <div class="big b" style="font-size:18px">{{ $return->quantity }} × {{ $return->title }}</div>
    <hr>
    <table>
        <tr><td>Səbəb:</td><td class="r">{{ \App\Models\ItemReturn::REASONS[$return->reason] ?? $return->reason }}</td></tr>
        @if ($return->note)<tr><td colspan="2" class="muted">{{ $return->note }}</td></tr>@endif
        <tr><td>Stok:</td><td class="r">{{ \App\Models\ItemReturn::STOCK_ACTIONS[$return->stock_action] }}</td></tr>
        <tr><td>Edən:</td><td class="r">{{ $return->requester?->name ?? '—' }}</td></tr>
        <tr><td>Təsdiq:</td><td class="r b">{{ $return->approver?->name }}</td></tr>
    </table>
</x-layouts.print>
