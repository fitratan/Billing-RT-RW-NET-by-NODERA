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
        // 1. Table: registration_requests
        if (Schema::hasTable('registration_requests')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('registration_requests', 'unique_code')) {
                    $table->unsignedInteger('unique_code')->nullable()->after('payment_notes');
                }
                if (!Schema::hasColumn('registration_requests', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->nullable()->after('unique_code');
                }
                if (!Schema::hasColumn('registration_requests', 'dynamic_qris_string')) {
                    $table->text('dynamic_qris_string')->nullable()->after('total_amount');
                }
                if (!Schema::hasColumn('registration_requests', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('dynamic_qris_string');
                }
                if (!Schema::hasColumn('registration_requests', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('expires_at');
                }
                if (!Schema::hasColumn('registration_requests', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id', 50)->nullable()->after('paid_at');
                }
                if (!Schema::hasColumn('registration_requests', 'telegram_message_id')) {
                    $table->string('telegram_message_id', 50)->nullable()->after('telegram_chat_id');
                }
            });
        }

        // 2. Table: shop_orders
        if (Schema::hasTable('shop_orders')) {
            Schema::table('shop_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('shop_orders', 'unique_code')) {
                    $table->unsignedInteger('unique_code')->nullable()->after('total_amount');
                }
                if (!Schema::hasColumn('shop_orders', 'dynamic_qris_string')) {
                    $table->text('dynamic_qris_string')->nullable()->after('unique_code');
                }
                if (!Schema::hasColumn('shop_orders', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('dynamic_qris_string');
                }
                if (!Schema::hasColumn('shop_orders', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('expires_at');
                }
                if (!Schema::hasColumn('shop_orders', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id', 50)->nullable()->after('paid_at');
                }
            });
        }

        // 3. Table: tenant_addons
        if (Schema::hasTable('tenant_addons')) {
            Schema::table('tenant_addons', function (Blueprint $table) {
                if (!Schema::hasColumn('tenant_addons', 'unique_code')) {
                    $table->unsignedInteger('unique_code')->nullable()->after('config');
                }
                if (!Schema::hasColumn('tenant_addons', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->nullable()->after('unique_code');
                }
                if (!Schema::hasColumn('tenant_addons', 'dynamic_qris_string')) {
                    $table->text('dynamic_qris_string')->nullable()->after('total_amount');
                }
                if (!Schema::hasColumn('tenant_addons', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('dynamic_qris_string');
                }
                if (!Schema::hasColumn('tenant_addons', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('expires_at');
                }
                if (!Schema::hasColumn('tenant_addons', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id', 50)->nullable()->after('paid_at');
                }
                if (!Schema::hasColumn('tenant_addons', 'telegram_message_id')) {
                    $table->string('telegram_message_id', 50)->nullable()->after('telegram_chat_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('registration_requests')) {
            Schema::table('registration_requests', function (Blueprint $table) {
                $cols = ['unique_code', 'total_amount', 'dynamic_qris_string', 'expires_at', 'paid_at', 'telegram_chat_id', 'telegram_message_id'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('registration_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('shop_orders')) {
            Schema::table('shop_orders', function (Blueprint $table) {
                $cols = ['unique_code', 'dynamic_qris_string', 'expires_at', 'paid_at', 'telegram_chat_id'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('shop_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('tenant_addons')) {
            Schema::table('tenant_addons', function (Blueprint $table) {
                $cols = ['unique_code', 'total_amount', 'dynamic_qris_string', 'expires_at', 'paid_at', 'telegram_chat_id', 'telegram_message_id'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('tenant_addons', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
