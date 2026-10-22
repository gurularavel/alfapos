<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_enabled' => 'boolean', 'track_stock' => 'boolean', 'stock_quantity' => 'decimal:2', 'stock_min' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(MenuItemAddon::class);
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function readyStockMovements(): HasMany
    {
        return $this->hasMany(ReadyStockMovement::class);
    }

    /** Hazır stokla izlənən məhsulun vəziyyəti: in | low | out (izlənmirsə null). */
    public function readyStockStatus(): ?string
    {
        return match (true) {
            ! $this->track_stock => null,
            $this->stock_quantity <= 0 => 'out',
            $this->stock_quantity <= $this->stock_min => 'low',
            default => 'in',
        };
    }
}
