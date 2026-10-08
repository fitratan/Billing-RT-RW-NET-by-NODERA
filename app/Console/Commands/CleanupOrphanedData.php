<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedData extends Command
{
    protected $signature = 'app:cleanup-orphaned';
    protected $description = 'Bersihin data orphan — tenant_id null atau nunjuk ke tenant yg sudah dihapus';

    // Semua tabel yang punya tenant_id
    protected array $tables = [
        'agents', 'attendance', 'collectors', 'customers',
        'expenses', 'inventory_categories', 'inventory_items',
        'invoices', 'odp_locations', 'olts', 'onu_locations',
        'onus', 'packages', 'payment_gateway_settings',
        'payment_transactions', 'payments', 'payroll',
        'revenue_reports', 'settings', 'trouble_tickets',
        'users', 'vouchers',
    ];

    public function handle(): int
    {
        $deletedTenantIds = DB::table('tenants')->pluck('id')->toArray();

        $totalDeleted = 0;
        $results = [];

        foreach ($this->tables as $table) {
            // 1) Data dengan tenant_id nunjuk ke tenant yang ga ada
            $orphans = DB::table($table)
                ->whereNotNull('tenant_id')
                ->whereNotIn('tenant_id', $deletedTenantIds)
                ->count();

            if ($orphans > 0) {
                $deleted = DB::table($table)
                    ->whereNotNull('tenant_id')
                    ->whereNotIn('tenant_id', $deletedTenantIds)
                    ->delete();
                $totalDeleted += $deleted;
                $results[] = "  - {$table} (orphan): {$deleted} baris";
                $this->info("{$table}: {$deleted} orphan records deleted.");
            }

            // 2) Data dengan tenant_id IS NULL — dari deleted tenant yg nullOnDelete
            $nulls = DB::table($table)
                ->whereNull('tenant_id')
                ->count();

            if ($nulls > 0) {
                $deleted = DB::table($table)
                    ->whereNull('tenant_id')
                    ->delete();
                $totalDeleted += $deleted;
                $results[] = "  - {$table} (null tenant): {$deleted} baris";
                $this->info("{$table}: {$deleted} null-tenant records deleted.");
            }

            if ($orphans === 0 && $nulls === 0) {
                $this->line("{$table}: bersih.");
            }
        }

        $this->newLine();
        $this->info("✅ Selesai! Total {$totalDeleted} data orphan berhasil dibersihkan.");

        if (!empty($results)) {
            $this->table(['Tabel', 'Dihapus'], array_map(fn($r) => explode(': ', $r), $results));
        }

        return Command::SUCCESS;
    }
}
