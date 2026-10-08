<?php
// app/Http/Middleware/TenantMiddleware.php
namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        if (!$slug) {
            // Super admin — no tenant restriction
            return $next($request);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (!$tenant) {
            return response()->view('errors.tenant-not-found', [
                'subdomain' => $slug,
            ], 404);
        }

        if ($tenant->isExpired() || !$tenant->is_active) {
            $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST));
            $scheme = $request->getScheme();
            $port = $request->getPort();
            $portSuffix = ($port && !in_array($port, [80, 443])) ? ":{$port}" : '';
            $panelUrl = $scheme . '://panel.' . $baseDomain . $portSuffix;

            return response()->view('errors.tenant-expired', [
                'subdomain'   => $slug,
                'tenant'      => $tenant,
                'expired_at'  => $tenant->expired_at ? (string) $tenant->expired_at : null,
                'is_active'   => (bool) $tenant->is_active,
                'panelUrl'    => $panelUrl,
            ], 403);
        }

        // Set tenant context
        session(['tenant_id' => $tenant->id, 'tenant_slug' => $tenant->slug, 'tenant_name' => $tenant->name]);
        config(['app.tenant' => $tenant]);

        return $next($request);
    }
}
