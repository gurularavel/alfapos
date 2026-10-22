<?php

namespace Tests\Feature;

use App\Events\OrderChanged;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeTest extends TestCase
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

    public function test_order_lifecycle_broadcasts_to_tenant_channel(): void
    {
        Event::fake([OrderChanged::class]);
        $plov = MenuItem::where('title', 'Plov')->value('id');

        $id = $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => $plov, 'quantity' => 1]],
        ])->assertOk()->json('order.id');
        $this->postJson(route('kitchen.ready', $id))->assertOk();
        $this->postJson(route('orders.pay'), ['order_ids' => [$id], 'payment_type_id' => PaymentType::value('id')])->assertOk();

        $types = [];
        Event::assertDispatched(OrderChanged::class, function (OrderChanged $e) use ($id, &$types) {
            $types[] = $e->type;

            return $e->orderId === $id && $e->broadcastOn()[0]->name === 'private-tenant.'.$this->admin->tenant_id;
        });
        $this->assertSame(['created', 'kitchen', 'paid'], $types);
    }

    public function test_drafts_are_not_broadcast(): void
    {
        Event::fake([OrderChanged::class]);
        $this->postJson(route('pos.store'), [
            'action' => 'draft', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => MenuItem::value('id'), 'quantity' => 1]],
        ])->assertOk();
        Event::assertNotDispatched(OrderChanged::class);
    }

    public function test_channel_only_authorizes_own_tenant(): void
    {
        $other = app(TenantProvisioner::class)->create('Başqa', 'Owner', 'o@pos.test', 'password');
        $channels = Broadcast::getChannels();
        $callback = $channels['tenant.{tenantId}'];

        $this->assertTrue($callback($this->admin, $this->admin->tenant_id));
        $this->assertFalse($callback($this->admin, $other->tenant_id));
    }

    public function test_broadcast_failure_does_not_break_orders(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.options.host' => '127.0.0.1', 'broadcasting.connections.reverb.options.port' => 1]);

        $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'takeaway', 'cart' => [['menu_item_id' => MenuItem::value('id'), 'quantity' => 1]],
        ])->assertOk();
        $this->assertSame(1, Order::where('status', 'created')->whereDate('created_at', today())->where('source', 'pos')->where('delivery_type', 'takeaway')->count());
    }
}
