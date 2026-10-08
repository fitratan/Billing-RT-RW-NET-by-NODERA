<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trouble_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20)->nullable();
            $table->text('description');
            $table->enum('status', ['pending','in_progress','resolved','closed'])->default('pending');
            $table->enum('priority', ['low','medium','high','urgent'])->default('low');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('attachment', 255)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trouble_tickets');
    }
};
