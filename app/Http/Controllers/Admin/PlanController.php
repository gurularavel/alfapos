<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', ['plans' => Plan::withCount('tenants')->orderBy('sort')->get()]);
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan(['is_active' => true, 'interval' => 'month', 'currency' => 'AZN'])]);
    }

    public function store(Request $request)
    {
        Plan::create($this->validated($request));

        return redirect()->route('admin.plans.index')->with('success', 'Plan yaradıldı.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('admin.plans.index')->with('success', 'Plan yeniləndi.');
    }

    public function destroy(Plan $plan)
    {
        abort_if($plan->tenants()->exists(), 422, 'Bu plana bağlı bizneslər var.');
        $plan->delete();

        return back()->with('success', 'Plan silindi.');
    }

    protected function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'interval' => ['required', Rule::in(['month', 'year'])],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_menu_items' => ['nullable', 'integer', 'min:1'],
            'max_tables' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'string'],
            'sort' => ['nullable', 'integer'],
        ]);
        $data['slug'] = $plan?->slug ?? Str::slug($data['name']).'-'.Str::lower(Str::random(3));
        $data['features'] = array_values(array_filter(array_map('trim', explode("\n", $data['features'] ?? ''))));
        $data['is_active'] = $request->boolean('is_active');
        $data['sort'] ??= 0;

        return $data;
    }
}
