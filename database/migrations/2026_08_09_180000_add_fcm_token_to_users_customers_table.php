<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'fcm_token')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->text('fcm_token')->nullable()->after('whatsapp_lid');
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('fcm_token')->nullable()->after('whatsapp_lid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'fcm_token')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }
    }
};
