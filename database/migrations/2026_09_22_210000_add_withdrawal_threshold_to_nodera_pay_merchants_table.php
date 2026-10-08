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
        Schema::table('nodera_pay_merchants', function (Blueprint $table) {
            if (!Schema::hasColumn('nodera_pay_merchants', 'min_withdrawal_threshold')) {
                $table->decimal('min_withdrawal_threshold', 15, 2)->default(50000.00)->after('balance');
            }
            if (!Schema::hasColumn('nodera_pay_merchants', 'auto_withdrawal_enabled')) {
                $table->boolean('auto_withdrawal_enabled')->default(false)->after('min_withdrawal_threshold');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nodera_pay_merchants', function (Blueprint $table) {
            if (Schema::hasColumn('nodera_pay_merchants', 'min_withdrawal_threshold')) {
                $table->dropColumn('min_withdrawal_threshold');
            }
            if (Schema::hasColumn('nodera_pay_merchants', 'auto_withdrawal_enabled')) {
                $table->dropColumn('auto_withdrawal_enabled');
            }
        });
    }
};
