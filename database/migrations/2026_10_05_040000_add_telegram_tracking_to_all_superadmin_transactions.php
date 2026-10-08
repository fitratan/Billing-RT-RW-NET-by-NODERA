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
        if (Schema::hasTable('nodera_pay_merchant_withdrawals')) {
            Schema::table('nodera_pay_merchant_withdrawals', function (Blueprint $table) {
                if (!Schema::hasColumn('nodera_pay_merchant_withdrawals', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id', 100)->nullable()->after('status');
                }
                if (!Schema::hasColumn('nodera_pay_merchant_withdrawals', 'telegram_message_id')) {
                    $table->string('telegram_message_id', 100)->nullable()->after('telegram_chat_id');
                }
            });
        }

        if (Schema::hasTable('referral_withdrawals')) {
            Schema::table('referral_withdrawals', function (Blueprint $table) {
                if (!Schema::hasColumn('referral_withdrawals', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id', 100)->nullable()->after('status');
                }
                if (!Schema::hasColumn('referral_withdrawals', 'telegram_message_id')) {
                    $table->string('telegram_message_id', 100)->nullable()->after('telegram_chat_id');
                }
            });
        }

        if (Schema::hasTable('referral_partners')) {
            Schema::table('referral_partners', function (Blueprint $table) {
                if (!Schema::hasColumn('referral_partners', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id', 100)->nullable()->after('status');
                }
                if (!Schema::hasColumn('referral_partners', 'telegram_message_id')) {
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
        if (Schema::hasTable('nodera_pay_merchant_withdrawals')) {
            Schema::table('nodera_pay_merchant_withdrawals', function (Blueprint $table) {
                $table->dropColumn(['telegram_chat_id', 'telegram_message_id']);
            });
        }

        if (Schema::hasTable('referral_withdrawals')) {
            Schema::table('referral_withdrawals', function (Blueprint $table) {
                $table->dropColumn(['telegram_chat_id', 'telegram_message_id']);
            });
        }

        if (Schema::hasTable('referral_partners')) {
            Schema::table('referral_partners', function (Blueprint $table) {
                $table->dropColumn(['telegram_chat_id', 'telegram_message_id']);
            });
        }
    }
};
