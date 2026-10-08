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
        if (Schema::hasTable('nodera_pay_merchants')) {
            Schema::table('nodera_pay_merchants', function (Blueprint $table) {
                if (!Schema::hasColumn('nodera_pay_merchants', 'ip_whitelist')) {
                    $table->text('ip_whitelist')->nullable()->after('webhook_url');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('nodera_pay_merchants')) {
            Schema::table('nodera_pay_merchants', function (Blueprint $table) {
                if (Schema::hasColumn('nodera_pay_merchants', 'ip_whitelist')) {
                    $table->dropColumn('ip_whitelist');
                }
            });
        }
    }
};
