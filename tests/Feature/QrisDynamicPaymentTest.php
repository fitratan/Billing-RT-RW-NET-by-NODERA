<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\QrisDynamicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QrisDynamicPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $sampleGoPayQris = '00020101021126600014ID.GOPAY.WWW01189360089800012345670211GOPAY1234560303UMI5204481453033605802ID5914NODERA NETWORK6007JAKARTA61051234563044C81';

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Global / Default QRIS Gateway
        PaymentGateway::setConfig('manual', [
            'qris_raw_string'      => $this->sampleGoPayQris,
            'merchant_name'        => 'NODERA NETWORK',
            'merchant_city'        => 'JAKARTA',
            'enable_dynamic_qris'  => true,
            'enable_unique_code'   => true,
            'webhook_secret'       => 'test_secret_123',
            'qris_timeout_minutes' => 30,
        ]);
    }

    public function test_can_process_dynamic_qris_for_unpaid_invoice(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
        ]);

        $invoice = Invoice::create([
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'invoice_number' => 'INV-2026-TEST-001',
            'amount'         => 150000,
            'paid'           => false,
            'status'         => 'pending',
            'due_date'       => now()->addDays(7),
        ]);

        $paymentService = app(PaymentService::class);
        $result = $paymentService->processDynamicQris($invoice);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_dynamic']);
        $this->assertNotNull($result['qris_string']);
        $this->assertNotNull($result['qris_svg']);
        $this->assertGreaterThan(150000, $result['unique_amount']);
        $this->assertGreaterThan(0, $result['unique_code']);

        // Verify that the generated QR string contains the exact unique amount
        $tlvs = QrisDynamicService::parse($result['qris_string']);
        $this->assertEquals((string) (int) $result['unique_amount'], $tlvs['54']);
        $this->assertEquals('12', $tlvs['01']); // Dynamic mode
        $this->assertTrue(QrisDynamicService::validateCrc($result['qris_string']));
    }

    public function test_qris_webhook_auto_settles_invoice_from_gobiz_notification(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Ahmad Pelanggan',
            'phone' => '081987654321',
            'status' => 'isolated',
        ]);

        $invoice = Invoice::create([
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'invoice_number' => 'INV-2026-TEST-002',
            'amount'         => 200000,
            'paid'           => false,
            'status'         => 'pending',
            'due_date'       => now()->addDays(7),
        ]);

        // Generate dynamic QRIS to assign unique code e.g. 200150
        $payData = app(PaymentService::class)->processDynamicQris($invoice);
        $uniqueAmount = $payData['unique_amount'];

        // Simulate incoming webhook from Android GoBiz forwarder
        $response = $this->postJson('/api/v1/payments/qris/callback', [
            'secret'      => 'test_secret_123',
            'amount'      => $uniqueAmount,
            'raw_message' => "GoBiz: Pembayaran Diterima Rp " . number_format($uniqueAmount, 0, ',', '.') . " dari QRIS",
            'sender'      => 'GoBiz',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'invoice' => 'INV-2026-TEST-002',
        ]);

        // Check invoice is marked paid
        $invoice->refresh();
        $this->assertTrue((bool) $invoice->paid);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals('qris_gopay', $invoice->payment_method);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_realtime_status_polling_endpoint(): void
    {
        $customer = Customer::factory()->create(['name' => 'Status Checker']);
        $invoice = Invoice::create([
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'invoice_number' => 'INV-POLL-001',
            'amount'         => 100000,
            'paid'           => false,
            'status'         => 'pending',
            'due_date'       => now()->addDays(5),
        ]);

        // Poll when unpaid
        $res1 = $this->getJson("/api/v1/payments/qris/status/{$invoice->id}");
        $res1->assertStatus(200);
        $res1->assertJson(['paid' => false, 'status' => 'pending']);

        // Mark as paid
        $invoice->update(['paid' => true, 'status' => 'paid', 'paid_at' => now()]);

        // Poll when paid
        $res2 = $this->getJson("/api/v1/payments/qris/status/{$invoice->id}");
        $res2->assertStatus(200);
        $res2->assertJson(['paid' => true, 'status' => 'paid']);
    }

    public function test_admin_can_run_simulator_test_dynamic_generation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['admin_role' => 'admin', 'admin_id' => $admin->id])
            ->postJson('/admin/payments/qris/test-dynamic', [
                'amount' => 75500,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'amount'  => 75500,
        ]);
        $this->assertNotNull($response->json('qr_svg'));
        $this->assertNotNull($response->json('dynamic_string'));
    }

    public function test_admin_can_run_simulator_with_custom_raw_string_and_formatted_amount(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $customBcaQris = '"00020101021126610014COM.GO-JEK.WWW01189360091432618722690210G2618722690303UMI51440014ID.CO.QRIS.WWW0215ID10243662118850303UMI5204504553033605802ID5925CV. Digital Network Solut6009SITUBONDO61056835162070703A01630465BC"';

        $response = $this->actingAs($admin)
            ->withSession(['admin_role' => 'admin', 'admin_id' => $admin->id])
            ->postJson('/admin/payments/qris/test-dynamic', [
                'amount'     => '150.000',
                'raw_string' => $customBcaQris,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'amount'  => 150000,
        ]);
        $this->assertNotNull($response->json('qr_svg'));
        $this->assertNotNull($response->json('dynamic_string'));
        $this->assertEquals('CV. Digital Network Solut', $response->json('merchant_info.merchant_name'));
        $this->assertEquals('SITUBONDO', $response->json('merchant_info.merchant_city'));
    }
}
