<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Masa hesabının onlayn ödənişi:
 *  1. send()     — kassir masanın açıq hesabını göndərir (məbləğ serverdə, çek ilə eyni qayda ilə hesablanır)
 *  2. checkout() — müştəri masanın ödəniş QR-ını skan edib "Ödə" basır → Kapital Bank sifarişi, bankın ödəniş səhifəsi
 *  3. sync()     — bankdan qayıdanda / kassir "Yoxla" basanda status API-dən soruşulur; FullyPaid → hesab bağlanır
 */
class OnlinePaymentService
{
    public function __construct(protected Tenancy $tenancy, protected OrderService $orders) {}

    /** Masanın ödənilməmiş sifarişləri (Sifarişlər lövhəsi ilə eyni qayda). */
    public function unpaidOrders(StoreTable $table): Collection
    {
        return Order::with('items')->where('store_table_id', $table->id)->where('payment_status', 'pending')
            ->whereNotIn('status', ['draft', 'cancelled'])->orderBy('id')->get();
    }

    public function send(StoreTable $table, ?User $by): OnlinePayment
    {
        $tenant = $this->tenancy->tenant();
        if (! $tenant->onlinePaymentsEnabled()) {
            throw ValidationException::withMessages(['table' => 'Onlayn ödəniş aktiv deyil (Ayarlar → Onlayn ödəniş).']);
        }

        return DB::transaction(function () use ($table, $by, $tenant) {
            $orders = $this->unpaidOrders($table);
            if ($orders->isEmpty()) {
                throw ValidationException::withMessages(['table' => "{$table->title}: ödəniləcək hesab yoxdur."]);
            }
            $amount = $this->orders->billSums($orders, $tenant)['total'];
            if ($amount <= 0) {
                throw ValidationException::withMessages(['table' => "{$table->title}: hesabın məbləği sıfırdır."]);
            }

            // Masada bir aktiv sorğu olur: yenisi köhnəni əvəz edir (məs. əlavə sifarişdən sonra yenidən göndərilib)
            OnlinePayment::where('store_table_id', $table->id)->pending()->lockForUpdate()->get()
                ->each(fn (OnlinePayment $p) => $p->update(['status' => 'cancelled', 'note' => 'Yeni hesab göndərildi']));

            $payment = OnlinePayment::create([
                'store_table_id' => $table->id,
                'branch_id' => $table->branch_id ?? $orders->first()->branch_id,
                'order_ids' => $orders->pluck('id')->all(),
                'amount' => $amount,
                'is_test' => KapitalBank::for($tenant)->isTest(),
                'requested_by' => $by?->id,
            ]);
            AuditLog::record('online_payment.sent', $payment, ['table' => $table->title, 'amount' => $amount]);

            return $payment;
        });
    }

