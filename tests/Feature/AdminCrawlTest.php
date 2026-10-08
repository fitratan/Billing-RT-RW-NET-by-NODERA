<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrawlTest extends TestCase
{
    use RefreshDatabase;

    public $admin;

    public function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create([
            'name' => 'Firma Uji', 'slug' => 'fitra', 'email' => 't@t.com',
            'phone' => '0812', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'fitra', 'password' => bcrypt('secret'),
            'role' => 'admin', 'tenant_id' => $tenant->id, 'is_active' => true,
        ]);
    }

    public function test_admin_pages_render(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->admin->tenant_id, 'tenant_slug' => 'fitra', 'tenant_name' => 'Firma', 'admin_role' => 'admin']);

        $failed = [];
        $tried = 0;

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            $methods = array_diff($route->methods(), ['HEAD']);
            if (!in_array('GET', $methods)) continue;
            if (!str_starts_with($uri, 'admin/') && $uri !== 'dashboard') continue;
            if (str_contains($uri, '{')) continue;
            if (str_starts_with($uri, 'api/')) continue;

            $tried++;
            try {
                $response = $this->get('/' . $uri);
                $status = $response->getStatusCode();
                if ($status >= 500) {
                    $msg = method_exists($response, 'exception') && $response->exception
                        ? $response->exception->getMessage()
                        : 'no exception captured';
                    $failed[] = "$uri => $status :: $msg";
                }
            } catch (\Throwable $e) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() === 403) {
                    continue; // intentional permission gate
                }
                $failed[] = "$uri => EXCEPTION: " . $e->getMessage();
            }
        }

        $this->assertSame([], $failed, "Admin pages with >=500 (tried {$tried}):\n" . implode("\n", $failed));
    }

    public function test_superadmin_pages_render(): void
    {
        $this->withoutExceptionHandling();

        $super = User::create([
            'name' => 'Super', 'username' => 'superadmin', 'password' => bcrypt('secret'),
            'role' => 'superadmin', 'is_active' => true,
        ]);
        $this->actingAs($super)
            ->withSession(['admin_role' => 'superadmin']);

        $failed = [];
        $tried = 0;

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            $methods = array_diff($route->methods(), ['HEAD']);
            if (!in_array('GET', $methods)) continue;
            if (!str_starts_with($uri, 'superadmin/')) continue;
            if (str_contains($uri, '{')) continue;
            if (str_contains($uri, 'download')) continue;

            $tried++;
            try {
                $response = $this->get('/' . $uri);
                $status = $response->getStatusCode();
                if ($status >= 500) {
                    $msg = method_exists($response, 'exception') && $response->exception
                        ? $response->exception->getMessage()
                        : 'no exception captured';
                    $failed[] = "$uri => $status :: $msg";
                }
            } catch (\Throwable $e) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() === 403) {
                    continue; // intentional permission gate
                }
                $failed[] = "$uri => EXCEPTION: " . $e->getMessage();
            }
        }

        $this->assertSame([], $failed, "Superadmin pages with >=500 (tried {$tried}):\n" . implode("\n", $failed));
    }
}