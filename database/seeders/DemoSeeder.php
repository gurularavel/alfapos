<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\PaymentType;
use App\Models\Plan;
use App\Models\RecipeItem;
use App\Models\StoreTable;
use App\Models\Tax;
use App\Models\User;
use App\Services\OrderService;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo biznes + istifadəçilər. Lokalda emaillər @pos.test, parollar "password".
 * Production-da `php artisan app:seed-demo` domeni və hər istifadəçi üçün təsadüfi parolu bura ötürür.
 */
class DemoSeeder extends Seeder
{
    public static string $domain = 'pos.test';

    /** @var array<string, string> açar (super|demo|kassir|metbex) => parol */
    public static array $passwords = [];

    /** @var array<string, string> açar => 4 rəqəmli PIN */
    public static array $pins = [];

    /** Panelə email/parol ilə daxil olanlar. */
    public const USERS = ['super' => 'Superadmin', 'demo' => 'Biznes admini', 'kassir' => 'Kassir', 'metbex' => 'Aşpaz (mətbəx)'];

    /** POS terminalında PIN ilə işləyənlər (lokal default PIN-lər). */
    public const PIN_USERS = ['demo' => '1234', 'kassir' => '1111', 'ofisiant' => '2222', 'metbex' => '3333'];

    protected function pin(string $key): string
    {
        return static::$pins[$key] ?? self::PIN_USERS[$key];
    }

    protected function email(string $key): string
    {
        return $key.'@'.static::$domain;
    }

    protected function password(string $key): string
    {
        return static::$passwords[$key] ?? 'password';
    }

