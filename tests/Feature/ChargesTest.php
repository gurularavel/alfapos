<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\PaymentType;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vergi və servis haqqının açarları. Plov = 14.00, ƏDV 18% (daxil), servis 5%. */
class ChargesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($this->admin->tenant);
        $this->actingAs($this->admin);
    }

    protected function pay(string $deliveryType, bool $waive = false): Invoice
    {
        $id = $this->postJson(route('pos.store'), [
            'action' => 'pay', 'delivery_type' => $deliveryType, 'no_service_charge' => $waive,
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 1]],
            'payment_type_id' => PaymentType::value('id'),
        ])->assertOk()->json('invoice_id');

        return Invoice::findOrFail($id);
    }

    public function test_settings_page_saves_switches(): void
    {
        $this->get(route('settings.taxes.index'))->assertOk()->assertSee('Servis haqqı');
        $this->put(route('settings.charges.update'), ['service_charge' => 10, 'is_service_charge_enabled' => 1])->assertSessionHasNoErrors();

        $tenant = $this->admin->tenant->fresh();
        $this->assertFalse($tenant->is_tax_enabled);
        $this->assertTrue($tenant->is_service_charge_enabled);
        $this->assertFalse($tenant->service_charge_dinein_only);
        $this->assertEquals(10, (float) $tenant->service_charge);
    }

    public function test_tax_can_be_disabled(): void
    {
        $this->admin->tenant->update(['is_tax_enabled' => false, 'is_service_charge_enabled' => false]);
        $invoice = $this->pay('takeaway');
        $this->assertEquals(0, (float) $invoice->tax_total);
        $this->assertEquals(14.00, (float) $invoice->total);
    }

    public function test_service_charge_can_be_disabled(): void
    {
        $this->admin->tenant->update(['is_service_charge_enabled' => false]);
        $invoice = $this->pay('dinein');
        $this->assertEquals(0, (float) $invoice->service_charge_total);
        $this->assertEquals(2.14, (float) $invoice->tax_total);
    }

    public function test_service_charge_dinein_only(): void
    {
        $this->admin->tenant->update(['is_service_charge_enabled' => true, 'service_charge_dinein_only' => true]);
        $this->assertEquals(0, (float) $this->pay('takeaway')->service_charge_total);
        $this->assertEquals(0.70, (float) $this->pay('dinein')->service_charge_total);
    }

    public function test_cashier_can_waive_service_charge_for_one_order(): void
    {
        $this->admin->tenant->update(['is_service_charge_enabled' => true, 'service_charge_dinein_only' => false]);
        $this->assertEquals(0, (float) $this->pay('dinein', waive: true)->service_charge_total);
        $this->assertEquals(0.70, (float) $this->pay('dinein')->service_charge_total);
    }

    public function test_table_bill_respects_waiver_on_orders_board(): void
    {
        $id = $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'dinein', 'no_service_charge' => true,
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 1]],
        ])->assertOk()->json('order.id');

        $group = collect($this->getJson(route('orders.board'))->json())->first(fn ($g) => in_array($id, $g['unpaid_ids']));
        $this->assertEquals(14.00, $group['due']);
    }
}
