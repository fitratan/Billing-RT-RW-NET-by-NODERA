<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addIndexIfNotExists('invoices', ['tenant_id', 'paid', 'due_date'], 'idx_invoices_tenant_paid_due');
        $this->addIndexIfNotExists('invoices', ['customer_id', 'paid'], 'idx_invoices_customer_paid');
        $this->addIndexIfNotExists('invoices', ['due_date', 'paid'], 'idx_invoices_due_paid');
        $this->addIndexIfNotExists('invoices', ['status'], 'idx_invoices_status');

        $this->addIndexIfNotExists('customers', ['tenant_id', 'status', 'router_id'], 'idx_customers_tenant_status_router');
        $this->addIndexIfNotExists('customers', ['pppoe_username'], 'idx_customers_pppoe');
        $this->addIndexIfNotExists('customers', ['collector_id'], 'idx_customers_collector_id');
        $this->addIndexIfNotExists('customers', ['code'], 'idx_customers_code');

        $this->addIndexIfNotExists('trouble_tickets', ['tenant_id', 'status'], 'idx_tickets_tenant_status');
        $this->addIndexIfNotExists('trouble_tickets', ['assigned_to', 'status'], 'idx_tickets_assigned_status');
        $this->addIndexIfNotExists('trouble_tickets', ['router_id', 'status'], 'idx_tickets_router_status');

        $this->addIndexIfNotExists('audit_logs', ['tenant_id', 'created_at'], 'idx_audit_logs_tenant_created');
        $this->addIndexIfNotExists('audit_logs', ['entity_type', 'entity_id'], 'idx_audit_logs_entity');

        $this->addIndexIfNotExists('client_logs', ['tenant_id', 'created_at'], 'idx_client_logs_tenant_created');
    }

    private function addIndexIfNotExists(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        // Verify columns exist
        foreach ($columns as $col) {
            if (! Schema::hasColumn($tableName, $col)) {
                return;
            }
        }

        try {
            // Check if index already exists in table
            $indexes = collect(DB::select("SHOW INDEX FROM `{$tableName}`"))->pluck('Key_name')->unique();
            if ($indexes->contains($indexName)) {
                return;
            }

            $colsFormatted = '`' . implode('`, `', $columns) . '`';
            DB::statement("ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` ({$colsFormatted})");
        } catch (\Throwable $e) {
            // If MySQL error or already exists, catch silently
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'invoices' => ['idx_invoices_tenant_paid_due', 'idx_invoices_customer_paid', 'idx_invoices_due_paid', 'idx_invoices_status'],
            'customers' => ['idx_customers_tenant_status_router', 'idx_customers_pppoe', 'idx_customers_collector_id', 'idx_customers_code'],
            'trouble_tickets' => ['idx_tickets_tenant_status', 'idx_tickets_assigned_status', 'idx_tickets_router_status'],
            'audit_logs' => ['idx_audit_logs_tenant_created', 'idx_audit_logs_entity'],
            'client_logs' => ['idx_client_logs_tenant_created'],
        ];

        foreach ($tables as $tableName => $indexes) {
            if (Schema::hasTable($tableName)) {
                foreach ($indexes as $idx) {
                    try {
                        DB::statement("ALTER TABLE `{$tableName}` DROP INDEX `{$idx}`");
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }
        }
    }
};
