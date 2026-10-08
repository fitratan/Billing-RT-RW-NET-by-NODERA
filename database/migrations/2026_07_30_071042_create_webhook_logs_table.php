<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('webhook_logs')) {
            return;
        }

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50);
            $table->longText('payload')->nullable();
            $table->integer('response_code')->default(0);
            $table->text('response_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
