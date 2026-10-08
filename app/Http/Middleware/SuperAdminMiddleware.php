<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Mode Standalone (Instalasi Mandiri Pembeli): Superadmin dinonaktifkan
        if (filter_var(env('STANDALONE_MODE', false), FILTER_VALIDATE_BOOLEAN) || config('app.standalone_mode', false)) {
            if (Auth::check()) {
                return redirect('/dashboard');
            }
            return redirect('/login');
        }

        // Check session first (fast path) — HARUS kombinasi dengan Auth::check()

        // supaya session admin_role yang tersisa tidak cukup untuk masuk tanpa
        // autentikasi yang valid (Auth laravel sudah expired/logout).
        if (session('admin_role') === 'superadmin' && Auth::check()) {
            return $next($request);
        }


        // Fallback: check database directly
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'superadmin') {
                // Sync session
                session(['admin_role' => 'superadmin']);
                return $next($request);
            }
            // Sudah login tapi bukan superadmin → tolak
            abort(403, 'Akses superadmin saja');
        }

        // Belum login → arahkan ke halaman login KHUSUS superadmin
        // (terpisah dari login app admin tenant di dgtlnetsolution.com/login)
        return redirect()->route('superadmin.login');
    }
}
