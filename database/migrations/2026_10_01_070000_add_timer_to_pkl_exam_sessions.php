<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pkl_exam_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('pkl_exam_sessions', 'started_at')) {
                $table->dateTime('started_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('pkl_exam_sessions', 'expires_at')) {
                $table->dateTime('expires_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pkl_exam_sessions', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'expires_at']);
        });
    }
};
