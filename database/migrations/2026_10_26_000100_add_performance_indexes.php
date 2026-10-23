<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sürət: panel, hesabatlar və siyahılar tarix aralığı ilə (tenant daxilində) süzür, müştəri cəmləri isə
     * customer_id üzrə. Bu indekslər olmadan hər sorğu biznesin bütün çeklərini / sifarişlərini oxuyurdu.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at']); // hesabatlar, panel
            $table->index(['tenant_id', 'created_at']); // çeklər siyahısı (ən yenilər)
            $table->index(['customer_id', 'status']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at']); // token nömrəsi (lockForUpdate) yalnız bu günün sətirlərini kilidləsin
            $table->index(['tenant_id', 'status', 'payment_status']); // Sifarişlər lövhəsi: açıq + ödənilməmiş tamamlanmış
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at']);
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['tenant_id', 'reserved_at']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at']);
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /** MySQL yeni indeks FK-nı əhatə edəndə köhnə tək sütunlu FK indeksini silir — geri qaytaranda onu bərpa edirik. */
    public function down(): void
    {
        foreach (['stock_movements' => 'created_at', 'audit_logs' => 'created_at', 'reservations' => 'reserved_at'] as $name => $column) {
            $this->restoreForeignIndex($name, 'tenant_id');
            Schema::table($name, fn (Blueprint $table) => $table->dropIndex(['tenant_id', $column]));
        }
        Schema::table('customers', fn (Blueprint $table) => $table->dropIndex(['tenant_id', 'created_at']));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'payment_status']);
            $table->dropIndex(['tenant_id', 'created_at']);
        });
        $this->restoreForeignIndex('invoices', 'customer_id');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['tenant_id', 'status', 'created_at']);
        });
    }

    protected function restoreForeignIndex(string $table, string $column): void
    {
        if (! Schema::hasIndex($table, [$column])) {
            Schema::table($table, fn (Blueprint $t) => $t->index($column, "{$table}_{$column}_foreign"));
        }
    }
};
