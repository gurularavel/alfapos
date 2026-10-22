<x-layouts.app title="Stok hərəkətləri">
    <x-page-header title="Stok hərəkətləri"><a href="{{ route('inventory.index') }}" class="btn-secondary">← Anbar</a></x-page-header>

    <form class="card mb-4 grid gap-2 p-3 sm:grid-cols-[1fr_1fr_auto]">
        <select name="item" class="input"><option value="">Bütün məhsullar</option>@foreach ($items as $i)<option value="{{ $i->id }}" @selected(request('item') == $i->id)>{{ $i->title }}</option>@endforeach</select>
        <select name="type" class="input"><option value="">Bütün tiplər</option>@foreach (\App\Models\StockMovement::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select>
        <button class="btn-primary">Filtr</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Tarix</th><th>Məhsul</th><th>Tip</th><th class="text-right">Miqdar</th><th class="text-right">Maya (vahid)</th><th class="text-right">Dəyər</th><th>Qeyd</th><th>İstifadəçi</th></tr></thead>
            <tbody>
            @forelse ($movements as $m)
                <tr>
                    <td class="whitespace-nowrap text-zinc-500">{{ $m->created_at->format('d.m.Y H:i') }}</td>
                    <td class="font-bold">{{ $m->item?->title }}</td>
                    <td><span class="{{ ['in' => 'badge-green', 'out' => 'badge-blue', 'wastage' => 'badge-red', 'sale' => 'badge-gray', 'production' => 'badge-amber', 'staff' => 'badge-blue', 'return' => 'badge-green', 'supplier' => 'badge-red'][$m->type] ?? 'badge-gray' }}">{{ \App\Models\StockMovement::TYPES[$m->type] ?? $m->type }}</span></td>
                    <td @class(['text-right font-semibold', 'text-brand-600' => $m->quantity > 0, 'text-red-600' => $m->quantity < 0])>{{ $m->quantity > 0 ? '+' : '' }}{{ qty($m->quantity) }} {{ $m->item?->unit }}</td>
                    <td class="text-right text-zinc-500">{{ $m->unit_cost !== null ? unit_money($m->unit_cost) : '—' }}</td>
                    <td class="text-right font-semibold">{{ $m->unit_cost !== null ? money(abs((float) $m->quantity) * (float) $m->unit_cost) : '—' }}</td>
                    <td class="text-zinc-500">{{ $m->note }}</td>
                    <td>{{ $m->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="history" title="Hərəkət yoxdur" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>
</x-layouts.app>
