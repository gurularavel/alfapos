<?php

namespace App\Http\Controllers;

use App\Support\Realtime;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Services\OrderService;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(PosTerminal $terminal)
    {
        return view('orders.index', [
            'paymentTypes' => PaymentType::where('is_active', true)->get(['id', 'title']),
            'terminal' => $terminal->state(),
            'tables' => StoreTable::orderBy('floor')->orderByRaw('LENGTH(title)')->orderBy('title')->get(['id', 'title', 'floor']),
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
                $due = $unpaid->sum(fn ($o) => $service->orderTotals($o, $o->items->where('status', '!=', 'cancelled'), $tenant)['total']);
                // Depozitli kabinet: ödəniləcək məbləğ minimum depozitdən az olmur
                $deposit = $unpaid->isNotEmpty() ? (float) ($service->depositTable($unpaid)?->deposit ?? 0) : 0.0;
                $topup = StoreTable::depositTopup($due, $deposit);

                return [
                    'key' => $first->store_table_id ? 't'.$first->store_table_id : 'o'.$first->id,
                    'title' => $first->table?->title ?? Order::DELIVERY_TYPES[$first->delivery_type],
                    'floor' => $first->table?->floor,
                    'is_table' => (bool) $first->store_table_id,
                    'unpaid_ids' => $unpaid->pluck('id')->values(),
                    'orders_total' => round($due, 2),
                    'deposit' => $deposit ?: (float) ($first->table?->deposit ?? 0),
                    'deposit_topup' => $topup,
                    'due' => round($due + $topup, 2),
                    'orders' => $group->map(fn (Order $o) => [
                        'id' => $o->id,
                        'token_no' => $o->token_no,
                        'time' => $o->created_at->format('H:i'),
                        'status' => $o->status,
                        'status_label' => Order::STATUSES[$o->status],
                        'payment_status' => $o->payment_status,
                        'invoice_id' => $o->invoice_id,
                        'source' => $o->source,
                        'is_addition' => $o->is_addition,
                        'delivery_type' => Order::DELIVERY_TYPES[$o->delivery_type],
                        'customer' => $o->customer?->name,
                        'waiter' => $o->creator?->name,
                        'notes' => $o->notes,
                        'items' => $o->items->map(fn ($i) => [
                            'title' => $i->title, 'variant' => $i->variant_title, 'qty' => $i->quantity, 'total' => $i->lineTotal(),
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

    /**
     * Masanı köçürmək: masanın ödənilməmiş sifarişləri başqa masaya keçir.
     * Hədəf masada açıq hesab varsa hesablar birləşir (bir qəbzlə bağlanacaq).
     */
    public function transfer(Request $request, Tenancy $tenancy)
    {
        $tid = $tenancy->id();
        $data = $request->validate([
            'from_table_id' => ['required', Rule::exists('store_tables', 'id')->where('tenant_id', $tid)],
            'to_table_id' => ['required', 'different:from_table_id', Rule::exists('store_tables', 'id')->where('tenant_id', $tid)],
            'order_ids' => ['nullable', 'array'],
            'order_ids.*' => ['integer'],
        ], ['to_table_id.different' => 'Eyni masaya köçürmək olmaz.']);

        $orders = Order::where('store_table_id', $data['from_table_id'])->where('payment_status', 'pending')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($data['order_ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('created_at')->get();
        abort_if($orders->isEmpty(), 422, 'Köçürüləcək açıq sifariş yoxdur.');

        $from = StoreTable::find($data['from_table_id']);
        $to = StoreTable::find($data['to_table_id']);
        $merged = Order::where('store_table_id', $to->id)->where('payment_status', 'pending')->whereNotIn('status', ['draft', 'cancelled'])->exists();

        DB::transaction(function () use ($orders, $to, $merged) {
            foreach ($orders as $order) {
                // Birləşmədə gələn sifarişlər hədəf masanın hesabına "əlavə" kimi düşür
                $order->update(['store_table_id' => $to->id, 'delivery_type' => 'dinein', 'is_addition' => $merged || $order->is_addition]);
                Realtime::order($order, 'updated');
            }
        });

        AuditLog::record('table.transfer', $to, ['from' => $from->title, 'to' => $to->title, 'orders' => $orders->pluck('token_no'), 'merged' => $merged]);

        return response()->json([
            'message' => $merged ? "{$from->title} → {$to->title}: hesablar birləşdirildi." : "{$from->title} → {$to->title} köçürüldü.",
            'to_table_id' => $to->id,
        ]);
    }

    /** Hesab çeki (pre-bill): masanın ödənilməmiş sifarişləri — müştəri ödəməzdən əvvəl baxır. */
    public function bill(Request $request, OrderService $service, Tenancy $tenancy)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids'))));
        $orders = Order::with('items', 'table', 'creator', 'customer')->whereIn('id', $ids)
            ->where('payment_status', 'pending')->where('status', '!=', 'cancelled')->orderBy('created_at')->get();
        abort_if($orders->isEmpty(), 404);

        $tenant = $tenancy->tenant();
        $totals = ['sub_total' => 0, 'discount_total' => 0, 'tax_total' => 0, 'service_charge_total' => 0, 'total' => 0];
        foreach ($orders as $order) {
            foreach ($service->orderTotals($order, $order->items->where('status', '!=', 'cancelled'), $tenant) as $k => $v) {
                $totals[$k] += $v;
            }
        }
        $deposit = (float) ($service->depositTable($orders)?->deposit ?? 0);
        $topup = StoreTable::depositTopup($totals['total'], $deposit);

        return view('print.bill', compact('orders', 'tenant', 'totals', 'deposit', 'topup'));
    }

    /** Mətbəx üçün token / KOT çapı. */
    public function token(Order $order, Tenancy $tenancy)
    {
        return view('print.token', ['order' => $order->load('items', 'table', 'creator'), 'tenant' => $tenancy->tenant()]);
    }
}
