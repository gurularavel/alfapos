<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Paneldəki stok bildirişi: anbar xammalı və ya hazır yemək azalıb / bitib. Stok bərpa olunanda avtomatik bağlanır. */
class StockAlert extends Model
{
    use BelongsToTenant;

    public const SOURCES = ['inventory' => 'Anbar', 'ready' => 'Hazır yemək'];

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'read_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public function url(): string
    {
        return $this->source === 'ready' ? route('ready-stock.index') : route('inventory.index', ['status' => $this->level === 'out' ? 'out' : 'low']);
    }
}
