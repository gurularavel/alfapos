<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\Tenancy;

class SubscriptionController extends Controller
{
    public function show(Tenancy $tenancy)
    {
        $tenant = $tenancy->tenant();
        abort_unless($tenant, 404);

        return view('subscription', [
            'tenant' => $tenant->load('plan', 'subscriptions.plan'),
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get(),
        ]);
    }
}
