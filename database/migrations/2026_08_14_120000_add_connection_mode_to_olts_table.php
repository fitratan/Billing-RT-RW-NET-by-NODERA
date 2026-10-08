<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            if (!Schema::hasColumn('olts', 'connection_mode')) {
                $table->string('connection_mode')->default('snmp')->after('model')->comment('snmp, telnet, hybrid');
            }
            if (!Schema::hasColumn('olts', 'enable_password')) {
                $table->string('enable_password')->nullable()->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->dropColumn(['connection_mode', 'enable_password']);
        });
    }
};
