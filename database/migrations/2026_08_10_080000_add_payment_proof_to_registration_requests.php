<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('registration_requests', 'payment_proof')) {
                $table->string('payment_proof')->nullable()->after('duration');
            }
            if (!Schema::hasColumn('registration_requests', 'payment_bank')) {
                $table->string('payment_bank')->nullable()->after('payment_proof');
            }
            if (!Schema::hasColumn('registration_requests', 'payment_notes')) {
                $table->text('payment_notes')->nullable()->after('payment_bank');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn(['payment_proof', 'payment_bank', 'payment_notes']);
        });
    }
};
