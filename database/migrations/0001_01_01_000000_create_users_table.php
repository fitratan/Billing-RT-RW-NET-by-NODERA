<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password', 255);
            $table->string('name', 100);
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->enum('role', ['superadmin','admin','agent','customer','technician'])->default('customer');
            $table->string('whatsapp_lid', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
