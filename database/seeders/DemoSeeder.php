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

/** Lokal inkişaf və testlər üçün demo biznes. Production-da işlədilmir (bütün parollar "password"). */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'super@pos.test'], [
            'name' => 'Super Admin', 'password' => 'password', 'role' => 'superadmin',
        ]);

        if (User::where('email', 'demo@pos.test')->exists()) {
            return;
        }

        $admin = app(TenantProvisioner::class)->create('Demo Restoran', 'Demo Admin', 'demo@pos.test', 'password', '+994501234567');
        $tenant = $admin->tenant;
        $tenant->update(['address' => 'Bakı, Nizami küç. 10', 'service_charge' => 5, 'daily_sales_goal' => 1000, 'is_qr_order_enabled' => true]);
        app(Tenancy::class)->set($tenant);
        auth()->setUser($admin);

        User::create([
            'tenant_id' => $tenant->id, 'name' => 'Kassir Aynur', 'email' => 'kassir@pos.test', 'password' => 'password',
            'role' => 'user', 'designation' => 'Kassir', 'scopes' => ['POS', 'ORDERS', 'INVOICES', 'CUSTOMERS'],
        ]);
        User::create([
            'tenant_id' => $tenant->id, 'name' => 'Aşpaz Rauf', 'email' => 'metbex@pos.test', 'password' => 'password',
            'role' => 'user', 'designation' => 'Aşpaz', 'scopes' => ['KITCHEN'],
        ]);

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
