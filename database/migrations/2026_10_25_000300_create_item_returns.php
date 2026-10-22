<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ləğv və qaytarmalar (menecer təsdiqi ilə), çek/hesab çekinin çap sayı, endirim və çek ləğvinin təsdiqçisi.
     */
    public function up(): void
    {
        Schema::create('item_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete(); // ləğv olunmuş (ayrılmış) sətir
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); // void (ödənişdən əvvəl) | refund (ödənişdən sonra, pul qaytarılır)
            $table->string('title');
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 12, 2); // sətirin dəyəri (refund-da — müştəriyə qaytarılan məbləğ)
            $table->decimal('cost', 12, 2)->nullable(); // israf olunanda itən maya
            $table->string('reason', 20);
            $table->string('note')->nullable();
            $table->string('stock_action', 10); // restore | waste
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('after_bill')->default(false); // hesab çeki artıq çap olunmuşdu
            $table->foreignId('order_creator_id')->nullable()->constrained('users')->nullOnDelete(); // sifarişi verən
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('refunded_total', 12, 2)->default(0)->after('total');
            $table->unsignedInteger('print_count')->default(0);
            $table->foreignId('void_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('bill_print_count')->default(0);
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_approved_by');
            $table->dropColumn('bill_print_count');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('void_approved_by');
            $table->dropColumn(['refunded_total', 'print_count']);
        });
        Schema::dropIfExists('item_returns');
    }
};
