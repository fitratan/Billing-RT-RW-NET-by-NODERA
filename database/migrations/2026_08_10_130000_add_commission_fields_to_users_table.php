<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'commission_type')) {
                    $table->string('commission_type')->default('fixed')->nullable();
                }
                if (!Schema::hasColumn('users', 'commission_value')) {
                    $table->decimal('commission_value', 15, 2)->default(0)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'commission_type')) {
                    $table->dropColumn('commission_type');
                }
                if (Schema::hasColumn('users', 'commission_value')) {
                    $table->dropColumn('commission_value');
                }
            });
        }
    }
};
