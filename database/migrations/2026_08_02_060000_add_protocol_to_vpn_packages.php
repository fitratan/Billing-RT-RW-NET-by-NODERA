<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('vpn_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_packages', 'protocol')) {
                $table->json('protocol')->nullable()->after('type');
            }
        });
    }
    public function down(): void {
        Schema::table('vpn_packages', function (Blueprint $table) {
            if (Schema::hasColumn('vpn_packages', 'protocol')) {
                $table->dropColumn('protocol');
            }
        });
    }
};
