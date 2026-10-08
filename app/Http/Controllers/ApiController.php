<?php

namespace App\Http\Controllers;

use App\Services\GenieacsService;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    protected GenieacsService $genieacs;
    protected MikrotikService $mikrotik;

    public function __construct(GenieacsService $genieacs, MikrotikService $mikrotik)
    {
        $this->genieacs = $genieacs;
        $this->mikrotik = $mikrotik;
    }

    /**
     * Get ONU locations from database
     */
    public function onuLocations()
    {
        try {
            $tenantId = \App\Models\Scopes\TenantScope::rawTenantId();
            // Select serial_number as serial for frontend compatibility
            $rawLocations = DB::table('onu_locations')
                ->when($tenantId, fn($q) => $q->where('onu_locations.tenant_id', $tenantId))
                ->select('id', 'name', 'serial_number as serial', 'lat', 'lng')
                ->get();

            // CRITICAL: Manually convert each field to correct type
            // This ensures JavaScript receives actual numbers, not strings
            $locations = $rawLocations->map(fn($loc) => [
                'id' => (int) $loc->id,
                'name' => (string) $loc->name,
                'serial' => (string) $loc->serial,
                'lat' => (double) $loc->lat,
                'lng' => (double) $loc->lng,
            ]);

            Log::info('API onuLocations: Returning ' . count($locations) . ' locations');

            // Use json_encode with JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION
            $json = json_encode($locations, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
            return response($json, 200)->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('API onuLocations error: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Get single ONU detail from GenieACS
     */
    public function onuDetail(Request $request)
    {
        $serial = $request->get('serial');
        if (!$serial) return response()->json([]);

        try {
            $device = $this->genieacs->getDevice($serial);
            return response()->json($device);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * Add or Update ONU location (Upsert)
     */
    public function addOnu(Request $request)
    {
        $json = $request->json()->all();
        $name = $json['name'] ?? '';
        $serial = $json['serial'] ?? '';
        $lat = $json['lat'] ?? 0;
        $lng = $json['lng'] ?? 0;

        if (empty($serial) || empty($lat) || empty($lng)) {
            return response()->json(['success' => false, 'message' => 'Serial, Latitude dan Longitude wajib diisi']);
        }

        try {
            $tenantId = \App\Models\Scopes\TenantScope::rawTenantId();
            $existing = DB::table('onu_locations')
                ->where('serial_number', $serial)
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->first();

            if ($existing) {
                DB::table('onu_locations')->where('id', $existing->id)->update([
                    'name' => $name, 'lat' => $lat, 'lng' => $lng, 'updated_at' => now(),
                ]);
                return response()->json(['success' => true, 'message' => 'Lokasi ONU berhasil diperbarui']);
            } else {
                DB::table('onu_locations')->insert([
                    'name' => $name, 'serial_number' => $serial, 'lat' => $lat, 'lng' => $lng,
                    'tenant_id' => $tenantId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                return response()->json(['success' => true, 'message' => 'Lokasi ONU berhasil ditambahkan']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete ONU location
     */
    public function deleteOnu(Request $request)
    {
        $json = $request->json()->all();
        $serial = $json['serial'] ?? '';

        if (empty($serial)) {
            return response()->json(['success' => false, 'message' => 'Serial number wajib diisi']);
        }

        try {
            $tenantId = \App\Models\Scopes\TenantScope::rawTenantId();
            $deleted = DB::table('onu_locations')
                ->where('serial_number', $serial)
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->delete();

            if ($deleted) {
                return response()->json(['success' => true, 'message' => 'Lokasi ONU berhasil dihapus']);
            } else {
                return response()->json(['success' => false, 'message' => 'ONU tidak ditemukan']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * Update ONU WiFi settings via GenieACS
     */
    public function updateWifi(Request $request)
    {
        $json = $request->json()->all();

        $serial = $json['serial'] ?? '';
        $ssid = $json['ssid'] ?? '';
        $password = $json['password'] ?? '';

        // Serial required; SSID or Password may be optional
        if (empty($serial)) {
            return response()->json(['success' => false, 'message' => 'Serial number wajib diisi']);
        }

        // At least one of SSID or password must be set
        if (empty($ssid) && empty($password)) {
            return response()->json(['success' => false, 'message' => 'SSID atau Password harus diisi (minimal salah satu)']);
        }

        // Validate password if provided
        if (!empty($password) && strlen($password) < 8) {
            return response()->json(['success' => false, 'message' => 'Password minimal 8 karakter']);
        }

        try {
            // Log request
            Log::info("API updateWifi: serial={$serial}, ssid={$ssid}, password=" . (empty($password) ? 'empty' : 'set'));

            $result = $this->genieacs->setWifi($serial, $ssid, $password);

            // Log response
            Log::info("GenieACS setWifi response: " . json_encode($result));

            $code = $result['code'] ?? 0;
            $error = $result['error'] ?? '';
            $body = $result['body'] ?? [];

            if ($code === 200 || $code === 202) {
                return response()->json(['success' => true, 'message' => 'WiFi berhasil diperbarui']);
            }

            // More detailed error message
            $errorMsg = 'Unknown error';

            if (!empty($error)) {
                $errorMsg = $error;
            } elseif (is_array($body) && isset($body['message'])) {
                $errorMsg = $body['message'];
            } elseif (is_array($body) && isset($body['error'])) {
                $errorMsg = $body['error'];
            } elseif ($code === 404) {
                $errorMsg = "Device not found (serial: {$serial})";
            } elseif ($code === 0) {
                $errorMsg = "Cannot connect to GenieACS server";
            }

            Log::error("GenieACS setWifi failed: code={$code}, error={$errorMsg}");

            return response()->json(['success' => false, 'message' => "GenieACS error: {$errorMsg} (code: {$code})"]);
        } catch (\Exception $e) {
            Log::error("API updateWifi exception: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Realtime Dashboard Stats
     * GET /api/dashboard/stats
     */
    public function dashboardStats()
    {
        try {
            $today = now()->format('Y-m-d');
            $tenantId = \App\Models\Scopes\TenantScope::rawTenantId();
            $todayRevenue = DB::table('invoices')->where('paid', 1)->whereNotNull('paid_at')->whereDate('paid_at', $today)->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))->sum('amount');
            if ($todayRevenue == 0) {
                $todayRevenue = DB::table('invoices')->where('paid', 1)->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))->sum('amount');
            }
            $onlinePppoe = DB::table('customers')->where('status', 'active')->when($tenantId, fn($q) => $q->where('customers.tenant_id', $tenantId))->count();
            $pendingInvoices = DB::table('invoices')->where('status', 'pending')->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))->count();
            $totalDevices = DB::table('onu_locations')->when($tenantId, fn($q) => $q->where('onu_locations.tenant_id', $tenantId))->count();
            $pendingTickets = DB::table('trouble_tickets')->whereIn('status', ['pending', 'in_progress'])->when($tenantId, fn($q) => $q->where('trouble_tickets.tenant_id', $tenantId))->count();

            $stats = [
                'onlinePppoe' => (int) $onlinePppoe,
                'totalDevices' => (int) $totalDevices,
                'pendingTickets' => (int) $pendingTickets,
                'timestamp' => time(),
            ];

            // Only Admin can see financial stats
            if (session('admin_role') === 'admin') {
                $stats['todayRevenue'] = (int) $todayRevenue;
                $stats['pendingInvoices'] = (int) $pendingInvoices;
            }

            return response()->json(['success' => true, 'stats' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Realtime Analytics Summary
     * GET /api/analytics/summary
     */
    public function analyticsSummary()
    {
        try {
            $currentMonth = now()->format('Y-m');
            $tenantId = \App\Models\Scopes\TenantScope::rawTenantId();
            $revenueThisMonth = DB::table('invoices')->where('paid', 1)->whereNotNull('paid_at')
                ->whereRaw("DATE_FORMAT(paid_at, '%Y-%m') = ?", [$currentMonth])->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))->sum('amount');
            if ($revenueThisMonth == 0) {
                $revenueThisMonth = DB::table('invoices')->where('paid', 1)->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))->sum('amount');
            }
            $paidInvoices = DB::table('invoices')->where('paid', 1)->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))->count();
            $unpaidInvoices = DB::table('invoices')->where('paid', 0)->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))->count();
            $totalCustomers = DB::table('customers')->when($tenantId, fn($q) => $q->where('customers.tenant_id', $tenantId))->count();

            return response()->json(['success' => true, 'summary' => [
                'revenueThisMonth' => (int) $revenueThisMonth,
                'paidInvoices' => (int) $paidInvoices,
                'unpaidInvoices' => (int) $unpaidInvoices,
                'totalCustomers' => (int) $totalCustomers,
                'timestamp' => time(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Realtime Recent Invoices
     * GET /api/invoices/recent
     */
    public function recentInvoices(Request $request)
    {
        try {
            $limit = (int) ($request->get('limit') ?? 10);
            $tenantId = \App\Models\Scopes\TenantScope::rawTenantId();
            $invoices = DB::table('invoices')
                ->select('invoices.*', 'customers.name as customer_name')
                ->join('customers', 'customers.id', '=', 'invoices.customer_id', 'left')
                ->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId))
                ->orderBy('invoices.created_at', 'DESC')
                ->limit($limit)
                ->get();

            return response()->json(['success' => true, 'invoices' => $invoices, 'count' => count($invoices), 'timestamp' => time()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Customers List with Pagination
     * GET /api/customers/list?page=1&per_page=50&search=
     */
    public function customersList(Request $request)
    {
        try {
            $page = max(1, (int) ($request->get('page') ?? 1));
            $perPage = max(10, min(100, (int) ($request->get('per_page') ?? 50)));
            $search = $request->get('search') ?? '';
            $tenantId = \App\Models\Scopes\TenantScope::rawTenantId();

            $query = DB::table('customers')
                ->select('customers.*', 'packages.name as package_name', 'packages.price as package_price')
                ->join('packages', 'packages.id', '=', 'customers.package_id', 'left')
                ->when($tenantId, fn($q) => $q->where('customers.tenant_id', $tenantId));

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('customers.name', 'like', "%{$search}%")
                      ->orWhere('customers.phone', 'like', "%{$search}%")
                      ->orWhere('customers.pppoe_username', 'like', "%{$search}%");
                });
            }

            $total = $query->count();
            $offset = ($page - 1) * $perPage;
            $customers = $query->orderBy('customers.id', 'DESC')->offset($offset)->limit($perPage)->get();

            return response()->json([
                'success' => true, 'data' => $customers,
                'pagination' => [
                    'current_page' => $page, 'per_page' => $perPage,
                    'total_records' => $total, 'total_pages' => ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Invoices List with Pagination
     * GET /api/invoices/list?page=1&per_page=50&search=
     */
    public function invoicesList(Request $request)
    {
        try {
            $page = max(1, (int) ($request->get('page') ?? 1));
            $perPage = max(10, min(100, (int) ($request->get('per_page') ?? 50)));
            $search = $request->get('search') ?? '';
            $tenantId = \App\Models\Scopes\TenantScope::rawTenantId();

            $query = DB::table('invoices')
                ->select('invoices.*', 'customers.name as customer_name', 'customers.pppoe_username')
                ->join('customers', 'customers.id', '=', 'invoices.customer_id', 'left')
                ->when($tenantId, fn($q) => $q->where('invoices.tenant_id', $tenantId));

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoices.invoice_number', 'like', "%{$search}%")
                      ->orWhere('customers.name', 'like', "%{$search}%")
                      ->orWhere('customers.pppoe_username', 'like', "%{$search}%");
                });
            }

            $total = $query->count();
            $offset = ($page - 1) * $perPage;
            $invoices = $query->orderBy('invoices.created_at', 'DESC')->offset($offset)->limit($perPage)->get();

            return response()->json([
                'success' => true, 'data' => $invoices,
                'pagination' => [
                    'current_page' => $page, 'per_page' => $perPage,
                    'total_records' => $total, 'total_pages' => ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * MikroTik Users with Pagination
     * GET /api/mikrotik/users?page=1&per_page=50
     */
    public function mikrotikUsers(Request $request)
    {
        try {
            $page = max(1, (int) ($request->get('page') ?? 1));
            $perPage = max(10, min(100, (int) ($request->get('per_page') ?? 50)));

            if (!$this->mikrotik->isConnected()) {
                return response()->json(['success' => false, 'error' => 'Cannot connect to MikroTik']);
            }

            $allUsers = $this->mikrotik->getPppoeSecrets();
            $total = count($allUsers);
            $offset = ($page - 1) * $perPage;
            $users = array_slice($allUsers, $offset, $perPage);

            return response()->json([
                'success' => true, 'data' => $users,
                'pagination' => [
                    'current_page' => $page, 'per_page' => $perPage,
                    'total_records' => $total, 'total_pages' => ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * WhatsApp Webhook
     */
    public function whatsappWebhook(Request $request)
    {
        $controller = new WebhookController();
        return $controller->whatsapp($request);
    }
}
