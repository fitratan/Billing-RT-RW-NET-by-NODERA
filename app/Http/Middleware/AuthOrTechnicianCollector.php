<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthOrTechnicianCollector
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $isPanel = str_starts_with($host, 'panel.');

        $isWa = str_starts_with($host, 'wa.') || str_starts_with($host, 'wagateway.');
        $isGateway = str_starts_with($host, 'gateway.');

        // 0. Cek session VPN / Member Panel jika di subdomain panel
        if ($isPanel) {
            if (auth('vpn')->check() || session('vpn_user_id')) {
                return $next($request);
            }
            return redirect()->to('/login');
        }

        // 0b. Cek session WA Gateway jika di subdomain wa
        if ($isWa) {
            if (session('wagateway_merchant_id') || $request->cookie('nodera_remember_wa_merchant')) {
                return $next($request);
            }
            return redirect()->to('/login');
        }

        // 0c. Cek session NODERA PAY jika di subdomain gateway
        if ($isGateway) {
            if (session('noderapay_merchant_id') || $request->cookie('nodera_remember_merchant')) {
                return $next($request);
            }
            return redirect()->to('/login');
        }

        // 1. Cek user auth
        if (auth()->check()) {
            return $next($request);
        }

        // 2. Cek session admin
        if (session('admin_logged_in') && session('admin_id')) {
            return $next($request);
        }

        // 3. Cek session teknisi
        if (session('technician_logged_in') && session('technician_id')) {
            return $next($request);
        }

        // 4. Cek session kolektor
        if (session('collector_logged_in') && session('collector_id')) {
            return $next($request);
        }

        // 5. Cek session kasir
        if (session('cashier_logged_in') && session('cashier_id')) {
            return $next($request);
        }

        // Ga ada yang cocok → redirect ke login
        return redirect()->to('/login');
    }
}
