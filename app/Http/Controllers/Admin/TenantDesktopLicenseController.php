<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesktopLicense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantDesktopLicenseController extends Controller
{
    /**
     * Display tenant's desktop licenses
     */
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant ?? auth()->user()->tenant;
        $tenantId = $tenant ? $tenant->id : null;

        $query = DesktopLicense::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Filter search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhere('hwid', 'like', "%{$search}%")
                  ->orWhere('device_name', 'like', "%{$search}%");
            });
        }

        // Filter status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $licenses = $query->latest()->paginate(15)->withQueryString();

        // Metrics
        $total = DesktopLicense::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();
        $active = DesktopLicense::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'ACTIVE')->count();
        $expired = DesktopLicense::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'EXPIRED')->count();

        return Inertia::render('Admin/DesktopLicenses', [
            'licenses' => $licenses,
            'filters' => $request->only(['search', 'status']),
            'metrics' => [
                'total' => $total,
                'active' => $active,
                'expired' => $expired,
            ],
            'tenant' => $tenant,
        ]);
    }
}
