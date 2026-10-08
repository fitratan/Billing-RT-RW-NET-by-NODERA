<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Kategori Produk
        if (!Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('slug', 150)->unique();
                $table->string('icon', 50)->nullable()->default('fa-cube');
                $table->string('image', 255)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 2. Produk
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
                $table->string('name', 200);
                $table->string('slug', 200)->unique();
                $table->string('sku', 100)->nullable();
                $table->decimal('price', 15, 2);
                $table->decimal('original_price', 15, 2)->nullable();
                $table->integer('stock')->default(50);
                $table->string('badge', 50)->nullable(); // e.g. "TERLARIS", "PROMO", "HOT"
                $table->string('image', 255)->nullable();
                $table->json('gallery')->nullable();
                $table->text('short_description')->nullable();
                $table->longText('description')->nullable();
                $table->text('specifications')->nullable();
                $table->integer('weight_gram')->default(500);
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 3. Pesanan Toko (Shop Orders)
        if (!Schema::hasTable('shop_orders')) {
            Schema::create('shop_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 50)->unique();
                $table->string('customer_name', 150);
                $table->string('customer_phone', 50); // WhatsApp
                $table->string('customer_email', 150)->nullable();
                $table->text('shipping_address');
                $table->text('customer_notes')->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('shipping_fee', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2);
                $table->string('payment_method', 50)->default('Transfer Bank');
                $table->string('payment_bank', 100)->nullable();
                $table->string('payment_proof', 255)->nullable();
                $table->string('payment_status', 30)->default('unpaid'); // unpaid, paid, verified, rejected
                $table->string('order_status', 30)->default('pending'); // pending, processing, shipped, completed, cancelled
                $table->string('tracking_number', 100)->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        // 4. Detail Item Pesanan
        if (!Schema::hasTable('shop_order_items')) {
            Schema::create('shop_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('product_name', 200);
                $table->string('product_image', 255)->nullable();
                $table->decimal('price', 15, 2);
                $table->integer('quantity')->default(1);
                $table->decimal('subtotal', 15, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
