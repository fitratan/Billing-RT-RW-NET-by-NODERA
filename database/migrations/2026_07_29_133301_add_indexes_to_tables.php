<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'tenants' => ['is_active', 'expired_at', 'created_at'],
        'users' => ['tenant_id', 'role', 'is_active'],
        'customers' => ['tenant_id', 'is_active', 'created_at'],
        'invoices' => ['tenant_id', 'paid', 'status', 'paid_at', 'period', 'created_at'],
        'payment_transactions' => ['tenant_id', 'status', 'created_at', 'method'],
        'expenses' => ['tenant_id', 'date', 'category'],
        'registration_requests' => ['status', 'slug', 'created_at'],
        'packages' => ['type', 'is_active'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $col) {
                $indexName = "idx_{$table}_{$col}";
                if (!Schema::hasColumn($table, $col) || Schema::hasIndex($table, $indexName)) {
                    continue;
                }
                try {
                    Schema::table($table, function (Blueprint $t) use ($col, $indexName) {
                        $t->index($col, $indexName);
                    });
                } catch (\Throwable $e) {
                    // Index already exists
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $col) {
                    $indexName = "idx_{$table}_{$col}";
                    try {
                        $t->dropIndex($indexName);
                    } catch (\Throwable $e) {
                        // skip if index doesn't exist
                    }
                }
            });
        }
    }
};
