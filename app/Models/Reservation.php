<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['booked' => 'Rezerv', 'arrived' => 'Gəldi', 'cancelled' => 'Ləğv'];

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['reserved_at' => 'datetime'];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(StoreTable::class, 'store_table_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
