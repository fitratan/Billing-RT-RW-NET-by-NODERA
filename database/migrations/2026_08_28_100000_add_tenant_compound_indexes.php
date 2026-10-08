<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Invoices Compound Indexes
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $hasDeleted = Schema::hasColumn('invoices', 'deleted_at');

                if (Schema::hasColumn('invoices', 'tenant_id') && Schema::hasColumn('invoices', 'status') && Schema::hasColumn('invoices', 'due_date')) {
                    $cols = ['tenant_id', 'status', 'due_date'];
                    if ($hasDeleted) {
                        $cols[] = 'deleted_at';
                    }
                    $table->index($cols, 'idx_inv_tenant_status_due');
                }

                if (Schema::hasColumn('invoices', 'tenant_id') && Schema::hasColumn('invoices', 'customer_id') && Schema::hasColumn('invoices', 'created_at')) {
                    $cols2 = ['tenant_id', 'customer_id', 'created_at'];
                    if ($hasDeleted) {
                        $cols2[] = 'deleted_at';
                    }
                    $table->index($cols2, 'idx_inv_tenant_cust_created');
                }
            });
        }

        // 2. Customers Compound Indexes
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $hasDeleted = Schema::hasColumn('customers', 'deleted_at');

                if (Schema::hasColumn('customers', 'tenant_id') && Schema::hasColumn('customers', 'status') && Schema::hasColumn('customers', 'router_id')) {
                    $cols = ['tenant_id', 'status', 'router_id'];
                    if ($hasDeleted) {
                        $cols[] = 'deleted_at';
                    }
                    $table->index($cols, 'idx_cust_tenant_status_router');
                }
            });
        }

        // 3. Payment Transactions Compound Indexes
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('payment_transactions', 'tenant_id') && Schema::hasColumn('payment_transactions', 'method')) {
                    $table->index(['tenant_id', 'method', 'created_at'], 'idx_tx_tenant_method_created');
                }
            });
        }

        // 4. Audit Logs Compound Index
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('audit_logs', 'tenant_id') && Schema::hasColumn('audit_logs', 'action')) {
                    $table->index(['tenant_id', 'action', 'created_at'], 'idx_audit_tenant_action_created');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_inv_tenant_status_due');
            $table->dropIndex('idx_inv_tenant_cust_created');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_cust_tenant_status_router');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_tx_tenant_method_created');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_tenant_action_created');
        });
    }
};
