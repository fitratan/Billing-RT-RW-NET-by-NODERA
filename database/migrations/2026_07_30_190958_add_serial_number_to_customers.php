<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'serial_number')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('serial_number', 100)->nullable()->after('install_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'serial_number')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('serial_number');
            });
        }
    }
};
