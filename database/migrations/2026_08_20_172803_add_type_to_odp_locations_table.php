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
        Schema::table('odp_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('odp_locations', 'type')) {
                $table->string('type', 20)->default('odp')->after('name'); // 'odp', 'htb', 'switch'
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('odp_locations', function (Blueprint $table) {
            if (Schema::hasColumn('odp_locations', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
