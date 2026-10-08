<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Add tenant_id & voucher_package_id to products
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete()->after('id');
                }
                if (!Schema::hasColumn('products', 'product_type')) {
                    $table->string('product_type', 30)->default('general')->after('sku'); // 'general' | 'voucher'
                }
                if (!Schema::hasColumn('products', 'voucher_package_id')) {
                    $table->foreignId('voucher_package_id')->nullable()->constrained('packages')->nullOnDelete()->after('product_type');
                }
            });
        }

        // 2. Add tenant_id & voucher details to shop_orders
        if (Schema::hasTable('shop_orders')) {
            Schema::table('shop_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('shop_orders', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete()->after('id');
                }
                if (!Schema::hasColumn('shop_orders', 'voucher_username')) {
                    $table->string('voucher_username', 100)->nullable()->after('customer_notes');
                }
                if (!Schema::hasColumn('shop_orders', 'voucher_password')) {
                    $table->string('voucher_password', 100)->nullable()->after('voucher_username');
                }
                if (!Schema::hasColumn('shop_orders', 'voucher_profile')) {
                    $table->string('voucher_profile', 100)->nullable()->after('voucher_password');
                }
                if (!Schema::hasColumn('shop_orders', 'voucher_created_in_mikrotik')) {
                    $table->boolean('voucher_created_in_mikrotik')->default(false)->after('voucher_profile');
                }
                if (!Schema::hasColumn('shop_orders', 'telegram_message_id')) {
                    $table->string('telegram_message_id', 100)->nullable()->after('voucher_created_in_mikrotik');
                }
                if (!Schema::hasColumn('shop_orders', 'payment_channel_id')) {
                    $table->unsignedBigInteger('payment_channel_id')->nullable()->after('payment_bank');
                }
            });
        }

        // 3. Add tenant_id to product_categories
        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('product_categories', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete()->after('id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table) {
                if (Schema::hasColumn('product_categories', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
            });
        }

        if (Schema::hasTable('shop_orders')) {
            Schema::table('shop_orders', function (Blueprint $table) {
                if (Schema::hasColumn('shop_orders', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                $table->dropColumn([
                    'voucher_username',
                    'voucher_password',
                    'voucher_profile',
                    'voucher_created_in_mikrotik',
                    'telegram_message_id',
                    'payment_channel_id',
                ]);
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'voucher_package_id')) {
                    $table->dropConstrainedForeignId('voucher_package_id');
                }
                if (Schema::hasColumn('products', 'tenant_id')) {
                    $table->dropConstrainedForeignId('tenant_id');
                }
                $table->dropColumn(['product_type']);
            });
        }
    }
};
