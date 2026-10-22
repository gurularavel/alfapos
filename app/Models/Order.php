<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;

    public const DELIVERY_TYPES = ['dinein' => 'Zalda', 'takeaway' => 'Özü aparır', 'delivery' => 'Çatdırılma'];

    public const STATUSES = [
        'draft' => 'Qaralama',
        'created' => 'Yeni',
        'preparing' => 'Hazırlanır',
        'ready' => 'Hazırdır',
        'completed' => 'Tamamlandı',
        'cancelled' => 'Ləğv edildi',
    ];

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['discount_value' => 'decimal:2', 'no_service_charge' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(StoreTable::class, 'store_table_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['created', 'preparing', 'ready']);
    }
}
