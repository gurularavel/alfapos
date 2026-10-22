<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\StaffMeal;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadyStockTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected MenuItem $dolma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($this->admin->tenant);
        $this->actingAs($this->admin);
        // Demo: 20 porsiya dolma hazırlanıb, 1-i ofisianta nahar verilib
        $this->dolma = MenuItem::where('title', 'Dolma')->first();
    }

    protected function stock(string $title): float
    {
        return (float) MenuItem::where('title', $title)->value('stock_quantity');
    }

    protected function inventory(string $title): float
    {
        return (float) InventoryItem::where('title', $title)->value('quantity');
    }

    protected function sell(string $title, int $quantity, string $action = 'kitchen')
    {
        return $this->postJson(route('pos.store'), [
            'action' => $action, 'delivery_type' => 'takeaway', 'payment_type_id' => PaymentType::value('id'),
            'cart' => [['menu_item_id' => MenuItem::where('title', $title)->value('id'), 'quantity' => $quantity]],
        ]);
    }

    public function test_production_moves_recipe_ingredients_into_ready_portions(): void
    {
        $this->assertSame(19.0, $this->stock('Dolma'));
        $beef = $this->inventory('Mal əti');
        $leaves = $this->inventory('Üzüm yarpağı');

        $this->post(route('ready-stock.produce', $this->dolma), ['portions' => 10, 'note' => 'Günorta partiyası'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Dolma: 10 porsiya hazır stoka əlavə edildi.')->assertSessionMissing('warning');

        $this->assertSame(29.0, $this->stock('Dolma'));
        $this->assertEqualsWithDelta($beef - 0.8, $this->inventory('Mal əti'), 0.0001);
        $this->assertEqualsWithDelta($leaves - 100, $this->inventory('Üzüm yarpağı'), 0.0001);
        $this->assertDatabaseHas('stock_movements', ['type' => 'production', 'quantity' => -100, 'inventory_item_id' => InventoryItem::where('title', 'Üzüm yarpağı')->value('id')]);
        $this->assertDatabaseHas('ready_stock_movements', ['menu_item_id' => $this->dolma->id, 'type' => 'production', 'quantity' => 10, 'balance' => 29, 'note' => 'Günorta partiyası']);
    }

    public function test_production_beyond_inventory_warns_about_negative_ingredients(): void
    {
        $this->post(route('ready-stock.produce', $this->dolma), ['portions' => 50])
            ->assertSessionHas('warning', 'Anbarda mənfi qalıq yarandı: Üzüm yarpağı. Anbara mal girişini qeyd edin.');
        $this->assertSame(69.0, $this->stock('Dolma'));
    }

    public function test_selling_ready_item_uses_portions_and_not_ingredients(): void
    {
        $beef = $this->inventory('Mal əti');
        $milk = $this->inventory('Süd');

        $this->sell('Dolma', 3)->assertOk()->assertJsonPath('stock.'.$this->dolma->id, 16);
        $this->sell('Latte', 1)->assertOk();

        $this->assertSame(16.0, $this->stock('Dolma'));
        $this->assertSame($beef, $this->inventory('Mal əti'));
        $this->assertEqualsWithDelta($milk - 200, $this->inventory('Süd'), 0.0001); // izlənməyən məhsul — reseptlə
    }

    public function test_cannot_sell_more_than_ready_stock(): void
    {
        $this->post(route('ready-stock.count', $this->dolma), ['actual' => 2])->assertSessionHasNoErrors();

        $this->sell('Dolma', 3)->assertUnprocessable()->assertJsonPath('errors.cart.0', 'Dolma: hazır stokda cəmi 2 porsiya qalıb.');
        $this->sell('Dolma', 2, 'pay')->assertOk();
        $this->sell('Dolma', 1)->assertUnprocessable()->assertJsonPath('errors.cart.0', 'Dolma: hazır stokda bitib.');
        $this->assertSame(0.0, $this->stock('Dolma'));

        // Bitmiş yemək QR menyuda göstərilmir, POS kartında qalığı 0-dır
        $this->get(route('qr.show', $this->admin->tenant->qr_code))->assertOk()->assertDontSee('"title":"Dolma"', false);
        $this->get(route('pos'))->assertViewHas('posData', fn ($d) => collect($d['items'])->firstWhere('title', 'Dolma')['stock'] === 0.0
            && collect($d['items'])->firstWhere('title', 'Plov')['stock'] === null);
    }

    public function test_cancelled_order_returns_portions(): void
    {
        $order = Order::find($this->sell('Dolma', 4)->assertOk()->json('order.id'));
        $this->assertSame(15.0, $this->stock('Dolma'));

        $this->postJson(route('orders.cancel', $order), ['reason' => 'Səhv'])->assertOk();

        $this->assertSame(19.0, $this->stock('Dolma'));
        $this->get(route('reports.show', 'ready-stock'))->assertOk()
            ->assertViewHas('rows', fn ($rows) => collect($rows)->firstWhere(0, 'Dolma') === ['Dolma', '20', '0', '1', '0', '0', '19']);
    }

    public function test_waste_requires_reason_and_cannot_exceed_stock(): void
    {
        $this->post(route('ready-stock.waste', $this->dolma), ['portions' => 3])->assertSessionHasErrors('note');
        $this->post(route('ready-stock.waste', $this->dolma), ['portions' => 25, 'note' => 'Vaxtı keçdi'])
            ->assertSessionHasErrors(['portions' => 'Hazır stokda cəmi 19 porsiya var.']);

        $this->post(route('ready-stock.waste', $this->dolma), ['portions' => 3, 'note' => 'Vaxtı keçdi'])->assertSessionHasNoErrors();

        $this->assertSame(16.0, $this->stock('Dolma'));
        $this->assertDatabaseHas('ready_stock_movements', ['type' => 'wastage', 'quantity' => -3, 'note' => 'Vaxtı keçdi']);
    }

    public function test_tracking_can_be_started_with_opening_portions_and_stopped(): void
    {
        $plov = MenuItem::where('title', 'Plov')->first();

        $this->post(route('ready-stock.track'), ['menu_item_id' => $plov->id, 'portions' => 12, 'stock_min' => 3])->assertSessionHasNoErrors();
        $this->assertSame(12.0, $this->stock('Plov'));
        $this->get(route('ready-stock.index'))->assertOk()->assertSee('Plov');

        $this->delete(route('ready-stock.untrack', $plov))->assertSessionHasNoErrors();
        $this->assertFalse($plov->fresh()->track_stock);
        $this->sell('Plov', 30)->assertOk(); // izlənmir — limitsiz
        $this->post(route('ready-stock.produce', $plov), ['portions' => 5])->assertNotFound();
    }

    public function test_staff_meal_takes_from_ready_stock_and_inventory_and_cancel_restores(): void
    {
        $waiter = User::where('role', 'waiter')->first();
        $milk = $this->inventory('Süd');

        $this->post(route('staff-meals.store'), ['user_id' => $waiter->id, 'meal_type' => 'lunch', 'items' => [
            ['menu_item_id' => $this->dolma->id, 'quantity' => 2],
            ['menu_item_id' => MenuItem::where('title', 'Latte')->value('id'), 'quantity' => 1],
        ]])->assertSessionHasNoErrors();

        $meal = StaffMeal::latest('id')->first();
        $this->assertSame([$waiter->name, '26.00', 2], [$meal->employee_name, $meal->total, $meal->items->count()]);
        $this->assertSame(17.0, $this->stock('Dolma'));
        $this->assertEqualsWithDelta($milk - 200, $this->inventory('Süd'), 0.0001);
        $this->get(route('staff-meals.index'))->assertOk()->assertSee('2 × Dolma, 1 × Latte');

        $this->post(route('staff-meals.cancel', $meal))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $meal->fresh()->status);
        $this->assertSame(19.0, $this->stock('Dolma'));
        $this->assertEqualsWithDelta($milk, $this->inventory('Süd'), 0.0001);
        $this->get(route('reports.show', 'ready-stock'))->assertViewHas('rows', fn ($rows) => collect($rows)->firstWhere(0, 'Dolma')[3] === '1');
    }

    public function test_staff_meal_validation_and_stock_limit(): void
    {
        $this->post(route('staff-meals.store'), ['meal_type' => 'lunch'])
            ->assertSessionHasErrors(['items' => 'Ən azı bir yemək seçin.', 'employee_name' => 'İşçini seçin və ya adını yazın.']);

        $this->post(route('staff-meals.store'), ['employee_name' => 'Qabyuyan Səbinə', 'meal_type' => 'dinner', 'items' => [['menu_item_id' => $this->dolma->id, 'quantity' => 20]]])
            ->assertSessionHasErrors(['items' => 'Dolma: hazır stokda cəmi 19 porsiya qalıb.']);
        $this->assertSame(1, StaffMeal::count());
    }

    public function test_staff_meal_free_text_name_is_escaped_and_reported(): void
    {
        $this->post(route('staff-meals.store'), ['employee_name' => '<script>alert(1)</script>', 'meal_type' => 'other', 'items' => [['menu_item_id' => $this->dolma->id, 'quantity' => 1]]])
            ->assertSessionHasNoErrors();

        $this->get(route('staff-meals.index'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('reports.show', 'staff-meals'))->assertOk()
            ->assertViewHas('rows', fn ($rows) => collect($rows)->firstWhere(0, 'Ofisiant Nicat') == ['Ofisiant Nicat', 1, money(10)]);
    }

    public function test_chef_can_use_kitchen_stock_and_cashier_cannot(): void
    {
        $this->actingAs(User::where('email', 'metbex@pos.test')->first());
        $this->get(route('ready-stock.index'))->assertOk();
        $this->get(route('staff-meals.index'))->assertOk();

        $this->actingAs(User::where('email', 'kassir@pos.test')->first());
        $this->get(route('ready-stock.index'))->assertForbidden();
        $this->post(route('staff-meals.store'), ['employee_name' => 'X', 'meal_type' => 'lunch', 'items' => [['menu_item_id' => $this->dolma->id, 'quantity' => 1]]])->assertForbidden();
        $this->assertSame(19.0, $this->stock('Dolma'));
    }

    public function test_other_business_menu_items_are_not_reachable(): void
    {
        $other = app(TenantProvisioner::class)->create('Başqa', 'O', 'o@pos.test', 'password');
        app(Tenancy::class)->set($other->tenant);
        $foreign = MenuItem::create(['title' => 'Yad dolma', 'price' => 5, 'track_stock' => true]);
        app(Tenancy::class)->set($this->admin->tenant);

        $this->post(route('ready-stock.produce', $foreign), ['portions' => 5])->assertNotFound();
        $this->post(route('ready-stock.track'), ['menu_item_id' => $foreign->id])->assertSessionHasErrors('menu_item_id');
        $this->post(route('staff-meals.store'), ['employee_name' => 'X', 'meal_type' => 'lunch', 'items' => [['menu_item_id' => $foreign->id, 'quantity' => 1]]])
            ->assertSessionHasErrors('items.0.menu_item_id');
    }
}
