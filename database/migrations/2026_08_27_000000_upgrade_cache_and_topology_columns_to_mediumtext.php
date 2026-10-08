<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade cache table value column from TEXT (64KB) to MEDIUMTEXT (16MB)
        if (Schema::hasTable('cache')) {
            try {
                DB::statement("ALTER TABLE `cache` MODIFY `value` MEDIUMTEXT NOT NULL");
            } catch (\Throwable $e) {}
        }

        // 2. Upgrade customers cable_path to LONGTEXT
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'cable_path')) {
            try {
                DB::statement("ALTER TABLE `customers` MODIFY `cable_path` LONGTEXT NULL");
            } catch (\Throwable $e) {}
        }

        // 3. Upgrade odp_locations cable_path to LONGTEXT
        if (Schema::hasTable('odp_locations') && Schema::hasColumn('odp_locations', 'cable_path')) {
            try {
                DB::statement("ALTER TABLE `odp_locations` MODIFY `cable_path` LONGTEXT NULL");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
    }
};
