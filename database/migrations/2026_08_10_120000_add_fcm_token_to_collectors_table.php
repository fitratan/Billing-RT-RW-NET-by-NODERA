<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collectors') && !Schema::hasColumn('collectors', 'fcm_token')) {
            Schema::table('collectors', function (Blueprint $table) {
                $table->text('fcm_token')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('collectors') && Schema::hasColumn('collectors', 'fcm_token')) {
            Schema::table('collectors', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }
    }
};
