<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_tax_enabled')->default(true)->after('service_charge');
            $table->boolean('is_service_charge_enabled')->default(false)->after('is_tax_enabled');
            // Servis haqqı yalnız zalda oturanlara (özü aparan / çatdırılma — yox)
            $table->boolean('service_charge_dinein_only')->default(true)->after('is_service_charge_enabled');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Kassir bu sifariş üçün servis haqqını ləğv edib
            $table->boolean('no_service_charge')->default(false)->after('discount_value');
        });

        // Mövcud bizneslər: servis faizi > 0 idisə açıq qalsın, əvvəlki kimi bütün sifarişlərə tətbiq olunsun
        DB::table('tenants')->where('service_charge', '>', 0)->update(['is_service_charge_enabled' => true, 'service_charge_dinein_only' => false]);
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['is_tax_enabled', 'is_service_charge_enabled', 'service_charge_dinein_only']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('no_service_charge'));
    }
};
