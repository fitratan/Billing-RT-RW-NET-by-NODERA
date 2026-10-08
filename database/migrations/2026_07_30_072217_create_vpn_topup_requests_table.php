<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_received', 15, 2)->nullable();
            $table->string('payment_proof')->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_destination', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['vpn_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_topup_requests');
    }
};
