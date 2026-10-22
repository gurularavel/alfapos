<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // CC BY / CC BY-SA şəkillər üçün müəllif və lisenziya (QR menyuda "Şəkil müəllifləri" səhifəsində göstərilir)
            $table->string('image_credit', 500)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', fn (Blueprint $table) => $table->dropColumn('image_credit'));
    }
};
