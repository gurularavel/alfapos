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
 * Anbar (xammal), hazır yemək stoku və maya dəyəri.
 *
 * Hazır stokla izlənən məhsul (track_stock) hazırlananda reseptinin xammalı anbardan silinir və porsiyası
 * hazır stoka əlavə olunur; satılanda / personala veriləndə yalnız porsiya hazır stokdan düşür.
 * İzlənməyən məhsulun xammalı isə satış anında reseptlə anbardan silinir.
 *
 * Maya dəyəri: anbar məhsulunun alış qiyməti (cost_price) hər girişdə orta çəkili yenilənir; hazır porsiyanın
 * mayası (stock_cost) hər partiyada orta çəkili yenilənir. Qiyməti bilinməyən xammal varsa, maya null qalır
 * (yanlış rəqəm göstərməkdənsə "bilinmir").
 */
class StockService
{
    /**
     * Sətirlərə görə stoku silir ($sign = -1) və ya geri qaytarır ($sign = 1).
     *
     * @param  Collection<int|string, object{menu_item_id: ?int, variant_id?: ?int, addons?: ?array, quantity: int|float}>  $lines
     * @param  string  $type  sale | staff
     * @param  array{order_id?: int, staff_meal_id?: int, note?: string}  $ref
     * @return array<int|string, ?float> hər sətirin 1 ədədinin mayası ($lines açarları ilə; bilinmirsə null)
     */
    public function consume(Collection $lines, int $sign, string $type, array $ref = []): array
    {
        $menuIds = $lines->pluck('menu_item_id')->filter()->unique();
        if ($menuIds->isEmpty()) {
            return $lines->map(fn () => null)->all();
        }
        $tracked = MenuItem::whereIn('id', $menuIds)->where('track_stock', true)->get(['id', 'stock_cost'])->keyBy('id');
        $recipes = RecipeItem::whereIn('menu_item_id', $menuIds)->get();
        $prices = InventoryItem::whereIn('id', $recipes->pluck('inventory_item_id')->unique())->pluck('cost_price', 'id');

        $usage = [];
        $portions = [];
        $costs = [];
        foreach ($lines as $key => $line) {
            $readyItem = $tracked[$line->menu_item_id] ?? null;
            $unitCost = $readyItem ? (float) $readyItem->stock_cost : 0.0;
            $known = ! $readyItem || $readyItem->stock_cost !== null;
            $hasRecipe = (bool) $readyItem;
            if ($readyItem) {
                $portions[$line->menu_item_id] = ($portions[$line->menu_item_id] ?? 0) + $line->quantity;
            }
            $addonIds = collect($line->addons ?? [])->pluck('id')->all();
            foreach ($recipes->where('menu_item_id', $line->menu_item_id) as $recipe) {
                // Hazır porsiyanın öz xammalı hazırlananda silinib; yalnız əlavələrin (addon) xammalı indi silinir
                if ($readyItem && ! $recipe->addon_id) {
                    continue;
                }
                $applies = match (true) {
                    (bool) $recipe->addon_id => in_array($recipe->addon_id, $addonIds),
                    (bool) $recipe->variant_id => $recipe->variant_id === ($line->variant_id ?? null),
                    default => true,
                };
                if ($applies) {
                    $usage[$recipe->inventory_item_id] = ($usage[$recipe->inventory_item_id] ?? 0) + $recipe->quantity * $line->quantity;
                    $price = $prices[$recipe->inventory_item_id] ?? null;
                    $known = $known && $price !== null;
                    $unitCost += $recipe->quantity * (float) $price;
                    $hasRecipe = true;
                }
            }
            $costs[$key] = $hasRecipe && $known ? round((float) $unitCost, 4) : null;
        }

        $movementType = $sign < 0 ? $type : 'return';
        $refs = array_filter(['order_id' => $ref['order_id'] ?? null, 'staff_meal_id' => $ref['staff_meal_id'] ?? null]);
        foreach ($usage as $itemId => $qty) {
            $this->moveInventory($itemId, $sign * $qty, $movementType, $ref['note'] ?? null, $refs);
        }
        foreach ($portions as $menuItemId => $qty) {
            $this->moveReady($menuItemId, $sign * $qty, $movementType, $ref['note'] ?? null, $refs);
        }

        return $costs;
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
     * Anbara mal girişi. Alış qiyməti verilibsə, məhsulun mayası orta çəkili yenilənir:
     * (qalıq × köhnə qiymət + giriş × yeni qiymət) / (qalıq + giriş). Qalıq mənfi/sıfırdırsa — yeni qiymət.
     */
    public function receive(InventoryItem $item, float $quantity, ?float $unitCost, ?string $note = null): void
    {
        DB::transaction(function () use ($item, $quantity, $unitCost, $note) {
            $item = InventoryItem::whereKey($item->id)->lockForUpdate()->first();
            if ($unitCost !== null) {
                $onHand = (float) $item->quantity;
                $item->cost_price = $onHand > 0 && $item->cost_price !== null
                    ? round(($onHand * (float) $item->cost_price + $quantity * $unitCost) / ($onHand + $quantity), 4)
                    : $unitCost;
                $item->save();
            }
            $this->moveInventory($item->id, $quantity, 'in', $note, [], $unitCost);
        });
    }

    /** Anbardan əl ilə çıxış / israf (cari maya ilə qeydə alınır). */
    public function issue(InventoryItem $item, float $quantity, string $type, ?string $note = null): void
    {
        DB::transaction(fn () => $this->moveInventory($item->id, -$quantity, $type, $note));
    }

    /**
     * Yeməyin əsas reseptinin (variant/əlavəsiz) 1 porsiyasının mayası.
     *
     * @return array{cost: ?float, missing: list<string>} missing — qiyməti yazılmamış xammal
     */
    public function recipeCost(MenuItem $item): array
    {
        $recipes = $item->relationLoaded('recipeItems')
            ? $item->recipeItems->whereNull('variant_id')->whereNull('addon_id')
            : $item->recipeItems()->whereNull('variant_id')->whereNull('addon_id')->with('inventoryItem')->get();
        if ($recipes->isEmpty()) {
            return ['cost' => null, 'missing' => []];
        }
        $missing = $recipes->filter(fn ($r) => $r->inventoryItem->cost_price === null)->map(fn ($r) => $r->inventoryItem->title)->values()->all();

        return [
            'cost' => $missing ? null : round($recipes->sum(fn ($r) => $r->quantity * $r->inventoryItem->cost_price), 4),
            'missing' => $missing,
        ];
    }

    /**
     * Porsiya hazırlanır: reseptin əsas xammalı × porsiya anbardan silinir, hazır stok artır,
     * porsiyanın mayası partiyanın mayası ilə orta çəkili yenilənir.
     *
     * @return list<string> anbarda mənfiyə düşən xammal (xəbərdarlıq üçün)
     */
    public function produce(MenuItem $item, float $portions, ?string $note = null): array
    {
        return DB::transaction(function () use ($item, $portions, $note) {
            $item = MenuItem::whereKey($item->id)->lockForUpdate()->first();
            $label = "Hazırlandı: {$item->title} × ".qty($portions).($note ? " — {$note}" : '');
            $recipes = RecipeItem::with('inventoryItem')->where('menu_item_id', $item->id)->whereNull('variant_id')->whereNull('addon_id')->get();
            $batchCost = $recipes->isNotEmpty() && $recipes->every(fn ($r) => $r->inventoryItem->cost_price !== null)
                ? $recipes->sum(fn ($r) => $r->quantity * $portions * $r->inventoryItem->cost_price)
                : null;

            $short = [];
            foreach ($recipes as $recipe) {
                $balance = $this->moveInventory($recipe->inventory_item_id, -$recipe->quantity * $portions, 'production', $label);
                if ($balance < 0) {
                    $short[] = $recipe->inventoryItem->title;
                }
            }

            $onHand = max(0, (float) $item->stock_quantity);
            $item->stock_cost = match (true) {
                $batchCost === null => null, // partiyanın mayası bilinmir — orta maya da etibarsızdır
                $onHand > 0 && $item->stock_cost !== null => round(($onHand * (float) $item->stock_cost + $batchCost) / ($onHand + $portions), 4),
                default => round($batchCost / $portions, 4),
            };
            $item->save();
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

    /**
     * @param  array{order_id?: int, staff_meal_id?: int}  $refs
     * @param  ?float  $unitCost  girişin alış qiyməti; verilməyibsə — məhsulun cari mayası
     */
    protected function moveInventory(int $itemId, float $quantity, string $type, ?string $note, array $refs = [], ?float $unitCost = null): float
    {
        InventoryItem::whereKey($itemId)->increment('quantity', $quantity);
        StockMovement::create([
            'inventory_item_id' => $itemId,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost ?? InventoryItem::whereKey($itemId)->value('cost_price'),
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
