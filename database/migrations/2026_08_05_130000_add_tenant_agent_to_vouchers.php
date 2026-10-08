<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('vouchers', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            }
            if (!Schema::hasColumn('vouchers', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['tenant_id', 'agent_id']);
        });
    }
};