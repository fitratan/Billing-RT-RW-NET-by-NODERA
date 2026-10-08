<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or Update Tenant Demo
        $tenant = Tenant::withoutGlobalScopes()->updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'NODERA Demo (ISP Demo)',
                'email' => 'demo@example.com',
                'phone' => '081234567890',
                'address' => 'Jl. Jenderal Sudirman No. 123, Jakarta Pusat',
                'is_active' => true,
                'expired_at' => now()->addYears(10),
                'settings' => [
                    'COMPANY_NAME' => 'NODERA Demo (ISP Demo)',
                    'COMPANY_ADDRESS' => 'Jl. Jenderal Sudirman No. 123, Jakarta Pusat',
                    'COMPANY_PHONE' => '081234567890',
                    'COMPANY_EMAIL' => 'demo@example.com',
                ],
            ]
        );

        $tenantId = $tenant->id;

        // Clean up any conflicting demo users from other tenants
        User::withoutGlobalScopes()->where(function ($q) {
            $q->whereIn('username', ['demo', 'kolektor_demo', 'teknisi_demo'])
              ->orWhere('email', 'demo@example.com');
        })->where('tenant_id', '!=', $tenantId)->delete();

        // 3. Admin User Demo
        $adminUser = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'demo', 'tenant_id' => $tenantId],
            [
                'name' => 'Admin Demo',
                'email' => 'demo@example.com',
                'phone' => '081234567890',
                'role' => 'admin',
                'password' => Hash::make('demo123'),
                'is_active' => true,
            ]
        );

        // 4. Kolektor User Demo
        $collectorUser = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'kolektor_demo', 'tenant_id' => $tenantId],
            [
                'name' => 'Kolektor Demo (Budi)',
                'email' => 'kolektor@demo.com',
                'phone' => '081299998888',
                'role' => 'collector',
                'password' => Hash::make('demo123'),
                'is_active' => true,
            ]
        );

        // 5. Teknisi User Demo
        $technicianUser = User::withoutGlobalScopes()->updateOrCreate(
            ['username' => 'teknisi_demo', 'tenant_id' => $tenantId],
            [
                'name' => 'Teknisi Demo (Andi)',
                'email' => 'teknisi@demo.com',
                'phone' => '081277776666',
                'role' => 'technician',
                'password' => Hash::make('demo123'),
                'is_active' => true,
            ]
        );

        // 6. Router MikroTik Demo
        $router = Mikrotik::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'Router Utama (MikroTik Core)', 'tenant_id' => $tenantId],
            [
                'host' => '103.100.200.1',
                'username' => 'demo',
                'password' => 'demo',
                'port' => 8728,
                'is_active' => true,
            ]
        );

        // 7. Paket Internet Demo
        $pkg1 = Package::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'Paket Starter 10 Mbps', 'tenant_id' => $tenantId],
            ['price' => 150000, 'speed' => '10M', 'auto_isolir' => true]
        );

        $pkg2 = Package::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'Paket Family 30 Mbps', 'tenant_id' => $tenantId],
            ['price' => 250000, 'speed' => '30M', 'auto_isolir' => true]
        );

        $pkg3 = Package::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'Paket Business 50 Mbps', 'tenant_id' => $tenantId],
            ['price' => 450000, 'speed' => '50M', 'auto_isolir' => true]
        );

        // 8. Pelanggan Sample Demo
        $sampleCustomers = [
            ['name' => 'Ahmad Subandi', 'pppoe_username' => 'ahmad_subandi', 'code' => 'DEMO-001', 'package_id' => $pkg2->id, 'amount' => 250000, 'phone' => '081211112222', 'address' => 'Jl. Melati No. 12, RT 01/02'],
            ['name' => 'Bambang Hermawan', 'pppoe_username' => 'bambang_h', 'code' => 'DEMO-002', 'package_id' => $pkg1->id, 'amount' => 150000, 'phone' => '081222223333', 'address' => 'Jl. Mawar No. 45, RT 03/02'],
            ['name' => 'Citra Lestari', 'pppoe_username' => 'citra_l', 'code' => 'DEMO-003', 'package_id' => $pkg3->id, 'amount' => 450000, 'phone' => '081233334444', 'address' => 'Ruko Plaza Mutiara Blok A/5'],
            ['name' => 'Dedi Prasetyo', 'pppoe_username' => 'dedi_p', 'code' => 'DEMO-004', 'package_id' => $pkg2->id, 'amount' => 250000, 'phone' => '081244445555', 'address' => 'Jl. Anggrek No. 88, RT 05/01'],
            ['name' => 'Eka Putri Rahayu', 'pppoe_username' => 'eka_putri', 'code' => 'DEMO-005', 'package_id' => $pkg1->id, 'amount' => 150000, 'phone' => '081255556666', 'address' => 'Jl. Dahlia No. 3, RT 02/04'],
            ['name' => 'Fajar Nugroho', 'pppoe_username' => 'fajar_n', 'code' => 'DEMO-006', 'package_id' => $pkg2->id, 'amount' => 250000, 'phone' => '081266667777', 'address' => 'Komp. Graha Indah Blok C2/10'],
            ['name' => 'Gita Gutawa', 'pppoe_username' => 'gita_g', 'code' => 'DEMO-007', 'package_id' => $pkg3->id, 'amount' => 450000, 'phone' => '081277778888', 'address' => 'Jl. Kenanga No. 19, RT 04/03'],
            ['name' => 'Hendra Wijaya', 'pppoe_username' => 'hendra_w', 'code' => 'DEMO-008', 'package_id' => $pkg1->id, 'amount' => 150000, 'phone' => '081288889999', 'address' => 'Jl. Flamboyan No. 7, RT 01/05'],
        ];

        foreach ($sampleCustomers as $sc) {
            $customer = Customer::withoutGlobalScopes()->updateOrCreate(
                ['code' => $sc['code'], 'tenant_id' => $tenantId],
                [
                    'name' => $sc['name'],
                    'pppoe_username' => $sc['pppoe_username'],
                    'pppoe_password' => '123456',
                    'package_id' => $sc['package_id'],
                    'amount' => $sc['amount'],
                    'phone' => $sc['phone'],
                    'address' => $sc['address'],
                    'router_id' => $router->id,
                    'status' => 'active',
                    'auto_isolir' => true,
                    'created_at' => now()->subDays(rand(10, 60)),
                ]
            );

            // Invoice Paid
            Invoice::withoutGlobalScopes()->updateOrCreate(
                ['invoice_number' => "INV-DEMO-{$customer->code}-PAID", 'tenant_id' => $tenantId],
                [
                    'customer_id' => $customer->id,
                    'amount' => $sc['amount'],
                    'status' => 'paid',
                    'paid' => true,
                    'period' => now()->subMonth()->format('Y-m'),
                    'due_date' => now()->subMonth()->setDay(15)->format('Y-m-d'),
                    'paid_at' => now()->subDays(rand(1, 10)),
                    'processed_by' => rand(0, 1) ? 'Kolektor Demo (Budi)' : 'Admin Demo',
                    'created_at' => now()->subMonth()->setDay(1),
                ]
            );

            // Invoice Pending Current Month
            Invoice::withoutGlobalScopes()->updateOrCreate(
                ['invoice_number' => "INV-DEMO-{$customer->code}-CURRENT", 'tenant_id' => $tenantId],
                [
                    'customer_id' => $customer->id,
                    'amount' => $sc['amount'],
                    'status' => 'pending',
                    'paid' => false,
                    'period' => now()->format('Y-m'),
                    'due_date' => now()->setDay(15)->format('Y-m-d'),
                    'paid_at' => null,
                    'processed_by' => null,
                    'created_at' => now()->startOfMonth(),
                ]
            );
        }
    }
}
