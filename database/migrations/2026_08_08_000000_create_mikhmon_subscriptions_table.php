<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikhmon_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_user_id')->nullable()->constrained('vpn_users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('subdomain')->unique()->comment('cth: mikhmon-username');
            $table->decimal('price', 12, 2)->default(10000)->comment('harga per bulan');
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['PENDING_APPROVAL', 'ACTIVE', 'EXPIRED', 'DISABLED'])->default('ACTIVE');
            $table->decimal('saldo_deducted', 12, 2)->default(0);
            $table->timestamp('order_date')->nullable();
            $table->timestamp('last_billed_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikhmon_subscriptions');
    }
};
