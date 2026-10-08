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
        if (!Schema::hasTable('whatsapp_devices')) {
            Schema::create('whatsapp_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('session_id', 100)->unique();
                $table->string('name', 150);
                $table->string('phone_number', 50)->nullable();
                $table->string('profile_name', 150)->nullable();
                $table->string('status', 50)->default('DISCONNECTED');
                $table->string('api_key', 255)->nullable();
                $table->string('webhook_url', 500)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamp('last_connected_at')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_devices');
    }
};
