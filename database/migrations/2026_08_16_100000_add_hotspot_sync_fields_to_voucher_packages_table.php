<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('voucher_packages', 'profile')) {
                $table->string('profile', 100)->nullable()->after('name');
            }
            if (!Schema::hasColumn('voucher_packages', 'time_limit')) {
                $table->string('time_limit', 50)->nullable()->after('duration_days');
            }
            if (!Schema::hasColumn('voucher_packages', 'data_limit')) {
                $table->integer('data_limit')->nullable()->after('time_limit');
            }
            if (!Schema::hasColumn('voucher_packages', 'router_id')) {
                $table->unsignedBigInteger('router_id')->nullable()->after('tenant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('voucher_packages', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('voucher_packages', 'profile')) $cols[] = 'profile';
            if (Schema::hasColumn('voucher_packages', 'time_limit')) $cols[] = 'time_limit';
            if (Schema::hasColumn('voucher_packages', 'data_limit')) $cols[] = 'data_limit';
            if (Schema::hasColumn('voucher_packages', 'router_id')) $cols[] = 'router_id';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
