<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\ReadyStockMovement;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Support\PosTerminal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anbar (xammal) və hazır yemək stoku.
 *
 * Hazır stokla izlənən məhsul (track_stock) hazırlananda reseptinin xammalı anbardan silinir və porsiyası
 * hazır stoka əlavə olunur; satılanda / personala veriləndə yalnız porsiya hazır stokdan düşür.
 * İzlənməyən məhsulun xammalı isə satış anında reseptlə anbardan silinir.
 */
class StockService
{
    /**
     * Sətirlərə görə stoku silir ($sign = -1) və ya geri qaytarır ($sign = 1).
     *
     * @param  Collection<int, object{menu_item_id: ?int, variant_id?: ?int, addons?: ?array, quantity: int|float}>  $lines
     * @param  string  $type  sale | staff
     * @param  array{order_id?: int, staff_meal_id?: int, note?: string}  $ref
     */
    public function consume(Collection $lines, int $sign, string $type, array $ref = []): void
    {
        $menuIds = $lines->pluck('menu_item_id')->filter()->unique();
        if ($menuIds->isEmpty()) {
            return;
        }
        $tracked = MenuItem::whereIn('id', $menuIds)->where('track_stock', true)->pluck('id')->flip();
        $recipes = RecipeItem::whereIn('menu_item_id', $menuIds)->get();

        $usage = [];
        $portions = [];
        foreach ($lines as $line) {
            $isTracked = isset($tracked[$line->menu_item_id]);
            if ($isTracked) {
                $portions[$line->menu_item_id] = ($portions[$line->menu_item_id] ?? 0) + $line->quantity;
            }
            $addonIds = collect($line->addons ?? [])->pluck('id')->all();
            foreach ($recipes->where('menu_item_id', $line->menu_item_id) as $recipe) {
                // Hazır porsiyanın öz xammalı hazırlananda silinib; yalnız əlavələrin (addon) xammalı indi silinir
                if ($isTracked && ! $recipe->addon_id) {
                    continue;
                }
                $applies = match (true) {
                    (bool) $recipe->addon_id => in_array($recipe->addon_id, $addonIds),
                    (bool) $recipe->variant_id => $recipe->variant_id === ($line->variant_id ?? null),
                    default => true,
                };
                if ($applies) {
                    $usage[$recipe->inventory_item_id] = ($usage[$recipe->inventory_item_id] ?? 0) + $recipe->quantity * $line->quantity;
                }
            }
        }

        $movementType = $sign < 0 ? $type : 'return';
        $refs = array_filter(['order_id' => $ref['order_id'] ?? null, 'staff_meal_id' => $ref['staff_meal_id'] ?? null]);
        foreach ($usage as $itemId => $qty) {
            $this->moveInventory($itemId, $sign * $qty, $movementType, $ref['note'] ?? null, $refs);
        }
        foreach ($portions as $menuItemId => $qty) {
            $this->moveReady($menuItemId, $sign * $qty, $movementType, $ref['note'] ?? null, $refs);
        }
    }

    /**
     * Hazır stokda kifayət qədər porsiya olduğunu yoxlayır (sətirlər bloklanır ki, eyni anda iki kassa satmasın).
     *
     * @param  Collection<int, object{menu_item_id: ?int, quantity: int|float}>  $lines
     *
     * @throws ValidationException
     */
    public function assertAvailable(Collection $lines, string $field = 'cart'): void
    {
        $need = $lines->filter(fn ($l) => $l->menu_item_id)->groupBy('menu_item_id')->map(fn ($g) => $g->sum('quantity'));
        if ($need->isEmpty()) {
            return;
        }
        $short = MenuItem::whereIn('id', $need->keys())->where('track_stock', true)->lockForUpdate()->get()
            ->filter(fn (MenuItem $m) => $need[$m->id] > (float) $m->stock_quantity)
            ->map(fn (MenuItem $m) => (float) $m->stock_quantity > 0
                ? "{$m->title}: hazır stokda cəmi ".qty($m->stock_quantity).' porsiya qalıb.'
                : "{$m->title}: hazır stokda bitib.");

        if ($short->isNotEmpty()) {
            throw ValidationException::withMessages([$field => $short->implode(' ')]);
        }
    }

    /**
     * Porsiya hazırlanır: reseptin əsas xammalı × porsiya anbardan silinir, hazır stok artır.
     *
     * @return list<string> anbarda mənfiyə düşən xammal (xəbərdarlıq üçün)
     */
    public function produce(MenuItem $item, float $portions, ?string $note = null): array
    {
        return DB::transaction(function () use ($item, $portions, $note) {
            $label = "Hazırlandı: {$item->title} × ".qty($portions).($note ? " — {$note}" : '');
            $short = [];
            foreach (RecipeItem::with('inventoryItem')->where('menu_item_id', $item->id)->whereNull('variant_id')->whereNull('addon_id')->get() as $recipe) {
                $balance = $this->moveInventory($recipe->inventory_item_id, -$recipe->quantity * $portions, 'production', $label);
                if ($balance < 0) {
                    $short[] = $recipe->inventoryItem->title;
                }
            }
            $this->moveReady($item->id, $portions, 'production', $note);

            return $short;
        });
    }

    /** Hazır porsiya israf olunur (xammal artıq hazırlananda silinib). */
    public function waste(MenuItem $item, float $portions, string $reason): void
    {
        DB::transaction(fn () => $this->moveReady($item->id, -$portions, 'wastage', $reason));
    }

    /** Sayım: faktiki qalıq yazılır, fərq hərəkət kimi qeydə alınır. */
    public function count(MenuItem $item, float $actual, ?string $note = null): void
    {
        DB::transaction(function () use ($item, $actual, $note) {
            $current = (float) MenuItem::whereKey($item->id)->lockForUpdate()->value('stock_quantity');
            if (abs($actual - $current) >= 0.005) {
                $this->moveReady($item->id, $actual - $current, 'count', $note ?: 'Sayım: '.qty($current).' → '.qty($actual));
            }
        });
    }

    /** @param array{order_id?: int, staff_meal_id?: int} $refs */
    protected function moveInventory(int $itemId, float $quantity, string $type, ?string $note, array $refs = []): float
    {
        InventoryItem::whereKey($itemId)->increment('quantity', $quantity);
        StockMovement::create([
            'inventory_item_id' => $itemId,
            'type' => $type,
            'quantity' => $quantity,
            'note' => $note,
            'user_id' => app(PosTerminal::class)->effectiveId(),
            ...$refs,
        ]);

        return (float) InventoryItem::whereKey($itemId)->value('quantity');
    }

    /** @param array{order_id?: int, staff_meal_id?: int} $refs */
    protected function moveReady(int $menuItemId, float $quantity, string $type, ?string $note, array $refs = []): void
    {
        MenuItem::whereKey($menuItemId)->increment('stock_quantity', $quantity);
        ReadyStockMovement::create([
            'menu_item_id' => $menuItemId,
            'type' => $type,
            'quantity' => $quantity,
            'balance' => MenuItem::whereKey($menuItemId)->value('stock_quantity'),
            'note' => $note,
            'user_id' => app(PosTerminal::class)->effectiveId(),
            ...$refs,
        ]);
    }
}
