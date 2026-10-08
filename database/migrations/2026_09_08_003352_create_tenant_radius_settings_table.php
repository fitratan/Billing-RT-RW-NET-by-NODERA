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
        if (Schema::hasTable('tenant_radius_settings')) {
            return;
        }

        Schema::create('tenant_radius_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->unique();
            $table->string('radius_mode', 32)->default('disabled'); // disabled, local_db, remote_db, userman_v7
            $table->boolean('is_active')->default(false);

            // Remote FreeRADIUS Database
            $table->string('remote_db_driver', 16)->default('mysql');
            $table->string('remote_db_host')->nullable();
            $table->integer('remote_db_port')->default(3306);
            $table->string('remote_db_name')->default('radius');
            $table->string('remote_db_user')->nullable();
            $table->text('remote_db_pass')->nullable();

            // NAS Router & RFC 3576 CoA / PoD Disconnect
            $table->string('nas_ip')->nullable();
            $table->text('nas_secret')->nullable();
            $table->integer('coa_port')->default(3799);

            // MikroTik RouterOS v7 User Manager
            $table->string('userman_host')->nullable();
            $table->integer('userman_port')->default(8728);
            $table->string('userman_user')->nullable();
            $table->text('userman_pass')->nullable();

            // Automations
            $table->boolean('auto_sync_on_create')->default(true);
            $table->boolean('auto_coa_on_isolate')->default(true);

            // Status & Diagnostics
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->string('last_test_status', 16)->nullable();
            $table->text('last_test_message')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_radius_settings');
    }
};
