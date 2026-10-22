<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hazır yemək stoku (porsiya ilə izlənən menyu məhsulları) və personal yeməyi.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->boolean('track_stock')->default(false)->after('is_enabled');
            $table->decimal('stock_quantity', 10, 2)->default(0)->after('track_stock');
            $table->decimal('stock_min', 10, 2)->default(0)->after('stock_quantity');
        });

        Schema::create('staff_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // yeməyi alan işçi (hesabı varsa)
            $table->string('employee_name');
            $table->string('meal_type', 12)->default('lunch');
            $table->string('note')->nullable();
            $table->decimal('total', 12, 2)->default(0); // menyu qiyməti ilə dəyəri
            $table->string('status', 10)->default('active'); // active | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('staff_meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedInteger('quantity');
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });

        Schema::create('ready_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12); // production | sale | staff | wastage | count | return
            $table->decimal('quantity', 10, 2); // işarəli: + giriş, - çıxış
            $table->decimal('balance', 10, 2); // hərəkətdən sonrakı qalıq
            $table->string('note')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_meal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('type', 12)->change();
            $table->foreignId('staff_meal_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_meal_id');
        });
        Schema::dropIfExists('ready_stock_movements');
        Schema::dropIfExists('staff_meal_items');
        Schema::dropIfExists('staff_meals');
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['track_stock', 'stock_quantity', 'stock_min']);
        });
    }
};
