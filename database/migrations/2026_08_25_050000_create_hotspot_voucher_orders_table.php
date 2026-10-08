<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hotspot_voucher_orders')) {
            Schema::create('hotspot_voucher_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 64)->unique();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('router_id')->nullable()->constrained('mikrotiks')->nullOnDelete();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->string('package_name', 150);
                $table->string('customer_name', 150)->nullable();
                $table->string('customer_phone', 50);
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('unique_code', 8, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('payment_method', 50)->default('qris');
                $table->string('payment_channel', 50)->nullable();
                $table->string('payment_status', 30)->default('unpaid'); // unpaid, paid, cancelled, expired
                $table->string('gateway_reference', 150)->nullable();
                $table->text('gateway_checkout_url')->nullable();
                $table->json('gateway_payload')->nullable();
                $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
                $table->string('voucher_code', 64)->nullable();
                $table->string('voucher_password', 64)->nullable();
                $table->string('voucher_profile', 100)->nullable();
                $table->string('voucher_timelimit', 50)->nullable();
                $table->string('voucher_datalimit', 50)->nullable();
                $table->text('connect_url')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'payment_status']);
                $table->index(['customer_phone']);
                $table->index(['voucher_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_voucher_orders');
    }
};
