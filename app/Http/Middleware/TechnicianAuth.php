<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TechnicianAuth
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Cek session teknisi atau kolektor langsung
        if (session('technician_logged_in') && session('technician_id')) {
            return $next($request);
        }

        if (session('collector_logged_in') && session('collector_id')) {
            return $next($request);
        }

        // 2. Jika user sedang login via Web Auth (Admin / SuperAdmin / Teknisi User)
        if (Auth::check()) {
            $user = Auth::user();

            // Jika akun adalah teknisi
            if ($user->role === 'technician') {
                session([
                    'technician_id' => $user->id,
                    'technician_name' => $user->name ?? $user->username,
                    'technician_username' => $user->username,
                    'technician_logged_in' => true,
                    'tenant_id' => $user->tenant_id,
                ]);
                return $next($request);
            }

            // Jika admin / superadmin ingin masuk/preview teknisi
            if (in_array($user->role, ['admin', 'superadmin'])) {
                $tech = User::where('tenant_id', $user->tenant_id)
                    ->where('role', 'technician')
                    ->first() ?? $user;

                session([
                    'technician_id' => $tech->id,
                    'technician_name' => $tech->name ?? $tech->username,
                    'technician_username' => $tech->username,
                    'technician_logged_in' => true,
                    'tenant_id' => $user->tenant_id,
                ]);
                return $next($request);
            }
        }

        return redirect('/teknisi/login');
    }
}
