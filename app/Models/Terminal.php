<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Filialdakı kassa / POS cihazı. Sifariş və çeklər hansı terminaldan verildiyini saxlayır. */
class Terminal extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Bu terminalda işləməyə icazəsi olan işçilər (işçinin heç bir terminalı yoxdursa — hamısında işləyir). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function label(): string
    {
        return $this->name.' · '.$this->branch?->name;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at?->gt(now()->subMinutes(2)) ?? false;
    }
}
