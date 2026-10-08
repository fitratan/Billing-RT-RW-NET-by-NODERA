<?php

namespace App\Http\Middleware;

use App\Models\Collector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CollectorAuth
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Cek session kolektor atau teknisi langsung
        if (session('collector_logged_in') && session('collector_id')) {
            return $next($request);
        }

        if (session('technician_logged_in') && session('technician_id')) {
            return $next($request);
        }

        // 2. Jika user sedang login via Web Auth (Admin / SuperAdmin / User)
        if (Auth::check()) {
            $user = Auth::user();
            $tenantId = session('tenant_id') ?? $user->tenant_id;

            // Cari data Collector di tenant ini
            $collector = Collector::where('tenant_id', $tenantId)->first();

            // Jika belum ada kolektor di tenant, buatkan default profile kolektor untuk testing / admin preview
            if (!$collector) {
                $collector = Collector::firstOrCreate(
                    ['tenant_id' => $tenantId, 'username' => 'kolektor_' . ($user->username ?? 'admin')],
                    [
                        'name' => $user->name ?? 'Kolektor Admin',
                        'phone' => $user->phone ?? '08123456789',
                        'password' => bcrypt('123456'),
                        'is_active' => true,
                    ]
                );
            }

            session([
                'collector_id' => $collector->id,
                'collector_name' => $collector->name,
                'collector_username' => $collector->username,
                'collector_logged_in' => true,
                'tenant_id' => $tenantId,
            ]);

            return $next($request);
        }

        return redirect()->route('kolektor.login');
    }
}
