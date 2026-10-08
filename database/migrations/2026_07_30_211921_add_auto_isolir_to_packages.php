<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'auto_isolir')) {
                $table->boolean('auto_isolir')->default(true)->after('materai');
                $table->integer('isolir_interval_months')->default(1)->after('auto_isolir');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['auto_isolir', 'isolir_interval_months']);
        });
    }
};
