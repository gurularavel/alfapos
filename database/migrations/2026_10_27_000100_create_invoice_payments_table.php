<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bölünmüş ödəniş: bir çek bir neçə ödəniş növü ilə (məs. yarısı nağd, yarısı kart).
     * invoices.payment_type_id əsas (ən böyük məbləğli) növ kimi qalır; hesabatlar bu cədvəldən toplanır.
     * Mövcud çeklər üçün bir sətir (çekin bütün məbləği) yaradılır.
     */
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('tendered', 12, 2)->nullable(); // nağd: müştərinin verdiyi (qalıq = tendered - amount)
            $table->timestamps();
        });

        DB::statement('INSERT INTO invoice_payments (tenant_id, invoice_id, payment_type_id, amount, created_at, updated_at)
            SELECT tenant_id, id, payment_type_id, total, created_at, updated_at FROM invoices');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
