<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OnlinePayment;
use App\Services\KapitalBank;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Onlayn ödəniş (Kapital Bank e-commerce): rejim (test / canlı), merchant login və parol, bağlantı yoxlaması. */
class OnlinePaymentController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit(): View
    {
        $tenant = $this->tenancy->tenant();

        return view('settings.online-payment', [
            'settings' => $tenant->kapitalbank(),
            'payments' => OnlinePayment::with('table:id,title', 'invoice:id,number', 'requester:id,name')->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->tenancy->tenant();
        $current = $tenant->kapitalbank();
        $data = $request->validate([
            'mode' => ['required', Rule::in(['test', 'live'])],
            'login' => ['nullable', 'string', 'max:120', Rule::requiredIf($request->input('mode') === 'live' && blank($current['login']))],
            // Boş qoyulsa köhnə parol saxlanılır (parol formada heç vaxt göstərilmir)
            'password' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('mode') === 'live' && blank($current['password']))],
        ], [
            'login.required' => 'Canlı rejim üçün Kapital Bank-ın verdiyi merchant login-i daxil edin.',
            'password.required' => 'Canlı rejim üçün merchant parolunu daxil edin.',
        ]);

        $tenant->update(['kapitalbank_settings' => [
            'enabled' => $request->boolean('enabled'),
            'mode' => $data['mode'],
            'login' => filled($data['login'] ?? null) ? $data['login'] : $current['login'],
            'password' => filled($data['password'] ?? null) ? $data['password'] : $current['password'],
        ]]);

        return back()->with('success', 'Onlayn ödəniş ayarları saxlanıldı.');
    }

    /** Giriş məlumatlarını yoxlayır: bankda 0.01 ₼-lıq sifariş yaradılır (ödənilmir). */
    public function test(): RedirectResponse
    {
        try {
            KapitalBank::for($this->tenancy->tenant())->testConnection();
        } catch (RuntimeException $e) {
            return back()->withErrors(['connection' => $e->getMessage()]);
        }

        return back()->with('success', 'Kapital Bank ilə bağlantı uğurludur.');
    }
}
