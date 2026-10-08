<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('install_date')->nullable()->after('router_id');
            $table->integer('promo_cycles_used')->default(0)->after('install_date');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['install_date', 'promo_cycles_used']);
        });
    }
};
