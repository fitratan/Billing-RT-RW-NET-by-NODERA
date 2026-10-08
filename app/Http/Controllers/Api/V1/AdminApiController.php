<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\User;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminApiController extends Controller
{
    /**
     * Executive Dashboard for Admin Mobile.
     * GET /api/v1/admin/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $today = Carbon::today();
        $thisMonth = Carbon::now()->month;
        $thisYear = Carbon::now()->year;

        $invBase = Invoice::query();
        $custBase = Customer::query();
        $routerBase = Mikrotik::query();

        if ($tenantId) {
            $invBase->where('tenant_id', $tenantId);
            $custBase->where('tenant_id', $tenantId);
            $routerBase->where('tenant_id', $tenantId);
        }

        // Daily Income
        $dailyIncome = (float) (clone $invBase)->where('paid', 1)
            ->whereDate('paid_at', $today)
            ->sum('amount');

        // Monthly Income
        $monthlyIncome = (float) (clone $invBase)->where('paid', 1)
            ->whereYear('paid_at', $thisYear)
            ->whereMonth('paid_at', $thisMonth)
            ->sum('amount');

        // Total Unpaid Amount
        $unpaidAmount = (float) (clone $invBase)->where('paid', 0)->sum('amount');
        $unpaidCount = (clone $invBase)->where('paid', 0)->count();

        // Customer Stats
        $totalCustomers = (clone $custBase)->count();
        $activeCustomers = (clone $custBase)->where('status', 'active')->count();
        $isolatedCustomers = (clone $custBase)->where('status', 'isolated')->count();

        // Router count
        $routersCount = (clone $routerBase)->where('is_active', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => [
                    'today' => $dailyIncome,
                    'this_month' => $monthlyIncome,
                    'unpaid_pending' => $unpaidAmount,
                    'unpaid_invoices_count' => $unpaidCount,
                ],
                'customers' => [
                    'total' => $totalCustomers,
                    'active' => $activeCustomers,
                    'isolated' => $isolatedCustomers,
                ],
                'routers_count' => $routersCount,
            ],
        ]);
    }

    /**
     * List of MikroTik Routers with Health Telemetry.
     * GET /api/v1/admin/routers
     */
    public function routers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $mikrotikService = app(MikrotikService::class);

        $query = Mikrotik::where('is_active', true);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $routers = $query->get()->map(function ($r) use ($mikrotikService) {
            $telemetry = $mikrotikService->getSystemHealth($r);
            $resource = $mikrotikService->getResource($r);

            return [
                'id' => $r->id,
                'name' => $r->name,
                'host' => $r->host,
                'port' => $r->port,
                'location' => $r->location ?? 'Headend Core',
                'is_online' => (bool) ($resource && isset($resource['cpu_load'])),
                'health' => [
                    'temperature_celsius' => $telemetry['temperature'] ?? null,
                    'voltage_volts' => $telemetry['voltage'] ?? null,
                    'cpu_load_percent' => $resource['cpu_load'] ?? null,
                    'memory_percent' => $resource['memory_percent'] ?? null,
                    'uptime' => $resource['uptime'] ?? null,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $routers,
        ]);
    }

    /**
     * Real-time Interface Traffic.
     * GET /api/v1/admin/routers/{id}/traffic
     */
    public function routerTraffic(Request $request, $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $query = Mikrotik::where('id', $id);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $router = $query->first();

        if (!$router) {
            return response()->json(['success' => false, 'message' => 'Router tidak ditemukan'], 404);
        }

        $interface = $request->get('interface', 'all');
        $mikrotikService = app(MikrotikService::class);
        $traffic = $mikrotikService->getInterfaceTraffic($router, $interface);

        return response()->json([
            'success' => true,
            'data' => $traffic,
        ]);
    }

    /**
     * Emergency Toggle Customer Status (Active vs Isolated).
     * POST /api/v1/admin/customers/{id}/toggle-status
     */
    public function toggleCustomerStatus(Request $request, $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $query = Customer::where('id', $id);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $customer = $query->first();

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Pelanggan tidak ditemukan'], 404);
        }

        $newStatus = $customer->status === 'active' ? 'isolated' : 'active';
        $isolationService = app(\App\Services\IsolationService::class);
        if ($newStatus === 'isolated') {
            $isolationService->isolateCustomer($customer, 'Admin API', true);
        } else {
            $isolationService->unisolateCustomer($customer, 'Admin API', true);
        }

        $customer->refresh();

        return response()->json([
            'success' => true,
            'message' => "Status pelanggan {$customer->name} berhasil diubah menjadi " . strtoupper($newStatus),
            'customer' => [
                'id' => $customer->id,
                'status' => $customer->status,
            ],
        ]);
    }
}
