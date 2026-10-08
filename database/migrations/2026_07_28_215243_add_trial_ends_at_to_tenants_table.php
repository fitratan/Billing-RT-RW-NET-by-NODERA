<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'trial_ends_at')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->timestamp('trial_ends_at')->nullable()->after('expired_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'trial_ends_at')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('trial_ends_at');
            });
        }
    }
};
