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
        if (!Schema::hasTable('wa_activity_logs')) {
            Schema::create('wa_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained('wa_merchants')->onDelete('cascade');
                $table->string('event', 50)->index();
                $table->string('category', 30)->default('general')->index();
                $table->text('description');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('status', 20)->default('info')->index(); // success, info, warning, error
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['merchant_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_activity_logs');
    }
};
