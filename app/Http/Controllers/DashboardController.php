<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Tenancy $tenancy)
    {
        $today = today();
        $paid = fn (Carbon $day) => Invoice::where('invoices.status', 'paid')->whereDate('invoices.created_at', $day);

        $revenueToday = (float) $paid($today)->sum('total');
        $revenueYesterday = (float) $paid($today->copy()->subDay())->sum('total');
        $ordersToday = Order::whereDate('created_at', $today)->where('status', '!=', 'draft')->count();
        $ordersYesterday = Order::whereDate('created_at', $today->copy()->subDay())->where('status', '!=', 'draft')->count();

        $trend = collect(range(6, 0))->map(function ($daysAgo) use ($paid, $today) {
            $day = $today->copy()->subDays($daysAgo);

            return ['label' => $day->translatedFormat('D, d M'), 'value' => (float) $paid($day)->sum('total')];
        });

        $topItems = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenancy->id())
            ->whereDate('orders.created_at', $today)
            ->whereNotIn('order_items.status', ['cancelled'])
            ->groupBy('order_items.title')
            ->select('order_items.title', DB::raw('SUM(order_items.quantity) as qty'), DB::raw('SUM(order_items.quantity * order_items.price) as amount'))
            ->orderByDesc('qty')->limit(6)->get();

        $orderTypes = Order::whereDate('created_at', $today)->where('status', '!=', 'draft')
            ->groupBy('delivery_type')->select('delivery_type', DB::raw('COUNT(*) as total'))->pluck('total', 'delivery_type');

        $paymentMix = $paid($today)->join('payment_types', 'payment_types.id', '=', 'invoices.payment_type_id')
            ->groupBy('payment_types.title')->select('payment_types.title', DB::raw('SUM(invoices.total) as total'))->pluck('total', 'title');

        // Bu gün terminallar üzrə satış (Hesabatlar → Terminallar üzrə satış ilə eyni qayda)
        $terminalSales = $paid($today)
            ->leftJoin('terminals', 'terminals.id', '=', 'invoices.terminal_id')
            ->leftJoin('branches', 'branches.id', '=', 'invoices.branch_id')
            ->groupBy('invoices.terminal_id', 'terminals.name', 'branches.name')
            ->selectRaw("invoices.terminal_id, COALESCE(terminals.name, 'Terminalsız (panel)') as name, branches.name as branch, COUNT(*) as checks, SUM(invoices.total) as total")
            ->orderByDesc('total')->get();

        // Bu gün filiallar üzrə satış (Hesabatlar → Filiallar üzrə satış ilə eyni qayda)
        $branchSales = $paid($today)
            ->leftJoin('branches', 'branches.id', '=', 'invoices.branch_id')
            ->groupBy('invoices.branch_id', 'branches.name')
            ->selectRaw("invoices.branch_id, COALESCE(branches.name, 'Filialsız') as name, COUNT(*) as checks, SUM(invoices.total) as total")
            ->orderByDesc('total')->get();

        $staffSales = $this->staffSales($today, $tenancy->id());

        $todayInvoices = $paid($today);

        return view('dashboard', [
            'revenueToday' => $revenueToday,
            'revenueChange' => $this->change($revenueToday, $revenueYesterday),
            'ordersToday' => $ordersToday,
            'ordersChange' => $this->change($ordersToday, $ordersYesterday),
            'goal' => (float) $tenancy->tenant()->daily_sales_goal,
            'trend' => $trend,
            'topItems' => $topItems,
            'orderTypes' => $orderTypes,
            'paymentMix' => $paymentMix,
            'terminalSales' => $terminalSales,
            'branchSales' => $branchSales,
            'staffSales' => $staffSales,
            'branchCount' => \App\Models\Branch::count(),
            'lowStock' => InventoryItem::whereColumn('quantity', '<=', 'min_quantity')->orderBy('quantity')->limit(5)->get(),
            'feedbacks' => Feedback::latest()->limit(3)->get(),
            'reservations' => Reservation::with('table')->whereDate('reserved_at', $today)->where('status', 'booked')->orderBy('reserved_at')->get(),
            'activeOrders' => Order::active()->count(),
            'cancelledToday' => Order::whereDate('created_at', $today)->where('status', 'cancelled')->count(),
            'taxToday' => (float) (clone $todayInvoices)->sum('tax_total'),
            'serviceToday' => (float) (clone $todayInvoices)->sum('service_charge_total'),
        ]);
    }

    /**
     * Bu gün işçilər üzrə: qəbul etdiyi sifarişlər (sifarişin müəllifi — ofisiant/kassir) və
     * kassaya yığdığı çeklər (çekin müəllifi — kassir). Ləğv olunmuş sifariş/məhsullar sayılmır.
     */
    protected function staffSales(Carbon $day, int $tenantId)
    {
        $orders = Order::query()
            ->whereDate('orders.created_at', $day)->whereNotIn('orders.status', ['draft', 'cancelled'])->whereNotNull('orders.created_by')
            ->groupBy('orders.created_by')
            ->selectRaw("orders.created_by as user_id, COUNT(*) as orders, SUM((SELECT COALESCE(SUM(oi.price * oi.quantity), 0) FROM order_items oi WHERE oi.order_id = orders.id AND oi.status != 'cancelled')) as orders_total")
            ->get()->keyBy('user_id');

        $collected = Invoice::query()
            ->where('status', 'paid')->whereDate('created_at', $day)->whereNotNull('created_by')
            ->groupBy('created_by')
            ->selectRaw('created_by as user_id, COUNT(*) as checks, SUM(total) as collected')
            ->get()->keyBy('user_id');

        $ids = $orders->keys()->merge($collected->keys())->unique();

        return \App\Models\User::where('tenant_id', $tenantId)->whereIn('id', $ids)->get(['id', 'name', 'role', 'designation', 'tenant_id'])
            ->map(fn ($u) => (object) [
                'name' => $u->name,
                'role' => $u->roleLabel(),
                'orders' => (int) ($orders[$u->id]->orders ?? 0),
                'orders_total' => (float) ($orders[$u->id]->orders_total ?? 0),
                'checks' => (int) ($collected[$u->id]->checks ?? 0),
                'collected' => (float) ($collected[$u->id]->collected ?? 0),
            ])
            ->sortByDesc(fn ($r) => max($r->orders_total, $r->collected))->values()->take(8);
    }

    protected function change(float $now, float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }
}
