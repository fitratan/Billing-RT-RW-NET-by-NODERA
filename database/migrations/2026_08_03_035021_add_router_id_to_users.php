<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('users', 'router_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('router_id')->nullable()->after('tenant_id');
                $t->foreign('router_id')->references('id')->on('mikrotiks')->nullOnDelete();
            });
        }
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['router_id']);
            $t->dropColumn('router_id');
        });
    }
};
