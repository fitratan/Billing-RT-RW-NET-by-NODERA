<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove the earlier unusable stub table if it exists.
        Schema::dropIfExists('cashier_tables');

        Schema::create('cashiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cashier_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->decimal('total_cash_in', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cashier_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('payment');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->default('cash');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_transactions');
        Schema::dropIfExists('cashier_sessions');
        Schema::dropIfExists('cashiers');
    }
};