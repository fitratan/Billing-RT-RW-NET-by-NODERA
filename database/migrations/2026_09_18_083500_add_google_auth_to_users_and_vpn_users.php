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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'google_id')) {
                    $table->string('google_id', 100)->nullable()->index()->after('email');
                }
                if (!Schema::hasColumn('users', 'avatar')) {
                    $table->string('avatar', 500)->nullable()->after('google_id');
                }
            });
        }

        if (Schema::hasTable('vpn_users')) {
            Schema::table('vpn_users', function (Blueprint $table) {
                if (!Schema::hasColumn('vpn_users', 'google_id')) {
                    $table->string('google_id', 100)->nullable()->index()->after('email');
                }
                if (!Schema::hasColumn('vpn_users', 'avatar')) {
                    $table->string('avatar', 500)->nullable()->after('google_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'avatar')) {
                    $table->dropColumn('avatar');
                }
                if (Schema::hasColumn('users', 'google_id')) {
                    $table->dropColumn('google_id');
                }
            });
        }

        if (Schema::hasTable('vpn_users')) {
            Schema::table('vpn_users', function (Blueprint $table) {
                if (Schema::hasColumn('vpn_users', 'avatar')) {
                    $table->dropColumn('avatar');
                }
                if (Schema::hasColumn('vpn_users', 'google_id')) {
                    $table->dropColumn('google_id');
                }
            });
        }
    }
};
