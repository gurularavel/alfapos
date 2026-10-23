<?php

namespace App\Http\Controllers;

use App\Models\OnlinePayment;
use App\Models\StoreTable;
use App\Services\OnlinePaymentService;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Kassir: masanın hesabını onlayn ödənişə göndərir, statusu yoxlayır, ləğv edir (POS masa paneli, Sifarişlər lövhəsi). */
class OnlinePaymentController extends Controller
{
    public function __construct(protected OnlinePaymentService $payments) {}

    public function store(Request $request, PosTerminal $terminal): JsonResponse
    {
        $data = $request->validate([
            'store_table_id' => ['required', Rule::exists('store_tables', 'id')->where('tenant_id', app(Tenancy::class)->id())],
        ]);
        $user = $terminal->effectiveUser();
        abort_unless($user?->hasScope('PAYMENTS'), 403, 'Ödəniş qəbul etmək icazəniz yoxdur.');

        $table = StoreTable::findOrFail($data['store_table_id']);
        $payment = $this->payments->send($table, $user);

        return response()->json([
            'message' => "{$table->title}: hesab onlayn ödənişə göndərildi (".money($payment->amount).'). Müştəri masadakı ödəniş QR-ını skan etsin.',
            'payment' => self::summary($payment),
        ]);
    }

    /** Statusu bankdan yoxlayır (ödənilibsə hesab bağlanır). */
    public function show(OnlinePayment $onlinePayment): JsonResponse
    {
        try {
            $payment = $this->payments->sync($onlinePayment);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'payment' => self::summary($onlinePayment)], 502);
        }

        return response()->json([
            'message' => match (true) {
                $payment->status === 'paid' => 'Ödənildi'.($payment->note ? ' — '.$payment->note : '.'),
                ! $payment->bank_order_id => 'Müştəri hələ ödəniş səhifəsini açmayıb.',
                in_array($payment->bank_status, OnlinePayment::BANK_FAILED, true) => 'Ödəniş alınmadı ('.$payment->bank_status.'). Müştəri yenidən cəhd edə bilər.',
                default => 'Ödəniş gözlənilir ('.$payment->bank_status.').',
            },
            'payment' => self::summary($payment),
        ]);
    }

    public function cancel(OnlinePayment $onlinePayment, PosTerminal $terminal): JsonResponse
    {
        abort_unless($terminal->effectiveUser()?->hasScope('PAYMENTS'), 403, 'Ödəniş qəbul etmək icazəniz yoxdur.');
        $payment = $this->payments->sync($onlinePayment);
        abort_unless($payment->status === 'pending', 422, 'Bu ödəniş artıq bağlanıb.');
        $payment->update(['status' => 'cancelled', 'note' => 'Kassir ləğv etdi']);

        return response()->json(['message' => 'Onlayn ödəniş sorğusu ləğv edildi.', 'payment' => self::summary($payment)]);
    }

    /** @return array{id: int, status: string, amount: float, bank_status: ?string, opened: bool, note: ?string, is_test: bool} */
    public static function summary(OnlinePayment $payment): array
    {
        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'bank_status' => $payment->bank_status,
            'opened' => (bool) $payment->bank_order_id,
            'note' => $payment->note,
            'is_test' => $payment->is_test,
        ];
    }
}
