<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acs_tenant_settings')) {
            Schema::table('acs_tenant_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('acs_tenant_settings', 'connection_mode')) {
                    $table->string('connection_mode', 32)->default('cloud')->after('is_enabled'); // 'cloud' or 'self_hosted'
                }
                if (!Schema::hasColumn('acs_tenant_settings', 'nbi_url')) {
                    $table->string('nbi_url')->nullable()->after('server_url');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('acs_tenant_settings')) {
            Schema::table('acs_tenant_settings', function (Blueprint $table) {
                if (Schema::hasColumn('acs_tenant_settings', 'connection_mode')) {
                    $table->dropColumn('connection_mode');
                }
                if (Schema::hasColumn('acs_tenant_settings', 'nbi_url')) {
                    $table->dropColumn('nbi_url');
                }
            });
        }
    }
};
