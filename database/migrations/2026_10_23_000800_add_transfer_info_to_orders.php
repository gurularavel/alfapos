<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Son masa köçürməsi: mətbəx çekində və mətbəx ekranında "Masa 5 → Masa 1" qeydi üçün
        Schema::table('orders', function (Blueprint $table) {
            $table->string('transferred_from', 100)->nullable()->after('is_addition');
            $table->timestamp('transferred_at')->nullable()->after('transferred_from');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['transferred_from', 'transferred_at']));
    }
};
