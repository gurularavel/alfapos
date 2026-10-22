<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['tenant_id', 'name', 'email', 'password', 'role', 'scopes', 'designation', 'phone', 'is_active'])]
#[Hidden(['password', 'pin_hash', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** İşçilərə verilə bilən icazələr (Restro PRO scope-ları əsasında). */
    public const SCOPES = [
        'DASHBOARD' => 'Dashboard',
        'POS' => 'POS',
        'ORDERS' => 'Sifarişlər',
        'KITCHEN' => 'Mətbəx',
        'RESERVATIONS' => 'Rezervlər',
        'CUSTOMERS' => 'Müştərilər',
        'PAYMENTS' => 'Ödəniş qəbulu',
        'INVOICES' => 'Fakturalar',
        'INVENTORY' => 'Anbar',
        'STAFF_MEALS' => 'Personal yeməyi',
        'FEEDBACK' => 'Rəylər',
        'REPORTS' => 'Hesabatlar',
        'SETTINGS' => 'Ayarlar',
        'APPROVE' => 'Təsdiq: ləğv, qaytarma, endirim',
    ];

    /** Biznes daxilindəki rollar və hər rol üçün təklif olunan icazələr (formda avtomatik doldurulur). */
    public const ROLES = [
        'admin' => ['Admin (sahibkar)', []],
        'manager' => ['Menecer', ['DASHBOARD', 'POS', 'ORDERS', 'KITCHEN', 'PAYMENTS', 'INVOICES', 'CUSTOMERS', 'RESERVATIONS', 'INVENTORY', 'STAFF_MEALS', 'REPORTS', 'APPROVE']],
        'cashier' => ['Kassir', ['POS', 'ORDERS', 'PAYMENTS', 'INVOICES', 'CUSTOMERS', 'RESERVATIONS']],
        'waiter' => ['Ofisiant', ['POS', 'ORDERS', 'RESERVATIONS']],
        'chef' => ['Aşpaz', ['KITCHEN']],
        'user' => ['Digər', []],
    ];

    protected $attributes = [
        'role' => 'user',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'scopes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(StoreTable::class, 'table_user');
    }

    /** İşçinin işləyə biləcəyi terminallar; boşdursa bütün terminallarda işləyə bilər. */
    public function terminals(): BelongsToMany
    {
        return $this->belongsToMany(Terminal::class);
    }

    public function canUseTerminal(?Terminal $terminal): bool
    {
        if (! $terminal || $this->isAdmin()) {
            return true;
        }
        $ids = $this->terminals()->pluck('terminals.id');

        return $ids->isEmpty() || $ids->contains($terminal->id);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasScope(string $scope): bool
    {
        return $this->isAdmin() || in_array($scope, $this->scopes ?? [], true);
    }

    public function roleLabel(): string
    {
        return match (true) {
            $this->isSuperAdmin() => 'Superadmin',
            default => self::ROLES[$this->role][0] ?? ($this->designation ?: 'İşçi'),
        };
    }

    // ---- PIN (POS terminalı üçün) ----

    public static function pinHash(int $tenantId, string $pin): string
    {
        return hash_hmac('sha256', $tenantId.':'.$pin, (string) config('app.key'));
    }

    public function setPin(?string $pin): void
    {
        $this->pin_hash = $pin === null || $pin === '' ? null : self::pinHash($this->tenant_id, $pin);
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    public static function findByPin(int $tenantId, string $pin): ?self
    {
        return static::where('tenant_id', $tenantId)
            ->where('pin_hash', self::pinHash($tenantId, $pin))
            ->where('is_active', true)
            ->first();
    }

    /** Girişdən sonra istifadəçinin açıla biləcəyi ilk səhifə. */
    public function homeRoute(): string
    {
        if ($this->isSuperAdmin()) {
            return route('admin.tenants.index');
        }

        foreach (['DASHBOARD' => 'dashboard', 'POS' => 'pos', 'ORDERS' => 'orders.index', 'KITCHEN' => 'kitchen'] as $scope => $route) {
            if ($this->hasScope($scope)) {
                return route($route);
            }
        }

        return route('profile');
    }
}
