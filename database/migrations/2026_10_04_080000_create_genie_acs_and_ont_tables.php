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
        if (!Schema::hasTable('acs_tenant_settings')) {
            Schema::create('acs_tenant_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->boolean('is_enabled')->default(true);
                $table->string('acs_username')->nullable();
                $table->string('acs_password')->nullable();
                $table->string('server_url')->default('http://127.0.0.1:7547');
                $table->string('billing_mode')->default('PAYG_DAILY'); // PAYG_DAILY, MONTHLY_TIER, FREE_TIER
                $table->decimal('daily_rate_per_ont', 10, 2)->default(33.33); // ~Rp 1.000 / month
                $table->integer('free_tier_quota')->default(10);
                $table->integer('active_ont_count')->default(0);
                $table->timestamp('last_billed_at')->nullable();
                $table->timestamps();

                $table->unique('tenant_id');
            });
        }

        if (!Schema::hasTable('ont_devices')) {
            Schema::create('ont_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('serial_number', 100)->unique()->index();
                $table->string('manufacturer', 100)->nullable(); // ZTE, Huawei, Fiberhome, VSOL, etc.
                $table->string('model_name', 100)->nullable();
                $table->string('hardware_version', 100)->nullable();
                $table->string('software_version', 100)->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('mac_address', 64)->nullable();
                $table->decimal('rx_power', 8, 2)->nullable(); // dBm
                $table->decimal('tx_power', 8, 2)->nullable(); // dBm
                $table->decimal('optical_voltage', 8, 2)->nullable(); // Volts
                $table->decimal('optical_temp', 8, 2)->nullable(); // Celsius
                $table->string('wifi_ssid', 100)->nullable();
                $table->string('wifi_password', 100)->nullable();
                $table->integer('wifi_channel')->nullable();
                $table->boolean('wifi_enabled')->default(true);
                $table->integer('connected_devices_count')->default(0);
                $table->string('status', 30)->default('ONLINE'); // ONLINE, OFFLINE, WARNING, CRITICAL
                $table->timestamp('last_inform_at')->nullable();
                $table->timestamp('registered_at')->nullable();
                $table->json('raw_parameters')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('acs_usage_logs')) {
            Schema::create('acs_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->date('usage_date')->index();
                $table->integer('total_ont_count')->default(0);
                $table->integer('billable_ont_count')->default(0);
                $table->decimal('rate_applied', 10, 2)->default(33.33);
                $table->decimal('amount_deducted', 12, 2)->default(0);
                $table->string('status', 30)->default('SETTLED'); // SETTLED, INSUFFICIENT_BALANCE, FREE_TIER
                $table->timestamps();

                $table->unique(['tenant_id', 'usage_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_usage_logs');
        Schema::dropIfExists('ont_devices');
        Schema::dropIfExists('acs_tenant_settings');
    }
};