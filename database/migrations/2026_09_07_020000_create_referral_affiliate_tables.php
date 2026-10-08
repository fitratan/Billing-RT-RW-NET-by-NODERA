<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. referral_partners
        if (!Schema::hasTable('referral_partners')) {
            Schema::create('referral_partners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vpn_user_id')->constrained('vpn_users')->onDelete('cascade');
                $table->string('referral_code', 32)->unique();
                $table->string('name');
                $table->string('phone');
                $table->string('email');
                $table->string('promotion_channel')->nullable();
                $table->text('reason')->nullable();
                $table->decimal('commission_rate', 5, 2)->default(10.00); // persentase komisi, misal 10.00%
                $table->decimal('commission_balance', 14, 2)->default(0.00);
                $table->decimal('total_commission_earned', 14, 2)->default(0.00);
                $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('vpn_user_id');
            });
        }

        // 2. Add referred_by_partner_id to vpn_users
        if (Schema::hasTable('vpn_users')) {
            Schema::table('vpn_users', function (Blueprint $table) {
                if (!Schema::hasColumn('vpn_users', 'referred_by_partner_id')) {
                    $table->unsignedBigInteger('referred_by_partner_id')->nullable()->after('id');
                    $table->string('referral_code_used', 32)->nullable()->after('referred_by_partner_id');
                    $table->index('referred_by_partner_id');
                }
            });
        }

        // 3. referral_commissions
        if (!Schema::hasTable('referral_commissions')) {
            Schema::create('referral_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referral_partner_id')->constrained('referral_partners')->onDelete('cascade');
                $table->foreignId('vpn_user_id')->constrained('vpn_users')->onDelete('cascade');
                $table->unsignedBigInteger('vpn_topup_request_id')->nullable();
                $table->decimal('topup_amount', 14, 2);
                $table->decimal('commission_rate', 5, 2);
                $table->decimal('commission_amount', 14, 2);
                $table->string('status', 32)->default('credited'); // 'credited', 'cancelled'
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->index(['referral_partner_id', 'created_at']);
                $table->index('vpn_user_id');
                $table->index('vpn_topup_request_id');
            });
        }

        // 4. referral_withdrawals
        if (!Schema::hasTable('referral_withdrawals')) {
            Schema::create('referral_withdrawals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referral_partner_id')->constrained('referral_partners')->onDelete('cascade');
                $table->foreignId('vpn_user_id')->constrained('vpn_users')->onDelete('cascade');
                $table->string('type', 32); // 'convert_saldo', 'bank_withdrawal'
                $table->decimal('amount', 14, 2);
                $table->string('bank_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('account_name')->nullable();
                $table->string('status', 32)->default('pending'); // 'pending', 'approved', 'rejected', 'completed'
                $table->text('admin_notes')->nullable();
                $table->string('transfer_proof')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamps();

                $table->index(['referral_partner_id', 'created_at']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_withdrawals');
        Schema::dropIfExists('referral_commissions');
        if (Schema::hasTable('vpn_users') && Schema::hasColumn('vpn_users', 'referred_by_partner_id')) {
            Schema::table('vpn_users', function (Blueprint $table) {
                $table->dropColumn(['referred_by_partner_id', 'referral_code_used']);
            });
        }
        Schema::dropIfExists('referral_partners');
    }
};
