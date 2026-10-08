<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodera_pay_merchants', function (Blueprint $table) {
            if (!Schema::hasColumn('nodera_pay_merchants', 'google_id')) {
                $table->string('google_id')->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('nodera_pay_merchants', 'avatar')) {
                $table->text('avatar')->nullable()->after('owner_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nodera_pay_merchants', function (Blueprint $table) {
            if (Schema::hasColumn('nodera_pay_merchants', 'google_id')) {
                $table->dropColumn('google_id');
            }
            if (Schema::hasColumn('nodera_pay_merchants', 'avatar')) {
                $table->dropColumn('avatar');
            }
        });
    }
};
