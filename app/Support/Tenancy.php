<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Cari sorğunun tenant-ı. Middleware (auth istifadəçisi) və ya public QR route-u tərəfindən təyin olunur.
 */
class Tenancy
{
    protected ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
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
