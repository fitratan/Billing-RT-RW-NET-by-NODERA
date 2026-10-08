<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodera_pay_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('nodera_pay_subscriptions', 'last_device_ping_at')) {
                $table->timestamp('last_device_ping_at')->nullable()->after('notes')->comment('Waktu terakhir perangkat companion APK mengirim ping / notifikasi');
            }
            if (!Schema::hasColumn('nodera_pay_subscriptions', 'last_device_name')) {
                $table->string('last_device_name')->nullable()->after('last_device_ping_at')->comment('Nama / model perangkat HP atau Tablet');
            }
            if (!Schema::hasColumn('nodera_pay_subscriptions', 'last_device_battery')) {
                $table->string('last_device_battery', 20)->nullable()->after('last_device_name')->comment('Status persentase baterai');
            }
            if (!Schema::hasColumn('nodera_pay_subscriptions', 'last_device_ip')) {
                $table->string('last_device_ip', 45)->nullable()->after('last_device_battery')->comment('IP address perangkat terakhir');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nodera_pay_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['last_device_ping_at', 'last_device_name', 'last_device_battery', 'last_device_ip']);
        });
    }
};
