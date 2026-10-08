<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IsolationService;
use App\Services\MikrotikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArpCustomerQueueAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limit_resolution(): void
    {
        $mik = new MikrotikService();

        $this->assertEquals('10M/10M', $mik->resolveRateLimit('10M'));
        $this->assertEquals('10M/10M', $mik->resolveRateLimit('10 Mbps'));
        $this->assertEquals('10M/10M', $mik->resolveRateLimit('10Mb'));
        $this->assertEquals('20M/20M', $mik->resolveRateLimit('20M/20M'));
        $this->assertEquals('512k/512k', $mik->resolveRateLimit('512k'));
        $this->assertEquals('1G/1G', $mik->resolveRateLimit('1G'));
        $this->assertEquals('128k/128k', $mik->resolveRateLimit('isolir'));
        $this->assertEquals('128k/128k', $mik->resolveRateLimit('profile_isolir'));
        $this->assertEquals('128k/128k', $mik->resolveRateLimit('expired'));
        $this->assertEquals('25M/25M', $mik->resolveRateLimit('Paket-25M'));
    }

    public function test_is_static_detection(): void
    {
        $staticCustomer1 = new Customer([
            'name' => 'Static 1',
            'connection_type' => 'static',
            'ip_address' => '192.168.1.50',
        ]);
        $this->assertTrue($staticCustomer1->isStatic());
        $this->assertTrue(IsolationService::isStaticCustomer($staticCustomer1));

        $arpCustomer = new Customer([
            'name' => 'ARP 1',
            'connection_type' => 'arp',
            'ip_address' => '192.168.1.51',
        ]);
        $this->assertTrue($arpCustomer->isStatic());
        $this->assertTrue(IsolationService::isStaticCustomer($arpCustomer));

        $autoStaticCustomer = new Customer([
            'name' => 'Auto Static',
            'connection_type' => null,
            'ip_address' => '192.168.1.52',
            'pppoe_username' => 'static_192_168_1_52',
        ]);
        $this->assertTrue($autoStaticCustomer->isStatic());
        $this->assertTrue(IsolationService::isStaticCustomer($autoStaticCustomer));

        $pppoeCustomer = new Customer([
            'name' => 'PPPoE 1',
            'connection_type' => 'pppoe',
            'pppoe_username' => 'user_pppoe_1',
            'ip_address' => '10.0.0.5',
        ]);
        $this->assertFalse($pppoeCustomer->isStatic());
        $this->assertFalse(IsolationService::isStaticCustomer($pppoeCustomer));

        // Array test
        $this->assertTrue(IsolationService::isStaticCustomer([
            'connection_type' => 'static',
            'ip_address' => '192.168.1.60',
        ]));
        $this->assertTrue(IsolationService::isStaticCustomer([
            'ip_address' => '192.168.1.60',
            'pppoe_username' => 'arp_user_1',
        ]));
        $this->assertFalse(IsolationService::isStaticCustomer([
            'connection_type' => 'pppoe',
            'pppoe_username' => 'pppoe_user_1',
        ]));
    }

    public function test_create_static_customer_via_billing_controller(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Paket 15M',
            'profile_normal' => '15M/15M',
            'profile_isolir' => '128k/128k',
        ]);

        $postData = [
            'name' => 'Pelanggan Static Test',
            'phone' => '081234567890',
            'connection_type' => 'static',
            'ip_address' => '192.168.88.50',
            'mac_address' => '00:11:22:33:44:55',
            'arp_interface' => 'bridge1',
            'create_arp' => '1',
            'package_id' => $package->id,
            'isolation_date' => '20',
        ];

        $response = $this->actingAs($admin)
            ->withSession(['admin_id' => $admin->id, 'admin_role' => 'admin', 'tenant_id' => $tenant->id])
            ->post('/admin/billing/customers/add', $postData);

        $response->assertStatus(302);

        $customer = Customer::where('name', 'Pelanggan Static Test')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('static', $customer->connection_type);
        $this->assertEquals('192.168.88.50', $customer->ip_address);
        $this->assertEquals('00:11:22:33:44:55', $customer->mac_address);
        $this->assertTrue($customer->isStatic());
    }

    public function test_isolate_and_unisolate_arp_customer(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Paket 10M',
            'profile_normal' => '10M/10M',
            'profile_isolir' => '128k/128k',
        ]);

        $customer = Customer::create([
            'name' => 'Pelanggan ARP Isolir Test',
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'connection_type' => 'static',
            'ip_address' => '192.168.88.99',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'status' => 'active',
        ]);

        $isoService = app(IsolationService::class);

        // 1. Isolate
        $resIso = $isoService->isolateCustomer($customer, 'Unit Test', true);
        $this->assertTrue($resIso);
        $customer->refresh();
        $this->assertEquals('isolated', $customer->status);

        // 2. Unisolate
        $resUni = $isoService->unisolateCustomer($customer, 'Unit Test', true);
        $this->assertTrue($resUni);
        $customer->refresh();
        $this->assertEquals('active', $customer->status);
    }
}