    /**
     * Müştəri "Ödə" basanda: hesab dəyişibsə (əlavə sifariş, qismən ləğv) məbləğ yenilənir, bankda sifariş yaradılır.
     * Eyni məbləğ üçün hələ ödənilməmiş bank sifarişi varsa — o yenidən açılır (təkrar sifariş yaradılmır).
     *
     * @return string bankın ödəniş səhifəsinin ünvanı
     */
    public function checkout(OnlinePayment $payment): string
    {
        $tenant = $this->tenancy->tenant();
        $bank = KapitalBank::for($tenant);

        // Səhv olanda da (hesab kassada bağlanıb) vəziyyət yadda qalsın — abort tranzaksiyadan sonra
        [$url, $error] = DB::transaction(function () use ($payment, $tenant, $bank) {
            $payment = OnlinePayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'pending') {
                return [null, 'Bu hesab artıq bağlanıb.'];
            }

            // Əvvəlki bank sifarişi başqa vərəqdə ödənilibsə — yenisini yaratmırıq, hesabı bağlayırıq
            if ($payment->bank_order_id && $payment->bank_status !== OnlinePayment::BANK_PAID) {
                $previous = rescue(fn () => $bank->orderStatus($payment->bank_order_id, $payment->bank_password), report: false);
                if ($previous === OnlinePayment::BANK_PAID) {
                    $payment->update(['bank_status' => $previous]);
                    $this->finalize($payment);

                    return [null, 'Bu hesab artıq ödənilib. Təşəkkür edirik!'];
                }
                if ($previous) {
                    $payment->update(['bank_status' => $previous]);
                }
            }

            $orders = $this->unpaidOrders($payment->table);
            if ($orders->isEmpty()) {
                $payment->update(['status' => 'cancelled', 'note' => 'Hesab kassada bağlanıb']);

                return [null, 'Bu hesab artıq kassada bağlanıb.'];
            }
            $amount = $this->orders->billSums($orders, $tenant)['total'];
            $changed = abs($amount - (float) $payment->amount) > 0.001 || $orders->pluck('id')->all() !== $payment->order_ids;

            if (! $changed && $payment->bank_order_id && $payment->bank_status === 'Preparing' && $payment->bank_hpp_url) {
                return [KapitalBank::paymentPageUrl($payment->bank_hpp_url, $payment->bank_order_id, $payment->bank_password), null];
            }

            try {
                $order = $bank->createOrder($amount, "{$tenant->name} · {$payment->table->title}", route('pay.return', $payment->token));
            } catch (RuntimeException $e) {
                Log::warning('Kapital Bank sifarişi yaradılmadı: '.$e->getMessage(), ['online_payment' => $payment->id]);
                abort(502, 'Bankın ödəniş səhifəsi hazırda açılmadı. Bir az sonra yenidən cəhd edin.');
            }

            $payment->update([
                'order_ids' => $orders->pluck('id')->all(),
                'amount' => $amount,
                'bank_order_id' => $order['id'],
                'bank_password' => $order['password'],
                'bank_status' => $order['status'] ?? 'Preparing',
                'bank_hpp_url' => $order['hpp_url'],
                'is_test' => $bank->isTest(),
            ]);

            return [$order['url'], null];
        });

        abort_if($error, 409, (string) $error);

        return $url;
    }

    /** Bankdakı statusu yoxlayır; "FullyPaid" olarsa hesabı (bir dəfə) bağlayır. */
    public function sync(OnlinePayment $payment): OnlinePayment
    {
        if ($payment->status !== 'pending' || ! $payment->bank_order_id) {
            return $payment;
        }

        $status = KapitalBank::for($this->tenancy->tenant())->orderStatus($payment->bank_order_id, $payment->bank_password);

        return DB::transaction(function () use ($payment, $status) {
            $payment = OnlinePayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'pending') {
                return $payment; // paralel sorğu artıq bağlayıb
            }
            $payment->update(['bank_status' => $status]);

            if ($status === OnlinePayment::BANK_PAID) {
                $this->finalize($payment);
            }

            return $payment->refresh();
        });
    }

    protected function finalize(OnlinePayment $payment): void
    {
        $orders = Order::with('items')->whereIn('id', $payment->order_ids)->where('payment_status', 'pending')
            ->where('status', '!=', 'cancelled')->lockForUpdate()->get();

        if ($orders->isEmpty()) {
            // Müştəri ödəyib, amma hesab bu arada kassada da bağlanıb — pul qaytarılmalıdır (kassirə göstərilir)
            $payment->update(['status' => 'paid', 'paid_at' => now(), 'note' => 'Hesab kassada da bağlanmışdı — ikiqat ödəniş, qaytarın']);
            AuditLog::record('online_payment.double', $payment, ['amount' => $payment->amount]);

            return;
        }

        $invoice = $this->orders->pay($orders, $this->paymentTypeId());
        // Çek onu göndərən kassirin adına (ictimai səhifədə daxil olmuş istifadəçi yoxdur)
        $invoice->forceFill(['created_by' => $invoice->created_by ?? $payment->requested_by])->save();

        $note = abs((float) $invoice->total - (float) $payment->amount) > 0.001
            ? 'Çekin məbləği ('.money($invoice->total).') ödənişdən fərqlidir — yoxlayın'
            : null;
        $payment->update(['status' => 'paid', 'paid_at' => now(), 'invoice_id' => $invoice->id, 'note' => $note]);
        AuditLog::record('online_payment.paid', $payment, ['amount' => $payment->amount, 'invoice' => $invoice->number]);
    }

    /** "Onlayn" ödəniş növü (yoxdursa yaradılır). */
    protected function paymentTypeId(): int
    {
        return PaymentType::where('icon', 'online')->orderByDesc('is_active')->value('id')
            ?? PaymentType::create(['title' => 'Onlayn', 'icon' => 'online'])->id;
    }
}
