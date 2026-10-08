<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tables that currently don't have tenant_id column
    // but need it for multi-tenant isolation
    private array $tables = [
        'agents',          // uses TenantAware trait
        'attendance',      // per-tenant employee attendance
        'collectors',      // uses TenantAware trait
        'customers',       // uses TenantAware trait
        'inventory_categories', // per-tenant inventory
        'inventory_items', // uses TenantAware trait
        'odp_locations',   // per-tenant ODP data
        'olts',            // uses TenantAware trait
        'onu_locations',   // per-tenant ONU map data
        'onus',            // per-tenant ONU devices
        'payment_gateway_settings', // per-tenant payment gateway config
        'payroll',         // per-tenant payroll
        'revenue_reports', // per-tenant revenue reports
        'vouchers',        // per-tenant vouchers
        'expenses',        // per-tenant expenses (used by Expense model)
        'invoices',        // per-tenant invoices (used by Invoice model)
    ];

    public function up(): void
    {
        foreach ($this->tables as $tbl) {
            if (Schema::hasTable($tbl) && !Schema::hasColumn($tbl, 'tenant_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->foreignId('tenant_id')
                        ->nullable()
                        ->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
