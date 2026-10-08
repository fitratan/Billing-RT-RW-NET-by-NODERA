<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance', 'collector_id')) {
                $table->foreignId('collector_id')->nullable()->after('user_id')->constrained('collectors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['collector_id']);
            $table->dropColumn('collector_id');
        });
    }
};
