<?php

namespace App\Http\Controllers;

use App\Models\OnlinePayment;
use App\Models\StoreTable;
use App\Models\Tenant;
use App\Services\OnlinePaymentService;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Müştərinin telefonu: masanın ödəniş QR-ı /p/{tenant qr_code}/{masa qr_token}.
 * Kassir hesabı göndəribsə məbləğ göstərilir, "Ödə" → Kapital Bank ödəniş səhifəsi → /odenis/{token} (nəticə).
 */
class TablePaymentController extends Controller
{
    public function __construct(protected Tenancy $tenancy, protected OnlinePaymentService $payments) {}

    public function show(string $code, string $token): View
    {
        [$tenant, $table] = $this->resolve($code, $token);
        $payment = $this->current($table);

        // Müştəri ödəyib QR-ı yenidən açıbsa (bankdan qayıtmayıbsa) statusu yoxlayırıq
        if ($payment?->bank_order_id) {
            $payment = rescue(fn () => $this->payments->sync($payment), $payment, report: false);
        }
        // Hesab bu arada kassada bağlanıbsa köhnə məbləğ göstərilməsin
        if ($payment?->status === 'pending' && $this->payments->unpaidOrders($table)->isEmpty()) {
            $payment->update(['status' => 'cancelled', 'note' => 'Hesab kassada bağlanıb']);
            $payment = null;
        }

        return view('pay.show', [
            'tenant' => $tenant,
            'table' => $table,
            'payment' => $payment?->status === 'pending' ? $payment : null,
            'paid' => $payment?->status === 'paid' ? $payment : $this->recentlyPaid($table),
            'orders' => $payment?->status === 'pending' ? $this->payments->unpaidOrders($table) : collect(),
        ]);
    }

    public function checkout(string $code, string $token): RedirectResponse
    {
        [, $table] = $this->resolve($code, $token);
        $payment = $this->current($table);
        abort_unless($payment, 404, 'Bu masa üçün ödəniləcək hesab yoxdur.');

        return redirect()->away($this->payments->checkout($payment));
    }

    /** Bankdan qayıdış (hppRedirectUrl). Linkdəki statusa etibar edilmir — bankın API-si ilə yoxlanılır. */
    public function return(string $token): View
    {
        $payment = OnlinePayment::withoutGlobalScopes()->where('token', $token)->firstOrFail();
        $this->tenancy->set($payment->tenant);
        $payment = rescue(fn () => $this->payments->sync($payment), $payment, report: false);

        return view('pay.result', [
            'tenant' => $payment->tenant,
            'payment' => $payment,
            'table' => $payment->table,
            // Preparing: müştəri bank səhifəsindən ödəmədən qayıdıb; digər aralıq statuslarda səhifə özü yenidən yoxlayır
            'failed' => $payment->status === 'pending'
                && (in_array($payment->bank_status, [...OnlinePayment::BANK_FAILED, 'Preparing', null], true)),
        ]);
    }

    /** @return array{0: Tenant, 1: StoreTable} */
    protected function resolve(string $code, string $token): array
    {
        $tenant = Tenant::where('qr_code', $code)->firstOrFail();
        abort_unless($tenant->hasActiveSubscription() && $tenant->onlinePaymentsEnabled(), 404);
        $this->tenancy->set($tenant);

        return [$tenant, StoreTable::where('qr_token', $token)->firstOrFail()];
    }

    protected function current(StoreTable $table): ?OnlinePayment
    {
        return OnlinePayment::where('store_table_id', $table->id)->pending()->latest('id')->first();
    }

    /** Son yarım saatda ödənilmiş hesab — müştəri QR-ı yenidən açanda "ödənildi" görsün. */
    protected function recentlyPaid(StoreTable $table): ?OnlinePayment
    {
        return OnlinePayment::where('store_table_id', $table->id)->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMinutes(30))->latest('id')->first();
    }
}
