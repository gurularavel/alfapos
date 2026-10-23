<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ItemReturn;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\StaffMeal;
use App\Models\StockMovement;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Tenancy;
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
        'returns' => ['Nəzarət', 'Ləğv və qaytarmalar', 'Hər ləğv olunan / qaytarılan məhsul: səbəb, kim edib, kim təsdiqləyib.'],
        'returns-by-staff' => ['Nəzarət', 'İşçilər üzrə ləğv', 'Hər işçinin sifarişlərindən nə qədəri ləğv / qaytarılıb (faizlə). Fırıldağa nəzarət.'],
        'discounts' => ['Nəzarət', 'Endirim və servis imtinası', 'Endirimli sifarişlər: kim verib, kim təsdiqləyib, məbləğ.'],
        'payment-summary' => ['Ödənişlər', 'Ödəniş növləri', 'Nağd, kart və digər ödəniş növləri üzrə cəm.'],
        'item-sales' => ['Menyu', 'Məhsul satışları', 'Hər məhsuldan satılan miqdar və məbləğ.'],
        'category-sales' => ['Menyu', 'Kateqoriya satışları', 'Kateqoriyalar üzrə gəlir.'],
        'item-profit' => ['Menyu', 'Məhsullar üzrə mənfəət', 'Satış, maya dəyəri, ümumi mənfəət və marja (anbar alış qiymətləri əsasında).'],
        'variant-sales' => ['Menyu', 'Variant satışları', 'Ölçü/variant üzrə satış.'],
        'top-customers' => ['Müştərilər', 'Top müştərilər', 'Ən çox xərcləyən müştərilər.'],
        'customer-birthdays' => ['Müştərilər', 'Ad günləri', 'Bu ay ad günü olan müştərilər.'],
        'staff-orders' => ['Əməliyyat', 'İşçilər üzrə sifarişlər', 'Hər işçinin yaratdığı sifariş və məbləğ.'],
        'kitchen-tokens' => ['Əməliyyat', 'Token hesabatı', 'Gün üzrə token sayı və statuslar.'],
        'low-stock' => ['Anbar', 'Az qalan stok', 'Minimum həddən aşağı düşən məhsullar.'],
        'stock-movements' => ['Anbar', 'Stok hərəkətləri', 'Giriş, çıxış, israf və satış hərəkətləri.'],
        'ready-stock' => ['Anbar', 'Hazır yemək hərəkəti', 'Hər hazır yemək üzrə hazırlanan, satılan, personala verilən, israf və qalıq porsiya.'],
        'staff-meals' => ['Anbar', 'Personal yeməyi', 'İşçilər üzrə verilən yemək və dəyəri.'],
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

        $from = query_date($request->query('from'), today()->subDays(29)->toDateString())->startOfDay();
        $to = query_date($request->query('to'), today()->toDateString())->endOfDay();
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
            'sales-summary' => (function () use ($paid, $from, $to) {
                $s = $paid()->selectRaw('COUNT(*) c, SUM(sub_total) sub, SUM(discount_total) disc, SUM(tax_total) tax, SUM(service_charge_total) svc, SUM(deposit_topup) dep, SUM(total) total')->first();
                $refunds = (float) ItemReturn::where('kind', 'refund')->whereBetween('created_at', [$from, $to])
                    ->when($this->branchId, fn ($w) => $w->where('branch_id', $this->branchId))
                    ->when($this->terminalId, fn ($w) => $w->where('terminal_id', $this->terminalId))->sum('amount');
                $rows = [
                    ['Çek sayı', $s->c],
                    ['Brüt satış', money($s->sub)],
                    ['Endirimlər', money($s->disc)],
                    ['Vergi', money($s->tax)],
                    ['Servis haqqı', money($s->svc)],
                    ['Depozit fərqləri (kabinet)', money($s->dep)],
                    ['Xalis gəlir', money($s->total)],
                    ['Orta çek', money($s->c ? $s->total / $s->c : 0)],
                    ['Müştəriyə qaytarılıb', money($refunds)],
                    ['Qaytarmalardan sonra', money($s->total - $refunds)],
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
            'item-profit' => $this->itemProfit($items()),
            'returns' => [['Vaxt', 'Token', 'Məhsul', 'Məbləğ', 'Növ', 'Səbəb', 'Sifarişi verən', 'Edən', 'Təsdiq'],
                ItemReturn::with(['order:id,token_no', 'orderCreator:id,name', 'requester:id,name', 'approver:id,name'])
                    ->whereBetween('created_at', [$from, $to])->when($this->branchId, fn ($w) => $w->where('branch_id', $this->branchId))
                    ->when($this->terminalId, fn ($w) => $w->where('terminal_id', $this->terminalId))->latest('id')->get()
                    ->map(fn (ItemReturn $r) => [$r->created_at->format('d.m H:i'), '#'.$r->order?->token_no, $r->quantity.' × '.$r->title, money($r->amount),
                        ItemReturn::KINDS[$r->kind].($r->after_bill ? ' · çekdən sonra' : ''), ItemReturn::REASONS[$r->reason] ?? $r->reason,
                        $r->orderCreator?->name ?? '—', $r->requester?->name ?? '—', $r->approver?->name ?? '—'])->all(), null],
            'returns-by-staff' => $this->returnsByStaff($from, $to, $ord, $tid),
            'discounts' => [['Vaxt', 'Token', 'Endirim', 'Servis imtinası', 'Sifarişi verən', 'Təsdiq'],
                Order::with(['creator:id,name', 'discountApprover:id,name'])->whereBetween('created_at', [$from, $to])->where('status', '!=', 'draft')->tap($ord)
                    ->where(fn ($q) => $q->where('discount_value', '>', 0)->orWhere('no_service_charge', true))->latest('id')->get()
                    ->map(fn (Order $o) => [$o->created_at->format('d.m H:i'), '#'.$o->token_no,
                        $o->discount_value > 0 ? ($o->discount_type === 'percentage' ? qty($o->discount_value).'%' : money($o->discount_value)) : '—',
                        $o->no_service_charge ? 'bəli' : '—', $o->creator?->name ?? '—', $o->discountApprover?->name ?? '—'])->all(), null],
            'ready-stock' => [['Yemək', 'Hazırlanıb', 'Satılıb', 'Personal', 'İsraf', 'Sayım fərqi', 'Cari qalıq', 'Maya / porsiya'],
                MenuItem::where('track_stock', true)->orderBy('title')->get()->map(function (MenuItem $m) use ($from, $to) {
                    // Ləğv olunan sifariş / personal yeməyi "return" kimi geri gəlir — öz mənbəyindən çıxılır
                    $q = $m->readyStockMovements()->whereBetween('created_at', [$from, $to])
                        ->selectRaw("CASE WHEN type = 'return' THEN IF(staff_meal_id IS NULL, 'sale', 'staff') ELSE type END t, SUM(quantity) q")
                        ->groupBy('t')->pluck('q', 't');

                    return [$m->title, qty($q['production'] ?? 0), qty(-($q['sale'] ?? 0)), qty(-($q['staff'] ?? 0)),
                        qty(-($q['wastage'] ?? 0)), qty($q['count'] ?? 0), qty($m->stock_quantity), $m->stock_cost !== null ? money($m->stock_cost) : '—'];
                })->all(), null],
            'staff-meals' => $this->staffMeals($from, $to),
            'low-stock' => [['Məhsul', 'Cari', 'Minimum', 'Vahid'],
                InventoryItem::whereColumn('quantity', '<=', 'min_quantity')->orderBy('quantity')->get()
                    ->map(fn ($i) => [$i->title, rtrim(rtrim($i->quantity, '0'), '.'), rtrim(rtrim($i->min_quantity, '0'), '.'), $i->unit])->all(), null],
            'stock-movements' => [['Tarix', 'Məhsul', 'Tip', 'Miqdar', 'Qeyd'],
                StockMovement::with('item')->whereBetween('created_at', [$from, $to])->latest()->limit(500)->get()
                    ->map(fn ($m) => [$m->created_at->format('d.m.Y H:i'), $m->item?->title, StockMovement::TYPES[$m->type] ?? $m->type, rtrim(rtrim($m->quantity, '0'), '.').' '.$m->item?->unit, $m->note])->all(), null],
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
    /**
     * Məhsullar üzrə mənfəət: satış anında sətirdə saxlanmış maya ilə. Mayası bilinməyən sətirlər (alış qiyməti
     * və ya resepti olmayan) mənfəətə qatılmır və ayrıca göstərilir.
     *
     * @return array{0: list<string>, 1: list<list<string|int|float>>, 2: list<string|int|float>}
     */
    protected function itemProfit($items): array
    {
        $data = $items->selectRaw('order_items.title k, SUM(order_items.quantity) qty, SUM(order_items.quantity * order_items.price) revenue,
                SUM(CASE WHEN order_items.cost IS NULL THEN 0 ELSE order_items.quantity * order_items.price END) known_revenue,
                SUM(order_items.quantity * order_items.cost) cost, SUM(CASE WHEN order_items.cost IS NULL THEN order_items.quantity ELSE 0 END) unknown_qty')
            ->groupBy('k')->orderByDesc('revenue')->get();
        $margin = fn ($revenue, $cost) => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100).'%' : '—';

        $rows = $data->map(fn ($r) => [
            $r->k,
            (float) $r->qty + 0,
            money($r->revenue),
            $r->cost !== null ? money($r->cost) : '—',
            $r->cost !== null ? money($r->known_revenue - $r->cost) : '—',
            $r->cost !== null ? $margin((float) $r->known_revenue, (float) $r->cost) : '—',
            $r->unknown_qty > 0 ? ((float) $r->unknown_qty + 0).' əd' : '',
        ])->all();
        $knownRevenue = (float) $data->sum('known_revenue');
        $cost = (float) $data->sum('cost');

        return [
            ['Məhsul', 'Miqdar', 'Gəlir', 'Maya dəyəri', 'Mənfəət', 'Marja', 'Mayası bilinmir'],
            $rows,
            ['Cəm', (float) $data->sum('qty') + 0, money($data->sum('revenue')), money($cost), money($knownRevenue - $cost), $margin($knownRevenue, $cost), ($u = (float) $data->sum('unknown_qty')) ? ($u + 0).' əd' : ''],
        ];
    }

    /**
     * İşçilər üzrə ləğv / qaytarma: sifarişi verən işçinin vurduğu məbləğin neçə faizi ləğv olunub.
     * Yüksək faiz (5%+) — "sifarişi vurub pulu götürüb, sonra ləğv etdirmək" kimi halların əlamətidir.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>, 2: null}
     */
    protected function returnsByStaff(Carbon $from, Carbon $to, callable $ord, int $tid): array
    {
        $returns = ItemReturn::whereBetween('created_at', [$from, $to])->whereNotNull('order_creator_id')
            ->when($this->branchId, fn ($w) => $w->where('branch_id', $this->branchId))
            ->groupBy('order_creator_id')
            ->selectRaw("order_creator_id, COUNT(*) c, SUM(amount) amount, SUM(kind = 'refund') refunds, SUM(after_bill) after_bill")
            ->get()->keyBy('order_creator_id');
        // Vurulan (ləğvdən əvvəlki) məbləğ: ləğv olunan sətirlər də daxil
        $ordered = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tid)->whereBetween('orders.created_at', [$from, $to])->where('orders.status', '!=', 'draft')->tap($ord)
            ->whereIn('orders.created_by', $returns->keys())
            ->groupBy('orders.created_by')->selectRaw('orders.created_by u, SUM(order_items.price * order_items.quantity) t')->pluck('t', 'u');

        $rows = User::whereIn('id', $returns->keys())->get(['id', 'name'])
            ->map(function ($u) use ($returns, $ordered) {
                $r = $returns[$u->id];
                $share = ($ordered[$u->id] ?? 0) > 0 ? round($r->amount / $ordered[$u->id] * 100, 1) : 0;

                return [$u->name, (int) $r->c, (int) $r->refunds, (int) $r->after_bill, money($r->amount), money($ordered[$u->id] ?? 0), $share.'%'.($share >= 5 ? ' ⚠' : '')];
            })->sortByDesc(fn ($row) => (float) $row[6])->values()->all();

        return [['İşçi', 'Ləğv / qaytarma', 'O cümlədən qaytarma', 'Hesab çekindən sonra', 'Məbləğ', 'Vurduğu sifarişlər', 'Pay'], $rows, null];
    }

    /** @return array{0: list<string>, 1: list<list<string|int>>, 2: list<string|int>} */
    protected function staffMeals(Carbon $from, Carbon $to): array
    {
        $data = StaffMeal::where('status', 'active')->whereBetween('created_at', [$from, $to])
            ->when($this->branchId, fn ($w) => $w->where('branch_id', $this->branchId))
            ->selectRaw('employee_name k, COUNT(*) c, SUM(total) t, SUM(cost) cost, SUM(cost IS NULL) unknown')
            ->groupBy('k')->orderByDesc('t')->get();

        return [
            ['İşçi', 'Dəfə', 'Dəyər (menyu qiyməti)', 'Maya dəyəri'],
            $data->map(fn ($r) => [$r->k, (int) $r->c, money($r->t), money($r->cost).($r->unknown ? " ({$r->unknown} bilinmir)" : '')])->all(),
            ['Cəm', (int) $data->sum('c'), money($data->sum('t')), money($data->sum('cost'))],
        ];
    }

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
