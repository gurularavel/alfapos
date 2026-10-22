<?php

namespace App\Support;

use App\Events\OrderChanged;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Canlı yeniləmə siqnalları. Reverb serveri əlçatmaz olsa belə sifariş/ödəniş pozulmamalıdır:
 * xəta yalnız log-a yazılır, ekranlar isə ehtiyat polling ilə yenilənməyə davam edir.
 */
class Realtime
{
    public static function order(Order $order, string $type): void
    {
        $event = OrderChanged::fromOrder($order, $type);

        // Tranzaksiya içindəyiksə, yalnız commit-dən sonra göndəririk (ekranlar köhnə məlumatı oxumasın)
        DB::afterCommit(function () use ($event) {
            try {
                broadcast($event);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
