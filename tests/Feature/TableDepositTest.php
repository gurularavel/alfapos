<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kabinet depoziti (minimum məbləğ). Plov = 14.00, servis 5% → 1 plov = 14.70. */
class TableDepositTest extends TestCase
{
    use RefreshDatabase;

    protected StoreTable $vip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($admin->tenant);
        $this->actingAs($admin);
        $this->vip = StoreTable::where('title', 'VIP')->firstOrFail();
        $this->vip->update(['deposit' => 50]);
    }

    protected function order(int $qty, string $action = 'kitchen', ?int $tableId = null, string $type = 'dinein'): array
    {
        return $this->postJson(route('pos.store'), [
            'action' => $action, 'delivery_type' => $type, 'store_table_id' => $tableId ?? $this->vip->id,
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => $qty]],
            'payment_type_id' => PaymentType::value('id'),
        ])->assertOk()->json();
    }

    public function test_bill_below_deposit_is_topped_up_and_shown_on_receipt(): void
    {
        $invoice = Invoice::findOrFail($this->order(1, 'pay')['invoice_id']);

        $this->assertEquals(50, (float) $invoice->deposit_amount);
        $this->assertEquals(35.30, (float) $invoice->deposit_topup);
        $this->assertEquals(50.00, (float) $invoice->total);

        $this->get(route('invoices.receipt', $invoice))->assertOk()
            ->assertSee('Kabinet depoziti (min.)')->assertSee('Depozitə qədər fərq')->assertSee('35.30');
    }

    public function test_bill_above_deposit_is_unchanged(): void
    {
        $invoice = Invoice::findOrFail($this->order(4, 'pay')['invoice_id']); // 4 × 14.70 = 58.80
        $this->assertEquals(0, (float) $invoice->deposit_topup);
        $this->assertEquals(58.80, (float) $invoice->total);
        $this->get(route('invoices.receipt', $invoice))->assertSee('Depozit qarşılanıb');
    }

    public function test_table_bill_with_several_orders_uses_deposit_once(): void
    {
        $a = $this->order(1)['order']['id'];
        $b = $this->order(1)['order']['id'];

        $group = collect($this->getJson(route('orders.board'))->json())->firstWhere('key', 't'.$this->vip->id);
        $this->assertEquals(29.40, $group['orders_total']);
        $this->assertEquals(20.60, $group['deposit_topup']);
        $this->assertEquals(50.00, $group['due']);

        $invoiceId = $this->postJson(route('orders.pay'), ['order_ids' => [$a, $b], 'payment_type_id' => PaymentType::value('id')])->json('invoice_id');
        $this->assertEquals(50.00, (float) Invoice::find($invoiceId)->total);
    }

    public function test_no_deposit_for_takeaway_or_regular_tables(): void
    {
        $regular = StoreTable::where('title', 'Masa 1')->value('id');
        $this->assertEquals(0, (float) Invoice::find($this->order(1, 'pay', $regular)['invoice_id'])->deposit_topup);
        $this->assertEquals(0, (float) Invoice::find($this->order(1, 'pay', null, 'takeaway')['invoice_id'])->deposit_amount);
    }

    public function test_deposit_is_set_in_table_settings(): void
    {
        $this->put(route('settings.tables.update', $this->vip), ['title' => 'VIP kabinet', 'seating_capacity' => 8, 'deposit' => 200])->assertSessionHasNoErrors();
        $this->assertEquals(200, (float) $this->vip->fresh()->deposit);
        $this->put(route('settings.tables.update', $this->vip), ['title' => 'VIP kabinet', 'seating_capacity' => 8, 'deposit' => ''])->assertSessionHasNoErrors();
        $this->assertEquals(0, (float) $this->vip->fresh()->deposit);
    }
}
