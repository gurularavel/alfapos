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
