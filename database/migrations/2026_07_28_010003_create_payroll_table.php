<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('period_month');
            $table->integer('period_year');
            $table->decimal('salary', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['period_month', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll');
    }
};
