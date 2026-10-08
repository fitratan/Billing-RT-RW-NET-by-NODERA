<?php

namespace App\Actions\Tenant;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\Agent;
use App\Models\Attendance;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\CustomerUsage;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Payroll;
use App\Models\RevenueReport;
use App\Models\TroubleTicket;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Cache;

class DeleteTenant
{
    public function execute(Tenant $tenant): void
    {
        $tenantId = $tenant->id;

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'tenant.deleted',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'old_values' => ['name' => $tenant->name, 'slug' => $tenant->slug],
        ]);

        // Cascade hapus semua data turunan tenant
        $this->cascadeDelete($tenantId);

        // Hapus semua cache terkait tenant ini
        Cache::forget('superadmin.dashboard.stats');
        Cache::forget('superadmin.dashboard.recent_tenants');
        // Hapus semua cache finance (semua bulan/tahun)
        $this->clearFinanceCache();

        $tenant->delete();
    }

    private function cascadeDelete(int $tenantId): void
    {
        try {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } catch (\Throwable $e) {}

        $tables = [
            'payment_transactions',
            'payments',
            'invoices',
            'trouble_tickets',
            'expenses',
            'revenue_reports',
            'customer_usages',
            'attendances',
            'payrolls',
            'customers',
            'collectors',
            'agents',
            'cashier_transactions',
            'cashier_shifts',
            'cashiers',
            'users',
            'vouchers',
            'voucher_packages',
            'packages',
            'mikrotiks',
            'olts',
            'onus',
            'odp_locations',
            'onu_locations',
            'inventory_items',
            'inventory_categories',
            'payment_gateways',
            'settings',
            'dashboard_menu_settings',
            'sidebar_settings',
            'whatsapp_templates',
            'client_logs',
            'bank_accounts',
            'promo_slides',
            'mikhmon_subscriptions',
            'radcheck',
            'radreply',
            'radusergroup',
            'radacct',
            'radpostauth',
            'nas',
        ];

        foreach ($tables as $table) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'tenant_id')) {
                        \Illuminate\Support\Facades\DB::table($table)->where('tenant_id', $tenantId)->delete();
                    }
                }
            } catch (\Throwable $ignored) {}
        }

        try {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } catch (\Throwable $e) {}
    }

    private function clearFinanceCache(): void
    {
        // Cache superadmin.finance.YYYY.MM — hapus semua pakai pattern
        $cacheStore = Cache::store();
        // Attempt cache key prefix matching
        try {
            // Laravel file/database cache - iterate known months
            $currentYear = date('Y');
            for ($y = $currentYear - 3; $y <= $currentYear; $y++) {
                for ($m = 1; $m <= 12; $m++) {
                    $month = str_pad($m, 2, '0', STR_PAD_LEFT);
                    Cache::forget("superadmin.finance.{$y}.{$month}");
                }
            }
        } catch (\Exception $e) {
            // silent — best effort
        }
    }
}
