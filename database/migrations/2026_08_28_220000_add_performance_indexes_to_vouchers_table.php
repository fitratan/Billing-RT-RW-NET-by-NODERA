<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            try {
                $table->index(['tenant_id', 'router_id', 'batch_id'], 'vouchers_tenant_router_batch_idx');
            } catch (\Throwable $e) {}

            try {
                $table->index(['tenant_id', 'router_id', 'used'], 'vouchers_tenant_router_used_idx');
            } catch (\Throwable $e) {}

            try {
                $table->index(['tenant_id', 'username'], 'vouchers_tenant_username_idx');
            } catch (\Throwable $e) {}

            try {
                $table->index(['tenant_id', 'created_at'], 'vouchers_tenant_created_at_idx');
            } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            try {
                $table->dropIndex('vouchers_tenant_router_batch_idx');
                $table->dropIndex('vouchers_tenant_router_used_idx');
                $table->dropIndex('vouchers_tenant_username_idx');
                $table->dropIndex('vouchers_tenant_created_at_idx');
            } catch (\Throwable $e) {}
        });
    }
};
