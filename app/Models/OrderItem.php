<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public const STATUSES = [
        'created' => 'Gözləyir',
        'preparing' => 'Hazırlanır',
        'ready' => 'Hazırdır',
        'completed' => 'Verildi',
        'cancelled' => 'Ləğv',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['addons' => 'array', 'price' => 'decimal:2', 'cost' => 'decimal:4', 'tax_rate' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function lineTotal(): float
    {
        return round((float) $this->price * $this->quantity, 2);
    }
}
