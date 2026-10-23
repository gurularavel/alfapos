<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Menyu axtarışı üçün normallaşdırılmış ad: "cay" / "chay" yazanda "Çay" tapılsın (search_key() helper-i). */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('search_key')->nullable()->after('title');
        });

        DB::table('menu_items')->select('id', 'title')->orderBy('id')->chunkById(500, function ($items) {
            foreach ($items as $item) {
                DB::table('menu_items')->where('id', $item->id)->update(['search_key' => search_index($item->title)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', fn (Blueprint $table) => $table->dropColumn('search_key'));
    }
};
