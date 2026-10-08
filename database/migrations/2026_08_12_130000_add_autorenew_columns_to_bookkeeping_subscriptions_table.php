<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookkeeping_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('bookkeeping_subscriptions', 'last_billed_at')) {
                $table->timestamp('last_billed_at')->nullable()->after('saldo_deducted');
            }
            if (!Schema::hasColumn('bookkeeping_subscriptions', 'expired_grace_at')) {
                $table->timestamp('expired_grace_at')->nullable()->after('last_billed_at');
            }
            if (!Schema::hasColumn('bookkeeping_subscriptions', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('expired_grace_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookkeeping_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['last_billed_at', 'expired_grace_at', 'suspended_at']);
        });
    }
};
