<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. shop_orders
        if (Schema::hasTable('shop_orders')) {
            Schema::table('shop_orders', function (Blueprint $table) {
                if (!Schema::hasIndex('shop_orders', 'shop_orders_tenant_status_idx')) {
                    $table->index(['tenant_id', 'order_status'], 'shop_orders_tenant_status_idx');
                }
                if (!Schema::hasIndex('shop_orders', 'shop_orders_tenant_payment_idx')) {
                    $table->index(['tenant_id', 'payment_status'], 'shop_orders_tenant_payment_idx');
                }
            });
        }

        // 2. payment_gateway_settings
        if (Schema::hasTable('payment_gateway_settings')) {
            Schema::table('payment_gateway_settings', function (Blueprint $table) {
                if (!Schema::hasIndex('payment_gateway_settings', 'pg_settings_tenant_gw_idx')) {
                    $table->index(['tenant_id', 'gateway'], 'pg_settings_tenant_gw_idx');
                }
            });
        }

        // 3. packages
        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table) {
                if (!Schema::hasIndex('packages', 'packages_tenant_active_idx')) {
                    $table->index(['tenant_id', 'is_active'], 'packages_tenant_active_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shop_orders')) {
            Schema::table('shop_orders', function (Blueprint $table) {
                if (Schema::hasIndex('shop_orders', 'shop_orders_tenant_status_idx')) {
                    $table->dropIndex('shop_orders_tenant_status_idx');
                }
                if (Schema::hasIndex('shop_orders', 'shop_orders_tenant_payment_idx')) {
                    $table->dropIndex('shop_orders_tenant_payment_idx');
                }
            });
        }

        if (Schema::hasTable('payment_gateway_settings')) {
            Schema::table('payment_gateway_settings', function (Blueprint $table) {
                if (Schema::hasIndex('payment_gateway_settings', 'pg_settings_tenant_gw_idx')) {
                    $table->dropIndex('pg_settings_tenant_gw_idx');
                }
            });
        }

        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table) {
                if (Schema::hasIndex('packages', 'packages_tenant_active_idx')) {
                    $table->dropIndex('packages_tenant_active_idx');
                }
            });
        }
    }
};
