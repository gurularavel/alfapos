<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        app(Tenancy::class)->set(null);
        auth()->logout();
    }

    protected function admin(): User
    {
        return User::where('email', 'demo@pos.test')->first();
    }

    /** Tenant scope-u test daxilində aktivləşdirir (model sorğuları üçün). */
    protected function actAs(User $user): static
    {
        app(Tenancy::class)->set($user->tenant);

        return $this->actingAs($user);
    }

    public function test_every_tenant_page_renders_for_admin(): void
    {
        $admin = $this->admin();
        $this->actAs($admin);
        $item = MenuItem::first();
        $table = StoreTable::first();
        $invoice = Invoice::first();
        $customer = \App\Models\Customer::first();

        $urls = [
            route('dashboard'), route('pos'), route('orders.index'), route('orders.board'), route('kitchen'), route('kitchen.feed'),
            route('invoices.index'), route('invoices.receipt', $invoice), route('orders.token', Order::first()),
            route('customers.index'), route('customers.show', $customer), route('reservations.index'),
            route('inventory.index'), route('inventory.movements'), route('ready-stock.index'), route('staff-meals.index'), route('feedbacks.index'),
            route('users.index'), route('reports.index'), route('audit-logs.index'), route('profile'), route('subscription'),
            route('settings.index'), route('settings.store'), route('settings.print'), route('settings.tables.index'),
            route('settings.tables.qr', $table), route('settings.categories.index'), route('settings.taxes.index'),
            route('settings.payment-types.index'), route('settings.menu-items.index'), route('settings.menu-items.create'),
            route('settings.menu-items.edit', $item), route('pos.drafts'), route('pos.qr'), route('pos.customers', ['q' => 'El']),
        ];
        foreach (array_keys(\App\Http\Controllers\ReportController::REPORTS) as $report) {
            $urls[] = route('reports.show', $report);
        }

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_superadmin_pages_and_public_pages(): void
    {
        $this->actingAs(User::where('email', 'super@pos.test')->first());
        $tenant = Tenant::first();
        foreach ([route('admin.tenants.index'), route('admin.tenants.create'), route('admin.tenants.edit', $tenant), route('admin.plans.index'), route('admin.plans.create')] as $url) {
            $this->get($url)->assertOk();
        }
        auth()->logout();

        $this->get('/')->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('qr.show', $tenant->qr_code))->assertOk();
    }

    public function test_pos_pay_flow_calculates_totals_and_deducts_stock(): void
    {
        $this->actAs($this->admin());
        $latte = MenuItem::where('title', 'Latte')->first();
        $variant = $latte->variants()->where('title', 'Böyük')->first(); // 7.50
        $addon = $latte->addons()->where('title', 'Əlavə şot')->first(); // 1.50
        $milkBefore = (float) InventoryItem::where('title', 'Süd')->value('quantity');

        $response = $this->postJson(route('pos.store'), [
            'action' => 'pay',
            'cart' => [['menu_item_id' => $latte->id, 'variant_id' => $variant->id, 'addon_ids' => [$addon->id], 'quantity' => 2]],
            'delivery_type' => 'takeaway',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'payment_type_id' => PaymentType::first()->id,
        ])->assertOk();

        $invoice = Invoice::findOrFail($response->json('invoice_id'));
        // 2 × (7.50 + 1.50) = 18.00; endirim 10% = 1.80; ƏDV daxildir; servis 5% × 16.20 = 0.81
        $this->assertEquals(18.00, (float) $invoice->sub_total);
        $this->assertEquals(1.80, (float) $invoice->discount_total);
        $this->assertEquals(0.81, (float) $invoice->service_charge_total);
        $this->assertEquals(17.01, (float) $invoice->total);
        $this->assertEquals($milkBefore - 400, (float) InventoryItem::where('title', 'Süd')->value('quantity'));
    }

    public function test_kitchen_and_table_payment_flow(): void
    {
        $this->actAs($this->admin());
        $table = StoreTable::where('title', 'Masa 5')->first();
        $plov = MenuItem::where('title', 'Plov')->first();

        $orderId = $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'dinein', 'store_table_id' => $table->id,
            'cart' => [['menu_item_id' => $plov->id, 'quantity' => 1]],
        ])->assertOk()->json('order.id');

        $item = Order::find($orderId)->items->first();
        $this->postJson(route('kitchen.item', $item), ['status' => 'preparing'])->assertOk();
        $this->assertSame('preparing', Order::find($orderId)->status);
        $this->postJson(route('kitchen.ready', $orderId))->assertOk();
        $this->assertSame('ready', Order::find($orderId)->status);

        $board = collect($this->getJson(route('orders.board'))->json())->firstWhere('key', 't'.$table->id);
        $this->assertEquals([$orderId], $board['unpaid_ids']);

        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payment_type_id' => PaymentType::first()->id])->assertOk();
        $order = Order::find($orderId);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('completed', $order->status);
    }

    public function test_tenants_are_isolated(): void
    {
        $other = app(TenantProvisioner::class)->create('Başqa Kafe', 'Owner', 'other@pos.test', 'password');
        app(Tenancy::class)->set(null);
        $foreignOrder = Order::withoutGlobalScopes()->where('tenant_id', $this->admin()->tenant_id)->first();
        $foreignItem = MenuItem::withoutGlobalScopes()->where('tenant_id', $this->admin()->tenant_id)->first();

        $this->actAs($other);
        $this->get(route('settings.menu-items.edit', $foreignItem))->assertNotFound();
        $this->postJson(route('orders.cancel', $foreignOrder))->assertNotFound();
        $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'takeaway',
            'cart' => [['menu_item_id' => $foreignItem->id, 'quantity' => 1]],
        ])->assertUnprocessable();
        $this->getJson(route('orders.board'))->assertOk()->assertJsonCount(0);
    }

    public function test_scopes_restrict_staff(): void
    {
        $chef = User::where('email', 'metbex@pos.test')->first();
        $this->actAs($chef);
        $this->get(route('kitchen'))->assertOk();
        $this->get(route('pos'))->assertForbidden();
        $this->get(route('settings.index'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_expired_subscription_redirects(): void
    {
        $admin = $this->admin();
        $admin->tenant->update(['status' => 'trial', 'trial_ends_at' => now()->subDay()]);
        $this->actAs($admin->fresh());
        $this->get(route('pos'))->assertRedirect(route('subscription'));
    }

    public function test_registration_creates_tenant_with_defaults(): void
    {
        $this->post(route('register'), [
            'business_name' => 'Yeni Kafe', 'name' => 'Sahib', 'email' => 'new@pos.test',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'new@pos.test')->first();
        $this->assertTrue($user->isAdmin());
        $this->assertSame(3, PaymentType::withoutGlobalScopes()->where('tenant_id', $user->tenant_id)->count());
        $this->assertTrue($user->tenant->hasActiveSubscription());
    }
}
