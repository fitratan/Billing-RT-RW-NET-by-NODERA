<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isp_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 64)->unique()->index();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('domain')->nullable()->index();
            $table->string('server_ip')->nullable();
            $table->string('hardware_id')->nullable()->index();
            $table->string('package_type')->default('STANDALONE_ISP');
            $table->unsignedInteger('max_customers')->default(0); // 0 = unlimited
            $table->unsignedInteger('max_routers')->default(0);   // 0 = unlimited
            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'REVOKED', 'EXPIRED'])->default('ACTIVE')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('last_heartbeat_ip')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isp_licenses');
    }
};
