<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditFullCrawlTest extends TestCase
{
    use RefreshDatabase;

    private function makeUsers(): array
    {
        $tenant = Tenant::create([
            'name' => 'Firma Uji', 'slug' => 'fitra', 'email' => 't@t.com',
            'phone' => '0812', 'is_active' => true,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'username' => 'fitra', 'password' => bcrypt('secret'),
            'role' => 'admin', 'tenant_id' => $tenant->id, 'is_active' => true,
        ]);
        $super = User::create([
            'name' => 'Super', 'username' => 'superadmin', 'password' => bcrypt('secret'),
            'role' => 'superadmin', 'is_active' => true,
        ]);
        return [$admin, $super];
    }

    private function substitute(string $uri): string
    {
        $uri = preg_replace('/\{[^}]*\}/', '999999', $uri);
        return trim($uri, '/');
    }

    private function matchAdmin(string $u): bool
    {
        return str_starts_with($u, 'admin/') || $u === 'dashboard';
    }

    private function matchSuper(string $u): bool
    {
        return str_starts_with($u, 'superadmin/');
    }

    public function test_audit_admin_500_errors(): void
    {
        [$admin] = $this->makeUsers();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->actingAs($admin)
            ->withSession(['tenant_id' => $admin->tenant_id, 'tenant_slug' => 'fitra', 'tenant_name' => 'Firma', 'admin_role' => 'admin']);

        $failed = [];
        $checks = 0;

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (!$this->matchAdmin($uri)) continue;
            if (str_contains($uri, 'download')) continue;

            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $verb) {
                if (!in_array($verb, $route->methods(), true)) continue;
                $url = '/' . $this->substitute($uri);
                $checks++;
                try {
                    $status = $this->request($verb, $url)->getStatusCode();
                    if ($status >= 500) {
                        $failed[] = "admin {$verb} {$uri} ({$url}) => {$status}";
                    }
                } catch (\Throwable $e) {
                    $failed[] = "admin {$verb} {$uri} => EXCEPTION: {$e->getMessage()}";
                }
            }
        }

        $this->assertSame([], $failed, "Admin 5xx (total {$checks} checks):\n" . implode("\n", $failed));
    }

    public function test_audit_superadmin_500_errors(): void
    {
        [, $super] = $this->makeUsers();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->actingAs($super)
            ->withSession(['admin_role' => 'superadmin']);

        $failed = [];
        $checks = 0;

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (!$this->matchSuper($uri)) continue;
            if (str_contains($uri, 'download')) continue;

            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $verb) {
                if (!in_array($verb, $route->methods(), true)) continue;
                $url = '/' . $this->substitute($uri);
                $checks++;
                try {
                    $status = $this->request($verb, $url)->getStatusCode();
                    if ($status >= 500) {
                        $failed[] = "superadmin {$verb} {$uri} ({$url}) => {$status}";
                    }
                } catch (\Throwable $e) {
                    $failed[] = "superadmin {$verb} {$uri} => EXCEPTION: {$e->getMessage()}";
                }
            }
        }

        $this->assertSame([], $failed, "Superadmin 5xx (total {$checks} checks):\n" . implode("\n", $failed));
    }

    private function request(string $verb, string $url)
    {
        if ($verb === 'GET') return $this->get($url);
        if ($verb === 'POST') return $this->post($url);
        if ($verb === 'PUT') return $this->put($url);
        return $this->delete($url);
    }

    private function crawlContext(array $prefixes, string $label): array
    {
        $failed = [];
        $checks = 0;

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            $hit = false;
            foreach ($prefixes as $p) {
                if ($uri === $p || str_starts_with($uri, $p . '/')) { $hit = true; break; }
            }
            if (!$hit) continue;
            if (str_contains($uri, 'download')) continue;

            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $verb) {
                if (!in_array($verb, $route->methods(), true)) continue;
                $url = '/' . $this->substitute($uri);
                $checks++;
                try {
                    $status = $this->request($verb, $url)->getStatusCode();
                    if ($status >= 500) {
                        $failed[] = "{$label} {$verb} {$uri} ({$url}) => {$status}";
                    }
                } catch (\Throwable $e) {
                    $failed[] = "{$label} {$verb} {$uri} => EXCEPTION: {$e->getMessage()}";
                }
            }
        }

        return [$failed, $checks];
    }

    public function test_audit_pelanggan_kolektor_teknisi_500_errors(): void
    {
        $tenant = Tenant::create([
            'name' => 'Firma Uji', 'slug' => 'fitra', 'email' => 't@t.com',
            'phone' => '0812', 'is_active' => true,
        ]);

        $customer = \App\Models\Customer::create([
            'name' => 'Pelanggan Satu', 'phone' => '08120001', 'status' => 'active',
            'tenant_id' => $tenant->id,
        ]);
        \App\Models\Invoice::create([
            'customer_id' => $customer->id, 'customer_name' => $customer->name,
            'invoice_number' => 'INV-001', 'amount' => 150000, 'due_date' => now()->addDays(7),
            'period' => '2026-08', 'paid' => false, 'status' => 'unpaid', 'tenant_id' => $tenant->id,
        ]);

        $collector = \App\Models\Collector::create([
            'name' => 'Kolektor Satu', 'username' => 'kolektor1', 'password' => bcrypt('secret'),
            'tenant_id' => $tenant->id, 'is_active' => true,
        ]);

        $teknisi = \App\Models\User::create([
            'name' => 'Teknisi Satu', 'username' => 'teknisi1', 'password' => bcrypt('secret'),
            'role' => 'technician', 'tenant_id' => $tenant->id, 'is_active' => true,
        ]);

        $contexts = [
            'pelanggan' => [
                ['customer_id' => $customer->id, 'customer_phone' => '08120001', 'customer_name' => $customer->name, 'tenant_id' => $tenant->id],
                ['portal', 'pelanggan', 'mobile'],
            ],
            'kolektor' => [
                ['collector_id' => $collector->id, 'collector_name' => $collector->name, 'collector_logged_in' => true, 'tenant_id' => $tenant->id],
                ['kolektor'],
            ],
            'teknisi' => [
                ['technician_id' => $teknisi->id, 'technician_name' => 'Teknisi Satu', 'technician_username' => 'teknisi1', 'technician_logged_in' => true, 'tenant_id' => $tenant->id, 'tenant_slug' => 'fitra'],
                ['teknisi'],
            ],
        ];

        $all = [];
        $total = 0;
        foreach ($contexts as $label => [$session, $prefixes]) {
            $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
                ->withSession($session);
            [$failed, $checks] = $this->crawlContext($prefixes, $label);
            $total += $checks;
            $all = array_merge($all, $failed);
        }

        $this->assertSame([], $all, "Pelanggan/Kolektor/Teknisi 5xx (total {$total} checks):\n" . implode("\n", $all));
    }
}