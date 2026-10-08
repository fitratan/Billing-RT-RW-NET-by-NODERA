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
        if (Schema::hasTable('wa_merchants')) {
            Schema::table('wa_merchants', function (Blueprint $table) {
                if (!Schema::hasColumn('wa_merchants', 'settings')) {
                    $table->json('settings')->nullable()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('wa_merchants')) {
            Schema::table('wa_merchants', function (Blueprint $table) {
                if (!Schema::hasColumn('wa_merchants', 'settings')) {
                    $table->dropColumn('settings');
                }
            });
        }
    }
};
