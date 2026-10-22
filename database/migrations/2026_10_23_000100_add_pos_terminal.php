<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // HMAC(tenant_id:pin) — PIN-ə görə axtarış üçün deterministik, açıq PIN saxlanmır
            $table->string('pin_hash', 64)->nullable()->after('password');
            $table->unique(['tenant_id', 'pin_hash']);
            // Yalnız PIN ilə işləyən işçilərin (ofisiant) email-i olmaya bilər
            $table->string('email')->nullable()->change();
        });

        Schema::table('tenants', function (Blueprint $table) {
            // { images: [path...], lock_timeout: int (san), lock_after_order: bool }
            $table->json('terminal_settings')->nullable()->after('print_settings');
        });

        // Əvvəllər POS icazəsi olanlar ödəniş də ala bilirdi — davranışı qoruyuruq
        DB::table('users')->whereNotNull('scopes')->orderBy('id')->each(function ($user) {
            $scopes = json_decode($user->scopes, true) ?: [];
            if (in_array('POS', $scopes, true) && ! in_array('PAYMENTS', $scopes, true)) {
                $scopes[] = 'PAYMENTS';
                DB::table('users')->where('id', $user->id)->update(['scopes' => json_encode($scopes)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'pin_hash']);
            $table->dropColumn('pin_hash');
        });
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('terminal_settings'));
    }
};
