<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'max_customers')) {
                $table->integer('max_customers')->nullable()->after('price');
            }
            if (!Schema::hasColumn('packages', 'max_routers')) {
                $table->integer('max_routers')->nullable()->after('max_customers');
            }
            if (!Schema::hasColumn('packages', 'type')) {
                $table->string('type', 20)->default('pppoe')->after('id');
            }
        });

        // Tandai paket yg punya harga > 0 sebagai subscription
        DB::table('packages')->whereNull('tenant_id')->where('price', '>', 0)->update(['type' => 'subscription']);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['max_customers', 'max_routers', 'type']);
        });
    }
};