    public function run(): void
    {
        User::updateOrCreate(['email' => $this->email('super')], [
            'name' => 'Super Admin', 'password' => $this->password('super'), 'role' => 'superadmin',
        ]);

        if (User::where('email', $this->email('demo'))->exists()) {
            return;
        }

        $admin = app(TenantProvisioner::class)->create('Demo Restoran', 'Demo Admin', $this->email('demo'), $this->password('demo'), '+994501234567');
        $tenant = $admin->tenant;
        $tenant->update(['plan_id' => \App\Models\Plan::where('slug', 'pro')->value('id') ?? $tenant->plan_id, 'address' => 'Bakı, Nizami küç. 10', 'service_charge' => 5, 'daily_sales_goal' => 1000, 'is_qr_order_enabled' => true]);
        app(Tenancy::class)->set($tenant);
        auth()->setUser($admin);

        $admin->setPin($this->pin('demo'));
        $admin->save();
        foreach ([
            ['kassir', 'Kassir Aynur', 'cashier', true],
            ['ofisiant', 'Ofisiant Nicat', 'waiter', false],
            ['metbex', 'Aşpaz Rauf', 'chef', true],
        ] as [$key, $name, $role, $hasLogin]) {
            $user = new User([
                'tenant_id' => $tenant->id, 'name' => $name, 'role' => $role, 'scopes' => User::ROLES[$role][1],
                'designation' => User::ROLES[$role][0],
                // Ofisiant panelə daxil olmur, yalnız terminalda PIN ilə işləyir
                'email' => $hasLogin ? $this->email($key) : null,
                'password' => $hasLogin ? $this->password($key) : Str::random(40),
            ]);
            $user->setPin($this->pin($key));
            $user->save();
        }

        $vat = Tax::first();
        $cats = Category::pluck('id', 'title');
        $menu = [
            'İsti içkilər' => [['Çay (dəmlik)', 6, null], ['Amerikano', 4.5, null], ['Kapuçino', 5.5, null], ['Latte', 6, null]],
            'Soyuq içkilər' => [['Limonad', 5, null], ['Ayran', 2, null], ['Su 0.5L', 1, null], ['Coca-Cola', 2.5, null]],
            'Əsas yeməklər' => [['Toyuq kababı', 12, null], ['Lülə kabab', 11, null], ['Plov', 14, null], ['Dolma', 10, null], ['Burger', 9, null], ['Pizza Marqarita', 13, null]],
            'Desertlər' => [['Paxlava', 4, null], ['Çizkeyk', 7, null], ['Dondurma', 4, null]],
        ];
        foreach ($menu as $cat => $items) {
            foreach ($items as [$title, $price]) {
                MenuItem::create(['category_id' => $cats[$cat], 'tax_id' => $vat->id, 'title' => $title, 'price' => $price]);
            }
        }

        $latte = MenuItem::where('title', 'Latte')->first();
        $latte->variants()->createMany([['title' => 'Kiçik', 'price' => 5], ['title' => 'Orta', 'price' => 6], ['title' => 'Böyük', 'price' => 7.5]]);
        $latte->addons()->createMany([['title' => 'Əlavə şot', 'price' => 1.5], ['title' => 'Karamel sirop', 'price' => 1], ['title' => 'Bitki südü', 'price' => 1.5]]);
        $burger = MenuItem::where('title', 'Burger')->first();
        $burger->addons()->createMany([['title' => 'Əlavə pendir', 'price' => 1.5], ['title' => 'Bekon', 'price' => 2.5]]);

        $milk = InventoryItem::create(['title' => 'Süd', 'unit' => 'ml', 'quantity' => 20000, 'min_quantity' => 3000]);
        $coffee = InventoryItem::create(['title' => 'Qəhvə dənəsi', 'unit' => 'g', 'quantity' => 5000, 'min_quantity' => 1000]);
        $bun = InventoryItem::create(['title' => 'Burger çörəyi', 'unit' => 'pc', 'quantity' => 8, 'min_quantity' => 10]);
        InventoryItem::create(['title' => 'Mal əti', 'unit' => 'kg', 'quantity' => 15, 'min_quantity' => 5]);
        RecipeItem::create(['menu_item_id' => $latte->id, 'inventory_item_id' => $milk->id, 'quantity' => 200]);
        RecipeItem::create(['menu_item_id' => $latte->id, 'inventory_item_id' => $coffee->id, 'quantity' => 18]);
        RecipeItem::create(['menu_item_id' => $burger->id, 'inventory_item_id' => $bun->id, 'quantity' => 1]);

        foreach ([['Masa 1', 'Zal'], ['Masa 2', 'Zal'], ['Masa 3', 'Zal'], ['Masa 4', 'Teras'], ['Masa 5', 'Teras'], ['VIP', '2-ci mərtəbə']] as $i => [$t, $f]) {
            StoreTable::create(['title' => $t, 'floor' => $f, 'seating_capacity' => $i === 5 ? 8 : 4]);
        }

        $customer = Customer::create(['phone' => '+994551112233', 'name' => 'Elvin Məmmədov', 'birth_date' => '1990-05-12', 'gender' => 'male']);

        // Nümunə sifarişlər: biri ödənilib, biri mətbəxdə
        $service = app(OrderService::class);
        $cash = PaymentType::where('icon', 'cash')->value('id');
        $paid = $service->create([
            ['menu_item_id' => MenuItem::where('title', 'Plov')->value('id'), 'quantity' => 2],
            ['menu_item_id' => MenuItem::where('title', 'Çay (dəmlik)')->value('id'), 'quantity' => 1],
        ], ['delivery_type' => 'dinein', 'store_table_id' => StoreTable::value('id'), 'customer_id' => $customer->id]);
        $service->pay(collect([$paid]), $cash);

        $service->create([
            ['menu_item_id' => $latte->id, 'variant_id' => $latte->variants()->value('id'), 'addon_ids' => [$latte->addons()->value('id')], 'quantity' => 2],
            ['menu_item_id' => $burger->id, 'quantity' => 1, 'notes' => 'Soğansız'],
        ], ['delivery_type' => 'dinein', 'store_table_id' => StoreTable::skip(1)->value('id')]);
    }
}
