<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_topup_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_topup_requests', 'unique_code')) {
                $table->integer('unique_code')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('vpn_topup_requests', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->nullable()->after('unique_code');
            }
            if (!Schema::hasColumn('vpn_topup_requests', 'dynamic_qris_string')) {
                $table->text('dynamic_qris_string')->nullable()->after('bank_destination');
            }
            if (!Schema::hasColumn('vpn_topup_requests', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vpn_topup_requests', function (Blueprint $table) {
            $table->dropColumn(['unique_code', 'total_amount', 'dynamic_qris_string', 'expires_at']);
        });
    }
};
