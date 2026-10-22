<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Başlanğıc', 'starter', 29, 3, 50, 10, 1],
            ['Biznes', 'business', 59, 10, 300, 40, 2],
            ['Pro', 'pro', 99, null, null, null, 3],
        ] as [$name, $slug, $price, $users, $items, $tables, $sort]) {
            Plan::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'price' => $price, 'currency' => 'AZN', 'max_users' => $users,
                'max_menu_items' => $items, 'max_tables' => $tables, 'sort' => $sort,
                'features' => ['POS', 'Mətbəx ekranı', 'QR menyu', 'Anbar', 'Hesabatlar'],
            ]);
        }
    }
}
