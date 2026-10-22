<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalTest extends TestCase
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

    protected function order(string $action = 'kitchen'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('pos.store'), [
            'action' => $action, 'delivery_type' => 'dinein', 'store_table_id' => StoreTable::where('title', 'Masa 4')->value('id'),
            'cart' => [['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 1]],
            'payment_type_id' => PaymentType::value('id'),
        ]);
    }

    public function test_locked_terminal_blocks_actions_and_other_pages(): void
    {
        $this->postJson(route('pos.terminal.enter'))->assertOk()->assertJsonPath('state.locked', true);

        $this->order()->assertStatus(423);
        $this->get(route('pos'))->assertOk();
        $this->get(route('dashboard'))->assertRedirect(route('pos'));
        $this->get(route('settings.index'))->assertRedirect(route('pos'));
    }

    public function test_waiter_pin_assigns_orders_but_cannot_take_payment(): void
    {
        $this->postJson(route('pos.terminal.enter'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '9999'])->assertUnprocessable();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '2222'])->assertOk()
            ->assertJsonPath('state.operator.name', 'Ofisiant Nicat')
            ->assertJsonPath('state.canPay', false);

        $orderId = $this->order('kitchen')->assertOk()->json('order.id');
        $this->assertSame('Ofisiant Nicat', Order::find($orderId)->creator->name);
        $this->order('pay')->assertForbidden();
        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payment_type_id' => PaymentType::value('id')])->assertForbidden();

        // Kassir öz PIN-i ilə açıb hesabı bağlayır — çekdə kassir, sifarişdə ofisiant qalır
        $this->postJson(route('pos.terminal.lock'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '1111'])->assertOk()->assertJsonPath('state.canPay', true);
        $invoiceId = $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payment_type_id' => PaymentType::value('id')])
            ->assertOk()->json('invoice_id');
        $this->assertSame('Kassir Aynur', Invoice::find($invoiceId)->creator->name);
        $this->assertSame('Ofisiant Nicat', Order::find($orderId)->creator->name);
    }

    public function test_chef_pin_cannot_unlock_pos(): void
    {
        $this->postJson(route('pos.terminal.enter'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '3333'])->assertUnprocessable();
    }

    public function test_idle_operator_is_locked_server_side(): void
    {
        $this->postJson(route('pos.terminal.enter'))->assertOk();
        $this->postJson(route('pos.terminal.unlock'), ['pin' => '1111'])->assertOk();
        $this->order()->assertOk();

        $this->travel(20 + 31)->seconds();
        $this->order()->assertStatus(423);
    }

    public function test_exit_requires_admin_pin(): void
    {
        $this->postJson(route('pos.terminal.enter'))->assertOk();
        $this->postJson(route('pos.terminal.exit'), ['pin' => '2222'])->assertUnprocessable();
        $this->postJson(route('pos.terminal.exit'), ['pin' => '1234'])->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_non_terminal_mode_keeps_normal_behaviour(): void
    {
        $id = $this->order('pay')->assertOk()->json('order.id');
        $this->assertSame($this->admin->id, Order::find($id)->created_by);
    }

    public function test_pin_must_be_unique_per_tenant(): void
    {
        $this->post(route('users.store'), [
            'name' => 'Yeni ofisiant', 'role' => 'waiter', 'scopes' => ['POS'], 'is_active' => 1, 'pin' => '2222',
        ])->assertSessionHasErrors('pin');

        $this->post(route('users.store'), [
            'name' => 'Yeni ofisiant', 'role' => 'waiter', 'scopes' => ['POS'], 'is_active' => 1, 'pin' => '4567',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Yeni ofisiant', User::findByPin($this->admin->tenant_id, '4567')?->name);
    }

    public function test_terminal_settings_page_saves_timeout(): void
    {
        $this->get(route('settings.terminal'))->assertOk();
        $this->assertTrue($this->admin->tenant->terminalSetting('sound_add_item'));

        // Səs açarları göndərilməyəndə (checkbox söndürülüb) false olur
        $this->put(route('settings.terminal.update'), ['lock_timeout' => 45, 'lock_after_order' => 1, 'sound_new_order' => 1])->assertSessionHasNoErrors();
        $tenant = $this->admin->tenant->fresh();
        $this->assertSame(45, $tenant->terminalSetting('lock_timeout'));
        $this->assertFalse($tenant->terminalSetting('sound_add_item'));
        $this->assertTrue($tenant->terminalSetting('sound_new_order'));

        $this->get(route('pos'))->assertOk()->assertViewHas('posData', fn ($d) => $d['sounds'] === ['addItem' => false, 'newOrder' => true]);
    }
}
