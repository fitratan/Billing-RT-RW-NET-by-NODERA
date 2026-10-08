<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('level', 20)->default('error');
            $table->string('message', 1000);
            $table->text('stack')->nullable();
            $table->string('source', 255)->nullable();
            $table->string('role', 50)->nullable()->index();
            $table->string('route', 255)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_logs');
    }
};
