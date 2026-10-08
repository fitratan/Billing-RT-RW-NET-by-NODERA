<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_ref', 100)->nullable();
            $table->enum('status', ['pending','paid','cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
