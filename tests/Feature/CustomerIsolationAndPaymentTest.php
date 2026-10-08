<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IsolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIsolationAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_customer_paying_invoice_does_not_kick_connection(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_normal' => '10M_PROFILE',
            'profile_isolir' => 'ISOLIR_PROFILE',
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'status' => 'active',
            'pppoe_username' => 'test_user_active',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'invoice_number' => 'INV-TEST-001',
            'amount' => 150000,
            'due_date' => now()->addDays(5),
            'period' => date('Y-m'),
            'status' => 'pending',
            'paid' => false,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['admin_id' => $admin->id, 'admin_role' => 'admin', 'tenant_id' => $tenant->id])
            ->post("/admin/billing/pay/{$invoice->id}");

        $response->assertStatus(302);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
        $invoice->refresh();
        $this->assertTrue((bool)$invoice->paid);
    }

    public function test_isolated_customer_paying_invoice_restores_and_unisolates(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_normal' => '10M_PROFILE',
            'profile_isolir' => 'ISOLIR_PROFILE',
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'status' => 'isolated',
            'pppoe_username' => 'test_user_isolated',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'invoice_number' => 'INV-TEST-002',
            'amount' => 150000,
            'due_date' => now()->addDays(5),
            'period' => date('Y-m'),
            'status' => 'pending',
            'paid' => false,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['admin_id' => $admin->id, 'admin_role' => 'admin', 'tenant_id' => $tenant->id])
            ->post("/admin/billing/pay/{$invoice->id}");

        $response->assertStatus(302);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
        $invoice->refresh();
        $this->assertTrue((bool)$invoice->paid);
    }
}
