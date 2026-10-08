<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pkl_exam_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pkl_exam_settings', 'group_chat_id')) {
                $table->string('group_chat_id')->nullable()->after('announcement_chat_id');
            }
            if (!Schema::hasColumn('pkl_exam_settings', 'group_thread_id')) {
                $table->string('group_thread_id')->nullable()->after('group_chat_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pkl_exam_settings', function (Blueprint $table) {
            $table->dropColumn(['group_chat_id', 'group_thread_id']);
        });
    }
};
