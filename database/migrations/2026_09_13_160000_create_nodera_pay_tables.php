<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodera_pay_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_user_id')->constrained('vpn_users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->enum('package_type', ['standalone', 'bundle'])->default('standalone');
            $table->foreignId('mikhmon_subscription_id')->nullable()->constrained('mikhmon_subscriptions')->nullOnDelete();
            $table->foreignId('vpn_account_id')->nullable()->constrained('vpn_accounts')->nullOnDelete();
            $table->string('name')->comment('Label/Nama instance NODERA Pay');
            $table->string('qris_image_path')->nullable();
            $table->text('qris_raw_string')->nullable();
            $table->string('merchant_name')->nullable();
            $table->string('merchant_city')->nullable();
            $table->string('nmid')->nullable();
            $table->string('webhook_url')->nullable()->comment('URL callback Mikhmon / external web');
            $table->string('api_key', 64)->unique();
            $table->string('secret_key', 64);
            $table->decimal('price', 12, 2)->default(20000)->comment('Harga sewa per bulan');
            $table->enum('status', ['ACTIVE', 'EXPIRED', 'DISABLED'])->default('ACTIVE');
            $table->timestamp('expires_at')->nullable();
            $table->decimal('saldo_deducted', 12, 2)->default(0);
            $table->timestamp('order_date')->nullable();
            $table->timestamp('last_billed_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('nodera_pay_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('nodera_pay_subscriptions')->cascadeOnDelete();
            $table->string('order_id')->index()->comment('ID transaksi dari sistem client');
            $table->decimal('amount', 12, 2);
            $table->integer('unique_code')->default(0);
            $table->decimal('total_amount', 12, 2)->index();
            $table->text('dynamic_qris_string');
            $table->enum('status', ['pending', 'paid', 'expired'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('webhook_status')->nullable();
            $table->integer('webhook_response_code')->nullable();
            $table->text('raw_notification')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodera_pay_transactions');
        Schema::dropIfExists('nodera_pay_subscriptions');
    }
};
