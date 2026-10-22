<?php

namespace App\Http\Controllers;

use App\Support\Realtime;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PaymentType;
use App\Services\OrderService;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(PosTerminal $terminal)
    {
        return view('orders.index', [
            'paymentTypes' => PaymentType::where('is_active', true)->get(['id', 'title']),
            'terminal' => $terminal->state(),
        ]);
    }

    /** Aktiv sifarişlər, masa üzrə qruplanmış (masasızlar ayrıca). Orders ekranı bunu poll edir. */
    public function board(Tenancy $tenancy, OrderService $service)
    {
        $tenant = $tenancy->tenant();

        $orders = Order::with(['items', 'table', 'customer:id,name,phone', 'creator:id,name'])
            ->where(fn ($q) => $q->active()->orWhere(fn ($w) => $w->where('payment_status', 'pending')->where('status', 'completed')))
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at')->get();

        return $orders->groupBy(fn (Order $o) => $o->store_table_id ? 't'.$o->store_table_id : 'o'.$o->id)
            ->map(function ($group) use ($service, $tenant) {
                $first = $group->first();
                $unpaid = $group->where('payment_status', 'pending');
                $due = $unpaid->sum(fn ($o) => $service->totals($o->items->where('status', '!=', 'cancelled'), $o->discount_type, (float) $o->discount_value, $tenant)['total']);

                return [
                    'key' => $first->store_table_id ? 't'.$first->store_table_id : 'o'.$first->id,
                    'title' => $first->table?->title ?? Order::DELIVERY_TYPES[$first->delivery_type],
                    'floor' => $first->table?->floor,
                    'is_table' => (bool) $first->store_table_id,
                    'unpaid_ids' => $unpaid->pluck('id')->values(),
                    'due' => round($due, 2),
                    'orders' => $group->map(fn (Order $o) => [
                        'id' => $o->id,
                        'token_no' => $o->token_no,
                        'time' => $o->created_at->format('H:i'),
                        'status' => $o->status,
                        'status_label' => Order::STATUSES[$o->status],
                        'payment_status' => $o->payment_status,
                        'invoice_id' => $o->invoice_id,
                        'source' => $o->source,
                        'delivery_type' => Order::DELIVERY_TYPES[$o->delivery_type],
                        'customer' => $o->customer?->name,
                        'waiter' => $o->creator?->name,
                        'notes' => $o->notes,
                        'items' => $o->items->map(fn ($i) => [
                            'title' => $i->title, 'variant' => $i->variant_title, 'qty' => $i->quantity,
                            'addons' => collect($i->addons)->pluck('title')->implode(', '), 'status' => $i->status, 'notes' => $i->notes,
                        ]),
                    ])->values(),
                ];
            })->values();
    }

    public function pay(Request $request, OrderService $service, PosTerminal $terminal)
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer'],
            'payment_type_id' => ['required', Rule::exists('payment_types', 'id')->where('tenant_id', app(Tenancy::class)->id())],
        ]);
        abort_unless($terminal->effectiveUser()?->hasScope('PAYMENTS'), 403, 'Ödəniş qəbul etmək icazəniz yoxdur.');

        $orders = Order::with('items')->whereIn('id', $data['order_ids'])->where('payment_status', 'pending')
            ->where('status', '!=', 'cancelled')->get();
        abort_if($orders->isEmpty(), 422, 'Ödəniləcək sifariş tapılmadı.');

        $invoice = $service->pay($orders, (int) $data['payment_type_id']);

        return response()->json(['message' => 'Ödəniş qəbul edildi.', 'invoice_id' => $invoice->id]);
    }

    public function status(Request $request, Order $order)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['created', 'preparing', 'ready', 'completed'])]]);

        $order->update($data);
        if ($data['status'] === 'completed') {
            $order->items()->where('status', '!=', 'cancelled')->update(['status' => 'completed']);
        }
        AuditLog::record('order.status', $order, $data);
        Realtime::order($order, 'updated');

        return response()->json(['message' => 'Status yeniləndi.']);
    }

    public function cancel(Request $request, Order $order, OrderService $service)
    {
        abort_if($order->payment_status === 'paid', 422, 'Ödənilmiş sifariş ləğv edilə bilməz — fakturanı ləğv edin.');
        $service->cancel($order, $request->input('reason'));

        return response()->json(['message' => 'Sifariş ləğv edildi.']);
    }

    /** Mətbəx üçün token / KOT çapı. */
    public function token(Order $order, Tenancy $tenancy)
    {
        return view('print.token', ['order' => $order->load('items', 'table', 'creator'), 'tenant' => $tenancy->tenant()]);
    }
}
