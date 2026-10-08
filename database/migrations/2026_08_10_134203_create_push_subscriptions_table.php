<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            // Siapa pemilik subscription
            $table->string('subscriber_type'); // 'customer', 'collector', 'technician', 'admin', 'user'
            $table->unsignedBigInteger('subscriber_id')->nullable();
            // Data subscription Web Push (JSON dari browser)
            $table->text('endpoint')->unique();
            $table->text('public_key')->nullable();
            $table->text('auth_token')->nullable();
            $table->text('raw_subscription')->nullable(); // JSON mentah dari browser
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subscriber_type', 'subscriber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
