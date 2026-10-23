<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\StockAlert;
use Illuminate\Support\Collection;

/**
 * Stok bildirişlərini cari qalıqlarla uyğunlaşdırır: azalan / bitən məhsul üçün bildiriş açılır,
 * "azalıb" → "bitib" keçidində yeni (oxunmamış) bildiriş gəlir, stok bərpa olunanda bildiriş bağlanır.
 * Qalıqdan hesablandığı üçün stoku dəyişən hər yol (satış, hazırlama, əl ilə düzəliş) avtomatik nəzərə alınır.
 */
class StockAlertService
{
    public function sync(): void
    {
        $current = $this->currentLevels();
        $open = StockAlert::open()->get()->keyBy(fn (StockAlert $a) => $a->source.':'.$a->item_id);

        foreach ($current as $key => $state) {
            $alert = $open->pull($key);
            if ($alert && $alert->level === $state['level']) {
                $alert->update(['quantity' => $state['quantity'], 'title' => $state['title']]);

                continue;
            }
            $alert?->update(['resolved_at' => now()]); // səviyyə dəyişib (azalıb → bitib): köhnəni bağlayıb yenisini açırıq
            StockAlert::create($state);
        }

        // Siyahıda qalmayanlar — stok bərpa olunub və ya məhsul silinib
        if ($open->isNotEmpty()) {
            StockAlert::whereIn('id', $open->pluck('id'))->update(['resolved_at' => now()]);
        }
    }

    /** @return Collection<string, array{source: string, item_id: int, title: string, level: string, quantity: float, unit: ?string}> */
    protected function currentLevels(): Collection
    {
        $inventory = InventoryItem::whereColumn('quantity', '<=', 'min_quantity')->orWhere('quantity', '<=', 0)->get()
            ->map(fn (InventoryItem $i) => [
                'source' => 'inventory', 'item_id' => $i->id, 'title' => $i->title, 'level' => $i->quantity <= 0 ? 'out' : 'low',
                'quantity' => (float) $i->quantity, 'unit' => InventoryItem::UNITS[$i->unit] ?? $i->unit,
            ]);
        $ready = MenuItem::where('track_stock', true)->where(fn ($q) => $q->whereColumn('stock_quantity', '<=', 'stock_min')->orWhere('stock_quantity', '<=', 0))->get()
            ->map(fn (MenuItem $m) => [
                'source' => 'ready', 'item_id' => $m->id, 'title' => $m->title, 'level' => $m->stock_quantity <= 0 ? 'out' : 'low',
                'quantity' => (float) $m->stock_quantity, 'unit' => 'porsiya',
            ]);

        return $inventory->concat($ready)->keyBy(fn ($s) => $s['source'].':'.$s['item_id']);
    }
}
