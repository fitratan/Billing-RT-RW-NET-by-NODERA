<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodera_pay_subscriptions') && Schema::hasColumn('nodera_pay_subscriptions', 'qris_raw_string')) {
            Schema::table('nodera_pay_subscriptions', function (Blueprint $table) {
                $table->text('qris_raw_string')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nodera_pay_subscriptions') && Schema::hasColumn('nodera_pay_subscriptions', 'qris_raw_string')) {
            Schema::table('nodera_pay_subscriptions', function (Blueprint $table) {
                $table->text('qris_raw_string')->nullable(false)->change();
            });
        }
    }
};
