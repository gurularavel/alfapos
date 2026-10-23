<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected const API = 'https://txpgtst.kapitalbank.az/api/';

    protected User $admin;

    protected Tenant $tenant;

    protected StoreTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->admin = User::where('email', 'demo@pos.test')->first();
        $this->tenant = $this->admin->tenant;
        app(Tenancy::class)->set($this->tenant);
        $this->tenant->update(['kapitalbank_settings' => ['enabled' => true, 'mode' => 'test']]);
        $this->table = StoreTable::where('title', 'Masa 4')->firstOrFail();
        $this->actingAs($this->admin);
    }

    protected function order(int $quantity = 1): int
    {
        return $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'dinein', 'store_table_id' => $this->table->id,
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => $quantity]],
        ])->assertOk()->json('order.id');
    }

    protected function send(): TestResponse
    {
        return $this->postJson(route('orders.online.store'), ['store_table_id' => $this->table->id]);
    }

    /** Bank: sifariş yaradılır, sonra status sorğusuna verilən cavablar. */
    protected function bank(string ...$statuses): void
    {
        $orderId = 1000;
        Http::fake([
            self::API.'order' => function () use (&$orderId) {
                $orderId++;

                return Http::response(['order' => ['id' => $orderId, 'hppUrl' => 'https://txpgtst.kapitalbank.az/flex', 'password' => 'pw'.$orderId, 'status' => 'Preparing', 'secret' => 'x']]);
            },
            self::API.'order/*' => Http::sequence(array_map(fn ($s) => ['order' => ['status' => $s]], $statuses)),
        ]);
    }

    protected function payUrl(): string
    {
        return route('pay.show', [$this->tenant->qr_code, $this->table->qr_token]);
    }

    protected function billTotal(): float
    {
        $orders = Order::with('items')->where('store_table_id', $this->table->id)->where('payment_status', 'pending')->get();

        return app(OrderService::class)->billSums($orders, $this->tenant)['total'];
    }

    public function test_full_flow_from_cashier_to_bank_and_back_closes_the_bill_once(): void
    {
        $orderId = $this->order(2);
        $this->get($this->payUrl())->assertOk()->assertSee('Hesab hələ göndərilməyib');

        $this->send()->assertOk()->assertJsonPath('payment.amount', $this->billTotal());
        $payment = OnlinePayment::sole();

        $this->bank('FullyPaid', 'FullyPaid');
        $this->get($this->payUrl())->assertOk()->assertSee(money($this->billTotal()))->assertSee('Plov')->assertSee('Test rejimi');

        $this->post(route('pay.checkout', [$this->tenant->qr_code, $this->table->qr_token]))
            ->assertRedirect('https://txpgtst.kapitalbank.az/flex?id=1001&password=pw1001');
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'order'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('TerminalSys/kapital:kapital123'))
            && $r['order']['typeRid'] === 'Order_SMS'
            && $r['order']['amount'] === number_format($this->billTotal(), 2, '.', '')
            && $r['order']['hppRedirectUrl'] === route('pay.return', $payment->token));

        // Bankdan qayıdış — linkdəki STATUS-a yox, API-yə baxılır
        $this->get(route('pay.return', $payment->token).'?ID=1001&STATUS=FullyPaid')->assertOk()->assertSee('Ödəniş uğurla tamamlandı');
        $payment->refresh();
        $order = Order::find($orderId);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $order->payment_status);
        $invoice = Invoice::find($payment->invoice_id);
        $this->assertSame('online', $invoice->paymentType->icon);
        $this->assertSame((float) $payment->amount, (float) $invoice->total);

        // Təkrar qayıdış (səhifə yenilənib) ikinci çek yaratmır
        $this->get(route('pay.return', $payment->token))->assertOk()->assertSee('Ödəniş uğurla tamamlandı');
        $this->assertSame(1, Invoice::where('id', $invoice->id)->count());
        $this->assertSame(1, OnlinePayment::where('invoice_id', $invoice->id)->count());
        $this->get($this->payUrl())->assertSee('Hesab ödənilib');
    }

    public function test_status_in_the_return_link_is_not_trusted_and_customer_can_retry(): void
    {
        $this->order();
        $this->send()->assertOk();
        $payment = OnlinePayment::sole();
        $this->bank('Declined', 'Declined', 'FullyPaid');

        $this->post(route('pay.checkout', [$this->tenant->qr_code, $this->table->qr_token]))->assertRedirect();
        $this->get(route('pay.return', $payment->token).'?ID=1001&STATUS=FullyPaid')->assertOk()->assertSee('Ödəniş tamamlanmadı');
        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertDatabaseCount('invoices', Invoice::count());

        // Yenidən cəhd: əvvəlki sifariş rədd edilib → bankda yeni sifariş
        $this->post(route('pay.checkout', [$this->tenant->qr_code, $this->table->qr_token]))
            ->assertRedirect('https://txpgtst.kapitalbank.az/flex?id=1002&password=pw1002');
        $this->get(route('pay.return', $payment->token))->assertSee('Ödəniş uğurla tamamlandı');
        $this->assertSame('1002', $payment->refresh()->bank_order_id);
    }

    public function test_reopening_the_page_reuses_the_unpaid_bank_order_and_new_items_update_the_amount(): void
    {
        $this->order();
        $this->send()->assertOk();
        $this->bank('Preparing', 'Preparing');
        $checkout = fn () => $this->post(route('pay.checkout', [$this->tenant->qr_code, $this->table->qr_token]));

        $checkout()->assertRedirect('https://txpgtst.kapitalbank.az/flex?id=1001&password=pw1001');
        $checkout()->assertRedirect('https://txpgtst.kapitalbank.az/flex?id=1001&password=pw1001');

        $this->order(); // əlavə sifariş: məbləğ artır, yeni bank sifarişi
        $checkout()->assertRedirect('https://txpgtst.kapitalbank.az/flex?id=1002&password=pw1002');
        $this->assertSame($this->billTotal(), (float) OnlinePayment::sole()->amount);
        Http::assertSentCount(4); // 2 sifariş + əvvəlki sifarişin statusu (2 dəfə)
    }

    public function test_bill_closed_at_the_till_cancels_the_request_and_double_payment_is_flagged(): void
    {
        $orderId = $this->order();
        $this->send()->assertOk();
        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payment_type_id' => PaymentType::where('icon', 'cash')->value('id')])->assertOk();
        $this->post(route('pay.checkout', [$this->tenant->qr_code, $this->table->qr_token]))->assertStatus(409);
        $this->assertSame('cancelled', OnlinePayment::sole()->status);

        // QR səhifəsi də köhnə məbləği göstərmir
        $this->order();
        $this->send()->assertOk();
        Order::where('store_table_id', $this->table->id)->where('payment_status', 'pending')->update(['status' => 'cancelled']);
        $this->get($this->payUrl())->assertOk()->assertSee('Hesab hələ göndərilməyib');
        $this->assertSame(0, OnlinePayment::pending()->count());

        // Müştəri bank səhifəsini açıb ödəyib, kassir isə eyni anda nağd bağlayıb
        $orderId = $this->order();
        $this->send()->assertOk();
        $payment = OnlinePayment::pending()->sole();
        $this->bank('FullyPaid');
        $this->post(route('pay.checkout', [$this->tenant->qr_code, $this->table->qr_token]))->assertRedirect();
        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payment_type_id' => PaymentType::where('icon', 'cash')->value('id')])->assertOk();
        $this->get(route('pay.return', $payment->token))->assertOk();
        $this->assertStringContainsString('ikiqat ödəniş', $payment->refresh()->note);
        $this->assertSame(1, Invoice::whereHas('orders', fn ($q) => $q->whereKey($orderId))->count());
    }

    public function test_only_staff_with_payments_scope_can_send_and_board_shows_status(): void
    {
        $this->order();
        $this->actingAs(User::where('email', 'metbex@pos.test')->first());
        $this->send()->assertForbidden();

        $this->actingAs(User::where('email', 'kassir@pos.test')->first());
        $this->send()->assertOk()->assertJsonPath('payment.status', 'pending');
        $group = collect($this->getJson(route('orders.board'))->json())->firstWhere('key', 't'.$this->table->id);
        $this->assertSame('pending', $group['online']['status']);
        $this->assertFalse($group['online']['opened']);

        $this->postJson(route('orders.online.cancel', $group['online']['id']))->assertOk();
        $this->get($this->payUrl())->assertSee('Hesab hələ göndərilməyib');
        $this->assertNull(collect($this->getJson(route('orders.board'))->json())->firstWhere('key', 't'.$this->table->id)['online']);
    }

    public function test_disabled_or_unconfigured_live_mode_blocks_sending_and_the_qr_page(): void
    {
        $this->order();
        $this->tenant->update(['kapitalbank_settings' => ['enabled' => false, 'mode' => 'test']]);
        $this->send()->assertUnprocessable();
        $this->get($this->payUrl())->assertNotFound();

        $this->put(route('settings.online-payment.update'), ['enabled' => '1', 'mode' => 'live'])->assertSessionHasErrors(['login', 'password']);
        $this->put(route('settings.online-payment.update'), ['enabled' => '1', 'mode' => 'live', 'login' => 'TerminalSys/real', 'password' => 'secret-pass'])->assertSessionHasNoErrors();
        $this->assertSame('secret-pass', $this->tenant->refresh()->kapitalbank()['password']);
        $this->assertStringNotContainsString('secret-pass', (string) $this->tenant->getRawOriginal('kapitalbank_settings'));

        // Parol formada göstərilmir, boş saxlananda köhnəsi qalır
        $this->get(route('settings.online-payment'))->assertOk()->assertDontSee('secret-pass')->assertSee('TerminalSys/real');
        $this->put(route('settings.online-payment.update'), ['enabled' => '1', 'mode' => 'live', 'login' => 'TerminalSys/real', 'password' => ''])->assertSessionHasNoErrors();
        $this->assertSame('secret-pass', $this->tenant->refresh()->kapitalbank()['password']);
        $this->assertArrayNotHasKey('kapitalbank_settings', $this->tenant->toArray());
    }

    public function test_bank_errors_are_shown_safely(): void
    {
        $this->order();
        $this->send()->assertOk();
        Http::fake([self::API.'*' => Http::response(['errorCode' => 'InvalidLogin', 'errorDescription' => 'Invalid login'], 401)]);

        $this->post(route('pay.checkout', [$this->tenant->qr_code, $this->table->qr_token]))->assertStatus(502);
        $this->post(route('settings.online-payment.test'))->assertSessionHasErrors(['connection' => 'Kapital Bank: login və ya parol yanlışdır.']);
    }

    public function test_table_payment_qr_print_links_to_the_payment_page(): void
    {
        $this->get(route('settings.tables.qr', [$this->table, 'kind' => 'pay']))->assertOk()
            ->assertSee($this->payUrl())->assertSee('Hesabı kartla ödəmək üçün skan edin');
        $this->get(route('settings.tables.qr', $this->table))->assertOk()->assertSee('Menyu və sifariş üçün skan edin');
    }
}
