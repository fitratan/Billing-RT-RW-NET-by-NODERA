<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Invoices Additional Indexes
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'tenant_id') && Schema::hasColumn('invoices', 'paid') && Schema::hasColumn('invoices', 'due_date')) {
                    $table->index(['tenant_id', 'paid', 'due_date'], 'idx_inv_tenant_paid_due');
                }
                if (Schema::hasColumn('invoices', 'tenant_id') && Schema::hasColumn('invoices', 'paid') && Schema::hasColumn('invoices', 'created_at')) {
                    $table->index(['tenant_id', 'paid', 'created_at'], 'idx_inv_tenant_paid_created');
                }
            });
        }

        // 2. Payments Additional Indexes
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'tenant_id') && Schema::hasColumn('payments', 'payment_date') && Schema::hasColumn('payments', 'status')) {
                    $table->index(['tenant_id', 'payment_date', 'status'], 'idx_pay_tenant_date_status');
                }
                if (Schema::hasColumn('payments', 'tenant_id') && Schema::hasColumn('payments', 'invoice_id') && Schema::hasColumn('payments', 'created_at')) {
                    $table->index(['tenant_id', 'invoice_id', 'created_at'], 'idx_pay_tenant_inv_created');
                }
            });
        }

        // 3. Customers Additional Indexes
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (Schema::hasColumn('customers', 'tenant_id') && Schema::hasColumn('customers', 'pppoe_username') && Schema::hasColumn('customers', 'is_active')) {
                    $table->index(['tenant_id', 'pppoe_username', 'is_active'], 'idx_cust_tenant_pppoe_active');
                }
                if (Schema::hasColumn('customers', 'tenant_id') && Schema::hasColumn('customers', 'is_active') && Schema::hasColumn('customers', 'status')) {
                    $table->index(['tenant_id', 'is_active', 'status'], 'idx_cust_tenant_active_status');
                }
            });
        }

        // 4. Vouchers Additional Indexes
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                if (Schema::hasColumn('vouchers', 'tenant_id') && Schema::hasColumn('vouchers', 'is_active') && Schema::hasColumn('vouchers', 'used')) {
                    $table->index(['tenant_id', 'is_active', 'used'], 'idx_vouch_tenant_active_used');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropIndex('idx_inv_tenant_paid_due');
                $table->dropIndex('idx_inv_tenant_paid_created');
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('idx_pay_tenant_date_status');
                $table->dropIndex('idx_pay_tenant_inv_created');
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('idx_cust_tenant_pppoe_active');
                $table->dropIndex('idx_cust_tenant_active_status');
            });
        }

        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropIndex('idx_vouch_tenant_active_used');
            });
        }
    }
};
