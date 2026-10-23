<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant;

    /**
     * Hər çekə bir dəfə qoşulur: çekdəki sifarişlərin növü, masası və sayı (alias "io").
     * Çekin məbləğini sifariş sətirlərinə qoşub toplamaq əlavə sifarişli masalarda onu təkrarlayardı.
     * Alt sorğu yalnız bu ana qədər süzülmüş çeklərin sifarişlərini qruplaşdırır (əks halda biznesin bütün sifarişlərini).
     */
    public function scopeWithOrderInfo(Builder $query): void
    {
        $invoiceIds = (clone $query)->select('invoices.id')->toBase();

        $query->joinSub(
            Order::query()->whereIn('invoice_id', $invoiceIds)->groupBy('invoice_id')
                ->selectRaw('invoice_id, MIN(delivery_type) as delivery_type, MIN(store_table_id) as store_table_id, COUNT(*) as orders_count'),
            'io', 'io.invoice_id', '=', 'invoices.id',
        );
    }

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'sub_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'service_charge_total' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_topup' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Əsas (ən böyük məbləğli) ödəniş növü; bütün sətirlər — payments(). */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
