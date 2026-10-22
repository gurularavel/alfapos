<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Terminal = filialdakı konkret kassa/POS cihazı. Cihaz cookie ilə terminala bağlanır.
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // İşçinin işləyə biləcəyi terminallar (boşdursa — hamısı)
        Schema::create('terminal_user', function (Blueprint $table) {
            $table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['terminal_id', 'user_id']);
        });

        Schema::table('store_tables', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignId('terminal_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignId('terminal_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });

        // Mövcud bizneslər: "Əsas filial" + "Kassa 1"; masalar, sifarişlər və çeklər əsas filiala yazılır
        foreach (DB::table('tenants')->get(['id', 'address', 'phone']) as $tenant) {
            $branchId = DB::table('branches')->insertGetId([
                'tenant_id' => $tenant->id, 'name' => 'Əsas filial', 'address' => $tenant->address, 'phone' => $tenant->phone,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('terminals')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branchId, 'name' => 'Kassa 1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach (['store_tables', 'orders', 'invoices'] as $t) {
                DB::table($t)->where('tenant_id', $tenant->id)->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'orders'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('terminal_id');
                $table->dropConstrainedForeignId('branch_id');
            });
        }
        Schema::table('store_tables', fn (Blueprint $table) => $table->dropConstrainedForeignId('branch_id'));
        Schema::dropIfExists('terminal_user');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('branches');
    }
};
