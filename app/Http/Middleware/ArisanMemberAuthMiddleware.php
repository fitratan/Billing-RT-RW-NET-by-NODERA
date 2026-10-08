<?php

namespace App\Http\Middleware;

use App\Models\ArisanMember;
use App\Models\ArisanSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArisanMemberAuthMiddleware
{
    /**
     * Handle an incoming request for Arisan Member Portal.
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

        $memberId = session('arisan_member_id');

        if (!$memberId) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi member belum terautentikasi.',
                ], 401);
            }

            return redirect()->route('arisan.member.login', ['subdomain' => $subdomain])
                ->with('error', 'Silakan masuk dengan akun member Anda.');
        }

        $member = ArisanMember::where('id', $memberId)
            ->where('subscription_id', $subscription->id)
            ->where('is_active', true)
            ->first();

        if (!$member) {
            session()->forget('arisan_member_id');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun member tidak valid atau tidak aktif.',
                ], 401);
            }

            return redirect()->route('arisan.member.login', ['subdomain' => $subdomain])
                ->with('error', 'Akun member tidak ditemukan atau tidak aktif.');
        }

        $request->attributes->set('arisan_member', $member);
        $request->attributes->set('arisan_subscription', $subscription);
        $request->attributes->set('subdomain', $subdomain);

        view()->share('arisan_subscription', $subscription);
        view()->share('subdomain', $subdomain);
        view()->share('currentMember', $member);
        view()->share('current_member', $member);

        return $next($request);
    }
}
