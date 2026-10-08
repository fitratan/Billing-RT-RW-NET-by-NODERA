<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->string('method', 50)->default('cash'); // cash, transfer, qris, digiflazz, etc.
                $table->string('gateway', 50)->nullable(); // digiflazz, tripay, manual
                $table->string('gateway_ref', 100)->nullable(); // reference from gateway
                $table->string('status', 30)->default('pending'); // pending, success, failed, expired
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
