<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registration_requests')) {
            try {
                Schema::table('registration_requests', function (Blueprint $table) {
                    $table->dropUnique('registration_requests_slug_unique');
                });
            } catch (\Throwable $e) {
                // Index might already be dropped or named differently
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('registration_requests')) {
            try {
                Schema::table('registration_requests', function (Blueprint $table) {
                    $table->unique('slug');
                });
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
};
