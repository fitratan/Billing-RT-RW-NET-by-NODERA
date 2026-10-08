<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                // Drop old single-column unique on gateway if exists
                $indexes = DB::select("SHOW INDEXES FROM payment_gateway_settings WHERE Key_name = 'payment_gateway_settings_gateway_unique'");
                if (!empty($indexes)) {
                    $table->dropUnique('payment_gateway_settings_gateway_unique');
                }

                // Create composite unique index for multi-tenant support
                $compositeIndexes = DB::select("SHOW INDEXES FROM payment_gateway_settings WHERE Key_name = 'pg_settings_gateway_tenant_unique'");
                if (empty($compositeIndexes)) {
                    $table->unique(['gateway', 'tenant_id'], 'pg_settings_gateway_tenant_unique');
                }
            } else {
                try {
                    $table->unique(['gateway', 'tenant_id'], 'pg_settings_gateway_tenant_unique');
                } catch (\Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $compositeIndexes = DB::select("SHOW INDEXES FROM payment_gateway_settings WHERE Key_name = 'pg_settings_gateway_tenant_unique'");
                if (!empty($compositeIndexes)) {
                    $table->dropUnique('pg_settings_gateway_tenant_unique');
                }
            }
        });
    }
};
