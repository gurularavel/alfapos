<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\ItemReturn;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\PosTerminal;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sifariş sətirinin ləğvi / qaytarılması.
 *
 * Ödənişdən əvvəl (void): sətir (və ya onun bir hissəsi) ləğv olunur, hesabdan çıxır.
 * Ödənişdən sonra (refund): sətir ləğv olunur və müştəriyə pul qaytarılır (çekin refunded_total-ı artır).
 * Stok: mətbəxə çatmamış məhsul stoka qaytarılır (restore), hazırlanmış məhsul israf yazılır (waste) — mayası itki kimi qeydə alınır.
 */
class ReturnService
{
    public function __construct(protected StockService $stock) {}

    /**
     * @param  array{quantity: int, reason: string, note?: ?string, stock_action: string, refund_amount?: ?float, payment_type_id?: ?int}  $data
     *
     * @throws ValidationException
     */
    public function returnItem(OrderItem $item, array $data, User $approver): ItemReturn
    {
        return DB::transaction(function () use ($item, $data, $approver) {
            $item = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();
            $quantity = (int) $data['quantity'];
            if ($item->status === 'cancelled' || $order->status === 'cancelled') {
                throw ValidationException::withMessages(['quantity' => 'Bu məhsul artıq ləğv edilib.']);
            }
            if ($quantity < 1 || $quantity > $item->quantity) {
                throw ValidationException::withMessages(['quantity' => "Ən çox {$item->quantity} ədəd qaytarıla bilər."]);
            }

            $invoice = $order->invoice_id ? Invoice::whereKey($order->invoice_id)->lockForUpdate()->first() : null;
            $isRefund = $invoice && $invoice->status === 'paid';
            $amount = round((float) $item->price * $quantity, 2);
            if ($isRefund) {
                $refundable = round((float) $invoice->total - (float) $invoice->refunded_total, 2);
                $share = (float) $invoice->sub_total > 0 ? $amount / (float) $invoice->sub_total * ((float) $invoice->total - (float) $invoice->deposit_topup) : $amount;
                $amount = round(min($data['refund_amount'] ?? $share, $refundable), 2);
                if (isset($data['refund_amount']) && $data['refund_amount'] > $refundable) {
                    throw ValidationException::withMessages(['refund_amount' => 'Bu çekdən ən çox '.money($refundable).' qaytarıla bilər.']);
                }
            }

            // Hissəvi qaytarma: sətir bölünür, qaytarılan hissə ayrıca ləğv olunmuş sətir olur
            if ($quantity < $item->quantity) {
                $item->decrement('quantity', $quantity);
                $returned = $item->replicate();
                $returned->fill(['quantity' => $quantity, 'status' => 'cancelled'])->save();
            } else {
                $item->update(['status' => 'cancelled']);
                $returned = $item;
            }

            $note = ($isRefund ? 'Qaytarma' : 'Ləğv').": #{$order->token_no} {$returned->title} × {$quantity}";
            if ($data['stock_action'] === 'restore') {
                $this->stock->consume(collect([$returned]), 1, 'sale', ['order_id' => $order->id, 'note' => $note]);
            }

            if ($isRefund) {
                $invoice->increment('refunded_total', $amount);
            }
            // Bütün sətirlər ləğv olunubsa, ödənilməmiş sifariş də ləğv olunur (stok yuxarıda artıq həll olunub)
            if (! $isRefund && ! $order->items()->where('status', '!=', 'cancelled')->exists()) {
                $order->update(['status' => 'cancelled']);
            }

            $device = app(PosTerminal::class)->device();
            $return = ItemReturn::create([
                'branch_id' => $order->branch_id,
                'terminal_id' => $device?->id,
                'order_id' => $order->id,
                'order_item_id' => $returned->id,
                'invoice_id' => $isRefund ? $invoice->id : null,
                'kind' => $isRefund ? 'refund' : 'void',
                'title' => $returned->title.($returned->variant_title ? " ({$returned->variant_title})" : ''),
                'quantity' => $quantity,
                'amount' => $amount,
                'cost' => $data['stock_action'] === 'waste' && $returned->cost !== null ? round((float) $returned->cost * $quantity, 2) : null,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'stock_action' => $data['stock_action'],
                'payment_type_id' => $isRefund ? ($data['payment_type_id'] ?? $invoice->payment_type_id) : null,
                'after_bill' => $order->bill_print_count > 0,
                'order_creator_id' => $order->created_by,
                'requested_by' => app(PosTerminal::class)->effectiveId(),
                'approved_by' => $approver->id,
            ]);
            AuditLog::record($isRefund ? 'order_item.refunded' : 'order_item.voided', $order, [
                'item' => $return->title, 'quantity' => $quantity, 'amount' => $amount,
                'reason' => ItemReturn::REASONS[$data['reason']], 'approved_by' => $approver->name,
            ]);
            Realtime::order($order, 'updated');

            return $return;
        });
    }

    /** Bütün sifariş ləğv olunanda hər aktiv sətir üçün ləğv qeydi (hesabatda kim, nə, niyə görünsün). */
    public function recordOrderCancel(Order $order, string $reason, ?string $note, User $approver): void
    {
        $device = app(PosTerminal::class)->device();
        foreach ($order->items->where('status', '!=', 'cancelled') as $item) {
            ItemReturn::create([
                'branch_id' => $order->branch_id,
                'terminal_id' => $device?->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'kind' => 'void',
                'title' => $item->title.($item->variant_title ? " ({$item->variant_title})" : ''),
                'quantity' => $item->quantity,
                'amount' => $item->lineTotal(),
                'reason' => $reason,
                'note' => $note,
                'stock_action' => 'restore',
                'after_bill' => $order->bill_print_count > 0,
                'order_creator_id' => $order->created_by,
                'requested_by' => app(PosTerminal::class)->effectiveId(),
                'approved_by' => $approver->id,
            ]);
        }
    }
}
