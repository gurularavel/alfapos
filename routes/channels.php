<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Hər biznesin bütün ekranları (POS, Sifarişlər, Mətbəx) öz kanalını dinləyir; başqa biznes qoşula bilməz
Broadcast::channel('tenant.{tenantId}', function (User $user, int $tenantId) {
    return $user->tenant_id === $tenantId && $user->is_active;
});
