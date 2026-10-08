<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Collector;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\TroubleTicket;
use App\Models\User;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Services\GenieacsService;
use App\Services\PaymentService;
use App\Services\TripayService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PortalController extends Controller
{
    protected GenieacsService $genieacs;

    public function __construct(GenieacsService $genieacs)
    {
        $this->genieacs = $genieacs;
    }

    public function index(Request $request)
    {
        // Identifikasi via customer_id — BUKAN phone (banyak pelanggan
        // tanpa nomor HP → cek phone selalu gagal → ga bisa login).
        $customerId = session('customer_id');

        if (! $customerId) {
            return redirect()->to('/portal/login')->with('error', 'Silakan login terlebih dahulu');
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            return redirect()->to('/portal/login')->with('error', 'Data pelanggan tidak ditemukan');
        }

        if (! session('customer_phone')) {
            session([
                'customer_id' => $customer->id,
                'customer_phone' => $customer->phone,
                'customer_name' => $customer->name,
            ]);
        }

        $package = $customer->package;
        $tenantId = $customer->tenant_id ?? session('customer_tenant_id') ?? session('tenant_id');
        if ($tenantId && !session('tenant_id')) {
            session(['tenant_id' => (int) $tenantId]);
        }

        // Prioritaskan invoice yang belum lunas DENGAN nominal > 0
        $unpaidInvoice = Invoice::withoutGlobalScopes()->where('customer_id', $customer->id)
            ->where('paid', false)
            ->where('amount', '>', 0)
            ->orderBy('created_at', 'DESC')
            ->first();

        $latestInvoice = Invoice::withoutGlobalScopes()->where('customer_id', $customer->id)
            ->orderBy('created_at', 'DESC')
            ->first();

        $currentInvoice = $unpaidInvoice ?: $latestInvoice;

        $invoices = Invoice::withoutGlobalScopes()->where('customer_id', $customer->id)
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get();

        [$hasAcsDevice, $onuData] = $this->getOnuInfo($customer, $tenantId);
        $genieacsConfigured = $hasAcsDevice;

        $router = null;
        if ($customer->router_id) {
            $router = Mikrotik::withoutGlobalScopes()->find($customer->router_id);
            if ($router && $tenantId && $router->tenant_id && (int) $router->tenant_id !== (int) $tenantId) {
                $router = null;
            }
        }
        if (!$router && $tenantId) {
            $router = Mikrotik::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->first();
        }

        $secretUsage = null;
        if ($router && $router->is_active) {
            try {
                $mikService = new \App\Services\MikrotikService($router);
                if ($mikService->isConnected()) {
                    $isArpOrStatic = ($customer->connection_type === 'static' || $customer->connection_type === 'arp' || empty($customer->pppoe_username) || str_starts_with($customer->pppoe_username, 'arp_'));

                    if (!$isArpOrStatic && !empty($customer->pppoe_username)) {
                        // 1. Pelanggan PPPoE
                        $activeSessions = $mikService->getActivePppoe();
                        $userSession = collect($activeSessions)->first(fn ($a) => strtolower($a['name'] ?? '') === strtolower($customer->pppoe_username));

                        if ($userSession) {
                            $downloadBytes = 0;
                            $uploadBytes = 0;

                            // Ambil rx-byte dan tx-byte dari /interface/print
                            try {
                                $ifaces = $mikService->query('/interface/print', ['.proplist' => 'name,rx-byte,tx-byte']);
                                if (is_array($ifaces)) {
                                    $unameLower = strtolower(trim($customer->pppoe_username));
                                    foreach ($ifaces as $iface) {
                                        $iname = $iface['name'] ?? '';
                                        $cleanIface = strtolower(trim($iname, '<> '));
                                        if ($cleanIface === $unameLower || $cleanIface === "pppoe-{$unameLower}" || stripos($cleanIface, $unameLower) !== false) {
                                            $uploadBytes = (int) ($iface['rx-byte'] ?? 0);   // Client Upload (RX to router)
                                            $downloadBytes = (int) ($iface['tx-byte'] ?? 0); // Client Download (TX from router)
                                            break;
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {}

                            // Fallback jika interface 0 byte: cek Simple Queue
                            if ($downloadBytes === 0 && $uploadBytes === 0) {
                                try {
                                    $queues = $mikService->query('/queue/simple/print', ['.proplist' => 'name,target,bytes']);
                                    if (is_array($queues)) {
                                        $userIp = $userSession['address'] ?? '';
                                        foreach ($queues as $q) {
                                            $qTarget = $q['target'] ?? '';
                                            $qName = $q['name'] ?? '';
                                            if (($userIp && str_contains($qTarget, $userIp)) || stripos($qName, $customer->pppoe_username) !== false) {
                                                $parts = explode('/', $q['bytes'] ?? '0/0');
                                                $uploadBytes = isset($parts[0]) ? (int) $parts[0] : 0;
                                                $downloadBytes = isset($parts[1]) ? (int) $parts[1] : 0;
                                                break;
                                            }
                                        }
                                    }
                                } catch (\Throwable $e) {}
                            }

                            $totalBytes = $downloadBytes + $uploadBytes;
                            $secretUsage = [
                                'username' => $customer->pppoe_username,
                                'online' => true,
                                'address' => $userSession['address'] ?? '-',
                                'caller_id' => $userSession['caller-id'] ?? '-',
                                'uptime' => $userSession['uptime'] ?? '-',
                                'download' => $this->formatBytes($downloadBytes),
                                'upload' => $this->formatBytes($uploadBytes),
                                'total' => $this->formatBytes($totalBytes),
                                'type' => 'pppoe',
                            ];
                        } else {
                            $secretUsage = [
                                'username' => $customer->pppoe_username,
                                'online' => false,
                                'address' => '-',
                                'caller_id' => '-',
                                'uptime' => 'Offline',
                                'download' => '0 B',
                                'upload' => '0 B',
                                'total' => '0 B',
                                'type' => 'pppoe',
                            ];
                        }
                    } elseif (!empty($customer->ip_address)) {
                        // 2. Pelanggan ARP / Static IP
                        $targetIp = trim($customer->ip_address);
                        $downloadBytes = 0;
                        $uploadBytes = 0;
                        $isOnline = false;
                        $arpMac = $customer->mac_address ?? '-';
                        $iface = 'bridge';

                        // Cek status entri di ARP table
                        try {
                            $arps = $mikService->query('/ip/arp/print', ['.proplist' => 'address,mac-address,interface,complete']);
                            if (is_array($arps)) {
                                foreach ($arps as $arp) {
                                    if (($arp['address'] ?? '') === $targetIp) {
                                        $isOnline = ($arp['complete'] ?? '') === 'true';
                                        $arpMac = $arp['mac-address'] ?? $arpMac;
                                        $iface = $arp['interface'] ?? $iface;
                                        break;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}

                        // Ambil bandwidth dari Simple Queue
                        try {
                            $queues = $mikService->query('/queue/simple/print', ['.proplist' => 'name,target,bytes']);
                            if (is_array($queues)) {
                                foreach ($queues as $q) {
                                    $qTarget = $q['target'] ?? '';
                                    $qName = $q['name'] ?? '';
                                    if (str_contains($qTarget, $targetIp) || stripos($qName, $customer->name) !== false) {
                                        $parts = explode('/', $q['bytes'] ?? '0/0');
                                        $uploadBytes = isset($parts[0]) ? (int) $parts[0] : 0;
                                        $downloadBytes = isset($parts[1]) ? (int) $parts[1] : 0;
                                        $isOnline = true;
                                        break;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}

                        $totalBytes = $downloadBytes + $uploadBytes;
                        $secretUsage = [
                            'username' => $customer->name,
                            'online' => $isOnline,
                            'address' => $targetIp,
                            'caller_id' => $arpMac,
                            'uptime' => $isOnline ? 'Online (' . $iface . ')' : 'Offline',
                            'download' => $this->formatBytes($downloadBytes),
                            'upload' => $this->formatBytes($uploadBytes),
                            'total' => $this->formatBytes($totalBytes),
                            'type' => 'arp',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Mikrotik fetch secret usage error in Portal: '.$e->getMessage());
            }
        }

        $genieacsUrl = \App\Models\Setting::getValue('GENIEACS_URL', '') ?: config('app.genieacs_url', '');
        $genieacsConfigured = ! empty($genieacsUrl) && ! empty($onuData);
        $companyName = $customer->tenant?->name ?? \App\Models\Setting::getValue('COMPANY_NAME', config('app.name', 'NODERA'));

        $isDefaultPin = empty($customer->portal_password) || $customer->portal_password === '123456' || \Illuminate\Support\Facades\Hash::check('123456', $customer->portal_password) || session('is_default_pin', false);

        return Inertia::render('Portal/Dashboard', [
            'companyName' => $companyName,
            'adminWa' => $this->getAdminWaNumber($customer),
            'genieacsConfigured' => $genieacsConfigured,
            'isDefaultPin' => $isDefaultPin,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'pppoe_username' => $customer->pppoe_username,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'package' => $package?->name,
                'isolation_date' => $customer->isolation_date,
            ],
            'currentInvoice' => $currentInvoice ? [
                'id' => $currentInvoice->id,
                'invoice_number' => $currentInvoice->invoice_number,
                'amount' => (float) $currentInvoice->amount,
                'paid' => (bool) $currentInvoice->paid,
                'period' => $currentInvoice->period,
                'due_date' => $currentInvoice->due_date?->toIso8601String(),
            ] : null,
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'amount' => (float) $i->amount,
                'paid' => (bool) $i->paid,
                'period' => $i->period,
                'created_at' => $i->created_at?->toIso8601String(),
            ]),
            'onuData' => $onuData,
            'secretUsage' => $secretUsage,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }

    public function updateWifi(Request $request)
    {
        $isInertia = $request->header('X-Inertia') || !$request->wantsJson();
        $customerId = session('customer_id');
        if (! $customerId) {
            if ($isInertia) {
                return redirect()->to('/portal/login')->with('error', 'Sesi login telah berakhir. Silakan login kembali.');
            }
            return response()->json(['success' => false, 'message' => 'Session expired. Silakan login kembali.'], 401);
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            if ($isInertia) {
                return redirect()->back()->with('error', 'Data pelanggan tidak ditemukan.');
            }
            return response()->json(['success' => false, 'message' => 'Data customer tidak ditemukan'], 422);
        }

        $tenantId = $customer->tenant_id ?? 1;
        $ontDevice = \App\Models\OntDevice::where('customer_id', $customer->id)->first();
        if (! $ontDevice && ! empty($customer->pppoe_username)) {
            $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                ->where(function ($q) use ($customer) {
                    $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                      ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                })->first();
        }

        if (! $ontDevice) {
            if ($isInertia) {
                return redirect()->back()->with('error', 'Perangkat modem ONT belum terhubung ke otomasi TR-069.');
            }
            return response()->json(['success' => false, 'message' => 'Modem ONT tidak ditemukan'], 404);
        }

        $newSsid = trim((string) $request->input('ssid'));
        $newPassword = trim((string) $request->input('password'));

        if (empty($newSsid) && empty($newPassword)) {
            if ($isInertia) {
                return redirect()->back()->withErrors(['ssid' => 'Masukkan nama WiFi atau Password baru.']);
            }
            return response()->json(['success' => false, 'message' => 'Nama WiFi atau Password harus diisi.'], 422);
        }

        if (! empty($newPassword) && strlen($newPassword) < 8) {
            if ($isInertia) {
                return redirect()->back()->withErrors(['password' => 'Password WiFi minimal 8 karakter.']);
            }
            return response()->json(['success' => false, 'message' => 'Password minimal 8 karakter.'], 422);
        }

        try {
            /** @var \App\Services\GenieAcsService $acs */
            $acs = app(\App\Services\GenieAcsService::class);
            $pSsid = ! empty($newSsid) ? $newSsid : null;
            $pPass = ! empty($newPassword) ? $newPassword : null;

            $acs->setWifiConfig($ontDevice->serial_number, $pSsid, $pPass, $tenantId);

            $updateData = [];
            if ($pSsid) $updateData['wifi_ssid'] = $pSsid;
            if ($pPass) $updateData['wifi_password'] = $pPass;
            $ontDevice->update($updateData);

            $msg = 'Pengaturan WiFi berhasil diperbarui! Perubahan Nama WiFi dan/atau Password akan diterapkan ke modem Anda dalam 1-2 menit.';
            if ($isInertia) {
                return redirect()->back()->with('msg', $msg);
            }
            return response()->json(['success' => true, 'message' => $msg]);
        } catch (\Throwable $e) {
            Log::error('Portal updateWifi error: ' . $e->getMessage());
            if ($isInertia) {
                return redirect()->back()->with('error', 'Gagal memperbarui WiFi: ' . $e->getMessage());
            }
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function rebootOnt(Request $request)
    {
        $isInertia = $request->header('X-Inertia') || !$request->wantsJson();
        $customerId = session('customer_id');
        if (! $customerId) {
            if ($isInertia) {
                return redirect()->to('/portal/login')->with('error', 'Sesi login telah berakhir.');
            }
            return response()->json(['success' => false, 'message' => 'Session expired.'], 401);
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            if ($isInertia) {
                return redirect()->back()->with('error', 'Data pelanggan tidak ditemukan.');
            }
            return response()->json(['success' => false, 'message' => 'Customer tidak ditemukan.'], 422);
        }

        $tenantId = $customer->tenant_id ?? 1;
        $ontDevice = \App\Models\OntDevice::where('customer_id', $customer->id)->first();
        if (! $ontDevice && ! empty($customer->pppoe_username)) {
            $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                ->where(function ($q) use ($customer) {
                    $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                      ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                })->first();
        }

        if (! $ontDevice) {
            if ($isInertia) {
                return redirect()->back()->with('error', 'Modem ONT belum terhubung ke sistem otomasi TR-069.');
            }
            return response()->json(['success' => false, 'message' => 'Modem ONT tidak ditemukan.'], 404);
        }

        try {
            /** @var \App\Services\GenieAcsService $acs */
            $acs = app(\App\Services\GenieAcsService::class);
            $success = $acs->rebootDevice($ontDevice->serial_number, $tenantId);

            if ($success) {
                $msg = 'Perintah reboot berhasil dikirim. Modem sedang memulai ulang (restarting) dalam waktu 1-2 menit.';
                if ($isInertia) {
                    return redirect()->back()->with('msg', $msg);
                }
                return response()->json(['success' => true, 'message' => $msg]);
            }

            if ($isInertia) {
                return redirect()->back()->with('error', 'Modem tidak merespon perintah reboot. Pastikan modem sedang online.');
            }
            return response()->json(['success' => false, 'message' => 'Modem tidak merespon perintah reboot.'], 500);
        } catch (\Throwable $e) {
            Log::error('Portal rebootOnt error: ' . $e->getMessage());
            if ($isInertia) {
                return redirect()->back()->with('error', 'Gagal mereboot modem: ' . $e->getMessage());
            }
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateSsid(Request $request)
    {
        $isInertia = $request->header('X-Inertia') || !$request->wantsJson();
        $customerId = session('customer_id');
        if (! $customerId) {
            if ($isInertia) {
                return redirect()->to('/portal/login')->with('error', 'Sesi login telah berakhir. Silakan login kembali.');
            }
            return response()->json(['success' => false, 'message' => 'Session expired. Silakan login kembali.'], 401);
        }

        $newSsid = trim((string) $request->input('ssid'));
        if (empty($newSsid) || strlen($newSsid) < 3) {
            if ($isInertia) {
                return redirect()->back()->withErrors(['ssid' => 'Nama WiFi (SSID) minimal 3 karakter']);
            }
            return response()->json(['success' => false, 'message' => 'SSID minimal 3 karakter'], 422);
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            if ($isInertia) {
                return redirect()->back()->with('error', 'Data pelanggan tidak ditemukan.');
            }
            return response()->json(['success' => false, 'message' => 'Data customer tidak lengkap'], 422);
        }

        $tenantId = $customer->tenant_id ?? 1;
        $ontDevice = \App\Models\OntDevice::where('customer_id', $customer->id)->first();
        if (! $ontDevice && ! empty($customer->pppoe_username)) {
            $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                ->where(function ($q) use ($customer) {
                    $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                      ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                })->first();
        }

        if (! $ontDevice) {
            if ($isInertia) {
                return redirect()->back()->with('error', 'Perangkat modem ONT belum terhubung ke sistem otomasi TR-069.');
            }
            return response()->json(['success' => false, 'message' => 'Modem ONT tidak ditemukan'], 404);
        }

        try {
            /** @var \App\Services\GenieAcsService $acs */
            $acs = app(\App\Services\GenieAcsService::class);
            $acs->setWifiConfig($ontDevice->serial_number, $newSsid, null, $tenantId);
            $ontDevice->update(['wifi_ssid' => $newSsid]);

            $msg = "Nama WiFi berhasil diubah menjadi: {$newSsid}. Perubahan akan diterapkan ke modem Anda dalam beberapa saat.";
            if ($isInertia) {
                return redirect()->back()->with('msg', $msg);
            }
            return response()->json(['success' => true, 'message' => $msg]);
        } catch (\Throwable $e) {
            Log::error('Portal updateSsid exception: '.$e->getMessage());
            if ($isInertia) {
                return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
            }
            return response()->json(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        $isInertia = $request->header('X-Inertia') || !$request->wantsJson();
        $customerId = session('customer_id');
        if (! $customerId) {
            if ($isInertia) {
                return redirect()->to('/portal/login')->with('error', 'Sesi login telah berakhir. Silakan login kembali.');
            }
            return response()->json(['success' => false, 'message' => 'Session expired. Silakan login kembali.'], 401);
        }

        $newPassword = trim((string) $request->input('password'));
        if (empty($newPassword) || strlen($newPassword) < 8) {
            if ($isInertia) {
                return redirect()->back()->withErrors(['password' => 'Password WiFi minimal 8 karakter']);
            }
            return response()->json(['success' => false, 'message' => 'Password minimal 8 karakter'], 422);
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            if ($isInertia) {
                return redirect()->back()->with('error', 'Data pelanggan tidak ditemukan.');
            }
            return response()->json(['success' => false, 'message' => 'Data customer tidak lengkap'], 422);
        }

        $tenantId = $customer->tenant_id ?? 1;
        $ontDevice = \App\Models\OntDevice::where('customer_id', $customer->id)->first();
        if (! $ontDevice && ! empty($customer->pppoe_username)) {
            $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                ->where(function ($q) use ($customer) {
                    $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                      ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                })->first();
        }

        if (! $ontDevice) {
            if ($isInertia) {
                return redirect()->back()->with('error', 'Perangkat modem ONT belum terhubung ke sistem otomasi TR-069.');
            }
            return response()->json(['success' => false, 'message' => 'Modem ONT tidak ditemukan'], 404);
        }

        try {
            /** @var \App\Services\GenieAcsService $acs */
            $acs = app(\App\Services\GenieAcsService::class);
            $acs->setWifiConfig($ontDevice->serial_number, null, $newPassword, $tenantId);
            $ontDevice->update(['wifi_password' => $newPassword]);

            $msg = 'Password WiFi berhasil diubah. Silakan hubungkan kembali HP/laptop Anda dengan kata sandi baru.';
            if ($isInertia) {
                return redirect()->back()->with('msg', $msg);
            }
            return response()->json(['success' => true, 'message' => $msg]);
        } catch (\Throwable $e) {
            Log::error('Portal updatePassword error: '.$e->getMessage());
            if ($isInertia) {
                return redirect()->back()->with('error', 'Terjadi kesalahan sistem: '.$e->getMessage());
            }
            return response()->json(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    public function updatePin(Request $request)
    {
        $isInertia = $request->header('X-Inertia') || ! $request->wantsJson();
        $customerId = session('customer_id');

        if (! $customerId) {
            if ($isInertia) {
                return redirect()->to('/portal/login')->with('error', 'Sesi login telah berakhir. Silakan login kembali.');
            }
            return response()->json(['success' => false, 'message' => 'Sesi login tidak ditemukan.'], 401);
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            if ($isInertia) {
                return redirect()->to('/portal/login')->with('error', 'Data pelanggan tidak ditemukan.');
            }
            return response()->json(['success' => false, 'message' => 'Data pelanggan tidak ditemukan.'], 404);
        }

        // Support various field names: 'pin', 'new_pin', or 'portal_password'
        $rawPin = $request->input('pin') ?? $request->input('new_pin') ?? $request->input('portal_password');
        $pin = is_string($rawPin) ? trim($rawPin) : '';

        if (empty($pin) || strlen($pin) < 4) {
            if ($isInertia) {
                return redirect()->back()->withErrors(['pin' => 'PIN / Password minimal 4 karakter (disarankan 6 digit angka).']);
            }
            return response()->json(['success' => false, 'message' => 'PIN minimal 4 karakter.'], 422);
        }

        if ($pin === '123456') {
            if ($isInertia) {
                return redirect()->back()->withErrors(['pin' => 'PIN tidak boleh menggunakan 123456 (PIN default).']);
            }
            return response()->json(['success' => false, 'message' => 'PIN tidak boleh menggunakan 123456.'], 422);
        }

        $confirm = $request->input('confirm_pin') ?? $request->input('new_pin_confirmation') ?? $request->input('portal_password_confirmation') ?? $request->input('pin_confirmation');
        if ($confirm !== null && is_string($confirm) && trim($confirm) !== '' && trim($confirm) !== $pin) {
            if ($isInertia) {
                return redirect()->back()->withErrors(['pin' => 'Konfirmasi PIN tidak cocok.']);
            }
            return response()->json(['success' => false, 'message' => 'Konfirmasi PIN tidak cocok.'], 422);
        }

        Customer::withoutGlobalScopes()->where('id', $customerId)->update([
            'portal_password' => bcrypt($pin),
        ]);

        session(['is_default_pin' => false]);

        $msg = 'PIN Login Portal berhasil diperbarui.';

        if ($isInertia) {
            return redirect()->back()->with('success', $msg);
        }

        return response()->json(['success' => true, 'message' => $msg]);
    }

    public function updateProfile(Request $request)
    {
        $isInertia = $request->header('X-Inertia') || ! $request->wantsJson();
        $customerId = session('customer_id');

        if (! $customerId) {
            if ($isInertia) {
                return redirect()->to('/portal/login')->with('error', 'Sesi login telah berakhir.');
            }
            return response()->json(['success' => false, 'message' => 'Sesi login tidak ditemukan.'], 401);
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            if ($isInertia) {
                return redirect()->to('/portal/login')->with('error', 'Data pelanggan tidak ditemukan.');
            }
            return response()->json(['success' => false, 'message' => 'Data pelanggan tidak ditemukan.'], 404);
        }

        $phone = $request->input('phone');
        $address = $request->input('address');

        $updates = [];
        if ($phone !== null) {
            $updates['phone'] = trim((string) $phone);
        }
        if ($address !== null) {
            $updates['address'] = trim((string) $address);
        }

        if (! empty($updates)) {
            $customer->update($updates);
        }

        $msg = 'Profil berhasil diperbarui.';
        if ($isInertia) {
            return redirect()->back()->with('success', $msg);
        }

        return response()->json(['success' => true, 'message' => $msg]);
    }

    public function changePortalPassword(Request $request)
    {
        return $this->updatePin($request);
    }

    public function payment($invoiceId)
    {
        // Identifikasi via customer_id — BUKAN phone (banyak pelanggan tanpa
        // nomor HP → cek phone selalu gagal → kena kick ke login).
        $customerId = session('customer_id');
        if (! $customerId) {
            return redirect()->to('/portal/login')->with('error', 'Silakan login terlebih dahulu');
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            return redirect()->to('/portal/login')->with('error', 'Data pelanggan tidak ditemukan');
        }

        $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);
        if (! $invoice || (int) $invoice->customer_id !== (int) $customerId) {
            return redirect()->to('/portal')->with('error', 'Invoice tidak ditemukan');
        }
        if ($invoice->status === 'paid' || $invoice->paid) {
            return redirect()->back()->with('success', 'Invoice ini sudah lunas.');
        }

        $tenantId = $customer->tenant_id ?? $invoice->tenant_id ?? session('customer_tenant_id') ?? session('tenant_id');
        if ($tenantId) {
            session(['tenant_id' => (int) $tenantId]);
            if (empty($customer->tenant_id)) {
                $customer->tenant_id = (int) $tenantId;
                $customer->saveQuietly();
            }
        }

        // Customer & Collector info
        $collector = $customer->collector_id
            ? Collector::withoutGlobalScopes()->find($customer->collector_id)
            : null;

        // Helper to resolve gateway with strict tenant isolation (no cross-tenant leakage to superadmin gateway)
        $resolveGateway = function (string $gatewayName) use ($tenantId) {
            if ($tenantId) {
                return PaymentGateway::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('gateway', $gatewayName)
                    ->first();
            }
            return PaymentGateway::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where('gateway', $gatewayName)
                ->first();
        };

        // Check Usage Settings for Invoice Payment
        $usageGw = $resolveGateway('_usage_settings');
        $usageSettings = $usageGw?->config_json ?? [];
        $enableGateway = (bool) ($usageSettings['enable_gateway'] ?? ($usageSettings['enable_invoice_payment'] ?? true));
        // Static QRIS completely deprecated
        $enableBankManual = (bool) ($usageSettings['enable_bank_manual'] ?? true);

        $groupedChannels = [];
        $tripayConfigured = false;
        $midtransConfigured = false;
        $wijayapayConfigured = false;
        $noderapayConfigured = false;
        $xenditConfigured = false;
        $duitkuConfigured = false;

        if ($enableGateway) {
            $seenChannels = [];

            // 1. NODERA PAY (Universal Engine)
            $noderapayGw = $resolveGateway('noderapay');
            $noderapayConfig = $noderapayGw?->config_json ?? [];
            $noderapayActive = $noderapayGw ? (bool) $noderapayGw->is_active : false;
            $noderapayCode = $noderapayConfig['NODERAPAY_MERCHANT_CODE'] ?? ($noderapayConfig['merchant_code'] ?? '');
            $noderapayKey = $noderapayConfig['NODERAPAY_API_KEY'] ?? ($noderapayConfig['api_key'] ?? '');
            $noderapayEnabledChannels = $noderapayConfig['enabled_channels'] ?? [];

            if ($noderapayActive) {
                $noderapayConfigured = true;

                // Dynamically fetch live available channels from upstream (WijayaPay) if configured
                $wijayapay = new \App\Services\WijayaPayService($tenantId ? (string) $tenantId : null);
                $liveChannels = [];
                if ($wijayapay->isConfigured()) {
                    try {
                        $chRes = \Illuminate\Support\Facades\Cache::remember('wj_channels_live_' . ($tenantId ?: 'global'), 120, function () use ($wijayapay) {
                            return $wijayapay->getChannels();
                        });
                        if (!empty($chRes['data']) && is_array($chRes['data'])) {
                            $liveChannels = $chRes['data'];
                        }
                    } catch (\Throwable $e) {}
                }

                if (!empty($liveChannels)) {
                    foreach ($liveChannels as $lch) {
                        $statusCode = strtolower((string) ($lch['status'] ?? 'active'));
                        if ($statusCode !== 'active') {
                            continue; // Skip inactive channels from WijayaPay!
                        }
                        $code = strtoupper((string) ($lch['code_payment'] ?? ($lch['code'] ?? '')));
                        if (in_array($code, ['QRIS2', 'QRIS_REALTIME'], true)) {
                            continue;
                        }
                        if (!empty($noderapayEnabledChannels) && !in_array($code, $noderapayEnabledChannels, true)) {
                            continue;
                        }

                        $group = $lch['group'] ?? 'Payment Gateway';
                        if ($group === 'Virtual Account' || str_ends_with($code, 'VA')) {
                            $groupTitle = 'Virtual Account (Bank Otomatis)';
                        } elseif ($group === 'Retail' || in_array($code, ['ALFAMART', 'INDOMARET'], true)) {
                            $groupTitle = 'Gerai Ritel (Alfamart / Indomaret)';
                        } else {
                            $groupTitle = 'QRIS & E-Wallet';
                        }

                        $groupedChannels[$groupTitle][] = [
                            'code'        => 'noderapay:' . $code,
                            'name'        => $lch['name'] ?? $code,
                            'group'       => $groupTitle,
                            'active'      => true,
                            'fee'         => [
                                'flat'    => (float) ($lch['fee_amount'] ?? 0),
                                'percent' => (float) ($lch['fee_percent'] ?? 0),
                            ],
                            'description' => $lch['tutorial_pembayaran'] ?? ('Pembayaran via ' . ($lch['name'] ?? $code)),
                        ];
                        $seenChannels[$code] = true;
                    }
                } else {
                    // Fallback to QRIS if live channels cannot be fetched
                    if (empty($noderapayEnabledChannels) || in_array('QRIS', $noderapayEnabledChannels, true)) {
                        $groupedChannels['QRIS & E-Wallet'][] = [
                            'code'        => 'noderapay:QRIS',
                            'name'        => 'QRIS Realtime (Semua Bank & E-Wallet)',
                            'group'       => 'QRIS & E-Wallet',
                            'active'      => true,
                            'fee'         => ['flat' => 0, 'percent' => 0],
                            'description' => 'BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA, ShopeePay',
                        ];
                        $seenChannels['QRIS'] = true;
                    }
                }
            }

            // 2. WijayaPay (Hanya jika NODERA PAY tidak aktif dan direct WijayaPay aktif)
            if (!$noderapayConfigured) {
                $wijayapayGw = $resolveGateway('wijayapay');
                $wijayapayConfig = $wijayapayGw?->config_json ?? [];
                $upstreamGw = PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', 'noderapay_upstream')->first();
                $upConfig = $upstreamGw?->config_json ?? [];
                $isUpstreamWijaya = (!$tenantId && $upstreamGw && ($upConfig['active_upstream'] ?? '') === 'wijayapay');

                $wijayapayActive = $tenantId
                    ? ($wijayapayGw ? (bool) $wijayapayGw->is_active : false)
                    : (($wijayapayGw ? (bool) $wijayapayGw->is_active : false) || $isUpstreamWijaya);
                $wijayapayEnabledChannels = $wijayapayConfig['enabled_channels'] ?? ($upConfig['enabled_channels'] ?? []);

                $wijayapay = new \App\Services\WijayaPayService($tenantId ? (string) $tenantId : null);
                if ($wijayapay->isConfigured() && $wijayapayActive) {
                    $wijayapayConfigured = true;
                    $wjChannelsRes = $wijayapay->getChannels();
                    $wjData = $wjChannelsRes['data'] ?? [];
                    if (!empty($wjData) && is_array($wjData)) {
                        foreach ($wjData as $wch) {
                            $code = $wch['code_payment'] ?? ($wch['code'] ?? 'QRIS');
                            // Filter out inactive/secondary duplicate QRIS
                            if (in_array(strtoupper($code), ['QRIS2', 'QRIS_REALTIME'], true)) {
                                continue;
                            }
                            if (!empty($wijayapayEnabledChannels) && !in_array($code, $wijayapayEnabledChannels, true)) {
                                continue;
                            }

                            if (!empty($seenChannels[$code])) {
                                continue;
                            }
                            $seenChannels[$code] = true;

                            $name = $wch['nama_pembayaran'] ?? ($wch['name'] ?? $code);
                            $rawGroup = strtolower((string) ($wch['tipe'] ?? ($wch['group'] ?? '')));
                            if (str_contains($rawGroup, 'va') || str_contains($rawGroup, 'virtual') || str_contains($rawGroup, 'bank')) {
                                $group = 'Virtual Account (Bank Otomatis)';
                            } elseif (str_contains($rawGroup, 'ewallet') || str_contains($rawGroup, 'qris') || strtoupper($code) === 'QRIS') {
                                $group = 'QRIS & E-Wallet';
                            } elseif (str_contains($rawGroup, 'retail') || str_contains($rawGroup, 'cstore') || str_contains($rawGroup, 'convenience')) {
                                $group = 'Gerai Ritel (Alfamart / Indomaret)';
                            } else {
                                $group = $wch['tipe'] ?? ($wch['group'] ?? 'WijayaPay');
                            }

                            $feeFlat = (float) ($wch['fee'] ?? ($wch['admin_fee'] ?? 0));
                            $feePercent = (float) ($wch['fee_percent'] ?? 0);
                            $groupedChannels[$group][] = [
                                'code'        => 'noderapay:' . $code,
                                'name'        => $name,
                                'group'       => $group,
                                'active'      => true,
                                'fee'         => ['flat' => $feeFlat, 'percent' => $feePercent],
                                'description' => $wch['keterangan'] ?? ($wch['desc'] ?? ('Bayar instan via ' . $name)),
                            ];
                        }
                    }
                    if (empty($seenChannels['QRIS']) && (empty($wijayapayEnabledChannels) || in_array('QRIS', $wijayapayEnabledChannels, true))) {
                        $groupedChannels['QRIS & E-Wallet'][] = [
                            'code'        => 'wijayapay:QRIS',
                            'name'        => 'QRIS Realtime (Semua Bank & E-Wallet)',
                            'group'       => 'QRIS & E-Wallet',
                            'active'      => true,
                            'fee'         => ['flat' => 0, 'percent' => 0],
                            'description' => 'QRIS Realtime instan (Semua Bank & E-Wallet)',
                        ];
                        $seenChannels['QRIS'] = true;
                    }
                }
            }

            // 3. Tripay
            $tripayGw = $resolveGateway('tripay');
            $tripayConfig = $tripayGw?->config_json ?? [];
            $tripayActive = $tripayGw ? (bool) $tripayGw->is_active : false;
            $tripayEnabledChannels = $tripayConfig['enabled_channels'] ?? [];

            $tripay = new TripayService;
            if ($tripay->isConfigured() && $tripayActive) {
                $tripayConfigured = true;
                $channelsResponse = $tripay->getChannels();
                $tripayData = $channelsResponse['data'] ?? [];
                foreach ($tripayData as $chn) {
                    if ($chn['active']) {
                        if (!empty($tripayEnabledChannels) && !in_array($chn['code'], $tripayEnabledChannels, true)) {
                            continue;
                        }
                        $groupedChannels[$chn['group']][] = $chn;
                    }
                }
            }

            // 4. Midtrans
            $midtransGw = $resolveGateway('midtrans');
            if (!$midtransGw) {
                $midtransGw = PaymentGateway::withoutGlobalScopes()
                    ->where('gateway', 'midtrans')
                    ->where('is_active', true)
                    ->first();
            }
            $midtransConfig = $midtransGw?->config_json ?? [];
            if (is_string($midtransConfig)) {
                $midtransConfig = json_decode($midtransConfig, true) ?? [];
            }
            $midtransActive = $midtransGw ? (bool) $midtransGw->is_active : false;
            $midtransEnabledChannels = $midtransConfig['enabled_channels'] ?? [];

            $midtrans = new \App\Services\MidtransService($tenantId ? (string) $tenantId : null);
            if ($midtrans->isConfigured() && $midtransActive) {
                $midtransConfigured = true;
                foreach (\App\Services\MidtransService::CHANNELS as $mChan) {
                    if (!empty($midtransEnabledChannels) && !in_array($mChan['code'], $midtransEnabledChannels, true)) {
                        continue;
                    }
                    $groupedChannels[$mChan['group']][] = [
                        'code'        => $mChan['code'],
                        'name'        => $mChan['name'],
                        'group'       => $mChan['group'],
                        'active'      => true,
                        'fee'         => [
                            'flat'    => $mChan['fee_flat'],
                            'percent' => $mChan['fee_percent'],
                        ],
                        'description' => $mChan['description'] ?? '',
                    ];
                }
            }

            // 5. Xendit
            $xenditGw = $resolveGateway('xendit');
            $xenditActive = $xenditGw ? (bool) $xenditGw->is_active : false;
            $xendit = app(\App\Services\XenditService::class);
            if ($xendit->isConfigured() && $xenditActive) {
                $xenditConfigured = true;
                $groupedChannels['Xendit Payment Gateway'][] = [
                    'code'        => 'xendit:all',
                    'name'        => 'Xendit Invoice (Semua Channel)',
                    'group'       => 'Xendit Payment Gateway',
                    'active'      => true,
                    'fee'         => ['flat' => 0, 'percent' => 0],
                    'description' => 'Virtual Account, QRIS, Retail Outlet, E-Wallet',
                ];
            }

            // 6. Duitku
            $duitkuGw = $resolveGateway('duitku');
            $duitkuActive = $duitkuGw ? (bool) $duitkuGw->is_active : false;
            $duitku = app(\App\Services\DuitkuService::class);
            if ($duitku->isConfigured() && $duitkuActive) {
                $duitkuConfigured = true;
                $groupedChannels['Duitku Payment Gateway'][] = [
                    'code'        => 'duitku:all',
                    'name'        => 'Duitku Payment Gateway',
                    'group'       => 'Duitku Payment Gateway',
                    'active'      => true,
                    'fee'         => ['flat' => 0, 'percent' => 0],
                    'description' => 'Virtual Account & E-Wallet Duitku',
                ];
            }
        }

        // Process payment ONLY via active dynamic payment gateway (no static QRIS)
        $qrisImageUrl = null;
        $qrisText = '';
        $isDynamicQris = false;
        $dynamicQrisData = null;

        try {
            $dynamicQrisData = app(\App\Services\PaymentService::class)->processDynamicQris($invoice);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Portal Payment] Failed to process dynamic payment: ' . $e->getMessage());
        }

        if ($dynamicQrisData && !empty($dynamicQrisData['success'])) {
            $qrisImageUrl = !empty($dynamicQrisData['qris_svg'])
                ? $dynamicQrisData['qris_svg']
                : (!empty($dynamicQrisData['qris_image_url']) ? $dynamicQrisData['qris_image_url'] : ($dynamicQrisData['qris_string'] ?? null));
            $isDynamicQris = (bool) ($dynamicQrisData['is_dynamic'] ?? false);
        } else {
            $dynamicQrisData = null;
        }
        $qrisConfig = [];

        $uniqueAmount = (float) ($dynamicQrisData['unique_amount'] ?? $invoice->amount);
        $uniqueCode = (int) ($dynamicQrisData['unique_code'] ?? 0);
        $expiresAt = $dynamicQrisData['expires_at'] ?? now()->addMinutes(5)->toIso8601String();

        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
        $storeName = $tenant?->name ?? ($qrisConfig['merchant_name'] ?? 'NODERA NETWORK');
        $merchantName = $dynamicQrisData['merchant_name'] ?? ($qrisConfig['merchant_name'] ?? $storeName);
        $merchantCity = $dynamicQrisData['merchant_city'] ?? ($qrisConfig['merchant_city'] ?? null);
        $nmid = $dynamicQrisData['merchant_info']['nmid'] ?? ($qrisConfig['nmid'] ?? null);

        // Admin payment info for customer's specific tenant
        $adminWa = $this->getAdminWaNumber($customer, $tenantId);

        $gatewayConfigured = !empty($groupedChannels);

        // Bank accounts specifically for this customer's tenant
        if ($tenantId) {
            $bankAccounts = \Illuminate\Support\Facades\DB::table('bank_accounts')
                ->where('is_active', true)
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')->orderBy('bank_name')
                ->get();
        } else {
            // Standalone mode / global installation (no multi-tenant)
            $bankAccounts = \Illuminate\Support\Facades\DB::table('bank_accounts')
                ->where('is_active', true)
                ->whereNull('tenant_id')
                ->orderBy('sort_order')->orderBy('bank_name')
                ->get();
        }

        $activeTransaction = PaymentTransaction::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        $activePayment = null;
        if ($activeTransaction) {
            $notes = [];
            if (!empty($activeTransaction->notes)) {
                $notes = is_array($activeTransaction->notes) ? $activeTransaction->notes : (json_decode($activeTransaction->notes, true) ?? []);
            }
            $activePayment = [
                'id'           => $activeTransaction->id,
                'method'       => $activeTransaction->method,
                'gateway'      => $activeTransaction->gateway,
                'gateway_ref'  => $activeTransaction->gateway_ref,
                'channel'      => $notes['channel'] ?? $activeTransaction->method,
                'va_number'    => $notes['va_number'] ?? ($notes['pay_code'] ?? null),
                'pay_code'     => $notes['pay_code'] ?? ($notes['va_number'] ?? null),
                'qr_string'    => $notes['qr_string'] ?? null,
                'qr_image'     => $notes['qr_image'] ?? null,
                'total_amount' => (float) ($notes['total_amount'] ?? $activeTransaction->amount),
                'expired_at'   => $notes['expired_at'] ?? null,
                'instructions' => $notes['instructions'] ?? null,
                'checkout_url' => $notes['checkout_url'] ?? null,
                'created_at'   => $activeTransaction->created_at?->toIso8601String(),
            ];
        }

        return Inertia::render('Portal/Payment', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'period' => $invoice->period,
                'amount' => (float) $invoice->amount,
                'unique_amount' => $uniqueAmount,
                'unique_code' => $uniqueCode,
                'due_date' => $invoice->due_date?->toIso8601String(),
            ],
            'customer' => [
                'name' => $customer->name,
                'code' => $customer->code,
                'pppoe_username' => $customer->pppoe_username,
                'phone' => $customer->phone,
            ],
            'collector' => $collector ? ['name' => $collector->name, 'phone' => $collector->phone] : null,
            'adminWa' => $adminWa,
            'enableGateway' => (bool) $enableGateway,
            'enableQris' => (bool) ($isDynamicQris || !empty($dynamicQrisData)),
            'enableBank' => false,
            'activePayment' => $activePayment,
            'checkoutUrl' => $dynamicQrisData['checkout_url'] ?? ($activePayment['checkout_url'] ?? null),
            'snapToken' => $dynamicQrisData['snap_token'] ?? null,
            'snapJsUrl' => $dynamicQrisData['snap_js_url'] ?? null,
            'clientKey' => $dynamicQrisData['client_key'] ?? null,
            'gateway' => $dynamicQrisData['gateway'] ?? ($activePayment['gateway'] ?? null),
            'qrisImageUrl' => $qrisImageUrl,
            'qrisText' => $qrisText,
            'isDynamicQris' => $isDynamicQris,
            'uniqueAmount' => $uniqueAmount,
            'uniqueCode' => $uniqueCode,
            'expiresAt' => $expiresAt,
            'storeName' => $storeName,
            'merchantName' => $merchantName,
            'merchantCity' => $merchantCity,
            'nmid' => $nmid,
            'packageName' => $customer->package?->name ?? ($customer->profile ?? 'Paket Internet'),
            'statusCheckUrl' => url("/api/v1/payments/qris/status/{$invoice->id}"),
            'gatewayConfigured' => $gatewayConfigured,
            'midtransConfigured' => $midtransConfigured,
            'xenditConfigured' => $xenditConfigured,
            'duitkuConfigured' => $duitkuConfigured,
            'groupedChannels' => collect($groupedChannels)->map(fn ($channels, $group) => [
                'group' => $group,
                'channels' => collect($channels)->map(fn ($c) => [
                    'code' => $c['code'] ?? '',
                    'name' => $c['name'] ?? '',
                    'group' => $c['group'] ?? '',
                    'fee_flat' => (float) ($c['fee']['flat'] ?? 0),
                    'fee_percent' => (float) ($c['fee']['percent'] ?? 0),
                ]),
            ])->values(),
            'bankAccounts' => $bankAccounts->map(fn ($b) => [
                'bank_name' => $b->bank_name,
                'account_number' => $b->account_number,
                'account_name' => $b->account_name,
            ]),
        ]);
    }

    public function paymentManual(Request $request)
    {
        // Identifikasi via customer_id — BUKAN phone (pelanggan tanpa HP).
        $customerId = session('customer_id');
        if (! $customerId) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Silakan login terlebih dahulu'], 401);
            }
            return redirect()->to('/portal/login')->with('error', 'Silakan login terlebih dahulu');
        }

        $invoiceId = $request->post('invoice_id');
        $method = strtolower((string) $request->post('method', 'bank'));

        if (! $invoiceId || ! in_array($method, ['qris', 'qris_static', 'bank', 'transfer', 'collector'])) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Metode pembayaran tidak valid.'], 422);
            }
            return redirect()->back()->with('error', 'Metode pembayaran tidak valid.');
        }

        $invoice = Invoice::withoutGlobalScopes()->with(['customer.package', 'tenant'])->find($invoiceId);
        if (! $invoice || (int) $invoice->customer_id !== (int) $customerId) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Invoice tidak ditemukan'], 404);
            }
            return redirect()->to('/portal')->with('error', 'Invoice tidak ditemukan');
        }
        if ($invoice->status === 'paid' || $invoice->paid) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Invoice ini sudah lunas.'], 200);
            }
            return redirect()->back()->with('msg', 'Invoice ini sudah lunas.');
        }

        $paymentMethodCode = match($method) {
            'qris', 'qris_static' => 'qris_static',
            'collector' => 'collector',
            default => 'bank_manual',
        };

        $invoice->update([
            'status' => 'pending',
            'payment_method' => $paymentMethodCode,
        ]);

        $customer = Customer::withoutGlobalScopes()->with('package')->find($customerId);
        if (! $customer) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Data pelanggan tidak ditemukan'], 404);
            }
            return redirect()->to('/portal')->with('error', 'Data pelanggan tidak ditemukan');
        }

        // Send Telegram notification with interactive ACC/Reject buttons to Tenant's bot
        try {
            $tenantTelegram = app(\App\Services\TenantTelegramService::class);
            $tenantTelegram->sendInvoicePaymentNotification($invoice, $customer, $method);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[PortalController] Telegram invoice notification failed: ' . $e->getMessage());
        }

        $targetPhone = '';
        $amount = (int) $invoice->amount;

        if (in_array($method, ['qris', 'qris_static'])) {
            $targetPhone = $this->getAdminWaNumber($customer);
            $targetPhone = preg_replace('/[^0-9]/', '', $targetPhone);
            $waText = "Halo Admin, saya *{$customer->name}* (" . ($customer->pppoe_username ?: $customer->code ?: 'Pelanggan') . ").\n\n"
                . "Saya ingin konfirmasi pembayaran tagihan *{$invoice->invoice_number}* sebesar *Rp " . number_format($amount, 0, ',', '.') . "* via *QRIS*.\n\n"
                . "Berikut bukti pembayaran saya. Mohon bantu verifikasi. Terima kasih.";
        } elseif ($method === 'collector') {
            $collector = $customer->collector_id
                ? Collector::withoutGlobalScopes()->find($customer->collector_id)
                : null;
            $targetPhone = $collector?->phone ? preg_replace('/[^0-9]/', '', $collector->phone) : $this->getAdminWaNumber($customer);
            $waText = "Halo, saya *{$customer->name}* ingin melakukan pembayaran tagihan *{$invoice->invoice_number}* sebesar *Rp " . number_format($amount, 0, ',', '.') . "*.\n\n"
                . "Mohon bantuan untuk penagihan / kedatangan kolektor. Terima kasih.";
        } else {
            // Transfer Bank
            $targetPhone = $this->getAdminWaNumber($customer);
            $targetPhone = preg_replace('/[^0-9]/', '', $targetPhone);
            $waText = "Halo Admin, saya *{$customer->name}* (" . ($customer->pppoe_username ?: $customer->code ?: 'Pelanggan') . ").\n\n"
                . "Saya ingin konfirmasi pembayaran tagihan *{$invoice->invoice_number}* sebesar *Rp " . number_format($amount, 0, ',', '.') . "* via *Transfer Bank*.\n\n"
                . "Berikut bukti transfer pembayaran saya. Mohon bantu verifikasi. Terima kasih.";
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Notifikasi pembayaran berhasil dikirim ke admin.',
                'wa_url' => $targetPhone ? ('https://wa.me/' . $targetPhone . '?text=' . rawurlencode($waText)) : null,
            ]);
        }

        if ($targetPhone) {
            return Inertia::location('https://wa.me/' . $targetPhone . '?text=' . rawurlencode($waText));
        }

        return redirect('/portal')->with('msg', 'Pembayaran dicatat. Silakan hubungi admin.');
    }

    public function processPayment(Request $request)
    {
        // Identifikasi via customer_id — BUKAN phone (pelanggan tanpa HP).
        $customerId = session('customer_id');
        if (! $customerId) {
            return redirect()->to('/portal/login')->with('error', 'Silakan login terlebih dahulu');
        }

        $invoiceId = $request->post('invoice_id');
        $method = $request->post('method');

        if (! $invoiceId || ! $method) {
            return redirect()->back()->with('error', 'Metode pembayaran tidak valid.');
        }

        $invoice = Invoice::withoutGlobalScopes()->with('customer.package')->find($invoiceId);

        if (! $invoice || (int) $invoice->customer_id !== (int) $customerId) {
            return redirect()->to('/portal')->with('error', 'Invoice tidak ditemukan');
        }

        if ($invoice->status === 'paid' || $invoice->paid) {
            return redirect()->to("/portal/payment/{$invoice->id}")->with('msg', 'Invoice ini sudah lunas.');
        }

        $tenantId = $invoice->tenant_id ?? $invoice->customer?->tenant_id;
        $usageGw = PaymentGateway::withoutGlobalScopes()
            ->where('gateway', '_usage_settings')
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                } else {
                    $q->whereNull('tenant_id');
                }
            })->first();
        $usageSettings = $usageGw?->config_json ?? [];
        $enableGateway = (bool) ($usageSettings['enable_gateway'] ?? ($usageSettings['enable_invoice_payment'] ?? true));

        if (! $enableGateway) {
            return redirect()->to("/portal/payment/{$invoice->id}")->with('error', 'Metode pembayaran via Payment Gateway dinonaktifkan oleh admin.');
        }

        try {
            $channel = 'QRIS';
            if (str_starts_with($method, 'noderapay:')) {
                $channel = substr($method, 10);
            } elseif (str_starts_with($method, 'wijayapay:')) {
                $channel = substr($method, 10);
            } elseif (str_contains($method, ':')) {
                $parts = explode(':', $method, 2);
                $channel = $parts[1];
            }

            if (strtoupper($channel) === 'QRIS2' || strtoupper($channel) === 'QRIS_REALTIME') {
                $channel = 'QRIS';
            }

            if (str_starts_with($method, 'midtrans')) {
                $result = app(PaymentService::class)->processMidtrans($invoice, $method);
                $label = 'Midtrans';
            } elseif (str_starts_with($method, 'xendit:')) {
                $result = app(PaymentService::class)->processXendit($invoice, $channel);
                $label = 'Xendit';
            } elseif (str_starts_with($method, 'duitku:')) {
                $result = app(PaymentService::class)->processDuitku($invoice, $channel);
                $label = 'Duitku';
            } elseif (str_starts_with($method, 'tripay:')) {
                $result = app(PaymentService::class)->processTripay($invoice, $channel);
                $label = 'Tripay';
            } else {
                $result = app(PaymentService::class)->processNoderapay($invoice, $channel);
                $label = 'NODERA PAY';
            }

            if (! empty($result['payment_url']) && filter_var($result['payment_url'], FILTER_VALIDATE_URL) && ! preg_match('/\.(png|jpg|jpeg|svg|webp)$/i', parse_url($result['payment_url'], PHP_URL_PATH) ?? '')) {
                return redirect()->to($result['payment_url']);
            }

            return redirect()->to("/portal/payment/{$invoice->id}")->with('msg', 'Transaksi pembayaran berhasil dibuat. Silakan selesaikan pembayaran.');
        } catch (\Throwable $e) {
            Log::error('Portal processPayment error: ' . $e->getMessage());

            return redirect()->to("/portal/payment/{$invoice->id}")->with('warning', 'Metode pembayaran ' . ($label ?? 'Gateway') . ' belum dapat diproses: ' . $e->getMessage() . '. Silakan coba channel pembayaran lain atau hubungi admin.');
        }
    }

    public function cancelActivePayment(Request $request, $invoiceId)
    {
        $customerId = session('customer_id');
        if (! $customerId) {
            return redirect()->to('/portal/login')->with('error', 'Silakan login terlebih dahulu');
        }

        $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);
        if (! $invoice || (int) $invoice->customer_id !== (int) $customerId) {
            return redirect()->to('/portal')->with('error', 'Invoice tidak ditemukan');
        }

        PaymentTransaction::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return redirect()->to("/portal/payment/{$invoice->id}")->with('msg', 'Silakan pilih metode pembayaran yang diinginkan.');
    }

    public function invoices()
    {
        // Identifikasi via customer_id — BUKAN phone (banyak pelanggan tanpa
        // nomor HP → cek phone selalu gagal → kena kick ke login).
        $customerId = session('customer_id');
        if (! $customerId) {
            return redirect()->to('/portal/login')->with('error', 'Silakan login terlebih dahulu');
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            return redirect()->to('/portal/login')->with('error', 'Data pelanggan tidak ditemukan');
        }

        $invoices = Invoice::withoutGlobalScopes()->where('customer_id', $customer->id)
            ->orderBy('due_date', 'DESC')
            ->get();

        return Inertia::render('Portal/Invoices', [
            'companyName' => $customer->tenant?->name ?? \App\Models\Setting::getValue('COMPANY_NAME', config('app.name', 'NODERA')),
            'adminWa' => $this->getAdminWaNumber($customer),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ],
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'amount' => (float) $i->amount,
                'paid' => (bool) $i->paid,
                'period' => $i->period,
                'due_date' => $i->due_date?->toIso8601String(),
                'paid_at' => $i->paid_at?->toIso8601String(),
            ]),
        ]);
    }

    public function editWifi(Request $request)
    {
        return redirect()->to('/portal/usage');
    }

    public function usage(Request $request)
    {
        $customerId = session('customer_id');
        if (! $customerId) {
            return redirect()->to('/portal/login')->with('error', 'Silakan login terlebih dahulu');
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            return redirect()->to('/portal/login')->with('error', 'Data pelanggan tidak ditemukan');
        }

        // Ambil data usage dari MikroTik
        $usageData = null;
        $tenantId = $customer->tenant_id ?? session('customer_tenant_id') ?? session('tenant_id');
        $router = null;
        if ($customer->router_id) {
            $router = Mikrotik::withoutGlobalScopes()->find($customer->router_id);
            if ($router && $tenantId && $router->tenant_id && (int) $router->tenant_id !== (int) $tenantId) {
                $router = null;
            }
        }
        if (!$router && $tenantId) {
            $router = Mikrotik::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->first();
        }

        if ($router && $router->is_active) {
            try {
                $mik = new \App\Services\MikrotikService([
                    'host' => $router->host,
                    'user' => $router->username,
                    'pass' => $router->password ?? '',
                    'port' => (int) $router->port,
                ]);

                if ($mik->isConnected()) {
                    $isArpOrStatic = ($customer->connection_type === 'static' || $customer->connection_type === 'arp' || empty($customer->pppoe_username) || str_starts_with($customer->pppoe_username, 'arp_'));

                    if (!$isArpOrStatic && !empty($customer->pppoe_username)) {
                        // 1. Pelanggan PPPoE
                        $active = $mik->query('/ppp/active/print');

                        if (!empty($active)) {
                            // Ambil semua interface stats
                            $ifaces = $mik->query('/interface/print', ['.proplist' => 'name,rx-byte,tx-byte']);
                            $ifaceByName = [];
                            if (is_array($ifaces)) {
                                foreach ($ifaces as $iface) {
                                    $iname = $iface['name'] ?? '';
                                    if ($iname) {
                                        $ifaceByName[$iname] = $iface;
                                        $clean = strtolower(trim($iname, '<> '));
                                        $ifaceByName[$clean] = $iface;
                                    }
                                }
                            }

                            foreach ($active as $u) {
                                if (strtolower($u['name'] ?? '') === strtolower($customer->pppoe_username)) {
                                    $unameLower = strtolower(trim($customer->pppoe_username));
                                    $ifaceRow = $ifaceByName[$customer->pppoe_username] ?? ($ifaceByName[$unameLower] ?? ($ifaceByName["pppoe-{$unameLower}"] ?? null));
                                    if (!$ifaceRow) {
                                        foreach ($ifaceByName as $iname => $row) {
                                            if (stripos($iname, $unameLower) !== false) {
                                                $ifaceRow = $row;
                                                break;
                                            }
                                        }
                                    }

                                    $uploadBytes = $ifaceRow ? (int)($ifaceRow['rx-byte'] ?? 0) : 0;   // Client Upload
                                    $downloadBytes = $ifaceRow ? (int)($ifaceRow['tx-byte'] ?? 0) : 0; // Client Download

                                    // Fallback jika interface 0 byte: cek Simple Queue
                                    if ($downloadBytes === 0 && $uploadBytes === 0) {
                                        try {
                                            $queues = $mik->query('/queue/simple/print', ['.proplist' => 'name,target,bytes,rate']);
                                            if (is_array($queues)) {
                                                $userIp = $u['address'] ?? '';
                                                foreach ($queues as $q) {
                                                    $qTarget = $q['target'] ?? '';
                                                    $qName = $q['name'] ?? '';
                                                    if (($userIp && str_contains($qTarget, $userIp)) || stripos($qName, $customer->pppoe_username) !== false) {
                                                        $parts = explode('/', $q['bytes'] ?? '0/0');
                                                        $uploadBytes = isset($parts[0]) ? (int) $parts[0] : 0;
                                                        $downloadBytes = isset($parts[1]) ? (int) $parts[1] : 0;
                                                        break;
                                                    }
                                                }
                                            }
                                        } catch (\Throwable $e) {}
                                    }

                                    // Ambil real-time speed via monitor-traffic
                                    $rxSpeed = 0;
                                    $txSpeed = 0;
                                    try {
                                        $ifaceName = $ifaceRow['name'] ?? $customer->pppoe_username;
                                        $monitor = $mik->query('/interface/monitor-traffic', [
                                            'interface' => $ifaceName,
                                            'once' => '',
                                        ]);
                                        if (!empty($monitor[0])) {
                                            $rxSpeed = (int)($monitor[0]['rx-bits-per-second'] ?? 0);
                                            $txSpeed = (int)($monitor[0]['tx-bits-per-second'] ?? 0);
                                        }
                                    } catch (\Exception $e) {}

                                    $usageData = [
                                        'username' => $customer->pppoe_username,
                                        'address' => $u['address'] ?? '-',
                                        'uptime' => $u['uptime'] ?? '-',
                                        'uptime_human' => $this->formatUptimeHuman($u['uptime'] ?? '-'),
                                        'download' => $downloadBytes,
                                        'upload' => $uploadBytes,
                                        'download_speed' => $txSpeed,
                                        'upload_speed' => $rxSpeed,
                                        'total' => $downloadBytes + $uploadBytes,
                                    ];
                                    break;
                                }
                            }
                        }
                    } elseif (!empty($customer->ip_address)) {
                        // 2. Pelanggan ARP / Static IP
                        $targetIp = trim($customer->ip_address);
                        $downloadBytes = 0;
                        $uploadBytes = 0;
                        $rxSpeed = 0;
                        $txSpeed = 0;
                        $uptime = 'Active';

                        try {
                            $queues = $mik->query('/queue/simple/print', ['.proplist' => 'name,target,bytes,rate']);
                            if (is_array($queues)) {
                                foreach ($queues as $q) {
                                    $qTarget = $q['target'] ?? '';
                                    $qName = $q['name'] ?? '';
                                    if (str_contains($qTarget, $targetIp) || stripos($qName, $customer->name) !== false) {
                                        $parts = explode('/', $q['bytes'] ?? '0/0');
                                        $uploadBytes = isset($parts[0]) ? (int) $parts[0] : 0;
                                        $downloadBytes = isset($parts[1]) ? (int) $parts[1] : 0;

                                        $rateParts = explode('/', $q['rate'] ?? '0/0');
                                        $rxSpeed = isset($rateParts[0]) ? (int) $rateParts[0] : 0; // Upload bps
                                        $txSpeed = isset($rateParts[1]) ? (int) $rateParts[1] : 0; // Download bps
                                        break;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}

                        try {
                            $arps = $mik->query('/ip/arp/print', ['.proplist' => 'address,mac-address,interface,complete']);
                            if (is_array($arps)) {
                                foreach ($arps as $arp) {
                                    if (($arp['address'] ?? '') === $targetIp) {
                                        $iface = $arp['interface'] ?? 'bridge';
                                        $uptime = ($arp['complete'] ?? '') === 'true' ? "Active ({$iface})" : "Static ({$iface})";
                                        break;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}

                        $usageData = [
                            'username' => $customer->name,
                            'address' => $targetIp,
                            'uptime' => $uptime,
                            'uptime_human' => $this->formatUptimeHuman($uptime),
                            'download' => $downloadBytes,
                            'upload' => $uploadBytes,
                            'download_speed' => $txSpeed,
                            'upload_speed' => $rxSpeed,
                            'total' => $downloadBytes + $uploadBytes,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Portal usage error: ' . $e->getMessage());
            }
        }

        // Auto-sinkronkan pemakaian sesi aktif bulan berjalan ke histori
        $currentMonth = (int) now()->month;
        $currentYear = (int) now()->year;
        if ($usageData && ($usageData['download'] > 0 || $usageData['upload'] > 0)) {
            try {
                $existing = DB::table('customer_usage')
                    ->where('customer_id', $customer->id)
                    ->where('period_month', $currentMonth)
                    ->where('period_year', $currentYear)
                    ->first();

                if ($existing) {
                    $newBytesIn = max((int)($existing->bytes_in ?? 0), (int)$usageData['upload']);
                    $newBytesOut = max((int)($existing->bytes_out ?? 0), (int)$usageData['download']);
                    DB::table('customer_usage')
                        ->where('id', $existing->id)
                        ->update([
                            'bytes_in' => $newBytesIn,
                            'bytes_out' => $newBytesOut,
                            'last_update' => now(),
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('customer_usage')->insert([
                        'customer_id' => $customer->id,
                        'tenant_id' => $tenantId ?? 1,
                        'period_month' => $currentMonth,
                        'period_year' => $currentYear,
                        'bytes_in' => (int)$usageData['upload'],
                        'bytes_out' => (int)$usageData['download'],
                        'last_total_bytes_in' => (int)$usageData['upload'],
                        'last_total_bytes_out' => (int)$usageData['download'],
                        'last_update' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Portal usage sync error: ' . $e->getMessage());
            }
        }

        // Ambil history dari database
        $history = DB::table('customer_usage')
            ->where('customer_id', $customer->id)
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->limit(12)
            ->get();

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $formattedHistory = $history->map(function ($h) use ($monthNames) {
            $monthNum = (int) $h->period_month;
            $monthLabel = ($monthNames[$monthNum] ?? "Bulan {$monthNum}") . " {$h->period_year}";
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
        });

        // Ambil data ONT GenieACS
        [$hasAcsDevice, $onuData] = $this->getOnuInfo($customer, $tenantId);
        $companyName = $customer->tenant?->name ?? \App\Models\Setting::getValue('COMPANY_NAME', config('app.name', 'NODERA'));

        return Inertia::render('Portal/Usage', [
            'customer' => ['name' => $customer->name, 'pppoe_username' => $customer->pppoe_username],
            'usageData' => $usageData,
            'onuData' => $onuData,
            'hasAcsDevice' => $hasAcsDevice,
            'companyName' => $companyName,
            'adminWa' => $this->getAdminWaNumber($customer),
            'history' => $formattedHistory,
        ]);
    }

    public function realtimeTraffic(Request $request)
    {
        $customerId = session('customer_id');
        if (! $customerId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
        }

        $tenantId = $customer->tenant_id ?? session('customer_tenant_id') ?? session('tenant_id') ?? 1;
        $router = null;
        if (! empty($customer->router_id)) {
            $router = Mikrotik::where('tenant_id', $tenantId)->find($customer->router_id);
        }
        if (! $router) {
            $router = Mikrotik::where('tenant_id', $tenantId)->where('is_active', true)->first();
        }

        $rxSpeed = 0;
        $txSpeed = 0;
        $downloadBytes = 0;
        $uploadBytes = 0;

        if ($router && $router->is_active) {
            try {
                $mik = new \App\Services\MikrotikService([
                    'host' => $router->host,
                    'user' => $router->username,
                    'pass' => $router->password ?? '',
                    'port' => (int) $router->port,
                ]);

                if ($mik->isConnected()) {
                    $isArpOrStatic = ($customer->connection_type === 'static' || $customer->connection_type === 'arp' || empty($customer->pppoe_username) || str_starts_with($customer->pppoe_username, 'arp_'));

                    if (!$isArpOrStatic && !empty($customer->pppoe_username)) {
                        $unameLower = strtolower(trim($customer->pppoe_username));
                        $ifaces = $mik->query('/interface/print', ['.proplist' => 'name,rx-byte,tx-byte']);
                        $matchedIface = null;
                        if (is_array($ifaces)) {
                            foreach ($ifaces as $if) {
                                $iname = strtolower($if['name'] ?? '');
                                if ($iname === "<pppoe-{$unameLower}>" || $iname === "pppoe-{$unameLower}" || $iname === $unameLower || str_contains($iname, $unameLower)) {
                                    $matchedIface = $if['name'];
                                    $uploadBytes = (int)($if['rx-byte'] ?? 0);
                                    $downloadBytes = (int)($if['tx-byte'] ?? 0);
                                    break;
                                }
                            }
                        }

                        if ($matchedIface) {
                            try {
                                $mon = $mik->query('/interface/monitor-traffic', [
                                    'interface' => $matchedIface,
                                    'once' => '',
                                ]);
                                if (!empty($mon[0])) {
                                    $rxSpeed = (int)($mon[0]['rx-bits-per-second'] ?? 0);
                                    $txSpeed = (int)($mon[0]['tx-bits-per-second'] ?? 0);
                                }
                            } catch (\Throwable $e) {}
                        }

                        // Fallback Simple Queue jika monitor-traffic bernilai 0
                        if ($rxSpeed === 0 && $txSpeed === 0) {
                            try {
                                $queues = $mik->query('/queue/simple/print', ['.proplist' => 'name,target,bytes,rate']);
                                if (is_array($queues)) {
                                    foreach ($queues as $q) {
                                        $qName = strtolower($q['name'] ?? '');
                                        if (str_contains($qName, $unameLower)) {
                                            $rateParts = explode('/', $q['rate'] ?? '0/0');
                                            $rxSpeed = isset($rateParts[0]) ? (int) $rateParts[0] : 0;
                                            $txSpeed = isset($rateParts[1]) ? (int) $rateParts[1] : 0;
                                            if ($downloadBytes === 0 && $uploadBytes === 0) {
                                                $byteParts = explode('/', $q['bytes'] ?? '0/0');
                                                $uploadBytes = isset($byteParts[0]) ? (int) $byteParts[0] : 0;
                                                $downloadBytes = isset($byteParts[1]) ? (int) $byteParts[1] : 0;
                                            }
                                            break;
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {}
                        }
                    } elseif (!empty($customer->ip_address)) {
                        $targetIp = trim($customer->ip_address);
                        try {
                            $queues = $mik->query('/queue/simple/print', ['.proplist' => 'name,target,bytes,rate']);
                            if (is_array($queues)) {
                                foreach ($queues as $q) {
                                    $qTarget = $q['target'] ?? '';
                                    if (str_contains($qTarget, $targetIp) || stripos($q['name'] ?? '', $customer->name) !== false) {
                                        $rateParts = explode('/', $q['rate'] ?? '0/0');
                                        $rxSpeed = isset($rateParts[0]) ? (int) $rateParts[0] : 0;
                                        $txSpeed = isset($rateParts[1]) ? (int) $rateParts[1] : 0;
                                        $byteParts = explode('/', $q['bytes'] ?? '0/0');
                                        $uploadBytes = isset($byteParts[0]) ? (int) $byteParts[0] : 0;
                                        $downloadBytes = isset($byteParts[1]) ? (int) $byteParts[1] : 0;
                                        break;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Portal realtime traffic query failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'download_speed' => $txSpeed,
            'upload_speed' => $rxSpeed,
            'download' => $downloadBytes,
            'upload' => $uploadBytes,
            'total' => $downloadBytes + $uploadBytes,
        ]);
    }

    public function speedtestPing()
    {
        return response()->json([
            'success' => true,
            'timestamp' => microtime(true),
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    public function speedtestDownload(Request $request)
    {
        $sizeMB = min(16, max(0.5, (float) ($request->query('size', 4))));
        $totalBytes = (int) ($sizeMB * 1024 * 1024);
        $chunkSize = 64 * 1024;
        $chunkData = str_repeat('A', $chunkSize);

        return response()->stream(function () use ($totalBytes, $chunkSize, $chunkData) {
            $sent = 0;
            while ($sent < $totalBytes && !connection_aborted()) {
                $toSend = min($chunkSize, $totalBytes - $sent);
                if ($toSend === $chunkSize) {
                    echo $chunkData;
                } else {
                    echo substr($chunkData, 0, $toSend);
                }
                $sent += $toSend;
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="speedtest.bin"',
            'Content-Length' => (string) $totalBytes,
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0, no-transform',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function speedtestUpload(Request $request)
    {
        $content = $request->getContent();
        $bytesReceived = is_string($content) ? strlen($content) : 0;

        return response()->json([
            'success' => true,
            'received' => $bytesReceived,
            'timestamp' => microtime(true),
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    private function formatUptimeHuman(?string $raw): ?string
    {
        if (empty($raw) || $raw === '-') {
            return null;
        }
        $lower = strtolower(trim($raw));
        if (str_starts_with($lower, 'active') || str_starts_with($lower, 'static')) {
            return null;
        }

        $parts = [];
        if (preg_match('/(\d+)w/', $lower, $m)) {
            $parts[] = $m[1] . ' Minggu';
        }
        if (preg_match('/(\d+)d/', $lower, $m)) {
            $parts[] = $m[1] . ' Hari';
        }
        if (preg_match('/(\d+)h/', $lower, $m)) {
            $parts[] = $m[1] . ' Jam';
        }
        if (preg_match('/(\d+)m(?!s)/', $lower, $m)) {
            $parts[] = $m[1] . ' Menit';
        }
        if (empty($parts) && preg_match('/(\d+)s/', $lower, $m)) {
            $parts[] = $m[1] . ' Detik';
        }

        return !empty($parts) ? implode(' ', array_slice($parts, 0, 3)) : null;
    }

    public function tos()
    {
        return Inertia::render('Portal/Tos');
    }

    public function laporan(Request $request)
    {
        $customerId = session('customer_id');
        if (!$customerId) return redirect('/portal/login');

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (!$customer) return redirect('/portal/login')->with('error', 'Data tidak ditemukan');

        if ($request->isMethod('POST')) {
            $request->validate([
                'title' => 'required|min:5|max:200',
                'description' => 'required|min:10',
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png,mp4|max:20480',
            ]);

            $attachmentName = null;
            if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
                $uploadPath = public_path('uploads/tickets');
                if (!is_dir($uploadPath)) mkdir($uploadPath, 0755, true);
                $attachmentName = $request->file('attachment')->hashName();
                $request->file('attachment')->move($uploadPath, $attachmentName);
            }

            $ticket = TroubleTicket::create([
                'customer_id' => $customerId,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'title' => $request->title,
                'description' => $request->description,
                'status' => 'pending',
                'priority' => 'low',
                'router_id' => $customer->router_id,
                'attachment' => $attachmentName,
                'tenant_id' => session('tenant_id'),
            ]);

            // Notifikasi admin & teknisi via Push Notification + WhatsApp + Telegram
            try {
                app(\App\Services\PushNotificationService::class)->notifyTicketCreated($ticket);
            } catch (\Exception $e) {
                \Log::error('Push notif failed: ' . $e->getMessage());
            }

            return redirect('/portal/laporan')->with('success', 'Laporan berhasil dikirim! Admin & Teknisi akan segera memproses.');
        }

        $tickets = TroubleTicket::where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')->get();

        return Inertia::render('Portal/Laporan', [
            'customer' => ['name' => $customer->name, 'id' => $customer->id],
            'tickets' => $tickets->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function saveFcmToken(Request $request)
    {
        $customerId = session('customer_id');
        if ($customerId && $request->filled('token')) {
            Customer::where('id', $customerId)->update(['fcm_token' => $request->token]);
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 400);
    }

    private function getAdminWaNumber($customer, ?int $explicitTenantId = null): string
    {
        $tenantId = $explicitTenantId ?? ($customer?->tenant_id ?? session('tenant_id') ?? \App\Models\Scopes\TenantScope::currentTenantId());
        $phone = Setting::getTenantPhone($tenantId);
        return Setting::waNumber($phone) ?: '6281234567890';
    }

    public function profile(Request $request)
    {
        $customerId = session('customer_id');
        if (! $customerId) {
            return redirect()->to('/portal/login')->with('error', 'Silakan login terlebih dahulu');
        }

        $customer = Customer::withoutGlobalScopes()->with('package')->find($customerId);
        if (! $customer) {
            return redirect()->to('/portal/login')->with('error', 'Data pelanggan tidak ditemukan');
        }

        $tenantId = $customer->tenant_id ?? session('customer_tenant_id') ?? session('tenant_id');
        $setting = Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', 'company')->first();
        $companyName = $setting?->value['name'] ?? config('app.name', 'NODERA');

        return Inertia::render('Portal/Profile', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'code' => $customer->code ?? sprintf('CUST-%04d', $customer->id),
                'phone' => $customer->phone ?? '-',
                'email' => $customer->email ?? '-',
                'address' => $customer->address ?? '-',
                'pppoe_username' => $customer->pppoe_username ?? '-',
                'status' => $customer->status ?? 'active',
                'due_date' => $customer->due_date ?? 10,
                'auto_isolate' => (bool) ($customer->auto_isolate ?? true),
                'package_name' => $customer->package?->name ?? 'Paket Reguler',
                'package_price' => $customer->package?->price ?? 0,
                'package_speed' => $customer->package?->speed ?? '20 Mbps',
                'created_at' => $customer->created_at?->format('d M Y') ?? '-',
            ],
            'companyName' => $companyName,
        ]);
    }

    private function cleanScalarValue(mixed $val, ?string $fallback = null): ?string
    {
        if ($val === null) {
            return $fallback;
        }
        if (is_scalar($val)) {
            $s = trim((string) $val);
            return $s !== '' ? $s : $fallback;
        }
        if (is_array($val)) {
            if (isset($val['_value']) && is_scalar($val['_value'])) {
                $s = trim((string) $val['_value']);
                return $s !== '' ? $s : $fallback;
            }
            if (isset($val['value']) && is_scalar($val['value'])) {
                $s = trim((string) $val['value']);
                return $s !== '' ? $s : $fallback;
            }
            return $fallback;
        }
        return $fallback;
    }

    private function getOnuInfo($customer, $tenantId = null): array
    {
        $ontDevice = null;
        if (! empty($customer->id)) {
            $ontDevice = \App\Models\OntDevice::where('customer_id', $customer->id)->first();
        }
        if (! $ontDevice && ! empty($customer->pppoe_username) && $tenantId) {
            $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                ->where(function ($q) use ($customer) {
                    $q->where('wifi_ssid', 'like', "%{$customer->pppoe_username}%")
                        ->orWhere('serial_number', 'like', "%{$customer->pppoe_username}%");
                })
                ->first();
        }
        if (! $ontDevice && ! empty($customer->ip_address) && $tenantId) {
            $ontDevice = \App\Models\OntDevice::where('tenant_id', $tenantId)
                ->where('ip_address', $customer->ip_address)
                ->first();
        }

        $onuData = null;
        if ($ontDevice) {
            $isOnline = strtoupper($ontDevice->status ?? '') === 'ONLINE';
            if (! $isOnline && $ontDevice->last_inform_at) {
                // If informed within the last 60 minutes, treat as online
                $isOnline = $ontDevice->last_inform_at->diffInMinutes(now()) <= 60;
            }

            $rawHosts = $ontDevice->raw_parameters['hosts'] ?? [];
            $formattedHosts = [];
            if (is_array($rawHosts)) {
                foreach ($rawHosts as $h) {
                    if (is_array($h)) {
                        $formattedHosts[] = [
                            'hostname'       => $this->cleanScalarValue($h['hostname'] ?? ($h['HostName'] ?? null), 'Perangkat N/A'),
                            'ip_address'     => $this->cleanScalarValue($h['ip_address'] ?? ($h['IPAddress'] ?? null), '-'),
                            'mac_address'    => $this->cleanScalarValue($h['mac_address'] ?? ($h['MACAddress'] ?? null), '-'),
                            'is_active'      => !empty($h['is_active']) || (!empty($h['Active']) && $h['Active'] !== 'false'),
                            'interface_type' => $this->cleanScalarValue($h['interface_type'] ?? ($h['InterfaceType'] ?? null), 'WiFi 802.11'),
                            'lease_time'     => is_numeric($h['lease_time'] ?? ($h['LeaseTimeRemaining'] ?? null)) ? (int)($h['lease_time'] ?? $h['LeaseTimeRemaining']) : null,
                        ];
                    }
                }
            }

            $activeHosts = array_filter($formattedHosts, fn($h) => !empty($h['is_active']));
            $clientsCount = is_numeric($ontDevice->connected_devices_count) ? (int)$ontDevice->connected_devices_count : (count($activeHosts) ?: (count($formattedHosts) ?: null));

            $rxPower = $this->cleanScalarValue($ontDevice->rx_power ?? ($ontDevice->raw_parameters['optical']['rx_power'] ?? null));
            $txPower = $this->cleanScalarValue($ontDevice->optical_tx_power ?? ($ontDevice->raw_parameters['optical']['tx_power'] ?? null));
            $voltage = $this->cleanScalarValue($ontDevice->optical_voltage ?? ($ontDevice->raw_parameters['optical']['voltage'] ?? null));
            $temp = $this->cleanScalarValue($ontDevice->optical_temp ?? ($ontDevice->raw_parameters['optical']['temperature'] ?? null));
            $uptimeSeconds = $this->cleanScalarValue($ontDevice->raw_parameters['wan']['uptime_seconds'] ?? ($ontDevice->raw_parameters['wan']['uptime'] ?? null));

            $rxVal = is_numeric($rxPower) ? (float) $rxPower : null;
            $rxStatus = 'BAGUS';
            if ($rxVal !== null) {
                if ($rxVal < -27.0) {
                    $rxStatus = 'BURUK';
                } elseif ($rxVal < -24.0) {
                    $rxStatus = 'CUKUP';
                }
            }

            $onuData = [
                'serial'        => $this->cleanScalarValue($ontDevice->serial_number, ''),
                'model'         => $this->cleanScalarValue($ontDevice->model_name ?? ($ontDevice->model ?? null), 'ONT Router'),
                'manufacturer'  => $this->cleanScalarValue($ontDevice->manufacturer, 'ZICG'),
                'lastInform'    => $ontDevice->last_inform_at?->toIso8601String(),
                'online'        => $isOnline,
                'ssid'          => $this->cleanScalarValue($ontDevice->wifi_ssid ?? ($ontDevice->raw_parameters['wifi']['ssid'] ?? null), ''),
                'wifiPassword'  => $this->cleanScalarValue($ontDevice->wifi_password ?? ($ontDevice->raw_parameters['wifi']['password'] ?? null), ''),
                'wifiSecurity'  => $this->cleanScalarValue($ontDevice->raw_parameters['wifi']['security'] ?? null, 'WPA2-PSK (AES)'),
                'wifiChannel'   => $this->cleanScalarValue($ontDevice->raw_parameters['wifi']['channel'] ?? null, 'Auto'),
                'wifiEnabled'   => $ontDevice->wifi_enabled ?? true,
                'rxPower'       => $rxPower !== null ? (string) $rxPower : '',
                'rxStatus'      => $rxStatus,
                'txPower'       => $txPower,
                'voltage'       => $voltage,
                'temp'          => $temp,
                'pppoeUsername' => $this->cleanScalarValue($customer->pppoe_username ?? ($ontDevice->raw_parameters['wan']['username'] ?? null), ''),
                'pppoeIP'       => $this->cleanScalarValue($ontDevice->ip_address ?? ($ontDevice->raw_parameters['wan']['external_ip'] ?? null), ''),
                'clients'       => $clientsCount,
                'hosts'         => $formattedHosts,
                'uptime'        => $uptimeSeconds,
            ];
        }

        if (! empty($customer->pppoe_username) || ! empty($ontDevice?->serial_number)) {
            try {
                $this->genieacs->clearCache();
                $device = null;
                if (! empty($customer->pppoe_username)) {
                    $device = $this->genieacs->getDeviceByPppoeUsername($customer->pppoe_username, $tenantId);
                }
                if (! $device && ! empty($ontDevice?->serial_number)) {
                    $device = $this->genieacs->getDevice($ontDevice->serial_number, false);
                }

                if ($device) {
                    $params = $device['flatParams'] ?? [];
                    $deviceId = $device['_deviceId'] ?? [];
                    $onuSerial = $this->cleanScalarValue($deviceId['_SerialNumber'] ?? ($params['InternetGatewayDevice.DeviceInfo.SerialNumber'] ?? ($device['serial_number'] ?? null)));

                    $clientsRaw = $params['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations']
                        ?? ($params['VirtualParameters.activedevices']
                        ?? ($params['VirtualParameters.useraktif']
                        ?? ($params['Device.WiFi.AccessPoint.1.AssociatedDeviceNumberOfEntries'] ?? ($device['wifi_clients'] ?? null))));
                    $clients = is_numeric($clientsRaw) ? (int)$clientsRaw : ($clientsRaw['_value'] ?? null);

                    $uptime = $this->cleanScalarValue(
                        $params['VirtualParameters.getdeviceuptime']
                        ?? ($params['VirtualParameters.uptime']
                        ?? ($params['InternetGatewayDevice.DeviceInfo.UpTime']
                        ?? ($params['Device.DeviceInfo.UpTime'] ?? ($device['uptime'] ?? null))))
                    );

                    $temp = $this->cleanScalarValue(
                        $params['VirtualParameters.gettemp']
                        ?? ($params['VirtualParameters.temp']
                        ?? ($params['InternetGatewayDevice.DeviceInfo.TemperatureStatus.TemperatureValue']
                        ?? ($params['Device.DeviceInfo.TemperatureStatus.TemperatureSensor.1.Value'] ?? ($device['temperature'] ?? null))))
                    );

                    $deviceOnline = ! empty($device['online']) || ! empty($device['_lastInform']);

                    $liveRx = $this->cleanScalarValue($params['VirtualParameters.RXPower'] ?? ($device['rx_power'] ?? ($onuData['rxPower'] ?? '')));

                    $onuData = array_merge($onuData ?? [], [
                        'serial'       => $onuSerial ?: ($onuData['serial'] ?? ''),
                        'model'        => $this->cleanScalarValue($params['InternetGatewayDevice.DeviceInfo.ModelName'] ?? ($device['model'] ?? ($onuData['model'] ?? null)), 'ONT Router'),
                        'manufacturer' => $this->cleanScalarValue($params['InternetGatewayDevice.DeviceInfo.Manufacturer'] ?? ($device['manufacturer'] ?? ($onuData['manufacturer'] ?? null)), 'ZICG'),
                        'lastInform'   => is_string($device['_lastInform'] ?? null) ? $device['_lastInform'] : ($device['last_inform'] ?? ($onuData['lastInform'] ?? null)),
                        'online'       => $deviceOnline || ($onuData['online'] ?? false),
                        'ssid'         => $this->cleanScalarValue($params['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID'] ?? ($device['ssid'] ?? ($onuData['ssid'] ?? null)), ''),
                        'wifiPassword' => $this->cleanScalarValue($params['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase'] ?? ($onuData['wifiPassword'] ?? null), ''),
                        'rxPower'      => $liveRx ?: ($onuData['rxPower'] ?? ''),
                        'pppoeUsername'=> $this->cleanScalarValue($params['VirtualParameters.pppoeUsername'] ?? ($params['VirtualParameters.pppoeUsername2'] ?? ($device['pppoe_username'] ?? ($customer->pppoe_username ?? null))), ''),
                        'pppoeIP'      => $this->cleanScalarValue($params['VirtualParameters.pppoeIP'] ?? ($device['pppoe_ip'] ?? ($onuData['pppoeIP'] ?? null)), ''),
                        'clients'      => $clients !== null ? (int) $clients : ($onuData['clients'] ?? null),
                        'uptime'       => $uptime ?? ($onuData['uptime'] ?? null),
                        'temp'         => $temp ?? ($onuData['temp'] ?? null),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('GenieACS error in Portal: '.$e->getMessage());
            }
        }

        $hasAcsDevice = ! empty($ontDevice) || ! empty($onuData);
        return [$hasAcsDevice, $onuData];
    }

    public function logout()
    {
        session()->forget(['customer_id', 'customer_phone', 'customer_name', 'logged_in']);

        return redirect()->to('/portal/login')->with('success', 'Anda telah logout');
    }
}
