<?php

namespace Tests\Feature;

use App\Http\Controllers\TopBandwidthController;
use App\Models\Customer;
use App\Models\Mikrotik;
use App\Models\Tenant;
use App\Services\MikrotikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionMethod;
use Tests\TestCase;

class TenantBandwidthMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_bandwidth_arp_data_resolves_and_aggregates_bandwidth(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Tenant ISP']);

        $router = Mikrotik::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Router',
            'host' => '192.168.88.1',
            'username' => 'admin',
            'password' => 'secret',
            'port' => 8728,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pelanggan Statis 1',
            'connection_type' => 'static',
            'ip_address' => '192.168.88.50',
            'mac_address' => '00:11:22:33:44:55',
        ]);

        $mockMikrotik = $this->createMock(MikrotikService::class);
        $mockMikrotik->method('query')->willReturnCallback(function (string $path, array $params = []) {
            if ($path === '/queue/simple/print') {
                return [
                    [
                        'name' => 'queue-statis-1',
                        'target' => '192.168.88.50/32',
                        'bytes' => '2000000/8000000', // 2MB upload, 8MB download
                        'total-bytes' => '10000000',
                        'dynamic' => 'false',
                    ],
                ];
            }
            if ($path === '/ip/arp/print') {
                return [
                    [
                        'address' => '192.168.88.50',
                        'mac-address' => '00:11:22:33:44:55',
                        'interface' => 'ether2',
                        'complete' => 'true',
                    ],
                ];
            }
            return [];
        });

        $controller = app(TopBandwidthController::class);
        $refMethod = new ReflectionMethod($controller, 'getArpData');
        $refMethod->setAccessible(true);

        $response = $refMethod->invoke($controller, $mockMikrotik, $router, $tenant->id);
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('arp', $payload['type']);
        $this->assertCount(1, $payload['data']);

        $entry = $payload['data'][0];
        $this->assertSame('Pelanggan Statis 1', $entry['name']);
        $this->assertSame('192.168.88.50', $entry['ip']);
        $this->assertSame('00:11:22:33:44:55', $entry['mac']);
        $this->assertSame(10000000, $entry['total_bytes']);
        $this->assertStringContainsString('MB', $entry['total']);
        $this->assertStringContainsString('MB', $payload['grand_total']);
    }

    public function test_portal_dashboard_and_usage_renders_for_arp_customer(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Tenant ISP']);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pelanggan Statis Budi',
            'connection_type' => 'static',
            'ip_address' => '192.168.88.60',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'router_id' => null,
        ]);

        $response = $this->withSession([
            'customer_id' => $customer->id,
            'customer_phone' => $customer->phone,
            'customer_tenant_id' => $tenant->id,
            'tenant_id' => $tenant->id,
        ])->get('/portal');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Dashboard')
            ->has('customer')
            ->where('customer.name', 'Pelanggan Statis Budi')
        );

        $usageResponse = $this->withSession([
            'customer_id' => $customer->id,
            'customer_phone' => $customer->phone,
            'customer_tenant_id' => $tenant->id,
            'tenant_id' => $tenant->id,
        ])->get('/portal/usage');

        $usageResponse->assertStatus(200);
        $usageResponse->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Usage')
        );
    }
}
