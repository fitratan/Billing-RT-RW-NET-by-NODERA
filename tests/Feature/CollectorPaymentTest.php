<?php

namespace Tests\Feature;

use App\Models\Collector;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectorPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_collector_can_mark_invoice_as_paid(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'ispnet', 'is_active' => true]);
        $collector = Collector::create([
            'tenant_id' => $tenant->id,
            'name' => 'Budi Kolektor',
            'username' => 'budi',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'price' => 150000,
            'monthly_price' => 150000,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'collector_id' => $collector->id,
            'name' => 'Pelanggan Uji',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'invoice_number' => 'INV-TEST-001',
            'amount' => 150000,
            'status' => 'pending',
            'paid' => false,
            'due_date' => now()->addDays(5),
            'period' => date('Y-m'),
        ]);

        $response = $this->withSession([
            'collector_id' => $collector->id,
            'collector_name' => $collector->name,
            'collector_logged_in' => true,
            'tenant_id' => $tenant->id,
        ])->post("/kolektor/bayar/{$customer->id}");

        $response->assertSessionHas('msg');

        $invoice->refresh();
        $this->assertTrue((bool) $invoice->paid);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals('Budi Kolektor', $invoice->processed_by);
        $this->assertEquals($collector->id, $invoice->collector_id);
    }
}
