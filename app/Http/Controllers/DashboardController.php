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
            'lowStock' => InventoryItem::whereColumn('quantity', '<=', 'min_quantity')->orderBy('quantity')->limit(5)->get(),
            'feedbacks' => Feedback::latest()->limit(3)->get(),
            'reservations' => Reservation::with('table')->whereDate('reserved_at', $today)->where('status', 'booked')->orderBy('reserved_at')->get(),
            'activeOrders' => Order::active()->count(),
            'cancelledToday' => Order::whereDate('created_at', $today)->where('status', 'cancelled')->count(),
            'taxToday' => (float) (clone $todayInvoices)->sum('tax_total'),
            'serviceToday' => (float) (clone $todayInvoices)->sum('service_charge_total'),
        ]);
    }

    protected function change(float $now, float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }
}
