<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_topup_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_topup_requests', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 50)->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('vpn_topup_requests', 'telegram_message_id')) {
                $table->string('telegram_message_id', 50)->nullable()->after('telegram_chat_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vpn_topup_requests', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_message_id']);
        });
    }
};
