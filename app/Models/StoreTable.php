<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StoreTable extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['deposit' => 'decimal:2'];
    }

    public function hasDeposit(): bool
    {
        return (float) $this->deposit > 0;
    }

    /** Depozit qaydası: hesab depozitdən azdırsa fərq əlavə olunur. */
    public static function depositTopup(float $total, float $deposit): float
    {
        return $deposit > 0 ? round(max(0, $deposit - $total), 2) : 0.0;
    }

    protected static function booted(): void
    {
        static::creating(fn (StoreTable $table) => $table->qr_token ??= Str::lower(Str::random(16)));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function waiters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'table_user');
    }

    public function label(): string
    {
        return $this->title.($this->floor ? ' · '.$this->floor : '');
    }
}
