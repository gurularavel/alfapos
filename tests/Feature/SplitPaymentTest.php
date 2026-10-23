<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected int $cash;

    protected int $card;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($this->admin->tenant);
        $this->actingAs($this->admin);
        $this->cash = PaymentType::where('icon', 'cash')->value('id');
        $this->card = PaymentType::where('icon', 'card')->value('id');
    }

    /** Masada mətbəxə göndərilmiş sifariş: [order id, hesabın məbləği]. */
    protected function tableOrder(int $quantity = 2): array
    {
        $id = $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'dinein', 'store_table_id' => StoreTable::where('title', 'Masa 4')->value('id'),
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => $quantity]],
        ])->assertOk()->json('order.id');
        $group = collect($this->getJson(route('orders.board'))->json())->first(fn ($g) => in_array($id, $g['unpaid_ids'], true));

        return [$id, (float) $group['due']];
    }

    public function test_bill_is_split_between_cash_and_card_with_change(): void
    {
        [$orderId, $due] = $this->tableOrder();
        $cashPart = 10.0;

        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payments' => [
            ['payment_type_id' => $this->cash, 'amount' => $cashPart, 'tendered' => 20],
            ['payment_type_id' => $this->card, 'amount' => round($due - $cashPart, 2)],
        ]])->assertOk();

        $invoice = Invoice::with('payments')->find(Order::find($orderId)->invoice_id);
        $this->assertSame($due, (float) $invoice->total);
        $this->assertCount(2, $invoice->payments);
        $this->assertSame($due, round($invoice->payments->sum('amount'), 2));
        $this->assertSame($due - $cashPart > $cashPart ? $this->card : $this->cash, $invoice->payment_type_id); // əsas növ — böyük hissə
        $this->assertSame(10.0, $invoice->payments->firstWhere('payment_type_id', $this->cash)->change());

        $this->get(route('invoices.receipt', $invoice))->assertOk()
            ->assertSee('Nağd')->assertSee('Kart')->assertSee('Alınıb')->assertSee(money(10))->assertSee('Qalıq');

        // Hesabat hər hissəni öz növünə yazır (demo məlumatda bu gün başqa çeklər də ola bilər — növ üzrə cəmlə müqayisə)
        $todayByType = fn (int $type) => (float) InvoicePayment::where('payment_type_id', $type)
            ->whereHas('invoice', fn ($q) => $q->where('status', 'paid')->where('created_at', '>=', today()))->sum('amount');
        $this->get(route('reports.show', ['payment-summary', 'from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk()
            ->assertSee(money($todayByType($this->cash)))->assertSee(money($todayByType($this->card)));
        $this->assertGreaterThanOrEqual($due - $cashPart, $todayByType($this->card));
    }

    public function test_split_must_cover_the_bill_exactly_and_use_own_payment_types(): void
    {
        [$orderId, $due] = $this->tableOrder();

        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payments' => [
            ['payment_type_id' => $this->cash, 'amount' => 1],
            ['payment_type_id' => $this->card, 'amount' => 1],
        ]])->assertUnprocessable()->assertJsonValidationErrors('payments');

        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payments' => [
            ['payment_type_id' => $this->cash, 'amount' => $due / 2, 'tendered' => 1], // verilən pul hissədən azdır
            ['payment_type_id' => $this->card, 'amount' => $due / 2],
        ]])->assertUnprocessable();

        $foreign = Tenant::where('id', '!=', $this->admin->tenant_id)->value('id');
        $foreignType = $foreign ? PaymentType::withoutGlobalScopes()->where('tenant_id', $foreign)->value('id') : 999999;
        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payments' => [['payment_type_id' => $foreignType ?? 999999, 'amount' => $due]]])
            ->assertUnprocessable()->assertJsonValidationErrors('payments.0.payment_type_id');

        $this->assertSame('pending', Order::find($orderId)->payment_status);
    }

    public function test_single_payment_line_uses_the_server_total_and_keeps_cash_tendered(): void
    {
        [$orderId, $due] = $this->tableOrder();

        // Müştəri tərəfindəki məbləğ bir qəpik fərqlənsə də tək ödəniş keçir (server öz cəmini yazır)
        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payments' => [['payment_type_id' => $this->cash, 'amount' => $due - 0.01, 'tendered' => 100]]])->assertOk();
        $payment = Invoice::find(Order::find($orderId)->invoice_id)->payments->sole();
        $this->assertSame($due, (float) $payment->amount);
        $this->assertSame(round(100 - $due, 2), $payment->change());
    }

    public function test_pos_checkout_accepts_split_payment(): void
    {
        $plov = MenuItem::where('title', 'Plov')->first();
        $ordersBefore = Order::count();
        $response = $this->postJson(route('pos.store'), [
            'action' => 'pay', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => $plov->id, 'quantity' => 1]],
            'payments' => [['payment_type_id' => $this->cash, 'amount' => 1], ['payment_type_id' => $this->card, 'amount' => 999]],
        ]);
        $response->assertUnprocessable(); // cəm uyğun gəlmir — sifariş də yaradılmamalıdır (mətbəxə getməsin)
        $this->assertSame($ordersBefore, Order::count());

        $this->postJson(route('pos.store'), ['action' => 'pay', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => $plov->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_type_id');

        $total = (float) $plov->price; // takeaway: servis haqqı yalnız zalda deyilsə tətbiq olunur — serverin cəmi ilə yoxlayırıq
        $ok = $this->postJson(route('pos.store'), [
            'action' => 'pay', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => $plov->id, 'quantity' => 1]],
            'payments' => [['payment_type_id' => $this->cash, 'amount' => 5]],
        ])->assertOk();
        $invoice = Invoice::with('payments')->find($ok->json('invoice_id'));
        $this->assertCount(1, $invoice->payments);
        $this->assertSame((float) $invoice->total, (float) $invoice->payments->first()->amount);
        $this->assertGreaterThanOrEqual($total, (float) $invoice->total);
    }

    public function test_only_admin_edits_menu_items_inside_pos(): void
    {
        $plov = MenuItem::where('title', 'Plov')->first();
        $payload = ['title' => 'Plov (böyük)', 'price' => 14.5, 'category_id' => $plov->category_id, 'is_enabled' => true];

        $this->putJson(route('pos.items.update', $plov), $payload)->assertOk()->assertJsonPath('item.price', 14.5)->assertJsonPath('item.title', 'Plov (böyük)');
        $this->assertSame('14.50', $plov->fresh()->price);

        $this->actingAs(User::where('email', 'kassir@pos.test')->first());
        $this->putJson(route('pos.items.update', $plov), $payload)->assertForbidden();

        // Terminal: ofisiant PIN — qadağan, admin PIN — icazəli; gizlədilən məhsul satılmır
        $this->actingAs($this->admin);
        $this->postJson(route('pos.terminal.enter'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '2222'])->assertOk()->assertJsonPath('state.isAdmin', false);
        $this->putJson(route('pos.items.update', $plov), $payload)->assertForbidden();
        $this->postJson(route('pos.terminal.lock'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '1234'])->assertOk()->assertJsonPath('state.isAdmin', true);
        $this->putJson(route('pos.items.update', $plov), [...$payload, 'is_enabled' => false])->assertOk()->assertJsonPath('item.enabled', false);

        $this->postJson(route('pos.store'), ['action' => 'kitchen', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => $plov->id, 'quantity' => 1]]])
            ->assertStatus(422);
        $this->assertTrue(collect($this->get(route('pos'))->viewData('posData')['items'])->contains(fn ($i) => $i['id'] === $plov->id && ! $i['enabled']));
    }
}
