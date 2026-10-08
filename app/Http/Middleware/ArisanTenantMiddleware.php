<?php

namespace App\Http\Middleware;

use App\Models\ArisanSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArisanTenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->route('subdomain');

        if (!$subdomain) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            if (count($parts) >= 3 || (count($parts) >= 2 && !in_array($host, ['localhost', '127.0.0.1']))) {
                $subdomain = $parts[0];
            }
        }

        if (!$subdomain) {
            abort(404, 'Layanan Arisan tidak ditemukan.');
        }

        $subscription = ArisanSubscription::where('subdomain', $subdomain)->first();

        if (!$subscription) {
            abort(404, 'Layanan Arisan tidak ditemukan.');
        }

        if ($subscription->status !== 'ACTIVE' || $subscription->isExpired()) {
            abort(403, 'Layanan Arisan ini sedang tidak aktif atau masa aktif berlangganan telah berakhir.');
        }

        $request->attributes->set('arisan_subscription', $subscription);
        $request->attributes->set('subdomain', $subdomain);

        view()->share('arisan_subscription', $subscription);
        view()->share('subdomain', $subdomain);

        return $next($request);
    }
}
