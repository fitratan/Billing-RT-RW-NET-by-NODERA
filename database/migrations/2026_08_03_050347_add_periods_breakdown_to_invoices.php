<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $t) {
            $t->json('periods_breakdown')->nullable()->after('period');
            $t->string('accumulated_from')->nullable()->after('periods_breakdown');
        });
    }
    public function down(): void {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn(['periods_breakdown', 'accumulated_from']);
        });
    }
};
