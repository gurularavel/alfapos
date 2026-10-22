<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zal planında masanın yeri (mərtəbə kətanının faizi ilə, 0–100). null = avtomatik düzülür.
        Schema::table('store_tables', function (Blueprint $table) {
            $table->decimal('pos_x', 5, 2)->nullable()->after('deposit');
            $table->decimal('pos_y', 5, 2)->nullable()->after('pos_x');
        });
    }

    public function down(): void
    {
        Schema::table('store_tables', fn (Blueprint $table) => $table->dropColumn(['pos_x', 'pos_y']));
    }
};
