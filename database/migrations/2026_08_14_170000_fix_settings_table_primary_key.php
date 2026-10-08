<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Check if 'id' column exists
        if (!Schema::hasColumn('settings', 'id')) {
            // Drop existing primary key on 'key'
            Schema::table('settings', function (Blueprint $table) {
                $table->dropPrimary('key');
            });

            // Add auto-incrementing id as primary key
            Schema::table('settings', function (Blueprint $table) {
                $table->id()->first();
                $table->index(['key', 'tenant_id'], 'settings_key_tenant_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('settings', 'id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropIndex('settings_key_tenant_idx');
                $table->dropColumn('id');
                $table->primary('key');
            });
        }
    }
};
