<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('use_night_speed')->default(false);
            $table->string('night_profile_name')->nullable();
            $table->boolean('use_fup')->default(false);
            $table->decimal('fup_limit_gb', 10, 2)->default(0);
            $table->string('fup_profile_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['use_night_speed', 'night_profile_name', 'use_fup', 'fup_limit_gb', 'fup_profile_name']);
        });
    }
};
