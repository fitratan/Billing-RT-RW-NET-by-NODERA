<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('period_month'); // 1-12
            $table->year('period_year');
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->bigInteger('last_total_bytes_in')->default(0);
            $table->bigInteger('last_total_bytes_out')->default(0);
            $table->timestamp('last_update')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'period_month', 'period_year'], 'customer_usage_unique');
            $table->index(['tenant_id', 'period_month', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_usage');
    }
};
