<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            $table->decimal('amount', 14, 2);
            $table->string('category', 100)->default('operational');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->string('vendor', 100)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->string('payment_method', 50)->default('cash');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
