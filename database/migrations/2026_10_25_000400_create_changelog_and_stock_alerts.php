<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saytın "Yeniliklər" (changelog) bölməsi və restoran panelindəki stok bildirişləri.
     */
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->date('released_at');
            $table->string('title');
            $table->json('items'); // [{type: new|improved|fixed, text}]
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 10); // inventory (anbar xammalı) | ready (hazır yemək)
            $table->unsignedBigInteger('item_id');
            $table->string('title');
            $table->string('level', 5); // low | out
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 12)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable(); // stok bərpa olunanda avtomatik
            $table->timestamps();
            $table->index(['tenant_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
        Schema::dropIfExists('changelog_entries');
    }
};
