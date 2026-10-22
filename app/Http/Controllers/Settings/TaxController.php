<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    public function index(Tenancy $tenancy)
    {
        return view('settings.taxes', ['taxes' => Tax::orderBy('title')->get(), 'store' => $tenancy->tenant()]);
    }

    /** Vergi və servis haqqının ümumi açarları. */
    public function updateCharges(Request $request, Tenancy $tenancy)
    {
        $data = $request->validate([
            'service_charge' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tenancy->tenant()->update($data + [
            'is_tax_enabled' => $request->boolean('is_tax_enabled'),
            'is_service_charge_enabled' => $request->boolean('is_service_charge_enabled'),
            'service_charge_dinein_only' => $request->boolean('service_charge_dinein_only'),
        ]);

        return back()->with('success', 'Vergi və servis haqqı ayarları yeniləndi.');
    }

    public function store(Request $request)
    {
        Tax::create($this->validated($request));

        return back()->with('success', 'Vergi əlavə edildi.');
    }

    public function update(Request $request, Tax $tax)
    {
        $tax->update($this->validated($request));

        return back()->with('success', 'Vergi yeniləndi.');
    }

    public function destroy(Tax $tax)
    {
        $tax->delete();

        return back()->with('success', 'Vergi silindi.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['required', Rule::in(['inclusive', 'exclusive'])],
        ]);
    }
}
