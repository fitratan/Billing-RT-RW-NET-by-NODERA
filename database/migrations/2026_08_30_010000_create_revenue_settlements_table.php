<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20)->index(); // e.g. '2026-08'
            $table->string('title')->nullable();
            $table->decimal('total_revenue_fcfa', 16, 2)->default(0);
            $table->decimal('armand_share_fcfa', 16, 2)->default(0); // 60%
            $table->decimal('fitra_share_fcfa', 16, 2)->default(0);  // 40%
            $table->decimal('rate_usd_fcfa', 10, 4)->default(600);   // 1 USD = X FCFA
            $table->decimal('rate_usd_idr', 12, 2)->default(16200);  // 1 USD = X IDR
            $table->decimal('armand_share_usd', 12, 2)->default(0);
            $table->decimal('fitra_share_usd', 12, 2)->default(0);
            $table->decimal('fitra_share_idr', 16, 2)->default(0);
            $table->string('status', 30)->default('completed'); // pending, completed, transferred
            $table->timestamp('settled_at')->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->string('reference_no', 150)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_settlements');
    }
};
