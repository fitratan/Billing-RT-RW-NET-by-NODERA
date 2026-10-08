<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('license_key', 64)->unique()->index();
            $table->string('product_name', 128)->default('Mikhmon Desktop by NODERA');
            $table->string('hwid', 255)->nullable()->index();
            $table->string('device_name', 255)->nullable();
            $table->string('os_info', 255)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('status', 32)->default('ACTIVE')->index(); // PENDING, ACTIVE, EXPIRED, SUSPENDED, REVOKED
            $table->integer('activation_count')->default(0);
            $table->integer('max_activations')->default(1);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('features')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_licenses');
    }
};
