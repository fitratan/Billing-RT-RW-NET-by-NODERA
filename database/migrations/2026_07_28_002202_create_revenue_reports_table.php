<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_reports', function (Blueprint $table) {
            $table->id();
            $table->year('period_year');
            $table->tinyInteger('period_month'); // 1-12
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('total_expenses', 14, 2)->default(0);
            $table->decimal('profit_loss', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_reports');
    }
};
