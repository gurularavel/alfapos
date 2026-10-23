<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Çekin ödəniş sətri (bölünmüş ödənişdə bir neçə). tendered — nağdda müştərinin verdiyi məbləğ. */
class InvoicePayment extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tendered' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    /** Nağd ödənişdə müştəriyə qaytarılan qalıq. */
    public function change(): float
    {
        return $this->tendered !== null ? max(0, round((float) $this->tendered - (float) $this->amount, 2)) : 0.0;
    }
}
