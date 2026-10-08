<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodera_pay_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('nodera_pay_subscriptions', 'qris_timeout_minutes')) {
                $table->integer('qris_timeout_minutes')->default(5)->after('price')->comment('Durasi kadaluarsa QRIS dinamis dalam menit');
            }
            if (!Schema::hasColumn('nodera_pay_subscriptions', 'enable_dynamic_qris')) {
                $table->boolean('enable_dynamic_qris')->default(true)->after('qris_timeout_minutes')->comment('Aktifkan QRIS Dinamis');
            }
            if (!Schema::hasColumn('nodera_pay_subscriptions', 'enable_unique_code')) {
                $table->boolean('enable_unique_code')->default(true)->after('enable_dynamic_qris')->comment('Aktifkan Kode Unik 3 Digit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nodera_pay_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['qris_timeout_minutes', 'enable_dynamic_qris', 'enable_unique_code']);
        });
    }
};
