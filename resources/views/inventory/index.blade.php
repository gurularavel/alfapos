<x-layouts.app title="Anbar">
<div x-data="{ edit: null, move: null, blank: { id: null, title: '', unit: 'pc', quantity: 0, min_quantity: 0 } }">
    <x-page-header title="Anbar" subtitle="İnqrediyentlər və stok qalıqları. Satışda resept əsasında avtomatik silinir.">
        <a href="{{ route('inventory.movements') }}" class="btn-secondary"><x-icon name="history" class="size-4" /> Stok hərəkətləri</a>
        <button class="btn-primary" @click="edit = { ...blank }"><x-icon name="plus" class="size-4" /> Stok məhsulu</button>
    </x-page-header>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['', 'Bütün məhsullar', $stats['all'], 'border-l-zinc-400'], ['in', 'Kifayət qədər', $stats['in'], 'border-l-brand-500'], ['low', 'Az qalıb', $stats['low'], 'border-l-amber-500'], ['out', 'Bitib', $stats['out'], 'border-l-red-500']] as [$key, $label, $value, $border])
            <a href="{{ route('inventory.index', array_filter(['status' => $key])) }}" @class(['card card-pad border-l-4', $border, 'ring-2 ring-brand-200' => request('status', '') === $key])>
                <p class="text-sm font-semibold text-zinc-500">{{ $label }}</p><p class="text-2xl font-black">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <form class="card mb-4 flex gap-2 p-3">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Məhsul axtar">
        <button class="btn-primary"><x-icon name="search" class="size-4" /></button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Məhsul</th><th class="text-right">Cari stok</th><th class="text-right">Minimum</th><th>Status</th><th>Yenilənib</th><th></th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                @php($status = $item->stockStatus())
                <tr>
                    <td class="font-bold">{{ $item->title }}</td>
                    <td class="text-right font-semibold">{{ qty($item->quantity) }} {{ \App\Models\InventoryItem::UNITS[$item->unit] }}</td>
                    <td class="text-right text-zinc-500">{{ qty($item->min_quantity) }} {{ \App\Models\InventoryItem::UNITS[$item->unit] }}</td>
                    <td>{!! ['in' => '<span class="badge-green">Var</span>', 'low' => '<span class="badge-amber">Az qalıb</span>', 'out' => '<span class="badge-red">Bitib</span>'][$status] !!}</td>
                    <td class="text-zinc-500">{{ $item->updated_at->format('d.m.Y H:i') }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-secondary btn-sm" @click="move = { id: {{ $item->id }}, title: @js($item->title), unit: @js(\App\Models\InventoryItem::UNITS[$item->unit]), type: 'in', quantity: '', note: '' }">± Hərəkət</button>
                        <button class="btn-ghost btn-sm" @click="edit = @js(['id' => $item->id, 'title' => $item->title, 'unit' => $item->unit, 'quantity' => qty($item->quantity), 'min_quantity' => qty($item->min_quantity)])"><x-icon name="edit" class="size-4" /></button>
                        <form method="POST" action="{{ route('inventory.destroy', $item) }}" class="inline" onsubmit="return confirm('Silinsin? Reseptlərdən də çıxarılacaq.')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="box" title="Stok məhsulu yoxdur">Süd, qəhvə, ət kimi inqrediyentləri əlavə edin, sonra menyu məhsulunun reseptinə bağlayın.</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>

    <x-modal show="edit" close="edit = null" title="Stok məhsulu">
        <template x-if="edit"><form method="POST" :action="edit.id ? @js(url('dashboard/inventory')) + '/' + edit.id : @js(route('inventory.store'))" id="inv-form" class="grid gap-3 sm:grid-cols-2">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="sm:col-span-2"><label class="label">Ad *</label><input name="title" x-model="edit.title" class="input" required></div>
            <div><label class="label">Vahid</label><select name="unit" x-model="edit.unit" class="input">@foreach (\App\Models\InventoryItem::UNITS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div x-show="!edit.id"><label class="label">Başlanğıc miqdar</label><input type="number" step="0.0001" min="0" name="quantity" x-model="edit.quantity" class="input"></div>
            <div><label class="label">Minimum hədd</label><input type="number" step="0.0001" min="0" name="min_quantity" x-model="edit.min_quantity" class="input"></div>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="edit = null">Bağla</button><button class="btn-primary" form="inv-form">Saxla</button></x-slot:footer>
    </x-modal>

    <x-modal show="move" close="move = null" title="Stok hərəkəti" width="max-w-sm">
        <template x-if="move"><form method="POST" :action="@js(url('dashboard/inventory')) + '/' + move.id + '/movement'" id="move-form" class="space-y-3">
            @csrf
            <p class="font-bold" x-text="move.title"></p>
            <div class="grid grid-cols-3 gap-2">
                @foreach (['in' => 'Giriş', 'out' => 'Çıxış', 'wastage' => 'İsraf'] as $k => $v)
                    <label class="cursor-pointer rounded-xl border border-zinc-200 py-2 text-center text-sm font-bold has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50"><input type="radio" name="type" value="{{ $k }}" x-model="move.type" class="sr-only">{{ $v }}</label>
                @endforeach
            </div>
            <div><label class="label">Miqdar (<span x-text="move.unit"></span>)</label><input type="number" step="0.0001" min="0.0001" name="quantity" x-model="move.quantity" class="input" required></div>
            <div><label class="label">Qeyd</label><input name="note" x-model="move.note" class="input" placeholder="Məs. təchizatçı fakturası №"></div>
        </form></template>
        <x-slot:footer><button class="btn-secondary" @click="move = null">Bağla</button><button class="btn-primary" form="move-form">Saxla</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
