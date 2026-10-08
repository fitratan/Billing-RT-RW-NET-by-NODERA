<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('promo_slides', 'tenant_id')) {
            Schema::table('promo_slides', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->constrained('tenants')
                    ->nullOnDelete()
                    ->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('promo_slides', 'tenant_id')) {
            Schema::table('promo_slides', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
