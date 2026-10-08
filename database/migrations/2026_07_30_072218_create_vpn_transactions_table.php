<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->decimal('amount', 15, 2);
            $table->decimal('saldo_before', 15, 2);
            $table->decimal('saldo_after', 15, 2);
            $table->text('description')->nullable();
            $table->string('reference', 100)->nullable();
            $table->timestamps();

            $table->index(['vpn_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_transactions');
    }
};
