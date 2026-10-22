<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\Terminal;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\PosTerminal;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTerminalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Terminal $kassa;

    protected Terminal $bar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($this->admin->tenant);
        $this->actingAs($this->admin);
        $this->kassa = Terminal::where('name', 'Kassa 1')->firstOrFail();
        $this->bar = Terminal::where('name', 'Bar')->firstOrFail();
    }

    protected function onDevice(Terminal $terminal): static
    {
        // JSON sorğularında test klienti cookie-ləri yalnız withCredentials() ilə göndərir (brauzer həmişə göndərir)
        return $this->withCredentials()->withCookie(PosTerminal::DEVICE_COOKIE, (string) $terminal->id);
    }

    /** Cihaz terminala bağlı deyil (panel) — əvvəlki cookie-ləri təmizləyir. */
    protected function offDevice(): static
    {
        $this->defaultCookies = [];
        $this->withCredentials = false;

        return $this;
    }

    protected function order(string $action = 'pay', ?int $tableId = null): array
    {
        return $this->postJson(route('pos.store'), [
            'action' => $action, 'delivery_type' => $tableId ? 'dinein' : 'takeaway', 'store_table_id' => $tableId,
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 1]],
            'payment_type_id' => PaymentType::value('id'),
        ])->assertOk()->json();
    }

    public function test_settings_page_and_binding_this_device(): void
    {
        $this->get(route('settings.branches.index'))->assertOk()->assertSee('Kassa 1')->assertSee('Bar')->assertSee('Əsas filial');
        $this->post(route('settings.device.bind'), ['terminal_id' => $this->bar->id])->assertSessionHasNoErrors()->assertCookie(PosTerminal::DEVICE_COOKIE);
        $this->onDevice($this->bar)->get(route('pos'))->assertOk()
            ->assertViewHas('posData', fn ($d) => $d['terminal']['device']['name'] === 'Bar');
    }

    public function test_orders_and_invoices_record_terminal_and_branch(): void
    {
        $r = $this->onDevice($this->bar)->order();
        $order = Order::find($r['order']['id']);
        $invoice = Invoice::find($r['invoice_id']);
        $this->assertSame([$this->bar->id, $this->bar->branch_id], [$order->terminal_id, $order->branch_id]);
        $this->assertSame([$this->bar->id, $this->bar->branch_id], [$invoice->terminal_id, $invoice->branch_id]);
        $this->get(route('invoices.receipt', $invoice))->assertSee('Terminal:')->assertSee('Bar');

        // Cihaz bağlı deyilsə — terminalsız, filial masadan
        $free = Order::find($this->offDevice()->order('kitchen', StoreTable::value('id'))['order']['id']);
        $this->assertNull($free->terminal_id);
        $this->assertSame($this->kassa->branch_id, $free->branch_id);
    }

    public function test_waiter_bound_to_terminal(): void
    {
        $this->postJson(route('pos.terminal.enter'))->assertOk();
        // Ofisiant Nicat yalnız Kassa 1-ə bağlıdır
        $this->onDevice($this->bar)->postJson(route('pos.terminal.unlock'), ['pin' => '2222'])->assertUnprocessable()->assertJsonPath('errors.pin.0', 'Ofisiant Nicat bu terminalda işləyə bilməz.');
        $this->onDevice($this->kassa)->postJson(route('pos.terminal.unlock'), ['pin' => '2222'])->assertOk();
        // Terminala bağlanmamış kassir hər yerdə işləyir
        $this->onDevice($this->bar)->postJson(route('pos.terminal.unlock'), ['pin' => '1111'])->assertOk();
    }

    public function test_device_sees_only_its_branch(): void
    {
        $second = Branch::create(['name' => '28 May filialı']);
        $remote = Terminal::create(['branch_id' => $second->id, 'name' => 'Kassa A']);
        $farTable = StoreTable::create(['branch_id' => $second->id, 'title' => 'F1', 'seating_capacity' => 2]);

        $farOrder = $this->onDevice($remote)->order('kitchen', $farTable->id)['order']['id'];
        $nearOrder = $this->onDevice($this->kassa)->order('kitchen', StoreTable::where('title', 'Masa 1')->value('id'))['order']['id'];

        $kitchen = collect($this->onDevice($this->kassa)->getJson(route('kitchen.feed'))->json())->pluck('id');
        $this->assertTrue($kitchen->contains($nearOrder));
        $this->assertFalse($kitchen->contains($farOrder));
        $this->onDevice($this->kassa)->get(route('pos'))->assertViewHas('posData', fn ($d) => ! collect($d['tables'])->pluck('id')->contains($farTable->id));

        // Panel (cihaz bağlı deyil) hamısını görür
        $all = collect($this->offDevice()->getJson(route('kitchen.feed'))->json())->pluck('id');
        $this->assertTrue($all->contains($farOrder) && $all->contains($nearOrder));
    }

    public function test_reports_by_terminal_and_filters(): void
    {
        $this->onDevice($this->kassa)->order();
        $this->onDevice($this->bar)->order();
        $this->onDevice($this->bar)->order();

        $this->get(route('reports.show', 'sales-by-terminal'))->assertOk()->assertSee('Kassa 1 · Əsas filial')->assertSee('Bar · Əsas filial');
        $this->get(route('reports.show', ['sales-summary', 'terminal_id' => $this->bar->id]))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows[0] === ['Çek sayı', 2]);
        $this->get(route('reports.show', 'sales-by-branch'))->assertOk()->assertSee('Əsas filial');
        $this->get(route('dashboard'))->assertOk()->assertSee('Terminallar üzrə satış')
            ->assertViewHas('terminalSales', fn ($rows) => $rows->firstWhere('name', 'Bar')?->checks === 2 && $rows->firstWhere('name', 'Kassa 1')?->checks === 1);
        $this->get(route('reports.show', 'staff-by-terminal'))->assertOk()->assertSee('Demo Admin · Bar');
        $this->get(route('invoices.index', ['terminal_id' => $this->kassa->id]))->assertOk()
            ->assertViewHas('invoices', fn ($p) => $p->total() === 1 + Invoice::where('terminal_id', $this->kassa->id)->count() - 1);
    }

    public function test_dashboard_shows_sales_by_branch(): void
    {
        $second = Branch::create(['name' => '28 May filialı']);
        $remote = Terminal::create(['branch_id' => $second->id, 'name' => 'Kassa A']);
        $this->onDevice($this->kassa)->order();
        $this->onDevice($remote)->order();
        $this->onDevice($remote)->order();

        $this->offDevice()->get(route('dashboard'))->assertOk()->assertSee('Filiallar üzrə satış')->assertSee('28 May filialı')
            ->assertViewHas('branchSales', fn ($rows) => $rows->firstWhere('name', '28 May filialı')?->checks === 2 && $rows->firstWhere('name', 'Əsas filial')?->checks >= 1);
    }

    public function test_dashboard_shows_sales_by_staff(): void
    {
        $plov = MenuItem::where('title', 'Plov')->value('id');
        $send = fn () => $this->postJson(route('pos.store'), ['action' => 'kitchen', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => $plov, 'quantity' => 2]]])->assertOk()->json('order.id');

        // Ofisiant PIN ilə iki sifariş verir, kassir PIN ilə hesabı bağlayır
        $this->onDevice($this->kassa)->postJson(route('pos.terminal.enter'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '2222'])->assertOk();
        $ids = [$send(), $send()];
        $this->postJson(route('pos.terminal.lock'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '1111'])->assertOk();
        $this->postJson(route('orders.pay'), ['order_ids' => $ids, 'payment_type_id' => PaymentType::value('id')])->assertOk();
        $this->postJson(route('pos.terminal.exit'), ['pin' => '1234'])->assertOk();

        $this->offDevice()->get(route('dashboard'))->assertOk()->assertSee('İşçilər üzrə satış')
            ->assertViewHas('staffSales', function ($rows) {
                $waiter = $rows->firstWhere('name', 'Ofisiant Nicat');
                $cashier = $rows->firstWhere('name', 'Kassir Aynur');

                return $waiter->orders === 2 && $waiter->orders_total === 56.0 && $waiter->checks === 0
                    && $cashier->checks === 1 && $cashier->collected > 56.0 && $cashier->orders === 0;
            });
    }

    public function test_dashboard_shows_sales_by_hour_with_last_week(): void
    {
        // Demo seed-in bugünkü çekləri pik saatı dəyişməsin
        Invoice::query()->delete();
        $tz = $this->admin->tenant->timezone; // sorğular biznesin saat qurşağında işləyir
        $this->travelTo(today($tz)->setTime(13, 20));
        $this->onDevice($this->kassa)->order();
        $this->onDevice($this->kassa)->order();
        $this->travelTo(today($tz)->subWeek()->setTime(13, 5));
        $this->onDevice($this->kassa)->order();
        $this->travelBack();
        $this->travelTo(today($tz)->setTime(15, 0));

        $this->offDevice()->get(route('dashboard'))->assertOk()->assertSee('Saatlar üzrə satış')->assertSee('Pik saat:')->assertSee('13:00–14:00')
            ->assertViewHas('hourly', function ($hours) {
                $h13 = $hours->firstWhere('hour', 13);

                return $h13['checks'] === 2 && $h13['last_week'] > 0 && $h13['total'] === 2 * $h13['last_week']
                    && $hours->first()['hour'] <= 9 && $hours->last()['hour'] >= 21;
            });
    }

    public function test_dashboard_shows_sales_by_category(): void
    {
        Order::query()->delete(); // demo seed-in bugünkü sifarişləri nəticəyə qarışmasın
        $item = fn (string $title, int $qty) => ['menu_item_id' => MenuItem::where('title', $title)->value('id'), 'quantity' => $qty];
        $send = fn (array $cart) => $this->postJson(route('pos.store'), ['action' => 'kitchen', 'delivery_type' => 'takeaway', 'cart' => $cart])->assertOk()->json('order.id');

        $send([$item('Plov', 2), $item('Dolma', 1)]);
        $cancelled = $send([$item('Plov', 5)]);
        $this->postJson(route('orders.cancel', $cancelled), ['reason' => 'wrong'])->assertOk();

        $this->get(route('dashboard'))->assertOk()->assertSee('Kateqoriyalar üzrə satış')->assertSee('Əsas yeməklər')
            ->assertViewHas('categorySales', fn ($rows) => $rows->count() === 1
                && $rows[0]->title === 'Əsas yeməklər' && (float) $rows[0]->qty === 3.0 && (float) $rows[0]->amount === 38.0);
    }

    public function test_sales_by_order_type_count_each_check_once(): void
    {
        Order::query()->delete(); // demo seed-in bugünkü sifarişləri nəticəyə qarışmasın
        $plov = MenuItem::where('title', 'Plov')->value('id');
        $table = StoreTable::where('title', 'Masa 1')->value('id');
        $send = fn (string $type, ?int $tableId = null) => $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => $type, 'store_table_id' => $tableId, 'cart' => [['menu_item_id' => $plov, 'quantity' => 1]],
        ])->assertOk()->json('order.id');

        // Masaya əsas + əlavə sifariş, bir çeklə bağlanır
        $tableOrders = [$send('dinein', $table), $send('dinein', $table)];
        $this->postJson(route('orders.pay'), ['order_ids' => $tableOrders, 'payment_type_id' => PaymentType::value('id')])->assertOk();
        $tableCheck = (float) Invoice::latest('id')->value('total');
        $this->postJson(route('orders.pay'), ['order_ids' => [$send('takeaway')], 'payment_type_id' => PaymentType::value('id')])->assertOk();
        $send('delivery'); // açıq, ödənilməyib

        $this->get(route('dashboard'))->assertOk()->assertSee('Sifariş növləri üzrə satış')
            ->assertViewHas('orderTypes', function ($rows) use ($tableCheck) {
                $by = $rows->keyBy('type');

                return $by['dinein']->checks === 1 && $by['dinein']->orders === 2 && $by['dinein']->total === $tableCheck
                    && $by['takeaway']->checks === 1 && $by['delivery']->checks === 0 && $by['delivery']->open === 1;
            });
        $this->get(route('reports.show', 'sales-by-order-type'))->assertOk()
            ->assertViewHas('rows', fn ($rows) => collect($rows)->firstWhere(0, 'Zalda') == ['Zalda', 2, money($tableCheck)]);
        $this->get(route('reports.show', 'sales-by-table'))->assertOk()
            ->assertViewHas('rows', fn ($rows) => collect($rows)->firstWhere(0, 'Masa 1') == ['Masa 1', 2, money($tableCheck)]);
    }

    public function test_dashboard_shows_sales_by_payment_type_with_yesterday(): void
    {
        Invoice::query()->delete(); // demo seed-in çekləri nəticəyə qarışmasın
        $plov = MenuItem::where('title', 'Plov')->value('id');
        $pay = fn (string $title) => $this->postJson(route('pos.store'), [
            'action' => 'pay', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => $plov, 'quantity' => 1]],
            'payment_type_id' => PaymentType::where('title', $title)->value('id'),
        ])->assertOk();

        $tz = $this->admin->tenant->timezone; // sorğular biznesin saat qurşağında işləyir
        $day = today($tz);
        $this->travelTo($day->copy()->subDay()->setTime(12, 0));
        $pay('Nağd');
        $this->travelTo($day->copy()->setTime(12, 0));
        $pay('Nağd');
        $pay('Nağd');
        $pay('Kart');

        $this->get(route('dashboard'))->assertOk()->assertSee('Ödəniş növləri üzrə satış')
            ->assertViewHas('paymentMix', function ($rows) {
                $cash = $rows->firstWhere('title', 'Nağd');
                $card = $rows->firstWhere('title', 'Kart');

                return $rows->first() === $cash && (int) $cash->checks === 2 && $cash->change === 100.0
                    && (int) $card->checks === 1 && $card->change === null && $rows->count() === 2;
            });
    }

    public function test_dashboard_shows_sales_by_table(): void
    {
        Order::query()->delete(); // demo seed-in sifarişləri nəticəyə qarışmasın
        Invoice::query()->delete();
        $plov = MenuItem::where('title', 'Plov')->value('id');
        $table = fn (string $title) => StoreTable::where('title', $title)->value('id');
        $send = fn (int $tableId) => $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'dinein', 'store_table_id' => $tableId, 'cart' => [['menu_item_id' => $plov, 'quantity' => 1]],
        ])->assertOk()->json('order.id');
        $close = fn (array $ids) => $this->postJson(route('orders.pay'), ['order_ids' => $ids, 'payment_type_id' => PaymentType::value('id')])->assertOk();

        $close([$send($table('Masa 1')), $send($table('Masa 1'))]); // əsas + əlavə sifariş, bir çek
        $close([$send($table('Masa 1'))]);
        $close([$send($table('Masa 2'))]);
        $send($table('Masa 2')); // yeni qonaqlar — dolu
        $this->postJson(route('pos.store'), ['action' => 'pay', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => $plov, 'quantity' => 1]], 'payment_type_id' => PaymentType::value('id')])->assertOk();

        $this->get(route('dashboard'))->assertOk()->assertSee('Masalar üzrə satış')
            ->assertViewHas('tableSales', function ($rows) {
                [$first, $second] = [$rows[0], $rows[1]];

                return $rows->count() === 2 && $first->title === 'Masa 1' && (int) $first->checks === 2
                    && round((float) $first->total, 2) === round(3 * (float) $second->total, 2) && $first->open_total === null
                    && $second->title === 'Masa 2' && (float) $second->open_total === 14.0;
            })
            ->assertViewHas('tableStats', fn ($s) => $s->used === 2 && $s->occupied === 1 && $s->checks === 3);
    }

    public function test_foreign_terminal_cannot_be_bound_and_new_tenant_gets_defaults(): void
    {
        $other = app(TenantProvisioner::class)->create('Başqa', 'O', 'o@pos.test', 'password');
        app(Tenancy::class)->set($other->tenant);
        $foreign = Terminal::firstOrFail();
        $this->assertSame('Kassa 1', $foreign->name);
        $this->assertSame('Əsas filial', $foreign->branch->name);
        app(Tenancy::class)->set($this->admin->tenant);

        $this->post(route('settings.device.bind'), ['terminal_id' => $foreign->id])->assertSessionHasErrors('terminal_id');
        // Başqa biznesin terminal cookie-si nəzərə alınmır
        $order = Order::find($this->onDevice($foreign)->order()['order']['id']);
        $this->assertNull($order->terminal_id);
    }
}
