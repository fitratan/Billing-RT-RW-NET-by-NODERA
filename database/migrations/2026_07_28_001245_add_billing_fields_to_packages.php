<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'promo_price')) $table->decimal('promo_price', 12, 2)->nullable()->after('price');
            if (!Schema::hasColumn('packages', 'promo_cycles')) $table->integer('promo_cycles')->default(0)->after('promo_price');
            if (!Schema::hasColumn('packages', 'prorate_first_invoice')) $table->boolean('prorate_first_invoice')->default(false)->after('promo_cycles');
            if (!Schema::hasColumn('packages', 'use_ppn')) $table->boolean('use_ppn')->default(false)->after('prorate_first_invoice');
            if (!Schema::hasColumn('packages', 'ppn_percentage')) $table->decimal('ppn_percentage', 5, 2)->default(11.00)->after('use_ppn');
            if (!Schema::hasColumn('packages', 'use_uso')) $table->boolean('use_uso')->default(false)->after('ppn_percentage');
            if (!Schema::hasColumn('packages', 'uso_percentage')) $table->decimal('uso_percentage', 5, 2)->default(1.75)->after('use_uso');
            if (!Schema::hasColumn('packages', 'admin_fee')) $table->decimal('admin_fee', 12, 2)->default(0)->after('uso_percentage');
            if (!Schema::hasColumn('packages', 'late_fee')) $table->decimal('late_fee', 12, 2)->default(0)->after('admin_fee');
            if (!Schema::hasColumn('packages', 'materai')) $table->decimal('materai', 12, 2)->default(0)->after('late_fee');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'promo_price', 'promo_cycles', 'prorate_first_invoice',
                'use_ppn', 'ppn_percentage', 'use_uso', 'uso_percentage',
                'admin_fee', 'late_fee', 'materai',
            ]);
        });
    }
};
