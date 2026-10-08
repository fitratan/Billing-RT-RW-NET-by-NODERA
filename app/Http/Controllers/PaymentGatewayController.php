<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        $gateways = ['noderapay', 'tripay', 'midtrans', 'doku', 'xendit', 'duitku', 'wijayapay', 'paydisini', 'pakasir', 'cinetpay', 'wave', 'paytech', 'fedapay'];
        $configs = [];
        $activeStatus = [];

        $configuredGateways = [];
        foreach ($gateways as $gw) {
            $record = PaymentGateway::where('gateway', $gw)->first();
            $config = $record?->config_json ?? [];
            $hasCreds = !empty($config) && (
                ($config['is_configured'] ?? false) ||
                !empty($config['NODERAPAY_API_KEY']) ||
                !empty($config['TRIPAY_API_KEY']) ||
                !empty($config['MIDTRANS_SERVER_KEY']) ||
                !empty($config['DOKU_CLIENT_ID']) ||
                !empty($config['WIJAYAPAY_API_KEY']) ||
                !empty($config['DUITKU_API_KEY']) ||
                !empty($config['XENDIT_SECRET_KEY']) ||
                !empty($config['PAYDISINI_API_KEY']) ||
                !empty($config['PAKASIR_API_KEY'])
            );

            // Auto-activate gateway if credentials are configured but is_active was left false
            if ($record && $hasCreds && !$record->is_active) {
                $record->update(['is_active' => true]);
                $isActive = true;
            } else {
                $isActive = $record ? (bool) $record->is_active : false;
            }

            $config['is_active'] = $isActive;
            $config['enabled_channels'] = $config['enabled_channels'] ?? [];
            $configs[$gw . 'Config'] = $config;
            $activeStatus[$gw] = $isActive;
            $activeStatus[$gw . 'Active'] = $isActive;

            if ($hasCreds) {
                $configuredGateways[] = $gw;
            }
        }

        $usageGw = PaymentGateway::where('gateway', '_usage_settings')->first();
        $usageSettings = $usageGw?->config_json ?? [
            'enable_checkout_landing' => true,
            'enable_invoice_payment' => true,
            'enable_topup_deposit' => true,
            'default_gateway' => 'noderapay',
        ];

        // Self-heal: If default_gateway points to an unconfigured gateway, switch to first configured gateway
        $currentDefault = $usageSettings['default_gateway'] ?? 'noderapay';
        if (!in_array($currentDefault, $configuredGateways) && !empty($configuredGateways)) {
            $usageSettings['default_gateway'] = $configuredGateways[0];
            if ($usageGw) {
                $c = $usageGw->config_json ?? [];
                $c['default_gateway'] = $configuredGateways[0];
                $usageGw->update(['config_json' => $c]);
            }
        }

        return Inertia::render('Admin/PaymentGateway', [
            'noderapayConfig' => $configs['noderapayConfig'],
            'tripayConfig'    => $configs['tripayConfig'],
            'midtransConfig'  => $configs['midtransConfig'],
            'dokuConfig'      => $configs['dokuConfig'] ?? [],
            'xenditConfig'    => $configs['xenditConfig'],
            'duitkuConfig'    => $configs['duitkuConfig'],
            'wijayapayConfig' => $configs['wijayapayConfig'],
            'paydisiniConfig' => $configs['paydisiniConfig'],
            'pakasirConfig'   => $configs['pakasirConfig'],
            'cinetpayConfig'  => $configs['cinetpayConfig'] ?? [],
            'waveConfig'      => $configs['waveConfig'] ?? [],
            'paytechConfig'   => $configs['paytechConfig'] ?? [],
            'fedapayConfig'   => $configs['fedapayConfig'] ?? [],
            'activeStatus'    => $activeStatus,
            'usageSettings'   => $usageSettings,
        ]);
    }

    public function saveUsage(Request $request)
    {
        $existing = PaymentGateway::getConfig('_usage_settings');
        $data = array_merge(is_array($existing) ? $existing : [], [
            'enable_checkout_landing' => $request->boolean('enable_checkout_landing', true),
            'enable_invoice_payment'  => $request->boolean('enable_invoice_payment', true),
            'enable_topup_deposit'    => $request->boolean('enable_topup_deposit', true),
            'default_gateway'         => $request->input('default_gateway', 'noderapay'),
            'expiry_minutes'          => $request->has('expiry_minutes') ? max(1, (int)$request->input('expiry_minutes')) : ($existing['expiry_minutes'] ?? 15),
        ]);

        PaymentGateway::setConfig('_usage_settings', $data);

        return redirect()->back()->with('msg', 'Pengaturan cakupan Payment Gateway berhasil disimpan!');
    }

    public function toggleGateway(Request $request, string $gateway)
    {
        $record = PaymentGateway::where('gateway', $gateway)->first();
        if ($record) {
            $newStatus = !$record->is_active;
            $record->update(['is_active' => $newStatus]);

            $tenantId = session('tenant_id') ?? 0;
            \Illuminate\Support\Facades\Cache::forget("pg_active_{$tenantId}_{$gateway}");
            \Illuminate\Support\Facades\Cache::forget("pg_active_0_{$gateway}");

            return redirect()->back()->with('msg', 'Status gateway ' . strtoupper($gateway) . ' berhasil di' . ($newStatus ? 'aktifkan' : 'nonaktifkan') . '.');
        }

        return redirect()->back()->with('error', 'Gateway belum dikonfigurasi.');
    }

    /**
     * Test connection to a Payment Gateway.
     */
    public function testGateway(Request $request, string $gateway): JsonResponse
    {
        $gateway = strtolower($gateway);

        try {
            switch ($gateway) {
                case 'noderapay':
                    $merchantCode = trim((string) ($request->input('NODERAPAY_MERCHANT_CODE') ?: (PaymentGateway::getConfig('noderapay')['NODERAPAY_MERCHANT_CODE'] ?? '')));
                    $apiKey = trim((string) ($request->input('NODERAPAY_API_KEY') ?: (PaymentGateway::getConfig('noderapay')['NODERAPAY_API_KEY'] ?? '')));

                    if (empty($apiKey)) {
                        return response()->json(['success' => false, 'message' => 'API Key NODERA PAY belum diisi.'], 422);
                    }

                    $res = Http::timeout(10)->withHeaders([
                        'X-Api-Key' => $apiKey,
                        'X-Merchant-Code' => $merchantCode,
                    ])->get('https://gateway.dgtlnetsolution.com/api/v1/noderapay/get-status', [
                        'merchant_code' => $merchantCode,
                        'ref_id' => 'TEST_PING_CONNECTION',
                    ]);

                    if ($res->successful() || $res->status() === 200 || $res->status() === 404) {
                        $record = PaymentGateway::where('gateway', 'noderapay')->first();
                        if ($record && !$record->is_active) {
                            $record->update(['is_active' => true]);
                            $tId = session('tenant_id') ?? 0;
                            \Illuminate\Support\Facades\Cache::forget("pg_active_{$tId}_noderapay");
                            \Illuminate\Support\Facades\Cache::forget("pg_active_0_noderapay");
                        }
                        return response()->json([
                            'success' => true,
                            'message' => "Koneksi ke NODERA PAY Berhasil! Akun terverifikasi aktif.",
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Koneksi NODERA PAY gagal. Periksa Kode Merchant dan API Key Anda.',
                    ], 400);

                case 'tripay':
                    $apiKey = trim((string) ($request->input('TRIPAY_API_KEY') ?: (PaymentGateway::getConfig('tripay')['TRIPAY_API_KEY'] ?? '')));
                    $mode = trim((string) ($request->input('TRIPAY_MODE') ?: (PaymentGateway::getConfig('tripay')['TRIPAY_MODE'] ?? 'sandbox')));
                    
                    if (empty($apiKey)) {
                        return response()->json(['success' => false, 'message' => 'API Key Tripay belum diisi.'], 422);
                    }

                    $url = ($mode === 'production')
                        ? 'https://tripay.co.id/api/merchant/payment-channel'
                        : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';

                    $res = Http::timeout(10)->withToken($apiKey)->get($url);
                    $json = $res->json();

                    if ($res->successful() && ($json['success'] ?? false)) {
                        $count = count($json['data'] ?? []);
                        return response()->json([
                            'success' => true,
                            'message' => "Koneksi Tripay Berhasil! Mode: " . strtoupper($mode) . " ({$count} channel pembayaran aktif).",
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $json['message'] ?? 'Gagal menghubungi server Tripay. Periksa kredensial Anda.',
                    ], 400);

                case 'midtrans':
                    $serverKey = trim((string) ($request->input('MIDTRANS_SERVER_KEY') ?: (PaymentGateway::getConfig('midtrans')['MIDTRANS_SERVER_KEY'] ?? '')));
                    $mode = trim((string) ($request->input('MIDTRANS_MODE') ?: (PaymentGateway::getConfig('midtrans')['MIDTRANS_MODE'] ?? 'sandbox')));

                    if (empty($serverKey)) {
                        return response()->json(['success' => false, 'message' => 'Server Key Midtrans belum diisi.'], 422);
                    }

                    $url = ($mode === 'production')
                        ? 'https://api.midtrans.com/v2/token'
                        : 'https://api.sandbox.midtrans.com/v2/token';

                    $res = Http::timeout(10)
                        ->withBasicAuth($serverKey, '')
                        ->get($url);

                    if ($res->status() !== 401 && $res->status() !== 403) {
                        return response()->json([
                            'success' => true,
                            'message' => "Koneksi Midtrans Berhasil! Mode: " . strtoupper($mode) . " (Server Key valid).",
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Server Key Midtrans tidak valid / ditolak (Error 401 Unauthorized).',
                    ], 401);

                case 'wijayapay':
                    $codeMerchant = trim((string) ($request->input('WIJAYAPAY_CODE_MERCHANT') ?: (PaymentGateway::getConfig('wijayapay')['WIJAYAPAY_CODE_MERCHANT'] ?? '')));
                    $apiKey = trim((string) ($request->input('WIJAYAPAY_API_KEY') ?: (PaymentGateway::getConfig('wijayapay')['WIJAYAPAY_API_KEY'] ?? '')));

                    if (empty($codeMerchant) || empty($apiKey)) {
                        return response()->json(['success' => false, 'message' => 'Code Merchant dan API Key WijayaPay belum diisi.'], 422);
                    }

                    $res = Http::timeout(10)->get('https://gateway.wijayapay.com/api/get-payment', [
                        'code_merchant' => $codeMerchant,
                        'api_key'       => $apiKey,
                    ]);

                    $json = $res->json();

                    if ($res->successful() && ($json['status'] ?? false || ($json['status'] ?? '') === 'success' || isset($json['data']))) {
                        $count = is_array($json['data'] ?? null) ? count($json['data']) : 1;
                        return response()->json([
                            'success' => true,
                            'message' => "Koneksi WijayaPay Berhasil! Terhubung dengan {$count} channel aktif.",
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $json['message'] ?? 'Kredensial WijayaPay ditolak. Periksa Code Merchant dan API Key.',
                    ], 400);

                case 'doku':
                    $clientId = trim((string) ($request->input('DOKU_CLIENT_ID') ?: (PaymentGateway::getConfig('doku')['DOKU_CLIENT_ID'] ?? '')));
                    $secretKey = trim((string) ($request->input('DOKU_SECRET_KEY') ?: (PaymentGateway::getConfig('doku')['DOKU_SECRET_KEY'] ?? '')));
                    $mode = strtolower(trim((string) ($request->input('DOKU_MODE') ?: (PaymentGateway::getConfig('doku')['DOKU_MODE'] ?? 'sandbox'))));

                    if (empty($clientId) || empty($secretKey)) {
                        return response()->json(['success' => false, 'message' => 'Client ID dan Secret Key DOKU belum diisi.'], 422);
                    }

                    $baseUrl = in_array($mode, ['production', 'live']) ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
                    $requestId = (string) \Illuminate\Support\Str::uuid();
                    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
                    $targetPath = '/orders/v1/status/PING-TEST';

                    $component = "Client-Id:" . $clientId . "\n"
                               . "Request-Id:" . $requestId . "\n"
                               . "Request-Timestamp:" . $timestamp . "\n"
                               . "Request-Target:" . $targetPath;
                    $signature = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $secretKey, true));

                    $res = Http::timeout(10)->withHeaders([
                        'Client-Id'         => $clientId,
                        'Request-Id'        => $requestId,
                        'Request-Timestamp' => $timestamp,
                        'Signature'         => $signature,
                        'Content-Type'      => 'application/json',
                    ])->get($baseUrl . $targetPath);

                    if ($res->status() !== 401 && $res->status() !== 403) {
                        return response()->json([
                            'success' => true,
                            'message' => "Koneksi DOKU Berhasil! Mode: " . strtoupper($mode) . " terautentikasi.",
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Kredensial DOKU ditolak (HTTP ' . $res->status() . '). Periksa Client ID & Secret Key.',
                    ], 400);

                case 'duitku':
                    $merchantCode = trim((string) ($request->input('DUITKU_MERCHANT_CODE') ?: (PaymentGateway::getConfig('duitku')['DUITKU_MERCHANT_CODE'] ?? '')));
                    $apiKey = trim((string) ($request->input('DUITKU_API_KEY') ?: (PaymentGateway::getConfig('duitku')['DUITKU_API_KEY'] ?? '')));
                    $mode = trim((string) ($request->input('DUITKU_MODE') ?: (PaymentGateway::getConfig('duitku')['DUITKU_MODE'] ?? 'sandbox')));

                    if (empty($merchantCode) || empty($apiKey)) {
                        return response()->json(['success' => false, 'message' => 'Merchant Code dan API Key Duitku belum diisi.'], 422);
                    }

                    $datetime = date('Y-m-d H:i:s');
                    $signature = hash('sha256', $merchantCode . '10000' . $datetime . $apiKey);
                    $url = ($mode === 'production')
                        ? 'https://passport.duitku.com/webapi/api/merchant/paymentmethod/getpaymentmethod'
                        : 'https://sandbox.duitku.com/webapi/api/merchant/paymentmethod/getpaymentmethod';

                    $res = Http::timeout(10)->post($url, [
                        'merchantcode' => $merchantCode,
                        'amount'       => '10000',
                        'datetime'     => $datetime,
                        'signature'    => $signature,
                    ]);

                    $json = $res->json();

                    if ($res->successful() && isset($json['paymentFee'])) {
                        $count = count($json['paymentFee']);
                        return response()->json([
                            'success' => true,
                            'message' => "Koneksi Duitku Berhasil! Mode: " . strtoupper($mode) . " ({$count} channel aktif).",
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $json['Message'] ?? ($json['responseMessage'] ?? 'Koneksi Duitku gagal. Periksa Merchant Code & Key.'),
                    ], 400);

                case 'xendit':
                    $secretKey = trim((string) ($request->input('XENDIT_SECRET_KEY') ?: (PaymentGateway::getConfig('xendit')['XENDIT_SECRET_KEY'] ?? '')));

                    if (empty($secretKey)) {
                        return response()->json(['success' => false, 'message' => 'Secret Key Xendit belum diisi.'], 422);
                    }

                    $res = Http::timeout(10)
                        ->withBasicAuth($secretKey, '')
                        ->get('https://api.xendit.co/balance');

                    if ($res->successful()) {
                        $json = $res->json();
                        $bal = number_format((float) ($json['balance'] ?? 0), 0, ',', '.');
                        return response()->json([
                            'success' => true,
                            'message' => "Koneksi Xendit Berhasil! Saldo akun: Rp {$bal}.",
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Secret Key Xendit tidak valid atau ditolak (HTTP ' . $res->status() . ').',
                    ], 400);

                default:
                    return response()->json(['success' => false, 'message' => 'Gateway tidak dikenal.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat menguji gateway: ' . $e->getMessage()], 500);
        }
    }

    public function saveNoderapay(Request $request)
    {
        $data = $request->only(['NODERAPAY_MERCHANT_CODE', 'NODERAPAY_API_KEY', 'NODERAPAY_MERCHANT_NAME']);
        $isActive = $request->boolean('is_active', true);
        if ($request->has('enabled_channels')) {
            $data['enabled_channels'] = (array) $request->input('enabled_channels');
        } else {
            $data['enabled_channels'] = ['QRIS'];
        }
        PaymentGateway::setConfig('noderapay', $data, $isActive);
        if ($isActive) {
            $usage = PaymentGateway::getConfig('_usage_settings');
            $usage['default_gateway'] = 'noderapay';
            $usage['enable_gateway'] = true;
            PaymentGateway::setConfig('_usage_settings', $usage, true);
        }
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi NODERA PAY berhasil disimpan.');
    }

    public function saveXendit(Request $request)
    {
        $data = [
            'XENDIT_SECRET_KEY' => trim((string) ($request->input('XENDIT_SECRET_KEY') ?: $request->input('secret_key'))),
            'XENDIT_PUBLIC_KEY' => trim((string) ($request->input('XENDIT_PUBLIC_KEY') ?: $request->input('public_key'))),
            'XENDIT_CALLBACK_TOKEN' => trim((string) ($request->input('XENDIT_CALLBACK_TOKEN') ?: $request->input('callback_token'))),
            'is_configured' => true,
        ];
        $isActive = $request->boolean('is_active', true);
        if ($request->has('enabled_channels')) {
            $data['enabled_channels'] = (array) $request->input('enabled_channels');
        }
        PaymentGateway::setConfig('xendit', $data, $isActive);
        if ($isActive) {
            $usage = PaymentGateway::getConfig('_usage_settings');
            $usage['default_gateway'] = 'xendit';
            $usage['enable_gateway'] = true;
            PaymentGateway::setConfig('_usage_settings', $usage, true);
        }
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi Xendit berhasil disimpan.');
    }

    public function saveWijayapay(Request $request)
    {
        $mode = strtolower((string) ($request->input('WIJAYAPAY_MODE') ?: $request->input('mode') ?: 'production'));
        $data = [
            'WIJAYAPAY_CODE_MERCHANT' => trim((string) ($request->input('WIJAYAPAY_CODE_MERCHANT') ?: $request->input('code_merchant'))),
            'WIJAYAPAY_API_KEY' => trim((string) ($request->input('WIJAYAPAY_API_KEY') ?: $request->input('api_key'))),
            'WIJAYAPAY_MODE' => $mode,
            'mode' => $mode,
            'is_configured' => true,
        ];
        $isActive = $request->boolean('is_active', true);
        if ($request->has('enabled_channels')) {
            $data['enabled_channels'] = (array) $request->input('enabled_channels');
        }
        PaymentGateway::setConfig('wijayapay', $data, $isActive);
        if ($isActive) {
            $usage = PaymentGateway::getConfig('_usage_settings');
            $usage['default_gateway'] = 'wijayapay';
            $usage['enable_gateway'] = true;
            PaymentGateway::setConfig('_usage_settings', $usage, true);
        }
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi WijayaPay berhasil disimpan.');
    }

    public function saveDuitku(Request $request)
    {
        $env = strtolower((string) ($request->input('DUITKU_ENV') ?: $request->input('DUITKU_MODE') ?: $request->input('mode') ?: 'sandbox'));
        $data = [
            'DUITKU_MERCHANT_CODE' => trim((string) ($request->input('DUITKU_MERCHANT_CODE') ?: $request->input('merchant_code'))),
            'DUITKU_API_KEY' => trim((string) ($request->input('DUITKU_API_KEY') ?: $request->input('api_key'))),
            'DUITKU_ENV' => $env,
            'DUITKU_MODE' => $env,
            'mode' => $env,
            'is_configured' => true,
        ];
        $isActive = $request->boolean('is_active', true);
        if ($request->has('enabled_channels')) {
            $data['enabled_channels'] = (array) $request->input('enabled_channels');
        }
        PaymentGateway::setConfig('duitku', $data, $isActive);
        if ($isActive) {
            $usage = PaymentGateway::getConfig('_usage_settings');
            $usage['default_gateway'] = 'duitku';
            $usage['enable_gateway'] = true;
            PaymentGateway::setConfig('_usage_settings', $usage, true);
        }
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi Duitku berhasil disimpan.');
    }

    public function saveTripay(Request $request)
    {
        $mode = strtolower((string) ($request->input('TRIPAY_MODE') ?: $request->input('mode') ?: 'sandbox'));
        $data = [
            'TRIPAY_MERCHANT_CODE' => trim((string) ($request->input('TRIPAY_MERCHANT_CODE') ?: $request->input('merchant_code'))),
            'TRIPAY_API_KEY' => trim((string) ($request->input('TRIPAY_API_KEY') ?: $request->input('api_key'))),
            'TRIPAY_PRIVATE_KEY' => trim((string) ($request->input('TRIPAY_PRIVATE_KEY') ?: $request->input('private_key'))),
            'TRIPAY_MODE' => $mode,
            'mode' => $mode,
            'is_configured' => true,
        ];
        $isActive = $request->boolean('is_active', true);
        if ($request->has('enabled_channels')) {
            $data['enabled_channels'] = (array) $request->input('enabled_channels');
        }
        PaymentGateway::setConfig('tripay', $data, $isActive);
        if ($isActive) {
            $usage = PaymentGateway::getConfig('_usage_settings');
            $usage['default_gateway'] = 'tripay';
            $usage['enable_gateway'] = true;
            PaymentGateway::setConfig('_usage_settings', $usage, true);
        }
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi Tripay berhasil disimpan.');
    }

    public function saveMidtrans(Request $request)
    {
        $isProd = $request->boolean('MIDTRANS_IS_PRODUCTION', false)
            || strtolower((string) $request->input('MIDTRANS_MODE')) === 'production'
            || strtolower((string) $request->input('mode')) === 'production'
            || $request->boolean('is_production', false);

        $data = [
            'MIDTRANS_SERVER_KEY' => trim((string) ($request->input('MIDTRANS_SERVER_KEY') ?: $request->input('server_key'))),
            'MIDTRANS_CLIENT_KEY' => trim((string) ($request->input('MIDTRANS_CLIENT_KEY') ?: $request->input('client_key'))),
            'MIDTRANS_MERCHANT_ID' => trim((string) ($request->input('MIDTRANS_MERCHANT_ID') ?: $request->input('merchant_id'))),
            'MIDTRANS_IS_PRODUCTION' => $isProd,
            'MIDTRANS_MODE' => $isProd ? 'production' : 'sandbox',
            'is_production' => $isProd,
            'mode' => $isProd ? 'production' : 'sandbox',
            'is_configured' => true,
        ];
        $isActive = $request->boolean('is_active', true);
        if ($request->has('enabled_channels')) {
            $data['enabled_channels'] = (array) $request->input('enabled_channels');
        }
        PaymentGateway::setConfig('midtrans', $data, $isActive);
        if ($isActive) {
            $usage = PaymentGateway::getConfig('_usage_settings');
            $usage['default_gateway'] = 'midtrans';
            $usage['enable_gateway'] = true;
            PaymentGateway::setConfig('_usage_settings', $usage, true);
        }
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi Midtrans berhasil disimpan.');
    }

    public function saveDoku(Request $request)
    {
        $mode = strtolower((string) ($request->input('DOKU_MODE') ?: $request->input('mode') ?: 'sandbox'));
        $data = [
            'DOKU_CLIENT_ID'  => trim((string) ($request->input('DOKU_CLIENT_ID') ?: $request->input('client_id'))),
            'DOKU_SECRET_KEY' => trim((string) ($request->input('DOKU_SECRET_KEY') ?: $request->input('secret_key'))),
            'DOKU_MODE'       => $mode,
            'mode'            => $mode,
            'is_configured'   => true,
        ];
        $isActive = $request->boolean('is_active', true);
        if ($request->has('enabled_channels')) {
            $data['enabled_channels'] = (array) $request->input('enabled_channels');
        }
        PaymentGateway::setConfig('doku', $data, $isActive);
        if ($isActive) {
            $usage = PaymentGateway::getConfig('_usage_settings');
            $usage['default_gateway'] = 'doku';
            $usage['enable_gateway'] = true;
            PaymentGateway::setConfig('_usage_settings', $usage, true);
        }
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi DOKU berhasil disimpan.');
    }

    public function savePaydisini(Request $request)
    {
        $mode = strtolower((string) ($request->input('PAYDISINI_MODE') ?: $request->input('mode') ?: 'production'));
        $data = [
            'PAYDISINI_API_KEY' => trim((string) ($request->input('PAYDISINI_API_KEY') ?: $request->input('api_key'))),
            'PAYDISINI_SERVICE_ID' => trim((string) ($request->input('PAYDISINI_SERVICE_ID') ?: $request->input('service_id'))),
            'PAYDISINI_MODE' => $mode,
            'mode' => $mode,
            'is_configured' => true,
        ];
        $isActive = $request->boolean('is_active', true);
        PaymentGateway::setConfig('paydisini', $data, $isActive);
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi Paydisini berhasil disimpan.');
    }

    public function savePakasir(Request $request)
    {
        $data = [
            'PAKASIR_API_KEY' => trim((string) ($request->input('PAKASIR_API_KEY') ?: $request->input('api_key'))),
            'PAKASIR_SLUG' => trim((string) ($request->input('PAKASIR_SLUG') ?: $request->input('slug'))),
            'PAKASIR_PROJECT_ID' => trim((string) ($request->input('PAKASIR_PROJECT_ID') ?: $request->input('project_id'))),
            'is_configured' => true,
        ];
        $isActive = $request->boolean('is_active', true);
        PaymentGateway::setConfig('pakasir', $data, $isActive);
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi Pakasir berhasil disimpan.');
    }

    public function saveCinetPay(Request $request)
    {
        $data = $request->only(['CINETPAY_API_KEY', 'CINETPAY_SITE_ID', 'CINETPAY_SECRET_KEY']);
        $isActive = $request->boolean('is_active', true);
        PaymentGateway::setConfig('cinetpay', $data, $isActive);
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi CinetPay berhasil disimpan.');
    }

    public function saveWave(Request $request)
    {
        $data = $request->only(['WAVE_API_KEY', 'WAVE_SECRET_KEY', 'WAVE_BUSINESS_ID']);
        $isActive = $request->boolean('is_active', true);
        PaymentGateway::setConfig('wave', $data, $isActive);
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi Wave berhasil disimpan.');
    }

    public function savePayTech(Request $request)
    {
        $data = $request->only(['PAYTECH_API_KEY', 'PAYTECH_SECRET_KEY', 'PAYTECH_ENV']);
        $isActive = $request->boolean('is_active', true);
        PaymentGateway::setConfig('paytech', $data, $isActive);
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi PayTech berhasil disimpan.');
    }

    public function saveFedaPay(Request $request)
    {
        $data = $request->only(['FEDAPAY_API_KEY', 'FEDAPAY_ENV']);
        $isActive = $request->boolean('is_active', true);
        PaymentGateway::setConfig('fedapay', $data, $isActive);
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi FedaPay berhasil disimpan.');
    }

    public function deleteGateway($gateway)
    {
        $tenantId = session('tenant_id') ?? 0;
        \Illuminate\Support\Facades\Cache::forget("pg_cfg_{$tenantId}_{$gateway}");
        \Illuminate\Support\Facades\Cache::forget("pg_cfg_0_{$gateway}");
        \Illuminate\Support\Facades\Cache::forget("pg_active_{$tenantId}_{$gateway}");
        \Illuminate\Support\Facades\Cache::forget("pg_active_0_{$gateway}");

        PaymentGateway::where('gateway', $gateway)->delete();
        return redirect()->to('/admin/payments/gateway')->with('msg', 'Konfigurasi gateway berhasil dihapus.');
    }

    public function handleWebhook($gateway, Request $request)
    {
        Log::info("Payment gateway webhook received: {$gateway}", $request->all());

        $json = $request->getContent();

        try {
            $this->logWebhook($gateway, $json, 200, 'Received');
        } catch (\Exception $e) {
            Log::error("Failed to log webhook: " . $e->getMessage());
        }

        return response()->json(['status' => 'ok']);
    }

    private function logWebhook($gateway, $payload, $code, $msg)
    {
        try {
            DB::table('webhook_logs')->insert([
                'source' => $gateway,
                'payload' => $payload,
                'response_code' => $code,
                'response_message' => $msg,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log webhook to DB: ' . $e->getMessage());
        }
    }
}
