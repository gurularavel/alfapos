<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Şöbələr (mətbəx, bar ...): məhsul kateqoriyaya görə (lazım olsa məhsulda ayrıca) şöbəyə aiddir;
     * sifariş göndəriləndə hər şöbəyə öz çeki çıxır. print_at: pos — kassada çap olunur, station — şöbənin öz cihazında.
     * Sifariş sətri şöbəni sifariş anında saxlayır (sonradan dəyişsə köhnə çeklər təsirlənməsin).
     * Boş (null) şöbə — biznesin əsas şöbəsi (is_default).
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('print_at', 10)->default('pos'); // pos | station
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        foreach (['categories', 'menu_items', 'order_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        $now = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('departments')->insert(['tenant_id' => $tenantId, 'name' => 'Mətbəx', 'print_at' => 'pos', 'is_default' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $bar = DB::table('departments')->insertGetId(['tenant_id' => $tenantId, 'name' => 'Bar', 'print_at' => 'pos', 'is_default' => false, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now]);
            // Yeni biznesdəki kimi: içki kateqoriyaları bara (qalanı əsas şöbə — mətbəx)
            DB::table('categories')->where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->where('title', 'like', '%içki%')->orWhere('title', 'like', '%İçki%'))
                ->update(['department_id' => $bar]);
        }
    }

    public function down(): void
    {
        foreach (['order_items', 'menu_items', 'categories'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }
        Schema::dropIfExists('departments');
    }
};
