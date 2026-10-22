<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\PaymentType;
use App\Models\StaffMeal;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demo maya: süd 0.0018/ml, qəhvə 0.045/q, mal əti 16/kq, üzüm yarpağı 0.03/əd, düyü 3.2/kq, qoz 18/kq.
 * Latte (200 ml süd + 18 q qəhvə) = 1.17; dolma porsiyası (0.08 kq ət + 10 yarpaq + 0.02 kq düyü) = 1.644.
 */
class CostTest extends TestCase
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

    protected function item(string $title): InventoryItem
    {
        return InventoryItem::where('title', $title)->first();
    }

    protected function sell(string $title, int $quantity): OrderItem
    {
        $id = $this->postJson(route('pos.store'), [
            'action' => 'pay', 'delivery_type' => 'takeaway', 'payment_type_id' => PaymentType::value('id'),
            'cart' => [['menu_item_id' => MenuItem::where('title', $title)->value('id'), 'quantity' => $quantity]],
        ])->assertOk()->json('order.id');

        return OrderItem::where('order_id', $id)->first();
    }

    public function test_purchase_updates_weighted_average_cost(): void
    {
        $beef = $this->item('Mal əti'); // 13.4 kq × 16 ₼ (dolma partiyasından sonra)

        $this->post(route('inventory.movement', $beef), ['type' => 'in', 'quantity' => 5, 'unit_cost' => 20, 'note' => 'Faktura 15'])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta((13.4 * 16 + 5 * 20) / 18.4, (float) $beef->fresh()->cost_price, 0.0001);
        $this->assertEqualsWithDelta(18.4, (float) $beef->fresh()->quantity, 0.0001);
        $this->assertDatabaseHas('stock_movements', ['inventory_item_id' => $beef->id, 'type' => 'in', 'quantity' => 5, 'unit_cost' => 20]);
    }

    public function test_purchase_without_price_and_issues_keep_cost(): void
    {
        $milk = $this->item('Süd');

        $this->post(route('inventory.movement', $milk), ['type' => 'in', 'quantity' => 1000])->assertSessionHasNoErrors();
        $this->post(route('inventory.movement', $milk), ['type' => 'wastage', 'quantity' => 500, 'note' => 'Turşudu'])->assertSessionHasNoErrors();

        $this->assertSame('0.0018', $milk->fresh()->cost_price);
        // Çıxış cari maya ilə qiymətləndirilir
        $this->assertSame('0.0018', StockMovement::where('inventory_item_id', $milk->id)->where('type', 'wastage')->value('unit_cost'));
    }

    public function test_negative_purchase_price_is_rejected(): void
    {
        $this->post(route('inventory.movement', $this->item('Süd')), ['type' => 'in', 'quantity' => 10, 'unit_cost' => -1])->assertSessionHasErrors('unit_cost');
        $this->assertSame('0.0018', $this->item('Süd')->cost_price);
    }

    public function test_sale_stores_cost_at_the_time_of_sale(): void
    {
        $latte = $this->sell('Latte', 2);
        $dolma = $this->sell('Dolma', 1);
        $plov = $this->sell('Plov', 1);

        $this->assertSame('1.1700', $latte->cost);
        $this->assertSame('1.6440', $dolma->cost); // hazır porsiyanın mayası
        $this->assertNull($plov->cost); // resepti yoxdur

        // Sonradan alış qiyməti dəyişsə də keçmiş satışın mayası dəyişmir
        $this->put(route('inventory.update', $this->item('Süd')), ['title' => 'Süd', 'unit' => 'ml', 'min_quantity' => 0, 'cost_price' => 0.01])->assertSessionHasNoErrors();
        $this->assertSame('1.1700', $latte->fresh()->cost);
        $this->assertSame('2.8100', $this->sell('Latte', 1)->cost);
    }

    public function test_production_averages_ready_portion_cost(): void
    {
        $dolma = MenuItem::where('title', 'Dolma')->first(); // 19 porsiya × 1.644
        $leaf = $this->item('Üzüm yarpağı');
        $this->put(route('inventory.update', $leaf), ['title' => $leaf->title, 'unit' => 'pc', 'min_quantity' => 0, 'cost_price' => 0.05])->assertSessionHasNoErrors();

        $this->post(route('ready-stock.produce', $dolma), ['portions' => 19])->assertSessionHasNoErrors();

        $this->assertSame('1.7440', $dolma->fresh()->stock_cost); // (19 × 1.644 + 19 × 1.844) / 38
    }

    public function test_cost_is_unknown_when_an_ingredient_has_no_price(): void
    {
        $paxlava = MenuItem::where('title', 'Paxlava')->first();
        $walnut = $this->item('Qoz ləpəsi');
        $this->put(route('inventory.update', $walnut), ['title' => $walnut->title, 'unit' => 'kg', 'min_quantity' => 0, 'cost_price' => ''])->assertSessionHasNoErrors();

        $this->post(route('ready-stock.produce', $paxlava), ['portions' => 6])->assertSessionHasNoErrors();

        $this->assertNull($paxlava->fresh()->stock_cost);
        $this->assertNull($this->sell('Paxlava', 1)->cost);
        $this->get(route('ready-stock.index'))->assertOk()->assertSee('bilinmir');
    }

    public function test_staff_meal_cost(): void
    {
        $waiter = User::where('role', 'waiter')->first();
        $this->post(route('staff-meals.store'), ['user_id' => $waiter->id, 'meal_type' => 'dinner', 'items' => [
            ['menu_item_id' => MenuItem::where('title', 'Dolma')->value('id'), 'quantity' => 2],
            ['menu_item_id' => MenuItem::where('title', 'Latte')->value('id'), 'quantity' => 1],
        ]])->assertSessionHasNoErrors();
        $this->post(route('staff-meals.store'), ['employee_name' => 'Qonaq aşpaz', 'meal_type' => 'lunch', 'items' => [
            ['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 1],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('4.46', StaffMeal::where('meal_type', 'dinner')->value('cost')); // 2 × 1.644 + 1.17
        $this->assertNull(StaffMeal::where('employee_name', 'Qonaq aşpaz')->value('cost'));
        $this->get(route('staff-meals.index'))->assertOk()->assertSee('1 qeydin mayası bilinmir');
        $this->get(route('reports.show', 'staff-meals'))->assertOk()
            ->assertViewHas('rows', fn ($rows) => collect($rows)->firstWhere(0, 'Ofisiant Nicat') === ['Ofisiant Nicat', 2, money(36), money(6.10)]);
    }

    public function test_item_profit_report(): void
    {
        $this->sell('Latte', 2);
        $this->sell('Plov', 1);

        $this->get(route('reports.show', 'item-profit'))->assertOk()
            ->assertViewHas('rows', function ($rows) {
                $rows = collect($rows)->keyBy(0);

                return $rows['Latte'] == ['Latte', 2, money(12), money(2.34), money(9.66), '81%', '']
                    && $rows['Plov'][3] === '—' && $rows['Plov'][6] === '3 əd'; // seed-in ödənilmiş 2 plovu + 1
            });
    }

    public function test_inventory_page_shows_prices_and_stock_value(): void
    {
        $this->get(route('inventory.index'))->assertOk()->assertSee('Anbarın dəyəri')->assertSee('0.0018 ₼')
            ->assertViewHas('stats', fn ($s) => $s['unpriced'] === 0 && $s['value'] > 0);
        $this->get(route('settings.menu-items.edit', MenuItem::where('title', 'Latte')->first()))->assertOk()->assertSee('Maya dəyəri (1 porsiya)');
    }
}
