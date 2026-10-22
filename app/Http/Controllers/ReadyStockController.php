<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\ReadyStockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Hazır yemək stoku: porsiya ilə izlənən menyu məhsulları, hazırlama, israf və sayım. */
class ReadyStockController extends Controller
{
    public function index(Request $request): View
    {
        $items = MenuItem::with(['category:id,title', 'recipeItems' => fn ($q) => $q->whereNull('variant_id')->whereNull('addon_id')->with('inventoryItem')])
            ->where('track_stock', true)->orderBy('title')->get();

        $today = ReadyStockMovement::whereDate('created_at', today())->whereIn('menu_item_id', $items->pluck('id'))
            // Ləğv qaytarmaları öz mənbəyindən (satış / personal) çıxılır
            ->selectRaw("menu_item_id, CASE WHEN type = 'return' THEN IF(staff_meal_id IS NULL, 'sale', 'staff') ELSE type END as kind, SUM(quantity) as qty")
            ->groupBy('menu_item_id', 'kind')->get()
            ->groupBy('menu_item_id')->map(fn ($rows) => $rows->pluck('qty', 'kind')->map(fn ($q) => abs((float) $q)));

        $movements = ReadyStockMovement::with(['menuItem:id,title', 'user:id,name'])
            ->when($request->integer('item'), fn ($q, $id) => $q->where('menu_item_id', $id))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest('id')->paginate(30)->withQueryString();

        return view('ready-stock.index', [
            'items' => $items,
            'today' => $today,
            'movements' => $movements,
            'untracked' => MenuItem::where('track_stock', false)->orderBy('title')->get(['id', 'title']),
            'units' => InventoryItem::UNITS,
        ]);
    }

    /** Menyu məhsulunu hazır stokla izləməyə başlayır. */
    public function track(Request $request, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'menu_item_id' => ['required', Rule::exists('menu_items', 'id')->where('tenant_id', app(\App\Support\Tenancy::class)->id())],
            'stock_min' => ['nullable', 'numeric', 'min:0'],
            'portions' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        $item = MenuItem::findOrFail($data['menu_item_id']);
        $item->update(['track_stock' => true, 'stock_min' => $data['stock_min'] ?? 0]);
        // Başlanğıc qalıq sayım kimi yazılır (xammal silinmir — artıq bişirilmiş porsiyalardır)
        if (($data['portions'] ?? 0) > 0) {
            $stock->count($item, (float) $data['portions'], 'Başlanğıc qalıq');
        }
        AuditLog::record('ready_stock.tracked', $item);

        return back()->with('success', "{$item->title} hazır stokla izlənir.");
    }

    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update($request->validate(['stock_min' => ['required', 'numeric', 'min:0']]));

        return back()->with('success', 'Yeniləndi.');
    }

    /** İzləməni dayandırır: məhsul yenidən satış anında reseptlə anbardan silinir. */
    public function untrack(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update(['track_stock' => false, 'stock_quantity' => 0]);
        AuditLog::record('ready_stock.untracked', $menuItem);

        return back()->with('success', "{$menuItem->title} artıq hazır stokla izlənmir.");
    }

    public function produce(Request $request, MenuItem $menuItem, StockService $stock): RedirectResponse
    {
        abort_unless($menuItem->track_stock, 404);
        $data = $request->validate([
            'portions' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $short = $stock->produce($menuItem, (float) $data['portions'], $data['note'] ?? null);
        AuditLog::record('ready_stock.produced', $menuItem, ['portions' => $data['portions']]);

        $message = "{$menuItem->title}: ".qty($data['portions']).' porsiya hazır stoka əlavə edildi.';

        return $short
            ? back()->with('success', $message)->with('warning', 'Anbarda mənfi qalıq yarandı: '.implode(', ', $short).'. Anbara mal girişini qeyd edin.')
            : back()->with('success', $message);
    }

    public function waste(Request $request, MenuItem $menuItem, StockService $stock): RedirectResponse
    {
        abort_unless($menuItem->track_stock, 404);
        $data = $request->validate([
            'portions' => ['required', 'numeric', 'gt:0', 'lte:'.max(0, (float) $menuItem->stock_quantity)],
            'note' => ['required', 'string', 'max:255'],
        ], ['portions.lte' => 'Hazır stokda cəmi :value porsiya var.']);
        $stock->waste($menuItem, (float) $data['portions'], $data['note']);
        AuditLog::record('ready_stock.wasted', $menuItem, $data);

        return back()->with('success', "{$menuItem->title}: ".qty($data['portions']).' porsiya israf kimi silindi.');
    }

    public function count(Request $request, MenuItem $menuItem, StockService $stock): RedirectResponse
    {
        abort_unless($menuItem->track_stock, 404);
        $data = $request->validate([
            'actual' => ['required', 'numeric', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $before = (float) $menuItem->stock_quantity;
        $stock->count($menuItem, (float) $data['actual'], $data['note'] ?? null);
        AuditLog::record('ready_stock.counted', $menuItem, ['before' => $before, 'actual' => $data['actual']]);

        return back()->with('success', "{$menuItem->title}: qalıq ".qty($data['actual']).' porsiya kimi yazıldı.');
    }
}
