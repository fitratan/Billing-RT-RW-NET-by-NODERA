<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'isolir_address_list')) {
                $table->string('isolir_address_list', 50)->nullable()->default('ISOLIR_LIST')->after('profile_isolir');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'isolir_address_list')) {
                $table->dropColumn('isolir_address_list');
            }
        });
    }
};
