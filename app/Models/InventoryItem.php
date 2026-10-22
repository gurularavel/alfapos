<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use BelongsToTenant;

    public const UNITS = ['pc' => 'ədəd', 'g' => 'q', 'kg' => 'kq', 'ml' => 'ml', 'l' => 'l'];

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'min_quantity' => 'decimal:4', 'cost_price' => 'decimal:4'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockStatus(): string
    {
        return match (true) {
            $this->quantity <= 0 => 'out',
            $this->quantity <= $this->min_quantity => 'low',
            default => 'in',
        };
    }
}
