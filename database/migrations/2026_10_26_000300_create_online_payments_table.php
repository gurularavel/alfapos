<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Masa hesabının onlayn ödənişi (Kapital Bank): kassir hesabı göndərir, müştəri masanın ödəniş QR-ını skan edib ödəyir.
     * Biznesin Kapital Bank merchant giriş məlumatları tenants.kapitalbank_settings-də şifrələnmiş saxlanılır.
     */
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 40)->unique(); // bankdan qayıdış linkində (id əvəzinə — təxmin edilməsin)
            $table->json('order_ids');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('AZN');
            $table->string('status', 20)->default('pending'); // pending | paid | cancelled
            $table->string('bank_order_id', 40)->nullable()->index();
            $table->text('bank_password')->nullable(); // şifrələnmiş
            $table->string('bank_status', 30)->nullable();
            $table->string('bank_hpp_url')->nullable(); // bankın ödəniş səhifəsi (id/parol olmadan)
            $table->boolean('is_test')->default(true);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_table_id', 'status']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->text('kapitalbank_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('kapitalbank_settings'));
        Schema::dropIfExists('online_payments');
    }
};
