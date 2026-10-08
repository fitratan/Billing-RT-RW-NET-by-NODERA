<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registration_requests') && !Schema::hasColumn('registration_requests', 'referral_code')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                $table->string('referral_code', 50)->nullable()->after('package_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn('referral_code');
        });
    }
};
