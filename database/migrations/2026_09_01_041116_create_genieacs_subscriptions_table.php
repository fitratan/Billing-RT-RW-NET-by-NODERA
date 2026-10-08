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
        if (Schema::hasTable('genieacs_subscriptions')) {
            return;
        }

        Schema::create('genieacs_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_user_id')->nullable()->constrained('vpn_users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('name')->comment('Nama Instance / Nama ISP');
            $table->string('instance_code')->unique()->comment('Kode unik instance, misal acs-ispjaya-821');
            $table->string('username')->default('admin');
            $table->string('password')->nullable();
            $table->string('cwmp_url')->nullable()->comment('URL CWMP untuk ONT (Port 7547)');
            $table->string('nbi_url')->nullable()->comment('URL NBI API untuk Billing (Port 7557)');
            $table->string('ui_url')->nullable()->comment('URL Web GUI GenieACS (Port 3000)');
            $table->decimal('price', 12, 2)->default(25000)->comment('Harga langganan per bulan');
            $table->decimal('saldo_deducted', 12, 2)->default(0);
            $table->enum('status', ['PENDING', 'ACTIVE', 'EXPIRED', 'SUSPENDED'])->default('ACTIVE');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('order_date')->nullable();
            $table->timestamp('last_billed_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('genieacs_subscriptions');
    }
};
