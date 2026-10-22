<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected StoreTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($this->admin->tenant);
        $this->actingAs($this->admin);
        $this->table = StoreTable::where('title', 'Masa 5')->firstOrFail();
    }

    protected function order(string $title, int $qty = 1): int
    {
        return $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'dinein', 'store_table_id' => $this->table->id,
            'cart' => [['menu_item_id' => MenuItem::where('title', $title)->value('id'), 'quantity' => $qty]],
        ])->assertOk()->json('order.id');
    }

    public function test_plan_editor_saves_positions_only_for_own_tables(): void
    {
        $this->get(route('settings.tables.plan'))->assertOk()->assertSee('Zal planı');

        $other = app(TenantProvisioner::class)->create('Başqa', 'O', 'o@pos.test', 'password');
        app(Tenancy::class)->set($other->tenant);
        $foreign = StoreTable::create(['title' => 'X', 'seating_capacity' => 2]);
        app(Tenancy::class)->set($this->admin->tenant);

        $this->putJson(route('settings.tables.plan.save'), ['positions' => [
            ['id' => $this->table->id, 'x' => 25.5, 'y' => 70],
            ['id' => $foreign->id, 'x' => 50, 'y' => 50],
        ]])->assertOk();

        $this->assertEquals([25.5, 70.0], [$this->table->fresh()->pos_x, $this->table->fresh()->pos_y]);
        $this->assertNull(StoreTable::withoutGlobalScopes()->find($foreign->id)->pos_x);
        $this->get(route('pos'))->assertViewHas('posData', fn ($d) => collect($d['tables'])->firstWhere('id', $this->table->id)['x'] === 25.5);
    }

    public function test_additional_order_is_flagged_and_gets_its_own_kitchen_ticket(): void
    {
        $first = $this->order('Plov');
        $second = $this->order('Ayran', 2);

        $this->assertFalse(Order::find($first)->is_addition);
        $this->assertTrue(Order::find($second)->is_addition);

        // Əlavə sifarişin mətbəx çeki yalnız yeni məhsulları göstərir
        $this->get(route('orders.token', $second))->assertOk()
            ->assertSee('ƏLAVƏ SİFARİŞ')->assertSee('Ayran')->assertDontSee('Plov')
            ->assertSee('Əvvəlki: #'.Order::find($first)->token_no);

        $group = collect($this->getJson(route('orders.board'))->json())->firstWhere('key', 't'.$this->table->id);
        $this->assertCount(2, $group['unpaid_ids']);
        $this->assertTrue($group['orders'][1]['is_addition']);
    }

    public function test_pre_bill_and_closing_the_table_bill(): void
    {
        $a = $this->order('Plov');
        $b = $this->order('Ayran', 2);

        $this->get(route('orders.bill', ['ids' => "$a,$b"]))->assertOk()
            ->assertSee('HESAB')->assertSee('Ödəniş çeki deyil')->assertSee('Plov')->assertSee('Ayran')->assertSee('ÖDƏNİLƏCƏK');

        $invoiceId = $this->postJson(route('orders.pay'), ['order_ids' => [$a, $b], 'payment_type_id' => PaymentType::value('id')])->assertOk()->json('invoice_id');
        $this->assertSame([$invoiceId, $invoiceId], Order::whereIn('id', [$a, $b])->pluck('invoice_id')->all());
        $this->assertEmpty(collect($this->getJson(route('orders.board'))->json())->firstWhere('key', 't'.$this->table->id)['unpaid_ids'] ?? []);

        // Növbəti müştərinin sifarişi artıq "əlavə" deyil
        $this->assertFalse(Order::find($this->order('Plov'))->is_addition);
    }
}
