<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            if (!Schema::hasColumn('olts', 'snmp_port')) {
                $table->integer('snmp_port')->default(161)->after('port');
            }
            if (!Schema::hasColumn('olts', 'telnet_port')) {
                $table->integer('telnet_port')->default(23)->after('snmp_port');
            }
            if (!Schema::hasColumn('olts', 'submodel')) {
                $table->string('submodel')->nullable()->after('model');
            }
            if (!Schema::hasColumn('olts', 'hardware_metrics')) {
                $table->json('hardware_metrics')->nullable()->after('location');
            }
            if (!Schema::hasColumn('olts', 'last_poll_at')) {
                $table->timestamp('last_poll_at')->nullable()->after('hardware_metrics');
            }
            if (!Schema::hasColumn('olts', 'last_poll_status')) {
                $table->string('last_poll_status')->nullable()->after('last_poll_at');
            }
        });

        Schema::table('onus', function (Blueprint $table) {
            if (!Schema::hasColumn('onus', 'rx_power')) {
                $table->decimal('rx_power', 8, 2)->nullable()->after('status');
            }
            if (!Schema::hasColumn('onus', 'tx_power')) {
                $table->decimal('tx_power', 8, 2)->nullable()->after('rx_power');
            }
            if (!Schema::hasColumn('onus', 'distance')) {
                $table->decimal('distance', 10, 2)->nullable()->after('tx_power');
            }
            if (!Schema::hasColumn('onus', 'temperature')) {
                $table->decimal('temperature', 6, 2)->nullable()->after('distance');
            }
            if (!Schema::hasColumn('onus', 'offline_reason')) {
                $table->string('offline_reason')->nullable()->after('temperature');
            }
            if (!Schema::hasColumn('onus', 'last_online_at')) {
                $table->timestamp('last_online_at')->nullable()->after('offline_reason');
            }
            if (!Schema::hasColumn('onus', 'last_sync_at')) {
                $table->timestamp('last_sync_at')->nullable()->after('last_online_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->dropColumn(['snmp_port', 'telnet_port', 'submodel', 'hardware_metrics', 'last_poll_at', 'last_poll_status']);
        });

        Schema::table('onus', function (Blueprint $table) {
            $table->dropColumn(['rx_power', 'tx_power', 'distance', 'temperature', 'offline_reason', 'last_online_at', 'last_sync_at']);
        });
    }
};
