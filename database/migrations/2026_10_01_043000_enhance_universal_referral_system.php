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
        // 1. referral_partners
        if (Schema::hasTable('referral_partners')) {
            Schema::table('referral_partners', function (Blueprint $table) {
                if (!Schema::hasColumn('referral_partners', 'bank_name')) {
                    $table->string('bank_name', 100)->nullable()->after('reason');
                }
                if (!Schema::hasColumn('referral_partners', 'bank_account_number')) {
                    $table->string('bank_account_number', 100)->nullable()->after('bank_name');
                }
                if (!Schema::hasColumn('referral_partners', 'bank_account_holder')) {
                    $table->string('bank_account_holder', 150)->nullable()->after('bank_account_number');
                }
                if (!Schema::hasColumn('referral_partners', 'bank_account_status')) {
                    $table->string('bank_account_status', 32)->default('unconfigured')->after('bank_account_holder');
                }
                if (!Schema::hasColumn('referral_partners', 'bank_account_verified_at')) {
                    $table->timestamp('bank_account_verified_at')->nullable()->after('bank_account_status');
                }
                if (!Schema::hasColumn('referral_partners', 'bank_account_verified_by')) {
                    $table->unsignedBigInteger('bank_account_verified_by')->nullable()->after('bank_account_verified_at');
                }
                if (!Schema::hasColumn('referral_partners', 'bank_account_rejection_reason')) {
                    $table->text('bank_account_rejection_reason')->nullable()->after('bank_account_verified_by');
                }
                if (!Schema::hasColumn('referral_partners', 'min_withdrawal_threshold')) {
                    $table->decimal('min_withdrawal_threshold', 14, 2)->default(50000.00)->after('commission_rate');
                }
            });
        }

        // 2. wa_merchants
        if (Schema::hasTable('wa_merchants')) {
            Schema::table('wa_merchants', function (Blueprint $table) {
                if (!Schema::hasColumn('wa_merchants', 'referred_by_partner_id')) {
                    $table->unsignedBigInteger('referred_by_partner_id')->nullable()->after('status');
                }
                if (!Schema::hasColumn('wa_merchants', 'referral_code_used')) {
                    $table->string('referral_code_used', 32)->nullable()->after('referred_by_partner_id');
                }
            });
        }

        // 3. nodera_pay_merchants
        if (Schema::hasTable('nodera_pay_merchants')) {
            Schema::table('nodera_pay_merchants', function (Blueprint $table) {
                if (!Schema::hasColumn('nodera_pay_merchants', 'referred_by_partner_id')) {
                    $table->unsignedBigInteger('referred_by_partner_id')->nullable()->after('status');
                }
                if (!Schema::hasColumn('nodera_pay_merchants', 'referral_code_used')) {
                    $table->string('referral_code_used', 32)->nullable()->after('referred_by_partner_id');
                }
            });
        }

        // 4. referral_commissions
        if (Schema::hasTable('referral_commissions')) {
            Schema::table('referral_commissions', function (Blueprint $table) {
                if (!Schema::hasColumn('referral_commissions', 'source_platform')) {
                    $table->string('source_platform', 32)->default('vpn')->after('commission_amount');
                }
                if (!Schema::hasColumn('referral_commissions', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->after('source_platform');
                }
                if (!Schema::hasColumn('referral_commissions', 'source_invoice')) {
                    $table->string('source_invoice', 100)->nullable()->after('source_id');
                }
                if (!Schema::hasColumn('referral_commissions', 'wa_merchant_id')) {
                    $table->unsignedBigInteger('wa_merchant_id')->nullable()->after('vpn_topup_request_id');
                }
                if (!Schema::hasColumn('referral_commissions', 'nodera_pay_merchant_id')) {
                    $table->unsignedBigInteger('nodera_pay_merchant_id')->nullable()->after('wa_merchant_id');
                }
                if (!Schema::hasColumn('referral_commissions', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('nodera_pay_merchant_id');
                }
            });
        }

        // 5. referral_withdrawals
        if (Schema::hasTable('referral_withdrawals')) {
            Schema::table('referral_withdrawals', function (Blueprint $table) {
                if (!Schema::hasColumn('referral_withdrawals', 'fee')) {
                    $table->decimal('fee', 14, 2)->default(0.00)->after('amount');
                }
                if (!Schema::hasColumn('referral_withdrawals', 'net_amount')) {
                    $table->decimal('net_amount', 14, 2)->default(0.00)->after('fee');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse operations safely
    }
};
