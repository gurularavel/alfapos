<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maya dəyəri: anbar məhsulunun alış qiyməti (orta çəkili), hərəkətin vahid mayası,
     * hazır porsiyanın orta mayası, satılan sətirin və personal yeməyinin mayası.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 4)->nullable()->after('min_quantity'); // 1 vahidin alış qiyməti
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 4)->nullable()->after('quantity');
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('stock_cost', 12, 4)->nullable()->after('stock_min'); // hazır porsiyanın orta mayası
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('cost', 12, 4)->nullable()->after('price'); // 1 ədədin satış anındakı mayası
        });
        Schema::table('staff_meals', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('staff_meals', fn (Blueprint $table) => $table->dropColumn('cost'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('cost'));
        Schema::table('menu_items', fn (Blueprint $table) => $table->dropColumn('stock_cost'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropColumn('cost_price'));
    }
};
