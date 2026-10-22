<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CustomerDisplay extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'token'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (CustomerDisplay $d) => $d->token ??= Str::random(40));
        static::deleted(fn (CustomerDisplay $d) => Cache::forget($d->stateKey()));
    }

    public function url(): string
    {
        return route('display.show', $this->token);
    }

    public function channel(): string
    {
        return 'display.'.$this->token;
    }

    public function stateKey(): string
    {
        return 'customer-display:'.$this->id;
    }

    /** Ekrana göstəriləcək son vəziyyət (Reverb olmayanda ekran bunu soruşur). */
    public function state(): array
    {
        return Cache::get($this->stateKey(), ['mode' => 'idle']);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at?->gt(now()->subMinute()) ?? false;
    }
}
