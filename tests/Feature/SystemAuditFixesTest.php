<?php

namespace Tests\Feature;

use App\Actions\Finance\GenerateSubscriptionInvoice;
use App\Jobs\SendWhatsappNotification;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentGateway;
use App\Models\ShopOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NominalUnikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_whatsapp_notification_job_runs_without_undefined_variable_errors(): void
    {
        $tenant = Tenant::factory()->create();
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'phone' => '081234567890',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'invoice_number' => 'INV-TEST-WA',
            'amount' => 150000,
            'due_date' => now()->addDays(5),
            'period' => date('Y-m'),
            'status' => 'pending',
            'paid' => false,
        ]);

        $job = new SendWhatsappNotification($customer, $invoice, 'payment_success');

        // Should execute handle() cleanly without throwing ErrorException (undefined variable)
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_generate_subscription_invoice_creates_and_prevents_duplicates(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'ISP Alpha',
            'settings' => ['subscribed_price' => 250000],
        ]);

        $action = new GenerateSubscriptionInvoice();
        $inv = $action->execute($tenant, '2026-08', 250000);

        $this->assertNotNull($inv);
        $this->assertEquals(250000, $inv->amount);
        $this->assertEquals('ISP Alpha', $inv->customer_name);
        $this->assertNull($inv->tenant_id);

        // Attempting to generate duplicate for same tenant and period must throw RuntimeException
        $this->expectException(\RuntimeException::class);
        $action->execute($tenant, '2026-08', 250000);
    }

    public function test_nominal_unik_service_assigns_distinct_codes(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $invoice1 = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-UNIK-1',
            'amount' => 100000,
            'due_date' => now()->addDays(5),
            'period' => '2026-08',
            'status' => 'pending',
            'paid' => false,
        ]);

        $invoice2 = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-UNIK-2',
            'amount' => 100000,
            'due_date' => now()->addDays(5),
            'period' => '2026-08',
            'status' => 'pending',
            'paid' => false,
        ]);

        $service = new NominalUnikService();
        $res1 = $service->assign($invoice1);
        $res2 = $service->assign($invoice2);

        $this->assertNotEquals($res1['code'], $res2['code']);
        $this->assertEquals(1, $res1['code']);
        $this->assertEquals(2, $res2['code']);
        $this->assertEquals(100001, $res1['amount']);
        $this->assertEquals(100002, $res2['amount']);
    }

    public function test_tenant_shop_order_status_endpoint_returns_json(): void
    {
        $tenant = Tenant::factory()->create();
        $order = ShopOrder::create([
            'tenant_id' => $tenant->id,
            'order_number' => 'ORD-TEST-999',
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'shipping_address' => 'Jl. Merdeka No. 1',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'payment_method' => 'manual',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'voucher_username' => 'user123',
            'voucher_password' => 'pass123',
            'voucher_profile' => '10M',
        ]);

        $response = $this->getJson("/tenant-shop/order/{$order->order_number}/status");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'order_number' => 'ORD-TEST-999',
            'is_paid' => true,
            'voucher' => [
                'username' => 'user123',
                'password' => 'pass123',
                'profile' => '10M',
            ],
        ]);
    }

    public function test_international_webhooks_respond_ok(): void
    {
        $response1 = $this->postJson('/webhook/cinetpay', ['cpm_trans_id' => '123']);
        $response1->assertStatus(200);

        $response2 = $this->postJson('/webhook/wave', ['id' => 'wave_123']);
        $response2->assertStatus(200);

        $response3 = $this->postJson('/webhook/paytech', ['item_price' => 100]);
        $response3->assertStatus(200);

        $response4 = $this->postJson('/webhook/fedapay', ['entity' => 'transaction']);
        $response4->assertStatus(200);
    }

    public function test_payment_gateway_controller_saves_configs(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['admin_id' => $admin->id, 'admin_role' => 'admin', 'tenant_id' => $tenant->id])
            ->post('/admin/payments/gateway/cinetpay/save', [
                'CINETPAY_API_KEY' => 'key_123',
                'CINETPAY_SITE_ID' => 'site_123',
                'CINETPAY_SECRET_KEY' => 'secret_123',
            ]);

        $response->assertRedirect('/admin/payments/gateway');

        $cfg = PaymentGateway::getConfig('cinetpay');
        $this->assertEquals('key_123', $cfg['CINETPAY_API_KEY']);
    }
}
