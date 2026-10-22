<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kabinet / VIP masa depoziti: minimum ödəniş məbləği. Hesab bundan azdırsa fərq əlavə olunur.
        Schema::table('store_tables', function (Blueprint $table) {
            $table->decimal('deposit', 12, 2)->default(0)->after('seating_capacity');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->default(0)->after('service_charge_total'); // masanın depoziti (ödəniş anında)
            $table->decimal('deposit_topup', 12, 2)->default(0)->after('deposit_amount');        // depozitə qədər əlavə olunan fərq
        });
    }

    public function down(): void
    {
        Schema::table('store_tables', fn (Blueprint $table) => $table->dropColumn('deposit'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['deposit_amount', 'deposit_topup']));
    }
};
