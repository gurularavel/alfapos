<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDisplay;
use App\Models\Department;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\Tenant;
use App\Services\ApprovalService;
use App\Services\OrderService;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function index(Tenancy $tenancy, PosTerminal $terminal)
    {
        $tenant = $tenancy->tenant();

        // Gizlədilmiş məhsullar da göndərilir: admin POS-da "Menyunu redaktə et" rejimində onları yenidən aça bilsin
        $items = MenuItem::with(self::ITEM_RELATIONS)->orderBy('title')->get()->map(fn (MenuItem $m) => $this->itemData($m, $tenant));

        $busyTables = Order::active()->whereNotNull('store_table_id')->pluck('store_table_id')->unique();

        return view('pos.index', [
            'posData' => [
                'items' => $items,
                'categories' => Category::where('is_enabled', true)->orderBy('sort')->get(['id', 'title']),
                'tables' => $terminal->scopeToBranch(StoreTable::query())->orderBy('floor')->orderByRaw('LENGTH(title)')->orderBy('title')->get()->map(fn ($t) => [
                    'id' => $t->id, 'title' => $t->title, 'floor' => $t->floor, 'label' => $t->label(), 'seats' => $t->seating_capacity, 'busy' => $busyTables->contains($t->id), 'deposit' => (float) $t->deposit, 'x' => $t->pos_x, 'y' => $t->pos_y,
                ]),
                'paymentTypes' => PaymentType::where('is_active', true)->get(['id', 'title', 'icon']),
                'service' => [
                    'enabled' => $tenant->is_service_charge_enabled && (float) $tenant->service_charge > 0,
                    'rate' => (float) $tenant->service_charge,
                    'dineinOnly' => $tenant->service_charge_dinein_only,
                ],
                'taxEnabled' => $tenant->is_tax_enabled,
                // Çap ayarı "Token çap et": mətbəxə göndəriləndə KOT avtomatik çap olunur (əlavə sifarişlər də ayrıca)
                'autoPrintKot' => (bool) $tenant->printSetting('print_token', true),
                'autoPrintReceipt' => (bool) $tenant->printSetting('auto_print', true),
                'currency' => $tenant->currency,
                // Kapital Bank: masa hesabını onlayn ödənişə göndərmək (Ayarlar → Onlayn ödəniş)
                'onlinePayments' => $tenant->onlinePaymentsEnabled(),
                'deliveryTypes' => Order::DELIVERY_TYPES,
                'terminal' => $terminal->state(),
                'displays' => CustomerDisplay::orderBy('name')->get()->map(fn (CustomerDisplay $d) => [
                    'id' => $d->id, 'name' => $d->name, 'url' => $d->url(), 'push' => route('pos.display.push', $d),
                ]),
                'sounds' => [
                    'addItem' => (bool) $tenant->terminalSetting('sound_add_item'),
                    'newOrder' => (bool) $tenant->terminalSetting('sound_new_order'),
                ],
                'routes' => [
                    'store' => route('pos.store'),
                    'drafts' => route('pos.drafts'),
                    'qr' => route('pos.qr'),
                    'customers' => route('pos.customers'),
                    'customerStore' => route('pos.customers.store'),
                    'receipt' => route('invoices.receipt', '__ID__'),
                    'token' => route('orders.token', '__ID__'),
                    'board' => route('orders.board'),
                    'pay' => route('orders.pay'),
                    'bill' => route('orders.bill'),
                    'transfer' => route('orders.transfer'),
                    'itemUpdate' => route('pos.items.update', '__ID__'),
                    'onlineStore' => route('orders.online.store'),
                    'onlineShow' => route('orders.online.show', '__ID__'),
                    'onlineCancel' => route('orders.online.cancel', '__ID__'),
                ],
            ],
        ]);
    }

    protected const ITEM_RELATIONS = ['variants:id,menu_item_id,title,price', 'addons:id,menu_item_id,title,price', 'tax:id,rate,type', 'category:id,title'];

    /**
     * Sifarişdə məhsulu olan və çeki kassada çap olunan şöbələr.
     *
     * @return list<array{id: ?int, name: string}>
     */
    public static function posPrintDepartments(Order $order): array
    {
        $ids = $order->items()->where('status', '!=', 'cancelled')->distinct()->pluck('department_id');
        $departments = Department::whereIn('id', $ids->filter())->get()->keyBy('id');

        return $ids->map(fn ($id) => $departments[$id] ?? null)
            ->filter(fn (?Department $d) => $d === null || $d->print_at === 'pos')
            ->map(fn (?Department $d) => ['id' => $d?->id, 'name' => $d?->name ?? 'Mətbəx'])
            ->values()->all();
    }

    /** POS kartı üçün məhsul məlumatı. */
    protected function itemData(MenuItem $m, Tenant $tenant): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'price' => (float) $m->price,
            'image' => $m->imageUrl(),
            'category_id' => $m->category_id,
            'category' => $m->category?->title,
            'enabled' => (bool) $m->is_enabled,
            'tax_rate' => $tenant->is_tax_enabled ? (float) ($m->tax?->rate ?? 0) : 0.0,
            'tax_type' => $m->tax?->type ?? 'exclusive',
            'variants' => $m->variants->map(fn ($v) => ['id' => $v->id, 'title' => $v->title, 'price' => (float) $v->price]),
            'addons' => $m->addons->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'price' => (float) $a->price]),
            'stock' => $m->track_stock ? (float) $m->stock_quantity : null, // hazır porsiya (izlənmirsə null)
        ];
    }

    /** Admin POS-un içində məhsulu redaktə edir: ad, qiymət, kateqoriya, POS / QR menyuda göstərilmə. */
    public function updateItem(Request $request, MenuItem $menuItem, Tenancy $tenancy, PosTerminal $terminal)
    {
        abort_unless($terminal->effectiveUser()?->isAdmin(), 403, 'Məhsulları yalnız admin redaktə edə bilər.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('tenant_id', $tenancy->id())],
            'is_enabled' => ['required', 'boolean'],
        ]);
        $menuItem->update($data);
        AuditLog::record('menu_item.updated_in_pos', $menuItem, $menuItem->getChanges());

        return response()->json([
            'message' => $menuItem->is_enabled ? "{$menuItem->title} yeniləndi." : "{$menuItem->title} menyuda gizlədildi.",
            'item' => $this->itemData($menuItem->load(self::ITEM_RELATIONS), $tenancy->tenant()),
        ]);
    }

    /**
     * action: draft | kitchen | pay
     *  - draft: qaralama kimi saxlanılır (mətbəxə getmir)
     *  - kitchen: sifariş mətbəxə göndərilir, ödəniş sonra (Orders ekranından)
     *  - pay: sifariş yaradılır, faktura kəsilir və mətbəxə göndərilir
     */
    public function store(Request $request, OrderService $service, PosTerminal $terminal, ApprovalService $approval)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['draft', 'kitchen', 'pay'])],
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.menu_item_id' => ['required', 'integer'],
            'cart.*.variant_id' => ['nullable', 'integer'],
            'cart.*.addon_ids' => ['nullable', 'array'],
            'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'cart.*.notes' => ['nullable', 'string', 'max:100'],
            'delivery_type' => ['required', Rule::in(array_keys(Order::DELIVERY_TYPES))],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', app(Tenancy::class)->id())],
            'store_table_id' => ['nullable', Rule::exists('store_tables', 'id')->where('tenant_id', app(Tenancy::class)->id())],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'no_service_charge' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'draft_reference' => ['nullable', 'string', 'max:60'],
            'draft_id' => ['nullable', 'integer'],
            ...OrderController::paymentRules(required: false),
            'approver_pin' => ['nullable', 'string'],
        ]);

        abort_if($data['action'] === 'pay' && ! $terminal->effectiveUser()?->hasScope('PAYMENTS'), 403, 'Ödəniş qəbul etmək icazəniz yoxdur. Sifarişi mətbəxə göndərin.');
        if ($data['action'] === 'pay' && empty($data['payment_type_id']) && empty($data['payments'])) {
            throw ValidationException::withMessages(['payment_type_id' => 'Ödəniş növünü seçin.']);
        }

        // Endirim və servis haqqından imtina pulu azaldır — menecer təsdiqi ilə (qaralamada hələ lazım deyil)
        $discounted = (float) ($data['discount_value'] ?? 0) > 0 || ! empty($data['no_service_charge']);
        $data['discount_approved_by'] = $discounted && $data['action'] !== 'draft' ? $approval->approve($data['approver_pin'] ?? null)->id : null;

        // Bir tranzaksiya: ödəniş alınmasa (məs. bölünmüş məbləğ uyğun gəlmir) sifariş də yaranmır, mətbəxə getmir
        [$order, $invoice] = DB::transaction(function () use ($data, $service) {
            // Qaralamadan davam edilirsə, köhnə qaralamanı silirik
            if (! empty($data['draft_id'])) {
                Order::where('status', 'draft')->whereKey($data['draft_id'])->delete();
            }

            $order = $service->create($data['cart'], [
                ...$data,
                'status' => $data['action'] === 'draft' ? 'draft' : 'created',
            ]);

            $invoice = $data['action'] === 'pay'
                ? $service->pay(collect([$order]), $data['payments'] ?? (int) $data['payment_type_id'], $data['customer_id'] ?? null, complete: false)
                : null;

            return [$order, $invoice];
        });

        return response()->json([
            'message' => match ($data['action']) {
                'draft' => 'Qaralama saxlanıldı.',
                'kitchen' => 'Sifariş mətbəxə göndərildi.',
                'pay' => 'Ödəniş qəbul edildi, sifariş mətbəxə göndərildi.',
            },
            'order' => ['id' => $order->id, 'token_no' => $order->token_no],
            // Kassada çap olunan şöbələr (öz cihazı olan şöbələrin çekini həmin cihaz çap edir)
            'print_departments' => $data['action'] === 'draft' ? [] : self::posPrintDepartments($order),
            'invoice_id' => $invoice?->id,
            // Satılan hazır yeməklərin yeni qalığı (POS kartlarında yenilənir)
            'stock' => MenuItem::whereIn('id', collect($data['cart'])->pluck('menu_item_id'))->where('track_stock', true)->pluck('stock_quantity', 'id')->map(fn ($q) => (float) $q),
        ]);
    }

    public function drafts()
    {
        return Order::with('items', 'customer:id,name,phone')->where('status', 'draft')->latest()->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'reference' => $o->draft_reference,
                'created_at' => $o->created_at->format('d.m H:i'),
                'delivery_type' => $o->delivery_type,
                'store_table_id' => $o->store_table_id,
                'customer' => $o->customer,
                'discount_type' => $o->discount_type,
                'discount_value' => (float) $o->discount_value,
                'notes' => $o->notes,
                'items' => $o->items->map(fn ($i) => [
                    'menu_item_id' => $i->menu_item_id, 'variant_id' => $i->variant_id,
                    'addon_ids' => collect($i->addons)->pluck('id'), 'quantity' => $i->quantity, 'notes' => $i->notes,
                ]),
            ]);
    }

    public function destroyDraft(Order $order)
    {
        abort_unless($order->status === 'draft', 404);
        $order->delete();

        return response()->noContent();
    }

    /** QR menyudan gələn, hələ qəbul edilməmiş sifarişlər. */
    public function qrOrders()
    {
        return app(PosTerminal::class)->scopeToBranch(Order::with('items', 'table'))->where('source', 'qr')->where('status', 'created')
            ->where('created_at', '>=', today())->latest()->get()
            ->map(fn (Order $o) => [
                'id' => $o->id, 'token_no' => $o->token_no, 'table' => $o->table?->label(),
                'time' => $o->created_at->format('H:i'), 'notes' => $o->notes,
                'items' => $o->items->map(fn ($i) => $i->quantity.' × '.$i->title.($i->variant_title ? ' ('.$i->variant_title.')' : '')),
            ]);
    }

    public function searchCustomers(Request $request)
    {
        $q = trim((string) $request->query('q'));

        return Customer::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'phone']);
    }

    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30', Rule::unique('customers')->where('tenant_id', app(Tenancy::class)->id())],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
        ]);

        return Customer::create($data)->only(['id', 'name', 'phone']);
    }
}
