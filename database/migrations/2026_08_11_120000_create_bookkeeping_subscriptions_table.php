<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bookkeeping_subscriptions')) {
            Schema::create('bookkeeping_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vpn_user_id')->constrained('vpn_users')->onDelete('cascade');
                $table->foreignId('tenant_id')->nullable();
                $table->string('subdomain')->unique();
                $table->string('business_name');
                $table->decimal('price', 12, 2)->default(10000.00);
                $table->timestamp('order_date')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('status')->default('ACTIVE'); // ACTIVE, EXPIRED, SUSPENDED
                $table->boolean('auto_renew')->default(false);
                $table->decimal('saldo_deducted', 12, 2)->default(10000.00);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookkeeping_subscriptions');
    }
};
