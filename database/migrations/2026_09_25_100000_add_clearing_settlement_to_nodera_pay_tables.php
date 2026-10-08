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
                if (!Schema::hasColumn('nodera_pay_merchants', 'clearing_balance')) {
                    $table->decimal('clearing_balance', 15, 2)->default(0.00)->after('balance')->comment('Saldo kliring tertahan T+1');
                }
            });
        }

        if (Schema::hasTable('nodera_pay_merchant_transactions')) {
            Schema::table('nodera_pay_merchant_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('nodera_pay_merchant_transactions', 'settlement_status')) {
                    $table->enum('settlement_status', ['clearing', 'settled'])->default('settled')->index()->after('status')->comment('Status kliring dana merchant');
                }
                if (!Schema::hasColumn('nodera_pay_merchant_transactions', 'settlement_due_at')) {
                    $table->timestamp('settlement_due_at')->nullable()->index()->after('settlement_status')->comment('Jadwal rilis kliring ke saldo');
                }
                if (!Schema::hasColumn('nodera_pay_merchant_transactions', 'settled_at')) {
                    $table->timestamp('settled_at')->nullable()->after('settlement_due_at')->comment('Waktu dana masuk saldo tersedia');
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
                if (Schema::hasColumn('nodera_pay_merchants', 'clearing_balance')) {
                    $table->dropColumn('clearing_balance');
                }
            });
        }

        if (Schema::hasTable('nodera_pay_merchant_transactions')) {
            Schema::table('nodera_pay_merchant_transactions', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('nodera_pay_merchant_transactions', 'settlement_status')) $columns[] = 'settlement_status';
                if (Schema::hasColumn('nodera_pay_merchant_transactions', 'settlement_due_at')) $columns[] = 'settlement_due_at';
                if (Schema::hasColumn('nodera_pay_merchant_transactions', 'settled_at')) $columns[] = 'settled_at';
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
