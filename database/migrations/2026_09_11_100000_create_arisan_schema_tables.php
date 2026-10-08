<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('arisan_subscriptions')) {
            Schema::create('arisan_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vpn_user_id')->constrained('vpn_users')->onDelete('cascade');
                $table->string('subdomain')->unique();
                $table->string('business_name');
                $table->decimal('price', 12, 2)->default(10000.00);
                $table->timestamp('order_date')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('status')->default('ACTIVE');
                $table->boolean('auto_renew')->default(false);
                $table->decimal('saldo_deducted', 12, 2)->default(10000.00);
                $table->text('admin_password_hash')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('arisan_groups')) {
            Schema::create('arisan_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained('arisan_subscriptions')->onDelete('cascade');
                $table->string('name');
                $table->string('period_type')->default('MONTHLY');
                $table->decimal('dues_amount', 12, 2)->default(0);
                $table->integer('total_slots')->default(10);
                $table->decimal('admin_fee_per_period', 12, 2)->default(0);
                $table->date('start_date')->nullable();
                $table->integer('due_day')->default(10);
                $table->integer('draw_day')->default(15);
                $table->string('status')->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('arisan_members')) {
            Schema::create('arisan_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained('arisan_subscriptions')->onDelete('cascade');
                $table->string('name');
                $table->string('phone_number');
                $table->string('pin_hash');
                $table->string('magic_token', 64)->unique()->nullable();
                $table->text('address_notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('arisan_group_members')) {
            Schema::create('arisan_group_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('arisan_groups')->onDelete('cascade');
                $table->foreignId('member_id')->constrained('arisan_members')->onDelete('cascade');
                $table->integer('slot_number');
                $table->boolean('has_won')->default(false);
                $table->unsignedBigInteger('won_period_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('arisan_periods')) {
            Schema::create('arisan_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('arisan_groups')->onDelete('cascade');
                $table->integer('period_number');
                $table->date('period_date');
                $table->date('due_date')->nullable();
                $table->date('draw_date')->nullable();
                $table->string('status')->default('COLLECTING');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('arisan_payments')) {
            Schema::create('arisan_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('period_id')->constrained('arisan_periods')->onDelete('cascade');
                $table->foreignId('group_member_id')->constrained('arisan_group_members')->onDelete('cascade');
                $table->decimal('amount', 12, 2);
                $table->string('status')->default('UNPAID');
                $table->timestamp('payment_date')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('proof_image')->nullable();
                $table->timestamp('verified_by_admin_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('arisan_draws')) {
            Schema::create('arisan_draws', function (Blueprint $table) {
                $table->id();
                $table->foreignId('period_id')->constrained('arisan_periods')->onDelete('cascade');
                $table->foreignId('winning_group_member_id')->constrained('arisan_group_members')->onDelete('cascade');
                $table->decimal('prize_amount', 12, 2);
                $table->timestamp('draw_timestamp');
                $table->string('draw_seed_hash')->nullable();
                $table->string('disbursement_status')->default('PENDING');
                $table->string('disbursement_proof')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('arisan_cashflows')) {
            Schema::create('arisan_cashflows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained('arisan_subscriptions')->onDelete('cascade');
                $table->foreignId('group_id')->nullable()->constrained('arisan_groups')->onDelete('set null');
                $table->string('type'); // IN, OUT
                $table->string('category'); // IURAN, PENCAIRAN_PEMENANG, BIAYA_ADMIN, KAS_DARURAT, LAINNYA
                $table->decimal('amount', 12, 2);
                $table->date('transaction_date');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('arisan_payment_settings')) {
            Schema::create('arisan_payment_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained('arisan_subscriptions')->onDelete('cascade');
                $table->string('bank_name');
                $table->string('account_number');
                $table->string('account_holder');
                $table->string('qris_image_path')->nullable();
                $table->text('instructions')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arisan_payment_settings');
        Schema::dropIfExists('arisan_cashflows');
        Schema::dropIfExists('arisan_draws');
        Schema::dropIfExists('arisan_payments');
        Schema::dropIfExists('arisan_periods');
        Schema::dropIfExists('arisan_group_members');
        Schema::dropIfExists('arisan_members');
        Schema::dropIfExists('arisan_groups');
        Schema::dropIfExists('arisan_subscriptions');
    }
};
