<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikhmon_subscriptions', function (Blueprint $table) {
            $table->string('ros_version', 3)->default('6')->after('subdomain')
                ->comment("Versi RouterOS: '6' = Mikhmon lama, '7' = mikhmon-agent (ROS7)");
        });
    }

    public function down(): void
    {
        Schema::table('mikhmon_subscriptions', function (Blueprint $table) {
            $table->dropColumn('ros_version');
        });
    }
};
