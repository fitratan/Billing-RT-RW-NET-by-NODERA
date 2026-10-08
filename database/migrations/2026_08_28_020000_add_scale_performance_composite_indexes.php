<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes untuk skala 10.000+ pelanggan & telemetri ISP cepat.
     * Format: ['table', 'index_name', ['columns']]
     */
    private array $indexes = [
        ['customers',     'idx_cust_tenant_status',       ['tenant_id', 'status']],
        ['customers',     'idx_cust_tenant_odp',          ['tenant_id', 'odp_id']],
        ['customers',     'idx_cust_tenant_iso_date',     ['tenant_id', 'isolation_date']],
        ['customers',     'idx_cust_tenant_pppoe',        ['tenant_id', 'pppoe_username']],
        ['customers',     'idx_cust_tenant_router',       ['tenant_id', 'router_id']],
        ['invoices',      'idx_inv_cust_period',          ['customer_id', 'period']],
        ['invoices',      'idx_inv_tenant_period',        ['tenant_id', 'period']],
        ['odp_locations', 'idx_odp_tenant_parent',        ['tenant_id', 'parent_odp_id']],
        ['odp_locations', 'idx_odp_tenant_router',        ['tenant_id', 'router_id']],
        ['odp_locations', 'idx_odp_tenant_type',          ['tenant_id', 'type']],
        ['onu_locations', 'idx_onu_tenant_odp',           ['tenant_id', 'odp_id']],
        ['onu_locations', 'idx_onu_tenant_olt',           ['tenant_id', 'olt_id']],
    ];

    /** Ambil daftar nama index yang sudah ada di tabel (MySQL / SQLite). */
    private function existingIndexes(string $table): array
    {
        try {
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                $rows = DB::select("PRAGMA index_list('{$table}')");
                return array_map(fn($r) => $r->name, $rows);
            }
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
