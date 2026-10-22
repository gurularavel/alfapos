<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant;

    public const TYPES = ['in' => 'Giriş', 'out' => 'Çıxış', 'wastage' => 'İsraf', 'sale' => 'Satış', 'production' => 'Hazırlama', 'staff' => 'Personal yeməyi', 'return' => 'Qaytarma', 'supplier' => 'Təchizatçıya qaytarma'];

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
