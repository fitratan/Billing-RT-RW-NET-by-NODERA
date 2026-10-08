<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('wa_topups')) {
            Schema::table('wa_topups', function (Blueprint $table) {
                if (!Schema::hasColumn('wa_topups', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id', 100)->nullable()->after('payment_status');
                }
                if (!Schema::hasColumn('wa_topups', 'telegram_message_id')) {
                    $table->string('telegram_message_id', 100)->nullable()->after('telegram_chat_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('wa_topups')) {
            Schema::table('wa_topups', function (Blueprint $table) {
                if (Schema::hasColumn('wa_topups', 'telegram_message_id')) {
                    $table->dropColumn('telegram_message_id');
                }
                if (Schema::hasColumn('wa_topups', 'telegram_chat_id')) {
                    $table->dropColumn('telegram_chat_id');
                }
            });
        }
    }
};
