<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desktop_licenses', function (Blueprint $table) {
            $table->foreignId('vpn_user_id')->nullable()->after('tenant_id')->constrained('vpn_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('desktop_licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vpn_user_id');
        });
    }
};
