<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Package;
use App\Models\RegistrationRequest;
use App\Models\ShopOrder;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\VpnUser;
use App\Models\NoderaPaySubscription;
use App\Services\QrisDynamicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrisAllWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    private string $validStaticQris = '00020101021126610014COM.GO-JEK.WWW01189360091432618722690210G2618722690303UMI51440014ID.CO.QRIS.WWW0215ID10243662118850303UMI5204504553033605802ID5925CV. Digital Network Solut6009SITUBONDO61056835162070703A01630465BC';

    private NoderaPaySubscription $sub;

    protected function setUp(): void
    {
        parent::setUp();

        $vpnUser = VpnUser::create([
            'name'     => 'Merchant Admin',
            'username' => 'merchantadmin',
            'email'    => 'merchant@dgtlnet.com',
            'password' => bcrypt('password'),
            'saldo'    => 0,
        ]);

        $this->sub = NoderaPaySubscription::create([
            'vpn_user_id'     => $vpnUser->id,
            'name'            => 'NODERA Testing Merchant',
            'api_key'         => 'np_live_testapikey123456789',
            'secret_key'      => 'device_secret_key_test_9999',
            'merchant_name'   => 'CV. Digital Network Solut',
            'qris_raw_string' => $this->validStaticQris,
            'status'          => 'ACTIVE',
            'expires_at'      => now()->addYear(),
        ]);
    }

    public function test_tenant_registration_creates_dynamic_qris_and_auto_settles(): void
    {
        $package = Package::create([
            'name'          => 'Paket Dedicated S',
            'slug'          => 'dedicated-s',
            'type'          => 'subscription',
            'price'         => 150000,
            'monthly_price' => 150000,
            'max_customers' => 100,
            'is_active'     => true,
        ]);

        $res = $this->post('/register', [
            'name'             => 'PT Solusi Fiber Net',
            'company'          => 'PT Solusi Fiber Net',
            'slug'             => 'solusinet',
            'email'            => 'admin@solusinet.id',
            'phone'            => '081234567890',
            'username'         => 'solusiadmin',
            'password'         => 'Secret123!',
            'package_id'       => $package->id,
            'duration'         => 1,
            'payment_method'   => 'qris',
        ]);

        $res->assertRedirect('/register/sukses?slug=solusinet');

        $reg = RegistrationRequest::where('slug', 'solusinet')->first();
        $this->assertNotNull($reg);
        $this->assertGreaterThan(0, $reg->unique_code);
        $this->assertGreaterThan(150000, $reg->total_amount);
        $this->assertNotNull($reg->dynamic_qris_string);
        $this->assertStringStartsWith('000201', $reg->dynamic_qris_string);
        $this->assertEquals('pending', $reg->status);

        // Check status endpoint
        $statusRes = $this->getJson("/register/status/{$reg->slug}");
        $statusRes->assertOk();
        $statusRes->assertJson([
            'status' => 'pending',
            'is_paid' => false,
        ]);

        // Simulate companion app notification matching total_amount
        $incomingAmount = (float) $reg->total_amount;
        $notifyRes = $this->withHeaders([
            'X-Device-Key' => $this->sub->secret_key,
        ])->postJson('/api/v1/noderapay/report-notification', [
            'amount' => $incomingAmount,
            'bank'   => 'BCA',
            'raw_text' => "Transfer Masuk Rp " . number_format($incomingAmount, 0, ',', '.'),
        ]);

        $notifyRes->assertOk();
        $notifyRes->assertJson([
            'success' => true,
            'slug'    => 'solusinet',
        ]);

        $reg->refresh();
        $this->assertEquals('approved', $reg->status);
        $this->assertNotNull($reg->paid_at);

        // Tenant and user must have been provisioned
        $tenant = Tenant::where('slug', 'solusinet')->first();
        $this->assertNotNull($tenant);
        $this->assertTrue($tenant->is_active);

        // Status polling endpoint now reports paid & approved
        $statusRes2 = $this->getJson("/register/status/{$reg->slug}");
        $statusRes2->assertOk();
        $statusRes2->assertJson([
            'status'    => 'approved',
            'is_paid'   => true,
            'login_url' => '/login',
        ]);
    }

    public function test_shop_order_creates_dynamic_qris_and_auto_settles(): void
    {
        $order = ShopOrder::create([
            'order_number'        => 'ORD-TEST-001',
            'customer_name'       => 'Ahmad Dani',
            'customer_phone'      => '08987654321',
            'shipping_address'    => 'Layanan Voucher Online',
            'total_amount'        => 10000,
            'payment_status'      => 'unpaid',
            'order_status'        => 'pending',
            'payment_method'      => 'qris',
            'unique_code'         => 425,
            'dynamic_qris_string' => QrisDynamicService::convertToDynamic($this->validStaticQris, 10425, 'ORD-TEST-001'),
            'expires_at'          => now()->addMinutes(15),
        ]);
        $order->total_amount = 10425;
        $order->save();

        // Check order status endpoint
        $statusRes = $this->getJson("/shop/order/{$order->order_number}/status");
        $statusRes->assertOk();
        $statusRes->assertJson([
            'is_paid' => false,
            'payment_status' => 'unpaid',
        ]);

        // Simulate companion app notification
        $notifyRes = $this->withHeaders([
            'X-Device-Key' => $this->sub->secret_key,
        ])->postJson('/api/v1/noderapay/report-notification', [
            'amount' => 10425,
            'bank'   => 'GOPAY',
            'raw_text' => 'Gopay masuk Rp 10.425',
        ]);

        $notifyRes->assertOk();
        $notifyRes->assertJson([
            'success'      => true,
            'order_number' => 'ORD-TEST-001',
        ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('completed', $order->order_status);
        $this->assertNotNull($order->paid_at);

        // Check status endpoint
        $statusRes2 = $this->getJson("/shop/order/{$order->order_number}/status");
        $statusRes2->assertOk();
        $statusRes2->assertJson([
            'is_paid' => true,
            'payment_status' => 'paid',
            'order_status' => 'completed',
        ]);
    }

    public function test_tenant_addon_auto_settles_and_activates(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo ISP Tenant',
            'slug' => 'demoisp',
            'is_active' => true,
        ]);

        $addon = Addon::create([
            'name' => 'Modul Telegram Bot Plus',
            'slug' => 'tg-plus',
            'price' => 50000,
            'is_active' => true,
            'is_enabled' => true,
        ]);

        $tenantAddon = TenantAddon::create([
            'tenant_id'    => $tenant->id,
            'addon_id'     => $addon->id,
            'is_active'    => false,
            'status'       => 'pending',
            'unique_code'  => 312,
            'total_amount' => 50312,
            'dynamic_qris_string' => QrisDynamicService::convertToDynamic($this->validStaticQris, 50312, "ADDON-{$addon->id}"),
            'expires_at'   => now()->addMinutes(15),
        ]);

        // Simulate companion app notification
        $notifyRes = $this->withHeaders([
            'X-Device-Key' => $this->sub->secret_key,
        ])->postJson('/api/v1/noderapay/report-notification', [
            'amount' => 50312,
            'bank'   => 'DANA',
            'raw_text' => 'DANA Saldo Masuk Rp 50.312',
        ]);

        $notifyRes->assertOk();
        $notifyRes->assertJson([
            'success'  => true,
            'addon_id' => $tenantAddon->id,
        ]);

        $tenantAddon->refresh();
        $this->assertTrue($tenantAddon->is_active);
        $this->assertEquals('approved', $tenantAddon->status);
        $this->assertNotNull($tenantAddon->paid_at);
    }
}
