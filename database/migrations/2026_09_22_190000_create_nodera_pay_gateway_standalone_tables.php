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
        if (!Schema::hasTable('nodera_pay_merchants')) {
            Schema::create('nodera_pay_merchants', function (Blueprint $table) {
                $table->id();
                $table->string('merchant_code', 32)->unique()->comment('Contoh: NP-92A8BC');
                $table->string('name')->comment('Nama Usaha / Toko');
                $table->string('owner_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone', 32)->comment('Nomor WhatsApp');
                $table->string('password');
                $table->string('api_key', 64)->unique();
                $table->string('secret_key', 64);
                $table->string('webhook_url')->nullable();
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->decimal('total_income', 15, 2)->default(0.00);
                $table->decimal('total_withdrawn', 15, 2)->default(0.00);
                
                // Settlement Bank Info
                $table->string('bank_name', 50)->nullable();
                $table->string('bank_account_number', 50)->nullable();
                $table->string('bank_account_name')->nullable();
                
                // Merchant Custom WhatsApp Gateway (Optional for their own customer notifications)
                $table->string('wa_gateway_type', 32)->nullable()->default('none');
                $table->text('wa_gateway_token')->nullable();
                $table->boolean('wa_notify_on_payment')->default(false);
                
                $table->enum('status', ['active', 'suspended'])->default('active');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodera_pay_merchant_transactions')) {
            Schema::create('nodera_pay_merchant_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('nodera_pay_merchants')->cascadeOnDelete();
                $table->string('trx_reference', 64)->unique()->comment('Contoh: NPTRX-982173');
                $table->string('ref_id', 128)->index()->comment('Order ID dari sistem external/Mikhmon');
                $table->string('payment_method', 32)->default('qris');
                $table->decimal('gross_amount', 15, 2);
                $table->decimal('midtrans_fee', 15, 2)->default(0.00);
                $table->decimal('admin_fee', 15, 2)->default(0.00);
                $table->decimal('total_fee', 15, 2)->default(0.00);
                $table->decimal('net_amount', 15, 2);
                
                // Customer details
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_phone', 32)->nullable();
                
                // Payment payload details
                $table->text('qr_string')->nullable();
                $table->text('qr_image_url')->nullable();
                $table->string('va_number', 64)->nullable();
                $table->string('va_bank', 32)->nullable();
                $table->string('snap_token')->nullable();
                $table->text('snap_redirect_url')->nullable();
                
                $table->enum('status', ['pending', 'paid', 'expired', 'failed'])->default('pending')->index();
                $table->string('midtrans_transaction_id', 128)->nullable()->index();
                $table->json('midtrans_response')->nullable();
                
                // Merchant Webhook Dispatch Status
                $table->text('callback_url')->nullable();
                $table->enum('callback_status', ['pending', 'success', 'failed'])->default('pending');
                $table->integer('callback_attempts')->default(0);
                $table->text('callback_response')->nullable();
                
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodera_pay_merchant_withdrawals')) {
            Schema::create('nodera_pay_merchant_withdrawals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('nodera_pay_merchants')->cascadeOnDelete();
                $table->string('withdrawal_code', 64)->unique()->comment('Contoh: NPWD-71829');
                $table->decimal('amount', 15, 2);
                $table->decimal('fee', 15, 2)->default(0.00);
                $table->decimal('net_amount', 15, 2);
                $table->string('bank_name', 50);
                $table->string('bank_account_number', 50);
                $table->string('bank_account_name');
                $table->string('proof_image')->nullable();
                $table->enum('status', ['pending', 'processing', 'completed', 'rejected'])->default('pending')->index();
                $table->text('admin_notes')->nullable();
                $table->timestamp('requested_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodera_pay_merchant_withdrawals');
        Schema::dropIfExists('nodera_pay_merchant_transactions');
        Schema::dropIfExists('nodera_pay_merchants');
    }
};
