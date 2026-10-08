<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerUsage;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\OntDevice;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\TroubleTicket;
use App\Services\GenieAcsService;
use App\Services\MikrotikService;
use App\Services\PaymentGatewayService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CustomerApiController extends Controller
{
    /**
     * Customer Dashboard Data.
     * GET /api/v1/customer/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $tenantId = $customer->tenant_id ?? 1;

        $package = $customer->package;

        // Find active unpaid invoice
        $unpaidInvoice = Invoice::where('customer_id', $customer->id)
            ->where('paid', 0)
            ->where('amount', '>', 0)
            ->orderBy('due_date', 'asc')
            ->first();

        // Count pending tickets
        $openTicketsCount = TroubleTicket::where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        // Get ONT & session info
        [$hasAcsDevice, $onuData] = $this->getOnuInfo($customer, $tenantId);

        // Active session metrics from MikroTik or customer usage
        $router = Mikrotik::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('tenant_id', $tenantId)
            ->first();

        $sessionData = [
            'ip_address' => $customer->ip_address ?? ($onuData['pppoeIP'] ?? '-'),
            'uptime' => 'Aktif Terhubung',
            'download_speed' => '0.0 Mbps',
            'upload_speed' => '0.0 Mbps',
            'download_bytes' => 0,
            'upload_bytes' => 0,
        ];

        if ($router && !empty($customer->pppoe_username)) {
            try {
                $mk = new MikrotikService($router);
                $activeList = $mk->query('/ppp/active/print', ['?name' => $customer->pppoe_username]);
                $activeSession = !empty($activeList[0]) ? $activeList[0] : null;
                if ($activeSession) {
                    $sessionData['ip_address'] = $activeSession['address'] ?? $sessionData['ip_address'];
                    $sessionData['uptime'] = $this->formatUptimeHuman($activeSession['uptime'] ?? null) ?? 'Aktif Terhubung';
                    $sessionData['download_bytes'] = (int) ($activeSession['bytes-in'] ?? 0);
                    $sessionData['upload_bytes'] = (int) ($activeSession['bytes-out'] ?? 0);
                }
            } catch (\Throwable $e) {
                // Ignore mikrotik timeout
            }
        }

        // Monthly usage history from customer_usage table
        $currentMonth = (int) now()->month;
        $currentYear = (int) now()->year;
        $history = DB::table('customer_usage')
            ->where('customer_id', $customer->id)
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->limit(6)
            ->get();

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];

        $formattedHistory = $history->map(function ($h) use ($monthNames) {
            $monthNum = (int) $h->period_month;
            $monthLabel = ($monthNames[$monthNum] ?? "Bln {$monthNum}") . " '{$h->period_year}";
            $download = (int) ($h->bytes_out ?? $h->tx_bytes ?? 0);
            $upload = (int) ($h->bytes_in ?? $h->rx_bytes ?? 0);
            $total = $download + $upload;

            return [
                'period' => $h->period_year . '-' . str_pad((string)$monthNum, 2, '0', STR_PAD_LEFT),
                'label' => $monthLabel,
                'download' => $download,
                'upload' => $upload,
                'total' => $total,
            ];
        })->toArray();

        $isDefaultPin = empty($customer->portal_password) || $customer->portal_password === '123456';

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'code' => $customer->code ?? (string) $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'status' => strtoupper($customer->status ?? 'active'),
                    'ip_address' => $sessionData['ip_address'],
                    'install_date' => $customer->install_date ? $customer->install_date->format('d/m/Y') : null,
                    'is_default_pin' => $isDefaultPin,
                ],
                'package' => $package ? [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price' => (float) $package->price,
                    'speed' => $package->speed ?? '20 Mbps',
                    'description' => $package->description,
                ] : null,
                'billing' => [
                    'has_unpaid_invoice' => (bool) $unpaidInvoice,
                    'unpaid_invoice' => $unpaidInvoice ? [
                        'id' => $unpaidInvoice->id,
                        'invoice_number' => $unpaidInvoice->invoice_number,
                        'amount' => (float) $unpaidInvoice->amount,
                        'period' => $unpaidInvoice->period,
                        'due_date' => $unpaidInvoice->due_date ? Carbon::parse($unpaidInvoice->due_date)->format('d/m/Y') : null,
                        'is_overdue' => $unpaidInvoice->due_date && Carbon::parse($unpaidInvoice->due_date)->isPast(),
                    ] : null,
                ],
                'session' => $sessionData,
                'has_acs_device' => $hasAcsDevice,
                'onu' => $onuData,
                'usage_history' => $formattedHistory,
                'open_tickets_count' => $openTicketsCount,
            ],
        ]);
    }

    /**
     * Customer Invoices List.
     * GET /api/v1/customer/invoices
     */
    public function invoices(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $invoices = Invoice::where('customer_id', $customer->id)
            ->where('amount', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'amount' => (float) $inv->amount,
                    'period' => $inv->period,
                    'paid' => (bool) $inv->paid,
                    'status' => $inv->paid ? 'paid' : ($inv->due_date && Carbon::parse($inv->due_date)->isPast() ? 'overdue' : 'unpaid'),
                    'due_date' => $inv->due_date ? Carbon::parse($inv->due_date)->format('d/m/Y') : null,
                    'paid_at' => $inv->paid_at ? Carbon::parse($inv->paid_at)->format('d/m/Y H:i') : null,
                    'payment_method' => $inv->payment_channel ?? $inv->payment_method ?? ($inv->paid ? 'CASH' : null),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Pay Unpaid Invoice (Generate Dynamic QRIS / Checkout).
     * POST /api/v1/customer/pay-invoice
     */
    public function payInvoice(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $tenantId = $customer->tenant_id ?? 1;

        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice ID diperlukan',
                'errors' => $validator->errors(),
            ], 422);
        }

        $invoice = Invoice::where('id', $request->invoice_id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Data tagihan tidak ditemukan',
            ], 404);
        }

        if ($invoice->paid) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan ini sudah berstatus lunas',
            ], 400);
        }

        try {
            $paymentService = app(PaymentService::class);
            $qrisResult = $paymentService->processDynamicQris($invoice, $tenantId);

            if ($qrisResult && !empty($qrisResult['success'])) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => (float) $invoice->amount,
                        'total_amount' => (float) ($qrisResult['total_amount'] ?? $invoice->amount),
                        'fee' => (float) ($qrisResult['fee'] ?? 0),
                        'qris_content' => $qrisResult['qris_content'] ?? null,
                        'qris_image_url' => $qrisResult['qris_image_url'] ?? null,
                        'checkout_url' => $qrisResult['checkout_url'] ?? null,
                        'gateway' => $qrisResult['gateway'] ?? 'QRIS',
                        'expires_at' => $qrisResult['expires_at'] ?? now()->addMinutes(15)->toIso8601String(),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $qrisResult['message'] ?? 'Metode pembayaran online belum tersedia. Hubungi admin untuk konfirmasi pembayaran.',
            ], 400);
        } catch (\Throwable $e) {
            Log::error('API payInvoice error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembayaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Customer WiFi & ONT Telemetry (TR-069 GenieACS).
     * GET /api/v1/customer/wifi
     */
    public function getWifi(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $tenantId = $customer->tenant_id ?? 1;

        [$hasAcsDevice, $onuData] = $this->getOnuInfo($customer, $tenantId);

        return response()->json([
            'success' => true,
            'data' => [
                'has_acs_device' => $hasAcsDevice,
                'onu' => $onuData,
            ],
        ]);
    }

    /**
     * Change Customer WiFi Password / SSID (TR-069 GenieACS).
     * POST /api/v1/customer/wifi/change-password
     */
    public function changeWifiPassword(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $tenantId = $customer->tenant_id ?? 1;

        $validator = Validator::make($request->all(), [
            'ssid' => 'nullable|string|min:3|max:32',
            'password' => 'nullable|string|min:8|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama SSID minimal 3 karakter & Password minimal 8 karakter',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ontDevice = OntDevice::where('customer_id', $customer->id)->first();
        if (!$ontDevice && !empty($customer->pppoe_username)) {
            $ontDevice = OntDevice::where('tenant_id', $tenantId)
                ->where(function ($q) use ($customer) {
                    $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                        ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                })->first();
        }

        if (!$ontDevice) {
            return response()->json([
                'success' => false,
                'message' => 'Perangkat modem ONT belum terhubung ke otomasi jaringan',
            ], 404);
        }

        $newSsid = trim((string) $request->input('ssid'));
        $newPassword = trim((string) $request->input('password'));

        if (empty($newSsid) && empty($newPassword)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama WiFi atau Password harus diisi',
            ], 422);
        }

        try {
            /** @var GenieAcsService $acs */
            $acs = app(GenieAcsService::class);
            $pSsid = !empty($newSsid) ? $newSsid : null;
            $pPass = !empty($newPassword) ? $newPassword : null;

            $acs->setWifiConfig($ontDevice->serial_number, $pSsid, $pPass, $tenantId);

            $updateData = [];
            if ($pSsid) $updateData['wifi_ssid'] = $pSsid;
            if ($pPass) $updateData['wifi_password'] = $pPass;
            $ontDevice->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Pengaturan WiFi berhasil dikirim. Perubahan akan diterapkan ke modem Anda dalam 1-2 menit.',
            ]);
        } catch (\Throwable $e) {
            Log::error('API changeWifiPassword error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah konfigurasi WiFi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reboot Customer Modem ONT.
     * POST /api/v1/customer/wifi/reboot
     */
    public function rebootModem(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $tenantId = $customer->tenant_id ?? 1;

        $ontDevice = OntDevice::where('customer_id', $customer->id)->first();
        if (!$ontDevice && !empty($customer->pppoe_username)) {
            $ontDevice = OntDevice::where('tenant_id', $tenantId)
                ->where(function ($q) use ($customer) {
                    $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                        ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                })->first();
        }

        if (!$ontDevice) {
            return response()->json([
                'success' => false,
                'message' => 'Modem ONT tidak ditemukan',
            ], 404);
        }

        try {
            /** @var GenieAcsService $acs */
            $acs = app(GenieAcsService::class);
            $success = $acs->rebootDevice($ontDevice->serial_number, $tenantId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Perintah reboot berhasil dikirim. Modem sedang memulai ulang dalam 1-2 menit.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Modem tidak merespon perintah reboot. Pastikan modem sedang online.',
            ], 400);
        } catch (\Throwable $e) {
            Log::error('API rebootModem error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mereboot modem: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update Customer Portal Security PIN.
     * POST /api/v1/customer/profile/pin
     */
    public function updatePin(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $validator = Validator::make($request->all(), [
            'current_pin' => 'nullable|string',
            'new_pin' => 'required|string|size:6|regex:/^[0-9]+$/',
            'new_pin_confirmation' => 'required|same:new_pin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'PIN harus terdiri dari 6 digit angka dan konfirmasi harus cocok.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify current PIN if user already set custom password
        if (!empty($customer->portal_password) && $customer->portal_password !== '123456') {
            $currentPin = $request->input('current_pin');
            if (empty($currentPin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masukkan PIN lama Anda saat ini',
                ], 422);
            }
            if (!Hash::check($currentPin, $customer->portal_password) && $customer->portal_password !== $currentPin) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN saat ini salah',
                ], 422);
            }
        }

        $newPin = $request->input('new_pin');
        $customer->update([
            'portal_password' => Hash::make($newPin),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'PIN Keamanan portal berhasil diperbarui.',
        ]);
    }

    /**
     * Realtime Traffic Telemetry (Auto-polling).
     * GET /api/v1/customer/realtime-traffic
     */
    public function realtimeTraffic(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $tenantId = $customer->tenant_id ?? 1;

        $router = Mikrotik::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('tenant_id', $tenantId)
            ->first();

        $downloadSpeed = '0.0 Mbps';
        $uploadSpeed = '0.0 Mbps';
        $downloadBytes = 0;
        $uploadBytes = 0;
        $uptime = 'Aktif Terhubung';

        if ($router && !empty($customer->pppoe_username)) {
            try {
                $mk = new MikrotikService($router);
                $activeList = $mk->query('/ppp/active/print', ['?name' => $customer->pppoe_username]);
                $activeSession = !empty($activeList[0]) ? $activeList[0] : null;
                if ($activeSession) {
                    $uptime = $this->formatUptimeHuman($activeSession['uptime'] ?? null) ?? 'Aktif Terhubung';
                    $downloadBytes = (int) ($activeSession['bytes-in'] ?? 0);
                    $uploadBytes = (int) ($activeSession['bytes-out'] ?? 0);

                    // Query queue rate
                    $queues = $mk->query('/queue/simple/print', ['.proplist' => 'name,target,bytes,rate']);
                    foreach ($queues as $q) {
                        if (($q['name'] ?? '') === $customer->pppoe_username || ($q['target'] ?? '') === ($activeSession['address'] ?? '')) {
                            $rateStr = $q['rate'] ?? '0/0';
                            $parts = explode('/', $rateStr);
                            $txBps = (float) ($parts[0] ?? 0);
                            $rxBps = (float) ($parts[1] ?? 0);
                            $uploadSpeed = $this->formatSpeedAdaptively($txBps);
                            $downloadSpeed = $this->formatSpeedAdaptively($rxBps);
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Return defaults
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'download_speed' => $downloadSpeed,
                'upload_speed' => $uploadSpeed,
                'download_bytes' => $downloadBytes,
                'upload_bytes' => $uploadBytes,
                'uptime' => $uptime,
                'timestamp' => now()->timestamp,
            ],
        ]);
    }

    /**
     * Speedtest Ping & Latency.
     * GET /api/v1/customer/speedtest/ping
     */
    public function speedtestPing(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'timestamp' => microtime(true),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Speedtest Download Stream.
     * GET /api/v1/customer/speedtest/download
     */
    public function speedtestDownload(Request $request)
    {
        $sizeMb = min(max((int) $request->query('size', 4), 1), 10);
        $chunk = str_repeat('0123456789abcdef', 4096); // 64KB chunk
        $totalBytes = $sizeMb * 1024 * 1024;
        $chunksNeeded = (int) ceil($totalBytes / strlen($chunk));

        return response()->stream(function () use ($chunk, $chunksNeeded) {
            for ($i = 0; $i < $chunksNeeded; $i++) {
                echo $chunk;
                if (ob_get_level() > 0) ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $totalBytes,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Speedtest Upload.
     * POST /api/v1/customer/speedtest/upload
     */
    public function speedtestUpload(Request $request): JsonResponse
    {
        $bytes = strlen($request->getContent());
        return response()->json([
            'success' => true,
            'received' => $bytes,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Customer Tickets List.
     * GET /api/v1/customer/tickets
     */
    public function tickets(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $tickets = TroubleTicket::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number ?? "TICK-{$t->id}",
                    'title' => $t->title,
                    'category' => $t->category ?? 'Koneksi Lambat',
                    'description' => $t->description,
                    'status' => $t->status,
                    'priority' => $t->priority ?? 'medium',
                    'created_at' => $t->created_at->format('d/m/Y H:i'),
                    'resolved_at' => $t->resolved_at ? Carbon::parse($t->resolved_at)->format('d/m/Y H:i') : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Submit a New Trouble Ticket.
     * POST /api/v1/customer/tickets
     */
    public function createTicket(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Lengkapi data laporan gangguan',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket = TroubleTicket::create([
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'title' => $request->title,
            'category' => $request->category,
            'description' => $request->description,
            'status' => 'open',
            'priority' => 'medium',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Laporan gangguan berhasil dikirim. Teknisi kami akan segera memproses.',
            'data' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number ?? "TICK-{$ticket->id}",
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->format('d/m/Y H:i'),
            ],
        ], 201);
    }

    // ──────────────────────────────────────────
    // Internal Helper Methods
    // ──────────────────────────────────────────

    private function getOnuInfo($customer, $tenantId = null): array
    {
        $ontDevice = OntDevice::where('customer_id', $customer->id)->first();
        if (!$ontDevice && !empty($customer->pppoe_username) && $tenantId) {
            $ontDevice = OntDevice::where('tenant_id', $tenantId)
                ->where(function ($q) use ($customer) {
                    $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                        ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                })->first();
        }

        if (!$ontDevice) {
            return [false, null];
        }

        $isOnline = strtoupper($ontDevice->status ?? '') === 'ONLINE';
        if (!$isOnline && $ontDevice->last_inform_at) {
            $isOnline = $ontDevice->last_inform_at->diffInMinutes(now()) <= 60;
        }

        $rawHosts = $ontDevice->raw_parameters['hosts'] ?? [];
        $formattedHosts = [];
        if (is_array($rawHosts)) {
            foreach ($rawHosts as $h) {
                if (is_array($h)) {
                    $formattedHosts[] = [
                        'hostname' => $h['hostname'] ?? ($h['HostName'] ?? 'Perangkat'),
                        'ip_address' => $h['ip_address'] ?? ($h['IPAddress'] ?? '-'),
                        'mac_address' => $h['mac_address'] ?? ($h['MACAddress'] ?? '-'),
                        'is_active' => !empty($h['is_active']) || (!empty($h['Active']) && $h['Active'] !== 'false'),
                        'interface_type' => $h['interface_type'] ?? ($h['InterfaceType'] ?? 'WiFi 802.11'),
                    ];
                }
            }
        }

        $activeHosts = array_filter($formattedHosts, fn($h) => !empty($h['is_active']));
        $clientsCount = $ontDevice->connected_devices_count ?: (count($activeHosts) ?: count($formattedHosts));

        $rxPower = $ontDevice->rx_power !== null ? (string) $ontDevice->rx_power : ($ontDevice->raw_parameters['optical']['rx_power'] ?? null);
        $txPower = $ontDevice->optical_tx_power ?? ($ontDevice->raw_parameters['optical']['tx_power'] ?? null);
        $voltage = $ontDevice->optical_voltage ?? ($ontDevice->raw_parameters['optical']['voltage'] ?? null);
        $temp = $ontDevice->optical_temp ?? ($ontDevice->raw_parameters['optical']['temperature'] ?? null);

        $rxVal = is_numeric($rxPower) ? (float) $rxPower : null;
        $rxStatus = 'BAGUS';
        if ($rxVal !== null) {
            if ($rxVal < -27.0) {
                $rxStatus = 'BURUK';
            } elseif ($rxVal < -24.0) {
                $rxStatus = 'CUKUP';
            }
        }

        return [
            true,
            [
                'serial' => $ontDevice->serial_number,
                'model' => $ontDevice->model_name ?: ($ontDevice->model ?? 'ONT Router'),
                'manufacturer' => $ontDevice->manufacturer ?? 'ZICG',
                'lastInform' => $ontDevice->last_inform_at?->toIso8601String(),
                'online' => $isOnline,
                'ssid' => $ontDevice->wifi_ssid ?? ($ontDevice->raw_parameters['wifi']['ssid'] ?? ''),
                'wifiPassword' => $ontDevice->wifi_password ?? ($ontDevice->raw_parameters['wifi']['password'] ?? ''),
                'rxPower' => $rxPower !== null ? (string) $rxPower : '',
                'rxStatus' => $rxStatus,
                'txPower' => $txPower !== null ? (string) $txPower : null,
                'voltage' => $voltage !== null ? (string) $voltage : null,
                'temp' => $temp !== null ? (string) $temp : null,
                'pppoeIP' => $ontDevice->ip_address ?? '',
                'clients' => $clientsCount,
                'hosts' => $formattedHosts,
            ],
        ];
    }

    private function formatUptimeHuman(?string $uptime): ?string
    {
        if (empty($uptime) || $uptime === '-' || $uptime === '0s') {
            return null;
        }

        $clean = strtolower(trim($uptime));
        $days = 0; $hours = 0; $mins = 0;

        if (preg_match('/(?:(\d+)w)?(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/', $clean, $m)) {
            $weeks = !empty($m[1]) ? (int)$m[1] : 0;
            $days = (!empty($m[2]) ? (int)$m[2] : 0) + ($weeks * 7);
            $hours = !empty($m[3]) ? (int)$m[3] : 0;
            $mins = !empty($m[4]) ? (int)$m[4] : 0;
        }

        $parts = [];
        if ($days > 0) $parts[] = "{$days} Hari";
        if ($hours > 0) $parts[] = "{$hours} Jam";
        if ($mins > 0) $parts[] = "{$mins} Menit";

        return !empty($parts) ? implode(' ', $parts) : 'Aktif Terhubung';
    }

    private function formatSpeedAdaptively(float $bps): string
    {
        if ($bps >= 1000000) {
            return number_format($bps / 1000000, 1, '.', '') . ' Mbps';
        }
        if ($bps >= 1000) {
            return number_format($bps / 1000, 0, '.', '') . ' Kbps';
        }
        return number_format($bps, 0, '.', '') . ' bps';
    }
}
