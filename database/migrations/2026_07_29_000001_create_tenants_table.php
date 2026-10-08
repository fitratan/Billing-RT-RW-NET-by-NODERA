<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prevent duplicate table creation — this migration is superseded by 005002
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
