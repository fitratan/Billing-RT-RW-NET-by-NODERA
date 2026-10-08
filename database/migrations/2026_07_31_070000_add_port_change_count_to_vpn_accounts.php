<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_accounts', 'port_change_count')) {
                $table->unsignedTinyInteger('port_change_count')->default(0)->after('ports');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            $table->dropColumn('port_change_count');
        });
    }
};
