<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sifariş sətirinin ləğvi (ödənişdən əvvəl) və ya qaytarılması (ödənişdən sonra, pul geri verilir). */
class ItemReturn extends Model
{
    use BelongsToTenant;

    public const KINDS = ['void' => 'Ləğv', 'refund' => 'Qaytarma'];

    public const REASONS = [
        'not_liked' => 'Müştəri bəyənmədi',
        'quality' => 'Keyfiyyət (soyuq, bişməyib, dad)',
        'foreign' => 'Yad cisim',
        'wrong' => 'Səhv sifariş vurulub',
        'late' => 'Çox gecikdi',
        'customer_left' => 'Müştəri getdi',
        'other' => 'Digər',
    ];

    public const STOCK_ACTIONS = ['restore' => 'Stoka qaytarıldı', 'waste' => 'İsraf (hazırlanmışdı)'];

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'cost' => 'decimal:2', 'after_bill' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function orderCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'order_creator_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
