<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $all = InventoryItem::all();

        $items = InventoryItem::query()
            ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->query('status') === 'low', fn ($q) => $q->whereColumn('quantity', '<=', 'min_quantity')->where('quantity', '>', 0))
            ->when($request->query('status') === 'out', fn ($q) => $q->where('quantity', '<=', 0))
            ->latest('updated_at')->paginate(30)->withQueryString();

        return view('inventory.index', [
            'items' => $items,
            'stats' => [
                'all' => $all->count(),
                'in' => $all->filter(fn ($i) => $i->stockStatus() === 'in')->count(),
                'low' => $all->filter(fn ($i) => $i->stockStatus() === 'low')->count(),
                'out' => $all->filter(fn ($i) => $i->stockStatus() === 'out')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        InventoryItem::create($this->validated($request));

        return back()->with('success', 'Stok məhsulu əlavə edildi.');
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $inventoryItem->update($request->validate([
            'title' => ['required', 'string', 'max:120'],
            'unit' => ['required', Rule::in(array_keys(InventoryItem::UNITS))],
            'min_quantity' => ['required', 'numeric', 'min:0'],
        ]));

        return back()->with('success', 'Yeniləndi.');
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return back()->with('success', 'Silindi.');
    }

    public function movement(Request $request, InventoryItem $inventoryItem)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['in', 'out', 'wastage'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $signed = $data['type'] === 'in' ? $data['quantity'] : -$data['quantity'];

        DB::transaction(function () use ($inventoryItem, $data, $signed) {
            $inventoryItem->increment('quantity', $signed);
            StockMovement::create([
                'inventory_item_id' => $inventoryItem->id,
                'type' => $data['type'],
                'quantity' => $signed,
                'note' => $data['note'] ?? null,
                'user_id' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Stok hərəkəti qeydə alındı.');
    }

    public function movements(Request $request)
    {
        $movements = StockMovement::with(['item', 'user:id,name'])
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('item'), fn ($q, $i) => $q->where('inventory_item_id', $i))
            ->latest()->paginate(50)->withQueryString();

        return view('inventory.movements', ['movements' => $movements, 'items' => InventoryItem::orderBy('title')->get(['id', 'title'])]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'unit' => ['required', Rule::in(array_keys(InventoryItem::UNITS))],
            'quantity' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
