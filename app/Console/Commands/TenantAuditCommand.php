<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantAuditCommand extends Command
{
    protected $signature = 'tenant:audit {--fix : Terapkan perbaikan otomatis yang aman}';

    protected $description = 'Audit & perbaiki data lintas tenant (kebocoran periode lama).';

    /** Model TenantAware + kolom relasi yang bisa dipakai menurunkan tenant yang benar. */
    protected array $models = [
        'customers'          => ['model' => \App\Models\Customer::class, 'parent' => 'router_id', 'parent_model' => \App\Models\Mikrotik::class, 'parent_tenant' => 'tenant_id'],
        'invoices'           => ['model' => \App\Models\Invoice::class, 'parent' => 'customer_id', 'parent_model' => \App\Models\Customer::class, 'parent_tenant' => 'tenant_id'],
        'trouble_tickets'    => ['model' => \App\Models\TroubleTicket::class, 'parent' => 'customer_id', 'parent_model' => \App\Models\Customer::class, 'parent_tenant' => 'tenant_id'],
        'mikrotiks'          => ['model' => \App\Models\Mikrotik::class],
        'packages'           => ['model' => \App\Models\Package::class],
        'olts'               => ['model' => \App\Models\Olt::class],
        'onus'               => ['model' => \App\Models\Onu::class, 'parent' => 'customer_id', 'parent_model' => \App\Models\Customer::class, 'parent_tenant' => 'tenant_id'],
        'onu_locations'      => ['model' => \App\Models\OnuLocation::class],
        'vouchers'           => ['model' => \App\Models\Voucher::class],
        'expenses'           => ['model' => \App\Models\Expense::class],
    ];

    public function handle(): int
    {
        $this->info('=== AUDIT DATA LINTAS TENANT ===');
        $this->line('Tenant terdaftar: ' . \App\Models\Tenant::withoutGlobalScopes()->pluck('id')->implode(', '));
        $this->line('');

        $tenantIds = \App\Models\Tenant::withoutGlobalScopes()->pluck('id')->all();
        $totalFixable = 0;
        $totalManual = 0;

        foreach ($this->models as $table => $cfg) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $model = $cfg['model'];
            $query = $model::withoutGlobalScopes();
            $total = (clone $query)->count();
            if ($total === 0) {
                continue;
            }

            $nullTenant = (clone $query)->whereNull('tenant_id')->count();
            $invalidTenant = (clone $query)->whereNotNull('tenant_id')->whereNotIn('tenant_id', $tenantIds)->count();

            // Paket SaaS (type=subscription) memang GLOBAL — tenant_id null itu
            // sengaja (dipakai halaman register tenant).
            $saasGlobal = 0;
            if ($table === 'packages' && Schema::hasColumn($table, 'type')) {
                $saasGlobal = (clone $query)->whereNull('tenant_id')->where('type', 'subscription')->count();
                $nullTenant = max(0, $nullTenant - $saasGlobal);
            }

            // Mismatch dengan parent (customer→router, invoice→customer, dll)
            $mismatch = 0;
            $fixable = 0;
            if (isset($cfg['parent']) && Schema::hasColumn($table, $cfg['parent'])) {
                $rows = (clone $query)->whereNotNull($cfg['parent'])->get(['id', 'tenant_id', $cfg['parent']]);
                foreach ($rows as $r) {
                    $parent = $cfg['parent_model']::withoutGlobalScopes()->find($r->{$cfg['parent']});
                    if ($parent && $parent->tenant_id && (int) $parent->tenant_id !== (int) $r->tenant_id) {
                        $mismatch++;
                    }
                }
            }

            $status = 'OK';
            if ($nullTenant || $invalidTenant || $mismatch) {
                $status = '<error>MASALAH</error>';
                $fixable += $nullTenant + $invalidTenant;
            }
            $this->line(sprintf(
                "%-16s total=%-6d null_tenant=%-5d tenant_tak_terdaftar=%-4d mismatch_parent=%-4d %s",
                $table, $total, $nullTenant, $invalidTenant, $mismatch, $status
            ));

            $totalFixable += $fixable;
            $totalManual += max(0, ($nullTenant + $invalidTenant + $mismatch) - $fixable);
        }

        $this->line('');
        $this->info("Total yang bisa diperbaiki otomatis: {$totalFixable}");

        if (! $this->option('fix')) {
            $this->line('');
            $this->info('Jalankan dengan --fix untuk menerapkan perbaikan otomatis yang aman.');
            $this->line('  php artisan tenant:audit --fix');
            return self::SUCCESS;
        }

        $this->line('');
        $this->info('=== MENERAPKAN PERBAIKAN ===');
        $fixed = 0;

        // 1. Customer → ikuti tenant router-nya
        $fixed += $this->fixFromParent('customers', 'router_id', \App\Models\Mikrotik::class);

        // 2. Invoice → ikuti tenant customer-nya
        $fixed += $this->fixFromParent('invoices', 'customer_id', \App\Models\Customer::class);

        // 3. TroubleTicket → ikuti tenant customer-nya
        $fixed += $this->fixFromParent('trouble_tickets', 'customer_id', \App\Models\Customer::class);

        // 4. Onu → ikuti tenant customer-nya
        $fixed += $this->fixFromParent('onus', 'customer_id', \App\Models\Customer::class);

        $this->line('');
        $this->info("Total diperbaiki: {$fixed}");
        $this->warn('Model tanpa parent (mikrotiks/olts/onu_locations/vouchers/expenses) dengan tenant_id NULL TIDAK bisa diperbaiki otomatis — perlu review manual:');
        $manual = \App\Models\Mikrotik::withoutGlobalScopes()->whereNull('tenant_id')->get(['id', 'name', 'host']);
        foreach ($manual as $m) {
            $this->warn("  Mikrotik id={$m->id} name={$m->name} host={$m->host}");
        }
        $manualPkgs = \App\Models\Package::withoutGlobalScopes()->whereNull('tenant_id')->get(['id', 'name', 'type']);
        foreach ($manualPkgs as $p) {
            $this->warn("  Package id={$p->id} name={$p->name} type={$p->type} (subscription=global SaaS, aman)");
        }

        return self::SUCCESS;
    }

    /** Pindahkan record ke tenant parent-nya (parent harus punya tenant). */
    protected function fixFromParent(string $table, string $parentCol, string $parentModel): int
    {
        $model = $this->models[$table]['model'];
        $rows = $model::withoutGlobalScopes()
            ->where(function ($q) use ($table) {
                $q->whereNull('tenant_id');
            })
            ->orWhereNotNull($parentCol)
            ->get(['id', 'tenant_id', $parentCol]);

        $fixed = 0;
        foreach ($rows as $r) {
            if (! $r->{$parentCol}) {
                continue;
            }
            $parent = $parentModel::withoutGlobalScopes()->find($r->{$parentCol});
            if (! $parent || ! $parent->tenant_id) {
                continue;
            }
            if ((int) $parent->tenant_id !== (int) $r->tenant_id) {
                DB::table($table)->where('id', $r->id)->update(['tenant_id' => (int) $parent->tenant_id]);
                $fixed++;
            }
        }

        return $fixed;
    }
}
