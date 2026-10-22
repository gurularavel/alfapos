<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ItemReturn;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ləğv / qaytarma və fırıldağa nəzarət. Demo PIN-lər: admin 1234 (təsdiq icazəli), kassir 1111, ofisiant 2222.
 */
class ReturnsTest extends TestCase
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

    /** Terminal rejimində PIN ilə işçi kimi işləmək. */
    protected function as(string $pin): static
    {
        $this->postJson(route('pos.terminal.enter'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => $pin])->assertOk();

        return $this;
    }

    /** Terminal rejimindən çıxıb admin panelinə qayıtmaq (hesabatlar terminalda açılmır). */
    protected function panel(): static
    {
        auth()->logout();
        $this->actingAs($this->admin->fresh())->flushSession();

        return $this;
    }

    protected function order(array $cart, string $action = 'kitchen', array $extra = []): Order
    {
        $id = $this->postJson(route('pos.store'), [
            'action' => $action, 'delivery_type' => 'takeaway', 'payment_type_id' => PaymentType::value('id'),
            'cart' => collect($cart)->map(fn ($q, $title) => ['menu_item_id' => MenuItem::where('title', $title)->value('id'), 'quantity' => $q])->values()->all(),
            ...$extra,
        ])->assertOk()->json('order.id');

        return Order::with('items')->find($id);
    }

    protected function returnItem(OrderItem $item, array $data = [])
    {
        return $this->postJson(route('orders.items.return', $item), ['quantity' => 1, 'reason' => 'not_liked', 'stock_action' => 'waste', ...$data]);
    }

    public function test_waiter_void_needs_manager_pin(): void
    {
        $this->as('2222');
        $item = $this->order(['Plov' => 3])->items->first();

        $this->returnItem($item)->assertUnprocessable()->assertJsonPath('errors.approver_pin.0', 'Menecer təsdiqi lazımdır.');
        $this->returnItem($item, ['approver_pin' => '2222'])->assertJsonPath('errors.approver_pin.0', 'Ofisiant Nicat təsdiq icazəsinə malik deyil.');
        $this->returnItem($item, ['approver_pin' => '9999'])->assertJsonPath('errors.approver_pin.0', 'PIN yanlışdır.');
        $this->assertSame(0, ItemReturn::count());

        $this->returnItem($item, ['approver_pin' => '1234', 'note' => 'Duzlu idi'])->assertOk()
            ->assertJsonPath('message', 'Ləğv edildi: Plov × 1.')->assertJsonPath('kind', 'void');

        $this->assertSame(2, $item->fresh()->quantity);
        $return = ItemReturn::first();
        $this->assertSame(['void', 1, '14.00', 'Duzlu idi'], [$return->kind, $return->quantity, $return->amount, $return->note]);
        $waiter = User::where('role', 'waiter')->value('id');
        $this->assertSame([$waiter, $waiter, $this->admin->id], [$return->order_creator_id, $return->requested_by, $return->approved_by]);
        $this->assertSame('cancelled', OrderItem::find($return->order_item_id)->status);
        $this->get(route('orders.returns.slip', $return))->assertOk()->assertSee('LƏĞV — HAZIRLAMAYIN')->assertSee('1 × Plov')->assertSee('Demo Admin');
    }

    public function test_approval_pin_attempts_are_limited(): void
    {
        $this->as('2222');
        $item = $this->order(['Plov' => 1])->items->first();
        foreach (range(1, 5) as $i) {
            $this->returnItem($item, ['approver_pin' => '000'.$i])->assertJsonPath('errors.approver_pin.0', 'PIN yanlışdır.');
        }

        $this->returnItem($item, ['approver_pin' => '1234'])->assertJsonPath('errors.approver_pin.0', 'Çox səhv cəhd. 5 dəqiqə sonra yenidən yoxlayın.');
        $this->assertSame('created', $item->fresh()->status);
    }

    public function test_terminal_pin_guessing_is_limited(): void
    {
        $this->postJson(route('pos.terminal.enter'))->assertOk();
        foreach (range(1, 10) as $i) {
            $this->postJson(route('pos.terminal.unlock'), ['pin' => sprintf('%04d', 5000 + $i)])->assertJsonPath('errors.pin.0', 'PIN yanlışdır.');
        }

        $this->postJson(route('pos.terminal.unlock'), ['pin' => '1234'])->assertJsonPath('errors.pin.0', 'Çox səhv cəhd. 5 dəqiqə sonra yenidən yoxlayın.');
    }

    public function test_stock_is_restored_or_written_off(): void
    {
        $milk = (float) InventoryItem::where('title', 'Süd')->value('quantity');
        $order = $this->order(['Latte' => 2, 'Dolma' => 1]);
        [$latte, $dolma] = [$order->items->firstWhere('title', 'Latte'), $order->items->firstWhere('title', 'Dolma')];

        // Admin özü təsdiq icazəlidir — PIN istənmir
        $this->returnItem($latte, ['stock_action' => 'restore'])->assertOk();
        $this->returnItem($dolma, ['stock_action' => 'waste'])->assertOk();

        $this->assertEqualsWithDelta($milk - 200, (float) InventoryItem::where('title', 'Süd')->value('quantity'), 0.001);
        $this->assertSame('18.00', MenuItem::where('title', 'Dolma')->value('stock_quantity')); // porsiya qayıtmır
        $this->assertSame('1.64', ItemReturn::where('title', 'Dolma')->value('cost'));
    }

    public function test_voiding_every_item_cancels_the_order_and_quantity_is_limited(): void
    {
        $order = $this->order(['Plov' => 2]);
        $item = $order->items->first();

        $this->returnItem($item, ['quantity' => 3])->assertJsonPath('errors.quantity.0', 'Ən çox 2 ədəd qaytarıla bilər.');
        $this->returnItem($item, ['quantity' => 2])->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->returnItem($item)->assertJsonPath('errors.quantity.0', 'Bu məhsul artıq ləğv edilib.');
    }

    public function test_refund_after_payment_by_cashier_with_manager_approval(): void
    {
        $this->as('1111');
        $order = $this->order(['Plov' => 2, 'Çay (dəmlik)' => 1], 'pay');
        $invoice = Invoice::find($order->invoice_id);
        $plov = $order->items->firstWhere('title', 'Plov');
        $expected = round(14 / (float) $invoice->sub_total * (float) $invoice->total, 2); // çekdəki payı (vergi, servis daxil)

        $this->returnItem($plov, ['refund_amount' => 999, 'approver_pin' => '1234'])
            ->assertJsonPath('errors.refund_amount.0', 'Bu çekdən ən çox '.money($invoice->total).' qaytarıla bilər.');
        $response = $this->returnItem($plov, ['approver_pin' => '1234'])->assertOk()->assertJsonPath('kind', 'refund');

        $this->assertSame(number_format($expected, 2, '.', ''), $invoice->fresh()->refunded_total);
        $this->get($response->json('receipt_url'))->assertOk()->assertSee('QAYTARMA QƏBZİ')->assertSee(money($expected))->assertSee('Müştərinin imzası');
        $this->get(route('invoices.receipt', $invoice))->assertSee('Qaytarılıb');
        $this->panel()->get(route('reports.show', 'sales-summary'))->assertViewHas('rows', fn ($rows) => collect($rows)->firstWhere(0, 'Müştəriyə qaytarılıb')[1] === money($expected));
    }

    public function test_waiter_cannot_refund_money_and_refunded_invoice_cannot_be_voided(): void
    {
        $order = $this->order(['Plov' => 1], 'pay');
        $this->as('2222');
        $this->returnItem($order->items->first(), ['approver_pin' => '1234'])->assertForbidden();

        $this->panel();
        $other = $this->order(['Plov' => 2], 'pay');
        $this->returnItem($other->items->first())->assertOk();
        $this->post(route('invoices.void', $other->invoice_id), ['reason' => 'Səhv'])->assertSessionHasErrors(['reason' => 'Bu çekdə qaytarma var — qalan məhsulları ayrıca qaytarın.']);
    }

    public function test_cancelling_an_order_needs_reason_and_approval(): void
    {
        $this->as('2222');
        $order = $this->order(['Plov' => 1, 'Ayran' => 2]);

        $this->postJson(route('orders.cancel', $order), [])->assertJsonPath('errors.reason.0', 'Ləğvin səbəbini seçin.');
        $this->postJson(route('orders.cancel', $order), ['reason' => 'customer_left'])->assertJsonPath('errors.approver_pin.0', 'Menecer təsdiqi lazımdır.');
        $this->postJson(route('orders.cancel', $order), ['reason' => 'customer_left', 'approver_pin' => '1234'])->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(['Plov', 'Ayran'], ItemReturn::where('order_id', $order->id)->pluck('title')->all());
        $this->assertSame([$this->admin->id], ItemReturn::where('order_id', $order->id)->distinct()->pluck('approved_by')->all());
    }

    public function test_discount_and_service_waiver_need_approval(): void
    {
        $this->as('2222');
        $this->postJson(route('pos.store'), ['action' => 'kitchen', 'delivery_type' => 'dinein', 'discount_type' => 'percentage', 'discount_value' => 50,
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 1]]])
            ->assertJsonPath('errors.approver_pin.0', 'Menecer təsdiqi lazımdır.');
        $this->postJson(route('pos.store'), ['action' => 'kitchen', 'delivery_type' => 'dinein', 'no_service_charge' => true,
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 1]]])
            ->assertJsonPath('errors.approver_pin.0', 'Menecer təsdiqi lazımdır.');
        $this->assertSame(2, Order::count()); // demo-nun iki sifarişi

        $order = $this->order(['Plov' => 1], 'kitchen', ['discount_type' => 'percentage', 'discount_value' => 50, 'approver_pin' => '1234']);
        $this->assertSame($this->admin->id, $order->discount_approved_by);
        // Endirimsiz sifariş və qaralama təsdiqsiz keçir
        $this->order(['Plov' => 1]);
        $this->panel()->get(route('reports.show', 'discounts'))->assertViewHas('rows', fn ($rows) => count($rows) === 1 && $rows[0][2] === '50%' && $rows[0][5] === 'Demo Admin');
    }

    public function test_invoice_void_needs_manager_pin_for_cashier(): void
    {
        $invoice = Invoice::where('status', 'paid')->first();
        $this->actingAs(User::where('email', 'kassir@pos.test')->first());

        $this->post(route('invoices.void', $invoice), ['reason' => 'Səhv kart'])->assertSessionHasErrors(['approver_pin' => 'Menecer təsdiqi lazımdır.']);
        $this->post(route('invoices.void', $invoice), ['reason' => 'Səhv kart', 'approver_pin' => '1234'])->assertSessionHasNoErrors();

        $this->assertSame(['void', $this->admin->id], [$invoice->fresh()->status, $invoice->fresh()->void_approved_by]);
    }

    public function test_reprints_are_marked_as_copies_and_voids_after_bill_are_flagged(): void
    {
        $invoice = Invoice::where('status', 'paid')->first();
        $this->get(route('invoices.receipt', $invoice))->assertDontSee('NÜSXƏ');
        $this->get(route('invoices.receipt', $invoice))->assertSee('NÜSXƏ · 2-ci çap');

        $order = Order::where('payment_status', 'pending')->where('status', '!=', 'draft')->first();
        $this->get(route('orders.bill', ['ids' => $order->id]))->assertDontSee('NÜSXƏ');
        $this->get(route('orders.bill', ['ids' => $order->id]))->assertSee('NÜSXƏ · 2-ci çap');

        $this->returnItem($order->items->first())->assertOk();
        $this->assertTrue(ItemReturn::first()->after_bill);
        $this->get(route('returns.index'))->assertOk()->assertSee('çekdən sonra')->assertViewHas('stats', fn ($s) => $s->after_bill === 1);
        $this->get(route('dashboard'))->assertOk()->assertSee('hesab çeki verildikdən sonra silinib');
    }

    public function test_returns_by_staff_report_shows_share_of_their_orders(): void
    {
        $this->as('2222');
        $order = $this->order(['Plov' => 4]); // 56 ₼
        $this->returnItem($order->items->first(), ['approver_pin' => '1234'])->assertOk(); // 14 ₼

        $this->panel()->get(route('reports.show', 'returns-by-staff'))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows[0] === ['Ofisiant Nicat', 1, 0, 0, money(14), money(56), '25%'.' ⚠']);
        $this->get(route('reports.show', 'returns'))->assertOk()->assertSee('Müştəri bəyənmədi');
    }

    public function test_other_business_items_and_returns_are_not_reachable(): void
    {
        $other = app(TenantProvisioner::class)->create('Başqa', 'O', 'o@pos.test', 'password');
        app(Tenancy::class)->set($other->tenant);
        $foreignOrder = Order::create(['token_no' => 1, 'delivery_type' => 'takeaway', 'status' => 'created']);
        $foreignItem = $foreignOrder->items()->create(['title' => 'X', 'price' => 5, 'quantity' => 1]);
        app(Tenancy::class)->set($this->admin->tenant);

        $this->returnItem($foreignItem)->assertNotFound();
        $this->assertSame('created', $foreignItem->fresh()->status);
    }

    public function test_supplier_return_leaves_inventory_at_current_cost(): void
    {
        $beef = InventoryItem::where('title', 'Mal əti')->first();
        $this->post(route('inventory.movement', $beef), ['type' => 'supplier', 'quantity' => 2, 'note' => 'Keyfiyyətsiz partiya'])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta((float) $beef->quantity - 2, (float) $beef->fresh()->quantity, 0.0001);
        $this->assertDatabaseHas('stock_movements', ['inventory_item_id' => $beef->id, 'type' => 'supplier', 'quantity' => -2, 'unit_cost' => 16]);
    }
}
