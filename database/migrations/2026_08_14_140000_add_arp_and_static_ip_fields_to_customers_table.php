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
            if (!Schema::hasColumn('customers', 'connection_type')) {
                $table->string('connection_type', 30)->default('pppoe')->after('pppoe_username');
            }
            if (!Schema::hasColumn('customers', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('connection_type');
            }
            if (!Schema::hasColumn('customers', 'mac_address')) {
                $table->string('mac_address', 30)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('customers', 'arp_interface')) {
                $table->string('arp_interface', 50)->nullable()->after('mac_address');
            }
            if (!Schema::hasColumn('customers', 'auto_arp')) {
                $table->boolean('auto_arp')->default(false)->after('arp_interface');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['connection_type', 'ip_address', 'mac_address', 'arp_interface', 'auto_arp']);
        });
    }
};
