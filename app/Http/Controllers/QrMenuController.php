<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\StoreTable;
use App\Models\Tenant;
use App\Services\OrderService;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** Müştərinin telefonda açdığı public QR menyu: /m/{tenant qr_code}?t={masa qr_token} */
class QrMenuController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function show(Request $request, string $code)
    {
        $tenant = $this->resolve($code);
        $table = $this->table($request->query('t'));

        return view('qr.menu', [
            'tenant' => $tenant,
            'table' => $table,
            'categories' => Category::where('is_enabled', true)->orderBy('sort')->get(),
            'items' => MenuItem::with('variants', 'addons')->where('is_enabled', true)->orderBy('title')->get(),
            'canOrder' => $tenant->is_qr_order_enabled && $table !== null,
        ]);
    }

    public function order(Request $request, string $code, OrderService $service)
    {
        $tenant = $this->resolve($code);
        $data = $request->validate([
            't' => ['required', 'string'],
            'cart' => ['required', 'array', 'min:1', 'max:50'],
            'cart.*.menu_item_id' => ['required', 'integer'],
            'cart.*.variant_id' => ['nullable', 'integer'],
            'cart.*.addon_ids' => ['nullable', 'array'],
            'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $table = $this->table($data['t']);
        abort_unless($tenant->is_qr_order_enabled && $table, 403, 'QR sifariş aktiv deyil.');

        $order = $service->create($data['cart'], [
            'delivery_type' => 'dinein',
            'store_table_id' => $table->id,
            'source' => 'qr',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Sifarişiniz qəbul edildi!', 'token_no' => $order->token_no]);
    }

    public function feedback(Request $request, string $code)
    {
        $tenant = $this->resolve($code);
        abort_unless($tenant->is_feedback_enabled, 403);

        Feedback::create($request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Rəyiniz üçün təşəkkür edirik!');
    }

    protected function resolve(string $code): Tenant
    {
        $tenant = Tenant::where('qr_code', $code)->firstOrFail();
        abort_unless($tenant->is_qr_menu_enabled && $tenant->hasActiveSubscription(), 404);
        $this->tenancy->set($tenant);

        return $tenant;
    }

    protected function table(?string $token): ?StoreTable
    {
        return $token ? StoreTable::where('qr_token', $token)->first() : null;
    }
}
