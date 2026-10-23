<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Cari sorğunun tenant-ı. Middleware (auth istifadəçisi) və ya public QR route-u tərəfindən təyin olunur.
 */
class Tenancy
{
    protected ?Tenant $tenant = null;

    /**
     * Tenant-ın saat qurşağı da tətbiq olunur: QR menyu, müştəri ekranı, onlayn ödəniş kimi ictimai səhifələrdə də
     * sifarişin vaxtı, token nömrəsi (gündəlik) və "bu gün" panel ilə eyni olsun (əks halda 00:00–04:00 arası UTC-yə düşürdü).
     */
    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        if ($tenant?->timezone) {
            config(['app.timezone' => $tenant->timezone]);
            date_default_timezone_set($tenant->timezone);
        }
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }
}
