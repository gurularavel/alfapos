<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        return view('settings.store', ['store' => $this->tenancy->tenant()]);
    }

    public function update(Request $request)
    {
        $tenant = $this->tenancy->tenant();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'currency' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'timezone'],
            'service_charge' => ['required', 'numeric', 'min:0', 'max:100'],
            'daily_sales_goal' => ['required', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);
        foreach (['is_qr_menu_enabled', 'is_qr_order_enabled', 'is_feedback_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        if ($request->hasFile('logo')) {
            if ($tenant->logo) {
                Storage::disk('public')->delete($tenant->logo);
            }
            $data['logo'] = $request->file('logo')->store("tenants/{$tenant->id}", 'public');
        } else {
            unset($data['logo']);
        }

        $tenant->update($data);

        return back()->with('success', 'Mağaza məlumatları yeniləndi.');
    }

    public function editPrint()
    {
        return view('settings.print', ['store' => $this->tenancy->tenant()]);
    }

    public function updatePrint(Request $request)
    {
        $data = $request->validate([
            'paper_width' => ['required', 'in:58,80'],
            'header' => ['nullable', 'string', 'max:255'],
            'footer' => ['nullable', 'string', 'max:255'],
        ]);
        foreach (['show_store_details', 'show_customer_details', 'show_notes', 'print_token', 'kot_show_prices', 'auto_print'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $this->tenancy->tenant()->update(['print_settings' => $data]);

        return back()->with('success', 'Çap ayarları yeniləndi.');
    }
}
