<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\BillingEngineService;
use App\Services\CronService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAccumulationPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_accumulates_periods_instead_of_creating_duplicate_card(): void
    {
        $tenant = Tenant::create([
            'name' => 'ISP Test',
            'slug' => 'isp-test',
            'is_active' => true,
        ]);

        $package = Package::create([
            'name' => '10 Mbps',
            'price' => 150000,
            'tenant_id' => $tenant->id,
            'profile_normal' => 'default',
            'profile_isolir' => 'isolir',
        ]);

        $customer = Customer::create([
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'status' => 'active',
            'package_id' => $package->id,
            'tenant_id' => $tenant->id,
            'isolation_date' => 20,
        ]);

        $billingEngine = app(BillingEngineService::class);

        // 1. Generate August 2026 invoice
        $inv1 = $billingEngine->generateSingleCustomerInvoice($customer, '2026-08');
        $this->assertNotNull($inv1);
        $this->assertEquals(150000, (float) $inv1->amount);
        $this->assertEquals('2026-08', $inv1->period);
        $this->assertCount(1, Invoice::where('customer_id', $customer->id)->get());

        $bd1 = json_decode($inv1->periods_breakdown, true);
        $this->assertCount(1, $bd1);
        $this->assertEquals('2026-08', $bd1[0]['period']);

        // 2. Generate September 2026 invoice while August is unpaid
        $inv2 = $billingEngine->generateSingleCustomerInvoice($customer, '2026-09');
        $this->assertNotNull($inv2);
        // It must return the same invoice instance (accumulated)
        $this->assertEquals($inv1->id, $inv2->id);

        // Total invoices in DB for this customer MUST STILL BE 1 (No duplicate card!)
        $this->assertCount(1, Invoice::where('customer_id', $customer->id)->get());

        $invRefreshed = $inv1->fresh();
        $this->assertEquals(300000, (float) $invRefreshed->amount);
        $this->assertEquals('2026-09', $invRefreshed->period);

        $bd2 = json_decode($invRefreshed->periods_breakdown, true);
        $this->assertCount(2, $bd2);
        $this->assertEquals('2026-08', $bd2[0]['period']);
        $this->assertEquals('2026-09', $bd2[1]['period']);

        // 3. Generate October 2026 invoice
        $inv3 = $billingEngine->generateSingleCustomerInvoice($customer, '2026-10');
        $this->assertNotNull($inv3);
        $this->assertCount(1, Invoice::where('customer_id', $customer->id)->get());

        $invRefreshed2 = $inv1->fresh();
        $this->assertEquals(450000, (float) $invRefreshed2->amount);
        $bd3 = json_decode($invRefreshed2->periods_breakdown, true);
        $this->assertCount(3, $bd3);

        // 4. Mark invoice as paid
        $invRefreshed2->update(['paid' => true, 'status' => 'paid']);

        // 5. Generate November 2026 invoice -> Since previous is paid, create a new fresh card
        $inv4 = $billingEngine->generateSingleCustomerInvoice($customer, '2026-11');
        $this->assertNotNull($inv4);
        $this->assertNotEquals($inv1->id, $inv4->id);
        $this->assertEquals(150000, (float) $inv4->amount);
        $this->assertCount(2, Invoice::where('customer_id', $customer->id)->get());
    }

    public function test_consolidate_merges_existing_duplicate_pending_invoices(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-20');

        $tenant = Tenant::create([
            'name' => 'ISP Test 2',
            'slug' => 'isp-test-2',
            'is_active' => true,
        ]);

        $package = Package::create([
            'name' => '20 Mbps',
            'price' => 200000,
            'tenant_id' => $tenant->id,
            'profile_normal' => 'default',
            'profile_isolir' => 'isolir',
        ]);

        $customer = Customer::create([
            'name' => 'Siti Aminah',
            'phone' => '08129876543',
            'status' => 'active',
            'package_id' => $package->id,
            'tenant_id' => $tenant->id,
            'isolation_date' => 20,
        ]);

        // Create 2 separate pending invoices manually (simulating prior duplicate bug)
        $invA = Invoice::create([
            'invoice_number' => 'INV-202607-1',
            'customer_id' => $customer->id,
            'amount' => 200000,
            'period' => '2026-07',
            'due_date' => '2026-07-20',
            'status' => 'pending',
            'paid' => false,
            'tenant_id' => $tenant->id,
        ]);

        $invB = Invoice::create([
            'invoice_number' => 'INV-202608-1',
            'customer_id' => $customer->id,
            'amount' => 200000,
            'period' => '2026-08',
            'due_date' => '2026-08-20',
            'status' => 'pending',
            'paid' => false,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertCount(2, Invoice::where('customer_id', $customer->id)->get());

        // Run consolidation
        $merged = CronService::consolidatePendingInvoices($tenant->id);
        $this->assertEquals(1, $merged);

        // Should now be only 1 invoice in DB with 400000 amount & 2 periods in breakdown
        $invoices = Invoice::where('customer_id', $customer->id)->get();
        $this->assertCount(1, $invoices);

        $mergedInv = $invoices->first();
        $this->assertEquals(400000, (float) $mergedInv->amount);
        $bd = json_decode($mergedInv->periods_breakdown, true);
        $this->assertCount(2, $bd);
        $this->assertEquals('2026-07', $bd[0]['period']);
        $this->assertEquals('2026-08', $bd[1]['period']);

        \Carbon\Carbon::setTestNow();
    }
}
