<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadyStockMovement extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'production' => 'Hazırlandı',
        'sale' => 'Satış',
        'staff' => 'Personal yeməyi',
        'wastage' => 'İsraf',
        'count' => 'Sayım',
        'return' => 'Qaytarma',
    ];

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'balance' => 'decimal:2'];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
