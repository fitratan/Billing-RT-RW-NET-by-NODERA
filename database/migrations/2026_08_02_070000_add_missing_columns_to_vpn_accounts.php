<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_accounts', 'vpn_user_id')) {
                $table->foreignId('vpn_user_id')->nullable()->after('id')->constrained('vpn_users')->nullOnDelete();
            }
            if (!Schema::hasColumn('vpn_accounts', 'saldo_deducted')) {
                $table->decimal('saldo_deducted', 15, 2)->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('vpn_accounts', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)->after('saldo_deducted');
            }
            if (!Schema::hasColumn('vpn_accounts', 'order_date')) {
                $table->timestamp('order_date')->nullable()->after('auto_renew');
            }
            if (!Schema::hasColumn('vpn_accounts', 'last_billed_at')) {
                $table->timestamp('last_billed_at')->nullable()->after('order_date');
            }
            if (!Schema::hasColumn('vpn_accounts', 'expired_grace_at')) {
                $table->timestamp('expired_grace_at')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('vpn_accounts', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('expired_grace_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            foreach (['vpn_user_id', 'saldo_deducted', 'auto_renew', 'order_date', 'last_billed_at', 'expired_grace_at', 'suspended_at'] as $column) {
                if (Schema::hasColumn('vpn_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
