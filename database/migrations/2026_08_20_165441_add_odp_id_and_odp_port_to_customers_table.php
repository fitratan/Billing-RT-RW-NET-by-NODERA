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
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'odp_id')) {
                $table->unsignedBigInteger('odp_id')->nullable()->after('package_id')->index();
            }
            if (!Schema::hasColumn('customers', 'odp_port')) {
                $table->unsignedInteger('odp_port')->nullable()->after('odp_id');
            }
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['odp_id', 'odp_port']);
        });
    }
};
