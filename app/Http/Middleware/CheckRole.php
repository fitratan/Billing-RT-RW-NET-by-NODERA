<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // 1. Superadmin selalu memiliki akses universal tanpa syarat
        if (session('superadmin_logged_in') || (auth()->check() && strtolower((string) auth()->user()->role) === 'superadmin')) {
            return $next($request);
        }

        // 2. Deteksi role aktif secara komprehensif
        $role = null;

        // Cek admin terlebih dahulu (baik dari session admin_logged_in maupun auth user role)
        if (session('admin_logged_in') || (auth()->check() && in_array(strtolower((string) auth()->user()->role), ['admin', 'superadmin', 'operator', 'owner']))) {
            $role = 'admin';
        } elseif (session('technician_logged_in') || (auth()->check() && in_array(strtolower((string) auth()->user()->role), ['technician', 'teknisi']))) {
            $role = 'technician';
        } elseif (session('collector_logged_in') || (auth()->check() && in_array(strtolower((string) auth()->user()->role), ['collector', 'kolektor']))) {
            $role = 'collector';
        } elseif (session('cashier_logged_in') || (auth()->check() && in_array(strtolower((string) auth()->user()->role), ['cashier', 'kasir']))) {
            $role = 'cashier';
        } elseif (auth()->check()) {
            $role = strtolower((string) auth()->user()->role);
        }

        if (! $role) {
            return redirect('/login');
        }

        // 3. Normalisasi alias role (Indonesia / English)
        $normalizedRole = match ($role) {
            'teknisi' => 'technician',
            'kolektor' => 'collector',
            'kasir' => 'cashier',
            'pelanggan' => 'customer',
            'operator', 'owner' => 'admin',
            default => $role,
        };

        // Jika tidak ada batasan $roles yang diminta, loloskan
        if (empty($roles)) {
            return $next($request);
        }

        // Flatten dan split semua $roles (mendukung format 'role:admin,superadmin' maupun ['admin', 'superadmin'])
        $allowedRoles = [];
        foreach ($roles as $r) {
            if (is_array($r)) {
                foreach ($r as $subR) {
                    $trimmed = strtolower(trim((string)$subR));
                    if ($trimmed !== '') {
                        $allowedRoles[] = match ($trimmed) {
                            'teknisi' => 'technician',
                            'kolektor' => 'collector',
                            'kasir' => 'cashier',
                            'pelanggan' => 'customer',
                            'operator', 'owner' => 'admin',
                            default => $trimmed,
                        };
                    }
                }
            } elseif (is_string($r)) {
                $parts = explode(',', $r);
                foreach ($parts as $part) {
                    $trimmed = strtolower(trim($part));
                    if ($trimmed !== '') {
                        $allowedRoles[] = match ($trimmed) {
                            'teknisi' => 'technician',
                            'kolektor' => 'collector',
                            'kasir' => 'cashier',
                            'pelanggan' => 'customer',
                            'operator', 'owner' => 'admin',
                            default => $trimmed,
                        };
                    }
                }
            }
        }
        $allowedRoles = array_unique($allowedRoles);

        // Cek kecocokan role
        if (in_array($role, $allowedRoles) || in_array($normalizedRole, $allowedRoles) || ($role === 'admin' && in_array('admin', $allowedRoles))) {
            return $next($request);
        }

        // Jika ditolak, redirect ke dashboard masing-masing jika akses web biasa
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            if ($role === 'technician' || $normalizedRole === 'technician') {
                return redirect('/teknisi/dashboard');
            }
            if ($role === 'collector' || $normalizedRole === 'collector') {
                return redirect('/kolektor/dashboard');
            }
            if ($role === 'cashier' || $normalizedRole === 'cashier') {
                return redirect('/kasir/dashboard');
            }
        }

        abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengakses fitur ini.');
    }
}
