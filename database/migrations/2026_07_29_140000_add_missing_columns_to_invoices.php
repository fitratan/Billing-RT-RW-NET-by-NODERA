<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'customer_name')) {
                $table->string('customer_name', 100)->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('invoices', 'period')) {
                $table->string('period', 7)->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('invoices', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $columns = ['customer_name', 'period', 'tenant_id'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
