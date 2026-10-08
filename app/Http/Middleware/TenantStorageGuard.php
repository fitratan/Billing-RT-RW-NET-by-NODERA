<?php

namespace App\Http\Middleware;

use App\Services\FileStorageService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TenantStorageGuard
{
    /**
     * Handle an incoming request for private tenant storage.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If request is a signed URL route, ensure valid signature
        if ($request->routeIs('tenant.storage.download') && !$request->hasValidSignature()) {
            Log::warning("[TenantStorageGuard] Invalid or expired signed URL requested: " . $request->fullUrl());
            abort(403, 'Link download kedaluwarsa atau tidak valid.');
        }

        // 2. Resolve decoded path
        $encodedPath = $request->route('path') ?? $request->query('path');
        if ($encodedPath) {
            $path = base64_decode($encodedPath);
            $activeTenantUuid = session('tenant_uuid');
            $isSuperAdmin = (session('admin_role') === 'superadmin') || (auth()->user()?->isSuperAdmin() ?? false);

            if (!$isSuperAdmin && !empty($activeTenantUuid)) {
                $storageService = app(FileStorageService::class);
                if (!$storageService->authorizeTenantAccess($path, $activeTenantUuid)) {
                    Log::warning("[TenantStorageGuard] IDOR attempt blocked. Tenant {$activeTenantUuid} tried to access {$path}");
                    abort(403, 'Akses tidak diizinkan ke dokumen ini.');
                }
            }
        }

        return $next($request);
    }
}
