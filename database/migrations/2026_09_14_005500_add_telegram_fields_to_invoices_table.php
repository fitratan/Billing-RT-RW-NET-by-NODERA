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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'telegram_message_id')) {
                $table->string('telegram_message_id')->nullable()->after('processed_by');
            }
            if (!Schema::hasColumn('invoices', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->after('telegram_message_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'telegram_chat_id')) {
                $table->dropColumn('telegram_chat_id');
            }
            if (Schema::hasColumn('invoices', 'telegram_message_id')) {
                $table->dropColumn('telegram_message_id');
            }
        });
    }
};
