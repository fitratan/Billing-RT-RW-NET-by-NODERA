<?php

namespace App\Http\Middleware;

use App\Models\ArisanSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArisanAdminAuthMiddleware
{
    /**
     * Handle an incoming request for Arisan Admin Panel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->route('subdomain') ?? $request->attributes->get('subdomain');
        $subscription = $request->attributes->get('arisan_subscription');

        if (!$subscription && $subdomain) {
            $subscription = ArisanSubscription::where('subdomain', $subdomain)->first();
        }

        if (!$subscription) {
            abort(404, 'Layanan Arisan tidak ditemukan.');
        }

        if (session('arisan_admin_id') != $subscription->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi admin belum terautentikasi.',
                ], 401);
            }

            return redirect()->route('arisan.admin.login', ['subdomain' => $subdomain])
                ->with('error', 'Silakan masuk ke panel admin terlebih dahulu.');
        }

        return $next($request);
    }
}
