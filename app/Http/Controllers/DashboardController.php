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

        // Bu gün kateqoriyalar üzrə satış (Hesabatlar → Kateqoriya satışları ilə eyni qayda)
        $categorySales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('categories', 'categories.id', '=', 'menu_items.category_id')
            ->where('orders.tenant_id', $tenancy->id())
            ->whereDate('orders.created_at', $today)
            ->whereNotIn('orders.status', ['draft', 'cancelled'])
            ->where('order_items.status', '!=', 'cancelled')
            ->groupBy('categories.id', 'categories.title')
            ->selectRaw("categories.id, COALESCE(categories.title, 'Kateqoriyasız') as title, SUM(order_items.quantity) as qty, SUM(order_items.quantity * order_items.price) as amount")
            ->orderByDesc('amount')->get();

        // Bu gün sifariş növləri üzrə: ödənilmiş satış (hər çek bir dəfə) + açıq (hələ ödənilməmiş) sifarişlər
        $paidByType = $paid($today)->withOrderInfo()->groupBy('io.delivery_type')
            ->selectRaw('io.delivery_type, COUNT(*) as checks, SUM(io.orders_count) as orders, SUM(invoices.total) as total')
            ->get()->keyBy('delivery_type');
        $openByType = Order::whereDate('created_at', $today)->whereNull('invoice_id')->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy('delivery_type')->selectRaw('delivery_type, COUNT(*) as orders')->pluck('orders', 'delivery_type');
        $orderTypes = collect(Order::DELIVERY_TYPES)->map(fn ($label, $type) => (object) [
            'type' => $type,
            'label' => $label,
            'checks' => (int) ($paidByType[$type]->checks ?? 0),
            'orders' => (int) ($paidByType[$type]->orders ?? 0),
            'total' => (float) ($paidByType[$type]->total ?? 0),
            'open' => (int) ($openByType[$type] ?? 0),
        ])->values();

        // Bu gün ödəniş növləri üzrə (Hesabatlar → Ödəniş növləri ilə eyni qayda) + dünənlə müqayisə
        $byPayment = fn (Carbon $day) => $paid($day)->leftJoin('payment_types', 'payment_types.id', '=', 'invoices.payment_type_id')
            ->groupBy('invoices.payment_type_id', 'payment_types.title', 'payment_types.icon')
            ->selectRaw("invoices.payment_type_id, COALESCE(payment_types.title, 'Digər') as title, COALESCE(payment_types.icon, 'other') as icon, COUNT(*) as checks, SUM(invoices.total) as total")
            ->get();
        $paymentYesterday = $byPayment($today->copy()->subDay())->pluck('total', 'payment_type_id');
        $paymentMix = $byPayment($today)->sortByDesc('total')->values()
            ->each(fn ($row) => $row->change = $this->change((float) $row->total, (float) ($paymentYesterday[$row->payment_type_id] ?? 0)));

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

        // Bu gün masalar üzrə satış (hər çek bir dəfə) + hazırda dolu masalar
        $occupied = Order::active()->whereNotNull('store_table_id') // POS masa xəritəsi ilə eyni qayda
            ->groupBy('store_table_id')->selectRaw('store_table_id, SUM((SELECT COALESCE(SUM(oi.price * oi.quantity), 0) FROM order_items oi WHERE oi.order_id = orders.id AND oi.status != \'cancelled\')) as open_total')
            ->pluck('open_total', 'store_table_id');
        $tableSales = $paid($today)->withOrderInfo()
            ->join('store_tables', 'store_tables.id', '=', 'io.store_table_id')
            ->leftJoin('branches', 'branches.id', '=', 'store_tables.branch_id')
            ->groupBy('store_tables.id', 'store_tables.title', 'store_tables.seating_capacity', 'branches.name')
            ->selectRaw('store_tables.id, store_tables.title, store_tables.seating_capacity as seats, branches.name as branch, COUNT(*) as checks, SUM(invoices.total) as total')
            ->orderByDesc('total')->get()
            ->each(fn ($row) => $row->open_total = $occupied[$row->id] ?? null);
        $tableStats = (object) [
            'tables' => \App\Models\StoreTable::count(),
            'used' => $tableSales->count(),
            'occupied' => $occupied->count(),
            'total' => (float) $tableSales->sum('total'),
            'checks' => (int) $tableSales->sum('checks'),
        ];

        // Saatlar üzrə: bu gün və müqayisə üçün keçən həftənin eyni günü
        $byHour = fn (Carbon $day) => $paid($day)
            ->selectRaw('HOUR(invoices.created_at) as h, COUNT(*) as checks, SUM(invoices.total) as total')
            ->groupBy('h')->get()->keyBy('h');
        $todayHours = $byHour($today);
        $lastWeekHours = $byHour($today->copy()->subWeek());
        $active = $todayHours->keys()->merge($lastWeekHours->keys());
        $from = min($active->min() ?? 9, 9);
        $to = max($active->max() ?? 21, 21, (int) now()->format('G'));
        $hourly = collect(range($from, $to))->map(fn ($h) => [
            'hour' => $h,
            'total' => (float) ($todayHours[$h]->total ?? 0),
            'checks' => (int) ($todayHours[$h]->checks ?? 0),
            'last_week' => (float) ($lastWeekHours[$h]->total ?? 0),
        ]);

        $todayInvoices = $paid($today);

        return view('dashboard', [
            'revenueToday' => $revenueToday,
            'revenueChange' => $this->change($revenueToday, $revenueYesterday),
            'ordersToday' => $ordersToday,
            'ordersChange' => $this->change($ordersToday, $ordersYesterday),
            'goal' => (float) $tenancy->tenant()->daily_sales_goal,
            'trend' => $trend,
            'topItems' => $topItems,
            'categorySales' => $categorySales,
            'orderTypes' => $orderTypes,
            'paymentMix' => $paymentMix,
            'terminalSales' => $terminalSales,
            'branchSales' => $branchSales,
            'staffSales' => $staffSales,
            'tableSales' => $tableSales->take(8),
            'tableStats' => $tableStats,
            'hourly' => $hourly,
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
