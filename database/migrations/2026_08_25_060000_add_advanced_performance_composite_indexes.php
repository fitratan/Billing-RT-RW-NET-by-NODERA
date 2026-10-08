<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar composite index tingkat lanjut untuk eliminasi slow queries pada volume data besar.
     * Format: ['table', 'index_name', ['columns']]
     */
    private array $indexes = [
        ['vouchers',             'idx_vouchers_tenant_router_used',        ['tenant_id', 'router_id', 'used']],
        ['invoices',             'idx_invoices_tenant_paid_paid_at',       ['tenant_id', 'paid', 'paid_at']],
        ['invoices',             'idx_invoices_tenant_status_due',         ['tenant_id', 'status', 'due_date']],
        ['payment_transactions', 'idx_pay_trans_tenant_status_created',    ['tenant_id', 'status', 'created_at']],
        ['expenses',             'idx_expenses_tenant_date',               ['tenant_id', 'expense_date']],
        ['customer_usage',       'idx_usage_customer_recorded',            ['customer_id', 'recorded_at']],
        ['customers',            'idx_customers_tenant_name',              ['tenant_id', 'name']],
    ];

    /** Ambil daftar nama index yang sudah ada di tabel (MySQL). */
    private function existingIndexes(string $table): array
    {
        try {
            $rows = DB::select("SHOW INDEX FROM `{$table}`");
            return array_unique(array_map(fn($r) => $r->Key_name, $rows));
        } catch (\Throwable) {
            return [];
        }
    }

    public function up(): void
    {
        $grouped = collect($this->indexes)->groupBy(fn($i) => $i[0]);

        foreach ($grouped as $table => $items) {
            if (!Schema::hasTable($table)) continue;

            $existing = $this->existingIndexes($table);

            Schema::table($table, function (Blueprint $blueprint) use ($table, $items, $existing) {
                foreach ($items as [$_, $indexName, $columns]) {
                    if (in_array($indexName, $existing, true)) continue;

                    $missingColumn = collect($columns)->first(
                        fn($col) => !Schema::hasColumn($table, $col)
                    );
                    if ($missingColumn) continue;

                    $blueprint->index($columns, $indexName);
                }
            });
        }
    }

    public function down(): void
    {
        $grouped = collect($this->indexes)->groupBy(fn($i) => $i[0]);

        foreach ($grouped as $table => $items) {
            if (!Schema::hasTable($table)) continue;

            $existing = $this->existingIndexes($table);

            Schema::table($table, function (Blueprint $blueprint) use ($items, $existing) {
                foreach ($items as [$_, $indexName, $columns]) {
                    if (!in_array($indexName, $existing, true)) continue;
                    $blueprint->dropIndex($indexName);
                }
            });
        }
    }
};
