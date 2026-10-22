<?php

namespace App\Http\Controllers;

use App\Events\DisplayUpdated;
use App\Models\CustomerDisplay;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class CustomerDisplayController extends Controller
{
    public const MODES = ['idle', 'cart', 'paid', 'sent'];

    /** POS səbətin vəziyyətini seçilmiş müştəri ekranına göndərir. */
    public function push(Request $request, CustomerDisplay $display, Tenancy $tenancy)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(self::MODES)],
            'items' => ['array', 'max:200'],
            'items.*.title' => ['required', 'string', 'max:120'],
            'items.*.subtitle' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.total' => ['required', 'numeric'],
            'totals' => ['array'],
            'totals.sub' => ['numeric'],
            'totals.discount' => ['numeric'],
            'totals.tax' => ['numeric'],
            'totals.service' => ['numeric'],
            'totals.deposit' => ['numeric'],
            'totals.topup' => ['numeric'],
            'totals.total' => ['numeric'],
            'token_no' => ['nullable', 'integer'],
            'customer' => ['nullable', 'string', 'max:120'],
        ]);

        $state = [
            'mode' => $data['mode'],
            'items' => array_values($data['items'] ?? []),
            'totals' => array_map(fn ($v) => round((float) $v, 2), $data['totals'] ?? []),
            'token_no' => $data['token_no'] ?? null,
            'customer' => $data['customer'] ?? null,
            'currency' => $tenancy->tenant()->currency,
            'at' => now()->timestamp,
        ];

        Cache::put($display->stateKey(), $state, now()->addDay());
        try {
            broadcast(new DisplayUpdated($display->channel(), $state));
        } catch (Throwable $e) {
            // Reverb işləmirsə ekran vəziyyəti polling ilə götürəcək
            report($e);
        }

        return response()->noContent();
    }

    /** Müştəriyə baxan ekran (girişsiz). */
    public function show(string $token, Tenancy $tenancy)
    {
        $display = $this->resolve($token, $tenancy);
        $tenant = $tenancy->tenant();

        return view('display.show', [
            'display' => $display,
            'tenant' => $tenant,
            'config' => [
                'channel' => $display->channel(),
                'stateUrl' => route('display.state', $token),
                'state' => $display->state(),
                'currency' => $tenant->currency,
                'store' => ['name' => $tenant->name, 'logo' => $tenant->logo ? Storage::disk('public')->url($tenant->logo) : null],
                'images' => $tenant->screensaverUrls(),
                'qrMenu' => $tenant->is_qr_menu_enabled ? route('qr.show', $tenant->qr_code) : null,
            ],
        ]);
    }

    /** Ehtiyat: Reverb bağlantısı olmayanda ekran son vəziyyəti buradan soruşur. */
    public function state(string $token, Tenancy $tenancy)
    {
        return response()->json($this->resolve($token, $tenancy)->state());
    }

    protected function resolve(string $token, Tenancy $tenancy): CustomerDisplay
    {
        $display = CustomerDisplay::withoutGlobalScopes()->where('token', $token)->with('tenant')->firstOrFail();
        abort_unless($display->tenant->hasActiveSubscription(), 404);
        $tenancy->set($display->tenant);

        if (! $display->last_seen_at || $display->last_seen_at->lt(now()->subSeconds(30))) {
            $display->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $display;
    }
}
