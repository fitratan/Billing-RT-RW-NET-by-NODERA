<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('packages', 'is_active')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('type');
            });
        }
        if (!Schema::hasColumn('packages', 'type')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('type', 20)->default('pppoe')->after('id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'type']);
        });
    }
};
