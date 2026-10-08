<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $isSuperadmin = session('admin_role') === 'superadmin' || $user->role === 'superadmin';

            // Hanya superadmin yang diwajibkan 2FA (bila diaktifkan).
            if ($isSuperadmin && $user->two_factor_enabled && !session('2fa_verified')) {
                return redirect()->route('superadmin.2fa');
            }
        }

        return $next($request);
    }
}