<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('isp_licenses', function (Blueprint $table) {
            if (!Schema::hasColumn('isp_licenses', 'vpn_user_id')) {
                $table->unsignedBigInteger('vpn_user_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('isp_licenses', 'price')) {
                $table->decimal('price', 14, 2)->default(0)->after('package_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('isp_licenses', function (Blueprint $table) {
            if (Schema::hasColumn('isp_licenses', 'vpn_user_id')) {
                $table->dropColumn('vpn_user_id');
            }
            if (Schema::hasColumn('isp_licenses', 'price')) {
                $table->dropColumn('price');
            }
        });
    }
};
