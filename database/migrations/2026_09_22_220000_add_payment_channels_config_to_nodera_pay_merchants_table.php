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
        if (Schema::hasTable('nodera_pay_merchants')) {
            Schema::table('nodera_pay_merchants', function (Blueprint $table) {
                if (!Schema::hasColumn('nodera_pay_merchants', 'payment_channels_config')) {
                    $table->json('payment_channels_config')->nullable()->after('wa_notify_on_payment');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('nodera_pay_merchants')) {
            Schema::table('nodera_pay_merchants', function (Blueprint $table) {
                if (Schema::hasColumn('nodera_pay_merchants', 'payment_channels_config')) {
                    $table->dropColumn('payment_channels_config');
                }
            });
        }
    }
};
