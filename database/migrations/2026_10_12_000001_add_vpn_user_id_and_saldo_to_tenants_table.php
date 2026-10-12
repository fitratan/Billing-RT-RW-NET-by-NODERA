<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (!Schema::hasColumn('tenants', 'vpn_user_id')) {
                    $table->unsignedBigInteger('vpn_user_id')->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('tenants', 'saldo')) {
                    $table->decimal('saldo', 14, 2)->default(0)->after('is_active');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (Schema::hasColumn('tenants', 'vpn_user_id')) {
                    $table->dropColumn('vpn_user_id');
                }
                if (Schema::hasColumn('tenants', 'saldo')) {
                    $table->dropColumn('saldo');
                }
            });
        }
    }
};
