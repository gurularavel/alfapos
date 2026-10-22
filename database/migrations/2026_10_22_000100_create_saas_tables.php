<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 10)->default('AZN');
            $table->string('interval', 10)->default('month'); // month | year
            $table->unsignedInteger('max_users')->nullable(); // null = limitsiz
            $table->unsignedInteger('max_menu_items')->nullable();
            $table->unsignedInteger('max_tables')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('trial'); // trial | active | expired | suspended
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            // Store settings
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('currency', 10)->default('₼');
            $table->string('timezone', 64)->default('Asia/Baku');
            $table->decimal('service_charge', 5, 2)->default(0); // faiz
            $table->decimal('daily_sales_goal', 12, 2)->default(0);
            $table->string('qr_code', 32)->unique();
            $table->boolean('is_qr_menu_enabled')->default(true);
            $table->boolean('is_qr_order_enabled')->default(false);
            $table->boolean('is_feedback_enabled')->default(true);
            $table->json('print_settings')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->string('gateway', 20)->default('manual');
            $table->string('reference')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('rate', 5, 2);
            $table->string('type', 10)->default('exclusive'); // inclusive | exclusive
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('image')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });

        Schema::create('menu_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('store_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('floor')->nullable();
            $table->unsignedSmallInteger('seating_capacity')->default(4);
            $table->string('qr_token', 32)->unique();
            $table->timestamps();
        });

        Schema::create('table_user', function (Blueprint $table) {
            $table->foreignId('store_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['store_table_id', 'user_id']);
        });

        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('icon', 30)->default('cash');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 30);
            $table->string('name');
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->boolean('is_member')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'phone']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('service_charge_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 20)->default('paid'); // paid | void
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('token_no');
            $table->string('delivery_type', 20)->default('dinein'); // dinein | takeaway | delivery
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 10)->default('pos'); // pos | qr
            $table->string('status', 20)->default('created'); // draft | created | preparing | ready | completed | cancelled
            $table->string('payment_status', 20)->default('pending'); // pending | paid
            $table->string('draft_reference')->nullable();
            $table->string('discount_type', 10)->nullable(); // fixed | percentage
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('menu_item_variants')->nullOnDelete();
            $table->string('title');
            $table->string('variant_title')->nullable();
            $table->json('addons')->nullable(); // [{id,title,price}]
            $table->decimal('price', 12, 2); // vahid qiymət (variant + addonlar)
            $table->unsignedInteger('quantity');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('tax_type', 10)->default('exclusive');
            $table->string('notes')->nullable();
            $table->string('status', 20)->default('created'); // created | preparing | ready | completed | cancelled
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('phone', 30)->nullable();
            $table->foreignId('store_table_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('reserved_at');
            $table->unsignedSmallInteger('people')->default(2);
            $table->string('status', 20)->default('booked'); // booked | arrived | cancelled
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('unit', 10)->default('pc'); // pc | g | kg | ml | l
            $table->decimal('quantity', 14, 4)->default(0);
            $table->decimal('min_quantity', 14, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // in | out | wastage | sale
            $table->decimal('quantity', 14, 4); // işarəli: + giriş, - çıxış
            $table->string('note')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('menu_item_variants')->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained('menu_item_addons')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->timestamps();
        });

        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'feedbacks', 'recipe_items', 'stock_movements', 'inventory_items', 'reservations',
            'order_items', 'orders', 'invoices', 'customers', 'payment_types', 'table_user', 'store_tables',
            'menu_item_addons', 'menu_item_variants', 'menu_items', 'taxes', 'categories', 'subscriptions',
            'tenants', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
