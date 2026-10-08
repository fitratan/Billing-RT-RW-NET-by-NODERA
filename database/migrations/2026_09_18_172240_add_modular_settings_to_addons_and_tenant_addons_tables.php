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
        if (Schema::hasTable('addons')) {
            Schema::table('addons', function (Blueprint $table) {
                if (!Schema::hasColumn('addons', 'billing_cycle')) {
                    $table->string('billing_cycle', 30)->default('monthly')->after('price'); // monthly, lifetime, yearly
                }
                if (!Schema::hasColumn('addons', 'icon')) {
                    $table->string('icon', 50)->nullable()->after('billing_cycle');
                }
                if (!Schema::hasColumn('addons', 'route_name')) {
                    $table->string('route_name', 100)->nullable()->after('icon');
                }
                if (!Schema::hasColumn('addons', 'feature_key')) {
                    $table->string('feature_key', 100)->nullable()->after('route_name');
                }
            });
        }

        if (Schema::hasTable('tenant_addons')) {
            Schema::table('tenant_addons', function (Blueprint $table) {
                if (!Schema::hasColumn('tenant_addons', 'auto_renew')) {
                    $table->boolean('auto_renew')->default(true)->after('config');
                }
                if (!Schema::hasColumn('tenant_addons', 'last_billed_at')) {
                    $table->dateTime('last_billed_at')->nullable()->after('paid_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('addons')) {
            Schema::table('addons', function (Blueprint $table) {
                $columns = ['billing_cycle', 'icon', 'route_name', 'feature_key'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('addons', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('tenant_addons')) {
            Schema::table('tenant_addons', function (Blueprint $table) {
                $columns = ['auto_renew', 'last_billed_at'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('tenant_addons', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
