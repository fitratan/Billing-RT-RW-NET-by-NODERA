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
        // 1. WhatsApp Gateway Merchants
        if (!Schema::hasTable('wa_merchants')) {
            Schema::create('wa_merchants', function (Blueprint $table) {
                $table->id();
                $table->string('merchant_code', 50)->unique();
                $table->string('name', 255);
                $table->string('owner_name', 255)->nullable();
                $table->string('email', 255)->unique();
                $table->string('phone', 50);
                $table->string('password');
                $table->string('api_key', 100)->unique();
                $table->string('secret_key', 100);
                $table->string('webhook_url', 500)->nullable();
                $table->text('ip_whitelist')->nullable();
                $table->string('plan_type', 50)->default('free'); // 'free', 'pro'
                $table->decimal('credit_balance', 14, 2)->default(0.00);
                $table->integer('device_limit')->default(1);
                $table->integer('quota_monthly')->default(500);
                $table->integer('quota_used_this_month')->default(0);
                $table->timestamp('quota_reset_at')->nullable();
                $table->timestamp('subscription_expires_at')->nullable();
                $table->string('status', 50)->default('active'); // 'active', 'suspended'
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // 2. Add merchant_id to whatsapp_devices if not present
        if (Schema::hasTable('whatsapp_devices')) {
            Schema::table('whatsapp_devices', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_devices', 'merchant_id')) {
                    $table->unsignedBigInteger('merchant_id')->nullable()->after('id')->index();
                    $table->foreign('merchant_id')->references('id')->on('wa_merchants')->onDelete('cascade');
                }
            });
        }

        // 3. WhatsApp Messages Log & Delivery Queue
        if (!Schema::hasTable('wa_messages')) {
            Schema::create('wa_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_id')->index();
                $table->unsignedBigInteger('device_id')->nullable()->index();
                $table->string('message_id', 150)->nullable()->index();
                $table->string('recipient', 50)->index();
                $table->string('message_type', 50)->default('text'); // 'text', 'image', 'document', 'button'
                $table->text('message_content');
                $table->text('media_url')->nullable();
                $table->string('status', 50)->default('pending')->index(); // 'pending', 'sent', 'delivered', 'read', 'failed'
                $table->text('error_message')->nullable();
                $table->decimal('cost', 8, 2)->default(0.00);
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->foreign('merchant_id')->references('id')->on('wa_merchants')->onDelete('cascade');
                $table->foreign('device_id')->references('id')->on('whatsapp_devices')->onDelete('set null');
            });
        }

        // 4. WhatsApp Billing & Invoices / Top Up
        if (!Schema::hasTable('wa_topups')) {
            Schema::create('wa_topups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('merchant_id')->index();
                $table->string('invoice_number', 100)->unique();
                $table->string('type', 50); // 'pro_subscription', 'device_slot', 'credit_topup'
                $table->decimal('amount', 14, 2);
                $table->decimal('admin_fee', 14, 2)->default(0.00);
                $table->decimal('total_amount', 14, 2);
                $table->string('payment_method', 50)->default('qris');
                $table->string('payment_status', 50)->default('pending')->index(); // 'pending', 'paid', 'expired', 'failed'
                $table->string('payment_reference', 150)->nullable();
                $table->text('qris_content')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('merchant_id')->references('id')->on('wa_merchants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_topups');
        Schema::dropIfExists('wa_messages');
        
        if (Schema::hasTable('whatsapp_devices') && Schema::hasColumn('whatsapp_devices', 'merchant_id')) {
            Schema::table('whatsapp_devices', function (Blueprint $table) {
                $table->dropForeign(['merchant_id']);
                $table->dropColumn('merchant_id');
            });
        }

        Schema::dropIfExists('wa_merchants');
    }
};
