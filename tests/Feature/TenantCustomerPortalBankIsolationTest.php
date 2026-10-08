<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantCustomerPortalBankIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_customer_portal_shows_only_tenant_bank_accounts_never_superadmin(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'ISP Mitra Maju']);

        // Superadmin bank account (tenant_id is null)
        $superadminBank = BankAccount::create([
            'bank_name' => 'BCA Superadmin',
            'account_number' => '9999-SUPERADMIN',
            'account_name' => 'PT NODERA SUPERADMIN',
            'tenant_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Tenant bank account
        $tenantBank = BankAccount::create([
            'bank_name' => 'BRI Mitra',
            'account_number' => '1111-TENANT',
            'account_name' => 'ISP Mitra Maju',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pelanggan Budi',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'invoice_number' => 'INV-TENANT-001',
            'amount' => 150000,
            'due_date' => now()->addDays(5),
            'period' => date('Y-m'),
            'status' => 'pending',
            'paid' => false,
        ]);

        $response = $this->withSession([
            'customer_id' => $customer->id,
            'customer_tenant_id' => $tenant->id,
            'tenant_id' => $tenant->id,
            'logged_in' => true,
        ])->get("/portal/payment/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Payment')
            ->has('bankAccounts', 1)
            ->where('bankAccounts.0.account_number', '1111-TENANT')
            ->where('bankAccounts.0.bank_name', 'BRI Mitra')
        );
    }

    public function test_tenant_customer_with_no_tenant_banks_gets_empty_list_not_superadmin_fallback(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'ISP Tanpa Rekening']);

        // Superadmin bank account
        BankAccount::create([
            'bank_name' => 'BCA Superadmin',
            'account_number' => '9999-SUPERADMIN',
            'account_name' => 'PT NODERA SUPERADMIN',
            'tenant_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Superadmin QRIS
        PaymentGateway::create([
            'gateway' => 'manual',
            'tenant_id' => null,
            'config_json' => ['qris_image' => 'superadmin_qris.png'],
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pelanggan Siti',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'invoice_number' => 'INV-TENANT-002',
            'amount' => 100000,
            'due_date' => now()->addDays(5),
            'period' => date('Y-m'),
            'status' => 'pending',
            'paid' => false,
        ]);

        $response = $this->withSession([
            'customer_id' => $customer->id,
            'customer_tenant_id' => $tenant->id,
            'tenant_id' => $tenant->id,
            'logged_in' => true,
        ])->get("/portal/payment/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Payment')
            ->has('bankAccounts', 0)
            ->where('qrisImageUrl', null)
        );
    }

    public function test_customer_with_null_tenant_id_resolves_from_invoice_and_never_leaks_superadmin_bank(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'ISP Mitra Maju']);

        BankAccount::create([
            'bank_name' => 'BCA Superadmin',
            'account_number' => '9999-SUPERADMIN',
            'account_name' => 'PT NODERA SUPERADMIN',
            'tenant_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        BankAccount::create([
            'bank_name' => 'BRI Mitra',
            'account_number' => '1111-TENANT',
            'account_name' => 'ISP Mitra Maju',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Customer created without tenant_id (legacy or un-scoped)
        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Pelanggan Null Tenant',
            'phone' => '08123456789',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'invoice_number' => 'INV-TENANT-003',
            'amount' => 200000,
            'due_date' => now()->addDays(5),
            'period' => date('Y-m'),
            'status' => 'pending',
            'paid' => false,
        ]);

        $response = $this->withSession([
            'customer_id' => $customer->id,
            'logged_in' => true,
        ])->get("/portal/payment/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Payment')
            ->has('bankAccounts', 1)
            ->where('bankAccounts.0.account_number', '1111-TENANT')
        );
    }
}
