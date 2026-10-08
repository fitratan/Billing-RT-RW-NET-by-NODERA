<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar index yang akan ditambahkan.
     * Format: ['table', 'name', 'columns']
     */
    private array $indexes = [
        ['vouchers',       'vouchers_batch_id_index',                ['batch_id']],
        ['vouchers',       'vouchers_router_id_index',               ['router_id']],
        ['vouchers',       'vouchers_tenant_id_used_used_at_index',  ['tenant_id', 'used', 'used_at']],
        ['vouchers',       'vouchers_username_index',                ['username']],
        ['invoices',       'invoices_invoice_number_index',          ['invoice_number']],
        ['invoices',       'invoices_collector_id_index',            ['collector_id']],
        ['customers',      'customers_ip_address_index',             ['ip_address']],
        ['customers',      'customers_phone_index',                  ['phone']],
        ['customers',      'customers_email_index',                  ['email']],
        ['vpn_accounts',   'vpn_accounts_status_expires_at_index',   ['status', 'expires_at']],
        ['trouble_tickets','trouble_tickets_resolved_by_index',      ['resolved_by']],
        ['attendance',     'attendance_collector_id_index',          ['collector_id']],
        ['invoices',       'invoices_tenant_status_due_date_index',  ['tenant_id', 'status', 'due_date']],
        ['invoices',       'invoices_tenant_customer_paid_index',    ['tenant_id', 'customer_id', 'paid']],
        ['customers',      'customers_tenant_status_index',          ['tenant_id', 'status']],
        ['customers',      'customers_tenant_router_index',          ['tenant_id', 'router_id']],
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
        // Kelompokkan per tabel agar hanya satu Schema::table() call per tabel
        $grouped = collect($this->indexes)->groupBy(fn($i) => $i[0]);

        foreach ($grouped as $table => $items) {
            if (!Schema::hasTable($table)) continue;

            $existing = $this->existingIndexes($table);

            Schema::table($table, function (Blueprint $blueprint) use ($table, $items, $existing) {
                foreach ($items as [$_, $indexName, $columns]) {
                    // Skip jika index sudah ada
                    if (in_array($indexName, $existing, true)) continue;

                    // Skip jika salah satu kolom tidak ada
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
