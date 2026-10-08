<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('vpn_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_packages', 'port_count')) {
                $table->integer('port_count')->default(1)->after('quota_gb');
            }
        });
    }
    public function down(): void {
        Schema::table('vpn_packages', function (Blueprint $table) {
            $table->dropColumn('port_count');
        });
    }
};
