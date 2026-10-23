<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Masa hesabının onlayn ödəniş sorğusu. status: pending (müştəri ödəməyib) | paid | cancelled.
 * bank_* sahələri bankdakı son cəhdi (Kapital Bank order) göstərir; uğursuz cəhddən sonra sorğu "pending" qalır.
 */
class OnlinePayment extends Model
{
    use BelongsToTenant;

    /** Bankın "ödənildi" statusu. */
    public const BANK_PAID = 'FullyPaid';

    /** Ödəniş alınmayıb — müştəri yenidən cəhd edə bilər. */
    public const BANK_FAILED = ['Cancelled', 'Rejected', 'Refused', 'Expired', 'Declined', 'Voided', 'Closed'];

    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['bank_password'];

    protected $attributes = ['status' => 'pending', 'currency' => 'AZN'];

    protected static function booted(): void
    {
        static::creating(fn (OnlinePayment $p) => $p->token ??= Str::random(40));
    }

    protected function casts(): array
    {
        return [
            'order_ids' => 'array',
            'amount' => 'decimal:2',
            'bank_password' => 'encrypted',
            'is_test' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(StoreTable::class, 'store_table_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
