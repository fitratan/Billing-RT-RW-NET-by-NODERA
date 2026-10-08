<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'monthly_price')) {
                $table->decimal('monthly_price', 12, 0)->nullable()->after('price');
            }
            if (!Schema::hasColumn('packages', 'semi_annual_price')) {
                $table->decimal('semi_annual_price', 12, 0)->nullable()->after('monthly_price');
            }
            if (!Schema::hasColumn('packages', 'annual_price')) {
                $table->decimal('annual_price', 12, 0)->nullable()->after('semi_annual_price');
            }
            // drop old price column since we use duration-based now
            // but keep it for backward compat, just set nullable
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['monthly_price', 'semi_annual_price', 'annual_price']);
        });
    }
};
