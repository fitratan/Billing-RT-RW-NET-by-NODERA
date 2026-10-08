<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pkl_exam_sessions', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->boolean('is_auto_broadcast')->default(true)->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('pkl_exam_sessions', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'is_auto_broadcast']);
        });
    }
};
