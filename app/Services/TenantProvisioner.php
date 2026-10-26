<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Department;
use App\Models\PaymentType;
use App\Models\Plan;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

class TenantProvisioner
{
    public const TRIAL_DAYS = 14;

    /** Yeni biznes + admin istifadəçi + ilkin ayarlar (ödəniş növləri, vergi, kateqoriyalar). */
    public function create(string $businessName, string $ownerName, string $email, string $password, ?string $phone = null): User
    {
        return DB::transaction(function () use ($businessName, $ownerName, $email, $password, $phone) {
            $tenant = Tenant::create([
                'name' => $businessName,
                'email' => $email,
                'phone' => $phone,
                'plan_id' => Plan::where('is_active', true)->orderBy('sort')->value('id'),
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'print_settings' => [
                    'paper_width' => 80,
                    'header' => $businessName,
                    'footer' => 'Bizi seçdiyiniz üçün təşəkkür edirik!',
                    'show_store_details' => true,
                    'show_customer_details' => true,
                    'show_notes' => true,
                    'print_token' => true,
                    'kot_show_prices' => false,
                ],
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $ownerName,
                'email' => $email,
                'password' => $password,
                'phone' => $phone,
                'role' => 'admin',
                'designation' => 'Sahibkar',
            ]);

            $tenancy = app(Tenancy::class);
            $previous = $tenancy->tenant();
            $tenancy->set($tenant);

            foreach ([['Nağd', 'cash'], ['Kart', 'card'], ['Onlayn', 'online']] as [$title, $icon]) {
                PaymentType::create(['title' => $title, 'icon' => $icon]);
            }
            Tax::create(['title' => 'ƏDV 18%', 'rate' => 18, 'type' => 'inclusive']);
            $branch = Branch::create(['name' => 'Əsas filial', 'phone' => $phone]);
            Terminal::create(['branch_id' => $branch->id, 'name' => 'Kassa 1']);
            // Şöbələr: hər şöbəyə öz çeki çıxır (içkilər — bar, yeməklər — mətbəx)
            Department::create(['name' => 'Mətbəx', 'is_default' => true, 'sort' => 1]);
            $bar = Department::create(['name' => 'Bar', 'sort' => 2]);
            foreach (['İsti içkilər', 'Soyuq içkilər', 'Əsas yeməklər', 'Desertlər'] as $i => $title) {
                Category::create(['title' => $title, 'sort' => $i, 'department_id' => str_contains($title, 'içki') ? $bar->id : null]);
            }

            $tenancy->set($previous);

            return $user;
        });
    }
}
