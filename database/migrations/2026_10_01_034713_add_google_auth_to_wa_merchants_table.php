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
                if (!Schema::hasColumn('wa_merchants', 'google_id')) {
                    $table->string('google_id', 100)->nullable()->unique()->after('password');
                }
                if (!Schema::hasColumn('wa_merchants', 'avatar')) {
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
        if (Schema::hasTable('wa_merchants')) {
            Schema::table('wa_merchants', function (Blueprint $table) {
                if (Schema::hasColumn('wa_merchants', 'avatar')) {
                    $table->dropColumn('avatar');
                }
                if (Schema::hasColumn('wa_merchants', 'google_id')) {
                    $table->dropColumn('google_id');
                }
            });
        }
    }
};
