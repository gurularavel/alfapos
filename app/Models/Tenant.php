<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'is_qr_menu_enabled' => 'boolean',
            'is_qr_order_enabled' => 'boolean',
            'is_feedback_enabled' => 'boolean',
            'print_settings' => 'array',
            'terminal_settings' => 'array',
            'service_charge' => 'decimal:2',
            'daily_sales_goal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            $tenant->slug ??= Str::slug($tenant->name).'-'.Str::lower(Str::random(4));
            $tenant->qr_code ??= Str::lower(Str::random(16));
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        return match ($this->status) {
            'trial' => $this->trial_ends_at?->isFuture() ?? false,
            'active' => $this->subscription_ends_at?->isFuture() ?? false,
            default => false,
        };
    }

    public function expiresAt(): ?\Illuminate\Support\Carbon
    {
        return $this->status === 'trial' ? $this->trial_ends_at : $this->subscription_ends_at;
    }

    public function printSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->print_settings, $key, $default);
    }

    public const TERMINAL_DEFAULTS = ['images' => [], 'lock_timeout' => 20, 'lock_after_order' => true];

    public function terminalSetting(string $key): mixed
    {
        return data_get($this->terminal_settings, $key, self::TERMINAL_DEFAULTS[$key] ?? null);
    }

    /** @return list<string> ekran qoruyucu şəkillərinin public URL-ləri */
    public function screensaverUrls(): array
    {
        return array_map(fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p), $this->terminalSetting('images'));
    }
}
