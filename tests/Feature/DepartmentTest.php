<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentType;
use App\Models\StoreTable;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Department $kitchen;

    protected Department $bar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->admin = User::where('email', 'demo@pos.test')->first();
        app(Tenancy::class)->set($this->admin->tenant);
        $this->actingAs($this->admin);
        $this->kitchen = Department::where('name', 'Mətbəx')->firstOrFail();
        $this->bar = Department::where('name', 'Bar')->firstOrFail();
    }

    /** Çay (içki → bar) + Plov (yemək → mətbəx) bir sifarişdə. */
    protected function order(): TestResponse
    {
        return $this->postJson(route('pos.store'), [
            'action' => 'kitchen', 'delivery_type' => 'dinein', 'store_table_id' => StoreTable::where('title', 'Masa 2')->value('id'),
            'cart' => [
                ['menu_item_id' => MenuItem::where('title', 'Çay (dəmlik)')->value('id'), 'quantity' => 2],
                ['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 1],
            ],
        ])->assertOk();
    }

    public function test_new_business_gets_kitchen_and_bar_with_drinks_in_the_bar(): void
    {
        $this->assertTrue($this->kitchen->is_default);
        $this->assertSame($this->bar->id, Category::where('title', 'İsti içkilər')->value('department_id'));
        $this->assertNull(Category::where('title', 'Əsas yeməklər')->value('department_id'));
    }

    public function test_order_lines_keep_their_department_and_pos_prints_one_ticket_per_department(): void
    {
        $response = $this->order();
        $orderId = $response->json('order.id');

        $items = OrderItem::where('order_id', $orderId)->pluck('department_id', 'title');
        $this->assertSame($this->bar->id, $items['Çay (dəmlik)']);
        $this->assertSame($this->kitchen->id, $items['Plov']);
        $this->assertEqualsCanonicalizing([$this->kitchen->id, $this->bar->id], array_column($response->json('print_departments'), 'id'));

        // Bar çeki — yalnız içkilər
        $this->get(route('orders.token', [$orderId, 'department' => $this->bar->id]))->assertOk()
            ->assertSee('BAR ÇEKİ')->assertSee('Çay (dəmlik)')->assertDontSee('Plov');
        $this->get(route('orders.token', [$orderId, 'department' => $this->kitchen->id]))->assertOk()
            ->assertSee('MƏTBƏX ÇEKİ')->assertSee('Plov')->assertDontSee('Çay (dəmlik)');
        // Ümumi çek — şöbələr ayrıca başlıqla
        $this->get(route('orders.token', $orderId))->assertOk()->assertSee('Çay (dəmlik)')->assertSee('Plov')->assertSee('BAR');

        // Şöbə sonradan dəyişsə köhnə sifariş öz şöbəsində qalır
        Category::where('title', 'İsti içkilər')->update(['department_id' => null]);
        $this->assertSame($this->bar->id, OrderItem::where('order_id', $orderId)->where('title', 'Çay (dəmlik)')->value('department_id'));
    }

    public function test_department_with_its_own_device_is_not_printed_at_the_till(): void
    {
        $this->bar->update(['print_at' => 'station']);

        $this->assertSame([['id' => $this->kitchen->id, 'name' => 'Mətbəx']], $this->order()->json('print_departments'));
    }

    public function test_product_can_override_its_category_department(): void
    {
        MenuItem::where('title', 'Plov')->first()->update(['department_id' => $this->bar->id]);
        $orderId = $this->order()->json('order.id');

        $this->assertSame([$this->bar->id], OrderItem::where('order_id', $orderId)->distinct()->pluck('department_id')->all());
    }

    public function test_kitchen_feed_and_mark_all_work_per_department(): void
    {
        $orderId = $this->order()->json('order.id');

        $feed = collect($this->getJson(route('kitchen.feed'))->json())->firstWhere('id', $orderId);
        $this->assertSame([$this->bar->id, $this->kitchen->id], collect($feed['items'])->pluck('department_id')->all());

        $this->postJson(route('kitchen.ready', $orderId), ['department_id' => $this->bar->id])->assertOk();
        $statuses = OrderItem::where('order_id', $orderId)->pluck('status', 'title');
        $this->assertSame('ready', $statuses['Çay (dəmlik)']);
        $this->assertSame('created', $statuses['Plov']);
        $this->assertNotSame('ready', Order::find($orderId)->status); // mətbəx hələ hazırlamayıb
    }

    public function test_departments_are_managed_in_settings(): void
    {
        $hookah = Category::create(['title' => 'Qəlyan']);
        $this->post(route('settings.departments.store'), ['name' => 'Qəlyan', 'print_at' => 'station', 'category_ids' => [$hookah->id]])->assertSessionHasNoErrors();
        $department = Department::where('name', 'Qəlyan')->firstOrFail();
        $this->assertSame($department->id, $hookah->fresh()->department_id);
        $this->get(route('settings.departments.index'))->assertOk()->assertSee('Qəlyan')->assertSee('Şöbənin öz cihazında');

        $this->delete(route('settings.departments.destroy', $this->kitchen))->assertStatus(422); // əsas şöbə
        $this->delete(route('settings.departments.destroy', $department))->assertRedirect();
        $this->assertNull($hookah->fresh()->department_id);

        // Başqa şöbə əsas ediləndə əvvəlki əsas olmaqdan çıxır
        $this->put(route('settings.departments.update', $this->bar), ['name' => 'Bar', 'print_at' => 'pos', 'is_default' => '1', 'category_ids' => []])->assertSessionHasNoErrors();
        $this->assertTrue($this->bar->fresh()->is_default);
        $this->assertFalse($this->kitchen->fresh()->is_default);
    }

    public function test_bartender_sees_bar_screen_prints_the_bill_but_cannot_close_it(): void
    {
        $orderId = $this->order()->json('order.id');
        $bartender = User::create([
            'tenant_id' => $this->admin->tenant_id, 'name' => 'Barmen Elçin', 'email' => 'barmen@pos.test', 'password' => 'secret123',
            'role' => 'bartender', 'scopes' => User::ROLES['bartender'][1],
        ]);
        $this->actingAs($bartender);

        $this->assertSame(route('kitchen'), $bartender->homeRoute());
        $this->get(route('kitchen'))->assertOk()->assertSee('Bar');
        $this->get(route('orders.index'))->assertOk()->assertSee('Hesab çeki');
        $this->get(route('orders.bill', ['ids' => $orderId]))->assertOk()->assertSee('Çay (dəmlik)');
        $this->postJson(route('orders.pay'), ['order_ids' => [$orderId], 'payment_type_id' => PaymentType::value('id')])->assertForbidden();
        $this->get(route('pos'))->assertForbidden();
        $this->assertSame('pending', Order::find($orderId)->payment_status);
    }
}
