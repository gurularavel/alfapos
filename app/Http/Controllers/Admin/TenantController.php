<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::with('plan')->withCount('users')
            ->addSelect(['revenue_30d' => Invoice::withoutGlobalScopes()->selectRaw('COALESCE(SUM(total),0)')
                ->whereColumn('invoices.tenant_id', 'tenants.id')->where('status', 'paid')->where('created_at', '>=', now()->subDays(30))])
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'stats' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('status', 'active')->where('subscription_ends_at', '>', now())->count(),
                'trial' => Tenant::where('status', 'trial')->where('trial_ends_at', '>', now())->count(),
                'mrr' => (float) Subscription::where('ends_at', '>', now())->sum('amount'),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.tenants.form', ['tenant' => new Tenant, 'plans' => Plan::orderBy('sort')->get()]);
    }

    public function store(Request $request, TenantProvisioner $provisioner)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::min(8)],
        ]);

        $user = $provisioner->create($data['name'], $data['owner_name'], $data['email'], $data['password'], $data['phone'] ?? null);

        return redirect()->route('admin.tenants.edit', $user->tenant_id)->with('success', 'Biznes yaradıldı.');
    }

    public function edit(Tenant $tenant)
    {
        return view('admin.tenants.form', [
            'tenant' => $tenant->load('users', 'subscriptions.plan'),
            'plans' => Plan::orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $tenant->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'status' => ['required', Rule::in(['trial', 'active', 'expired', 'suspended'])],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
        ]));

        return back()->with('success', 'Biznes yeniləndi.');
    }

    /** Ödənişi əl ilə qeyd edib abunəliyi uzadır (gateway inteqrasiyasına qədər). */
    public function extend(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $plan = Plan::findOrFail($data['plan_id']);

        DB::transaction(function () use ($tenant, $plan, $data) {
            $start = $tenant->subscription_ends_at?->isFuture() ? $tenant->subscription_ends_at : now();
            $end = $start->copy()->addMonths((int) $data['months']);
            Subscription::create([
                'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
                'amount' => $plan->price * $data['months'], 'gateway' => 'manual', 'reference' => $data['reference'] ?? null,
                'starts_at' => $start, 'ends_at' => $end,
            ]);
            $tenant->update(['plan_id' => $plan->id, 'status' => 'active', 'subscription_ends_at' => $end]);
        });

        return back()->with('success', 'Abunəlik uzadıldı.');
    }
}
