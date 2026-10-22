<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\Terminal;
use App\Support\Tenancy;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** key => [kateqoriya, başlıq, təsvir] */
    public const REPORTS = [
        'sales-summary' => ['Satış', 'Satış xülasəsi', 'Ümumi gəlir, endirim, vergi, servis haqqı, orta çek.'],
        'sales-by-day' => ['Satış', 'Günlər üzrə satış', 'Hər gün üçün çek sayı və gəlir.'],
        'sales-by-hour' => ['Satış', 'Saatlar üzrə satış', 'Pik saatları müəyyən edin.'],
        'sales-by-order-type' => ['Satış', 'Sifariş tipi üzrə', 'Zalda / özü aparır / çatdırılma.'],
        'sales-by-table' => ['Satış', 'Masalar üzrə satış', 'Hansı masa nə qədər gəlir gətirir.'],
        'voids' => ['Satış', 'Ləğvlər', 'Ləğv edilmiş fakturalar və sifarişlər.'],
        'payment-summary' => ['Ödənişlər', 'Ödəniş növləri', 'Nağd, kart və digər ödəniş növləri üzrə cəm.'],
        'item-sales' => ['Menyu', 'Məhsul satışları', 'Hər məhsuldan satılan miqdar və məbləğ.'],
        'category-sales' => ['Menyu', 'Kateqoriya satışları', 'Kateqoriyalar üzrə gəlir.'],
        'variant-sales' => ['Menyu', 'Variant satışları', 'Ölçü/variant üzrə satış.'],
        'top-customers' => ['Müştərilər', 'Top müştərilər', 'Ən çox xərcləyən müştərilər.'],
        'customer-birthdays' => ['Müştərilər', 'Ad günləri', 'Bu ay ad günü olan müştərilər.'],
        'staff-orders' => ['Əməliyyat', 'İşçilər üzrə sifarişlər', 'Hər işçinin yaratdığı sifariş və məbləğ.'],
        'kitchen-tokens' => ['Əməliyyat', 'Token hesabatı', 'Gün üzrə token sayı və statuslar.'],
        'low-stock' => ['Anbar', 'Az qalan stok', 'Minimum həddən aşağı düşən məhsullar.'],
        'stock-movements' => ['Anbar', 'Stok hərəkətləri', 'Giriş, çıxış, israf və satış hərəkətləri.'],
        'tax-summary' => ['Mühasibat', 'Vergi xülasəsi', 'Dövr üzrə toplanmış vergi və servis haqqı.'],
        'reservations' => ['Rezerv', 'Rezervlər', 'Dövr üzrə rezervlər və statusları.'],
        'sales-by-branch' => ['Filial və terminal', 'Filiallar üzrə satış', 'Hər filialın çek sayı və gəliri.'],
        'sales-by-terminal' => ['Filial və terminal', 'Terminallar üzrə satış', 'Hansı kassa/terminaldan nə qədər satış olub.'],
        'staff-by-terminal' => ['Filial və terminal', 'İşçilər və terminallar', 'Hər işçinin hansı terminalda neçə sifariş verdiyi.'],
    ];

    /** Hesabat filtrləri (show() təyin edir). */
    protected ?int $branchId = null;

    protected ?int $terminalId = null;

    public function index()
    {
        return view('reports.index', ['groups' => collect(self::REPORTS)->groupBy(fn ($r) => $r[0], preserveKeys: true)]);
    }

    public function show(Request $request, string $report, Tenancy $tenancy)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        $from = Carbon::parse($request->query('from', today()->subDays(29)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->endOfDay();
        $tid = $tenancy->id();
        // Yalnız bu biznesin filial/terminalı qəbul olunur (global scope)
        $this->branchId = Branch::find($request->integer('branch_id'))?->id;
        $this->terminalId = Terminal::find($request->integer('terminal_id'))?->id;

        [$columns, $rows, $summary] = $this->build($report, $from, $to, $tid);

        return view('reports.show', [
            'key' => $report,
            'meta' => self::REPORTS[$report],
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'branches' => Branch::orderBy('id')->get(['id', 'name']),
            'terminals' => Terminal::with('branch:id,name')->orderBy('branch_id')->orderBy('name')->get(),
            'branchId' => $this->branchId,
            'terminalId' => $this->terminalId,
        ]);
    }

    protected function build(string $report, Carbon $from, Carbon $to, int $tid): array
    {
        // Filial/terminal filtri: çeklər — vurulduğu terminal; sifarişlər — verildiyi terminal
        $inv = fn ($q) => $q->when($this->branchId, fn ($w) => $w->where('invoices.branch_id', $this->branchId))
            ->when($this->terminalId, fn ($w) => $w->where('invoices.terminal_id', $this->terminalId));
        $ord = fn ($q) => $q->when($this->branchId, fn ($w) => $w->where('orders.branch_id', $this->branchId))
            ->when($this->terminalId, fn ($w) => $w->where('orders.terminal_id', $this->terminalId));

        $paid = fn () => Invoice::where('invoices.status', 'paid')->whereBetween('invoices.created_at', [$from, $to])->tap($inv);
        $items = fn () => DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('invoices', 'invoices.id', '=', 'orders.invoice_id')
            ->where('orders.tenant_id', $tid)->where('invoices.status', 'paid')
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where('order_items.status', '!=', 'cancelled')->tap($inv);

        return match ($report) {
            'sales-summary' => (function () use ($paid) {
                $s = $paid()->selectRaw('COUNT(*) c, SUM(sub_total) sub, SUM(discount_total) disc, SUM(tax_total) tax, SUM(service_charge_total) svc, SUM(deposit_topup) dep, SUM(total) total')->first();
                $rows = [
                    ['Çek sayı', $s->c],
                    ['Brüt satış', money($s->sub)],
                    ['Endirimlər', money($s->disc)],
                    ['Vergi', money($s->tax)],
                    ['Servis haqqı', money($s->svc)],
                    ['Depozit fərqləri (kabinet)', money($s->dep)],
                    ['Xalis gəlir', money($s->total)],
                    ['Orta çek', money($s->c ? $s->total / $s->c : 0)],
                ];

                return [['Göstərici', 'Dəyər'], $rows, null];
            })(),
            'sales-by-day' => $this->grouped($paid()->selectRaw('DATE(created_at) k, COUNT(*) c, SUM(total) t')->groupBy('k')->orderBy('k'), ['Tarix', 'Çek', 'Gəlir']),
            'sales-by-hour' => $this->grouped($paid()->selectRaw('HOUR(created_at) k, COUNT(*) c, SUM(total) t')->groupBy('k')->orderBy('k'), ['Saat', 'Çek', 'Gəlir'], fn ($k) => sprintf('%02d:00', $k)),
            'sales-by-order-type' => $this->grouped(
                $paid()->withOrderInfo()->selectRaw('io.delivery_type k, SUM(io.orders_count) c, SUM(invoices.total) t')->groupBy('k'),
                ['Tip', 'Sifariş', 'Gəlir'], fn ($k) => Order::DELIVERY_TYPES[$k] ?? $k),
            'sales-by-table' => $this->grouped(
                $paid()->withOrderInfo()->leftJoin('store_tables', 'store_tables.id', '=', 'io.store_table_id')
                    ->selectRaw("COALESCE(store_tables.title, 'Masasız') k, SUM(io.orders_count) c, SUM(invoices.total) t")->groupBy('k')->orderByDesc('t'),
                ['Masa', 'Sifariş', 'Gəlir']),
            'voids' => [['Faktura', 'Tarix', 'Səbəb', 'Məbləğ'],
                Invoice::where('status', 'void')->whereBetween('updated_at', [$from, $to])->tap($inv)->latest()->get()
                    ->map(fn ($i) => [$i->number, $i->updated_at->format('d.m.Y H:i'), $i->void_reason, money($i->total)])->all(), null],
            'payment-summary' => $this->grouped($paid()->join('payment_types', 'payment_types.id', '=', 'invoices.payment_type_id')
                ->selectRaw('payment_types.title k, COUNT(*) c, SUM(invoices.total) t')->groupBy('k')->orderByDesc('t'), ['Ödəniş növü', 'Çek', 'Məbləğ']),
            'item-sales' => $this->grouped($items()->selectRaw('order_items.title k, SUM(order_items.quantity) c, SUM(order_items.quantity*order_items.price) t')->groupBy('k')->orderByDesc('c'), ['Məhsul', 'Miqdar', 'Məbləğ']),
            'category-sales' => $this->grouped($items()->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')->leftJoin('categories', 'categories.id', '=', 'menu_items.category_id')
                ->selectRaw("COALESCE(categories.title, '—') k, SUM(order_items.quantity) c, SUM(order_items.quantity*order_items.price) t")->groupBy('k')->orderByDesc('t'), ['Kateqoriya', 'Miqdar', 'Məbləğ']),
            'variant-sales' => $this->grouped($items()->whereNotNull('order_items.variant_title')
                ->selectRaw("CONCAT(order_items.title, ' — ', order_items.variant_title) k, SUM(order_items.quantity) c, SUM(order_items.quantity*order_items.price) t")->groupBy('k')->orderByDesc('c'), ['Variant', 'Miqdar', 'Məbləğ']),
            'top-customers' => $this->grouped($paid()->join('customers', 'customers.id', '=', 'invoices.customer_id')
                ->selectRaw("CONCAT(customers.name, ' (', customers.phone, ')') k, COUNT(*) c, SUM(invoices.total) t")->groupBy('k')->orderByDesc('t')->limit(50), ['Müştəri', 'Çek', 'Xərc']),
            'customer-birthdays' => [['Ad', 'Telefon', 'Doğum tarixi'],
                Customer::whereMonth('birth_date', now()->month)->orderByRaw('DAY(birth_date)')->get()
                    ->map(fn ($c) => [$c->name, $c->phone, $c->birth_date->format('d.m.Y')])->all(), null],
            'staff-orders' => $this->grouped(Order::join('users', 'users.id', '=', 'orders.created_by')->leftJoin('invoices', 'invoices.id', '=', 'orders.invoice_id')
                ->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'draft')->tap($ord)
                ->selectRaw('users.name k, COUNT(DISTINCT orders.id) c, SUM(CASE WHEN invoices.status = "paid" THEN invoices.total ELSE 0 END) t')->groupBy('k')->orderByDesc('c'), ['İşçi', 'Sifariş', 'Ödənilmiş məbləğ']),
            'kitchen-tokens' => [['Tarix', 'Token', 'Tamamlanan', 'Ləğv'],
                Order::whereBetween('created_at', [$from, $to])->where('status', '!=', 'draft')->tap($ord)
                    ->selectRaw("DATE(created_at) d, COUNT(*) c, SUM(status = 'completed') done, SUM(status = 'cancelled') cancelled")->groupBy('d')->orderBy('d')->get()
                    ->map(fn ($r) => [$r->d, $r->c, $r->done, $r->cancelled])->all(), null],
            'low-stock' => [['Məhsul', 'Cari', 'Minimum', 'Vahid'],
                InventoryItem::whereColumn('quantity', '<=', 'min_quantity')->orderBy('quantity')->get()
                    ->map(fn ($i) => [$i->title, rtrim(rtrim($i->quantity, '0'), '.'), rtrim(rtrim($i->min_quantity, '0'), '.'), $i->unit])->all(), null],
            'stock-movements' => [['Tarix', 'Məhsul', 'Tip', 'Miqdar', 'Qeyd'],
                \App\Models\StockMovement::with('item')->whereBetween('created_at', [$from, $to])->latest()->limit(500)->get()
                    ->map(fn ($m) => [$m->created_at->format('d.m.Y H:i'), $m->item?->title, \App\Models\StockMovement::TYPES[$m->type] ?? $m->type, rtrim(rtrim($m->quantity, '0'), '.').' '.$m->item?->unit, $m->note])->all(), null],
            'tax-summary' => $this->grouped($paid()->selectRaw('DATE(created_at) k, SUM(tax_total) c, SUM(service_charge_total) t')->groupBy('k')->orderBy('k'), ['Tarix', 'Vergi', 'Servis haqqı'], null, true),
            'sales-by-branch' => $this->grouped($paid()->leftJoin('branches', 'branches.id', '=', 'invoices.branch_id')
                ->selectRaw("COALESCE(branches.name, 'Filialsız') k, COUNT(*) c, SUM(invoices.total) t")->groupBy('k')->orderByDesc('t'), ['Filial', 'Çek', 'Gəlir']),
            'sales-by-terminal' => $this->grouped($paid()->leftJoin('terminals', 'terminals.id', '=', 'invoices.terminal_id')->leftJoin('branches', 'branches.id', '=', 'invoices.branch_id')
                ->selectRaw("CONCAT(COALESCE(terminals.name, 'Terminalsız (panel)'), ' · ', COALESCE(branches.name, '—')) k, COUNT(*) c, SUM(invoices.total) t")->groupBy('k')->orderByDesc('t'), ['Terminal · filial', 'Çek', 'Gəlir']),
            'staff-by-terminal' => $this->grouped(Order::join('users', 'users.id', '=', 'orders.created_by')->leftJoin('terminals', 'terminals.id', '=', 'orders.terminal_id')
                ->whereBetween('orders.created_at', [$from, $to])->whereNotIn('orders.status', ['draft', 'cancelled'])->tap($ord)
                ->selectRaw("CONCAT(users.name, ' · ', COALESCE(terminals.name, 'Terminalsız')) k, COUNT(*) c, SUM((SELECT SUM(oi.price * oi.quantity) FROM order_items oi WHERE oi.order_id = orders.id AND oi.status != 'cancelled')) t")
                ->groupBy('k')->orderBy('k'), ['İşçi · terminal', 'Sifariş', 'Sifariş məbləği']),
            'reservations' => [['Tarix', 'Müştəri', 'Telefon', 'Nəfər', 'Status'],
                Reservation::whereBetween('reserved_at', [$from, $to])->orderBy('reserved_at')->get()
                    ->map(fn ($r) => [$r->reserved_at->format('d.m.Y H:i'), $r->customer_name, $r->phone, $r->people, Reservation::STATUSES[$r->status]])->all(), null],
        };
    }

    /** k, c, t sütunlu sorğudan cədvəl + cəm sətri qurur. */
    protected function grouped($query, array $columns, ?callable $label = null, bool $bothMoney = false): array
    {
        $data = $query->get();
        $rows = $data->map(fn ($r) => [
            $label ? $label($r->k) : $r->k,
            $bothMoney ? money($r->c) : (float) $r->c + 0,
            money($r->t),
        ])->all();
        $summary = ['Cəm', $bothMoney ? money($data->sum('c')) : $data->sum('c') + 0, money($data->sum('t'))];

        return [$columns, $rows, $summary];
    }
}
