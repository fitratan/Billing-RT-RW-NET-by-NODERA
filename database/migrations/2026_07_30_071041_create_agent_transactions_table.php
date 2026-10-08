<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_transactions')) {
            return;
        }

        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_transactions');
    }
};
