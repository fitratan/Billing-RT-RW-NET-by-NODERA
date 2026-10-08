<?php

namespace App\Services;

use App\Models\PaymentGateway;
use App\Models\NoderaPayMerchant;
use App\Services\WijayaPayService;
use App\Services\NoderaPayEngineService;
use App\Services\TripayService;
use App\Services\MidtransService;
use App\Services\DuitkuService;
use App\Services\XenditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PlatformPaymentService
{
    /**
     * Get all active and configured Superadmin payment gateways.
     */
    public static function getActiveGateways(): array
    {
        $records = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('is_active', true)
            ->whereNotIn('gateway', ['_platform_payment_settings', 'manual'])
            ->get()
            ->keyBy(fn ($r) => strtolower($r->gateway));

        $active = [];

        // 1. WijayaPay
        $wpSvc = new WijayaPayService(null);
        if ($wpSvc->isConfigured()) {
            $record = $records->get('wijayapay');
            $cfg = $record?->config_json ?? [];
            if ($record === null || ($record->is_active ?? true)) {
                $active['wijayapay'] = [
                    'name' => 'WijayaPay',
                    'channels' => $cfg['enabled_channels'] ?? ['QRIS'],
                    'record' => $record,
                ];
            }
        }

        // 2. NODERA PAY
        $npEngine = app(NoderaPayEngineService::class);
        $npMerchant = NoderaPayMerchant::where('status', 'active')->first() ?? NoderaPayMerchant::first();
        if ($npMerchant && $npEngine->isConfigured()) {
            $record = $records->get('noderapay');
            $cfg = $record?->config_json ?? [];
            if ($record === null || ($record->is_active ?? true)) {
                $active['noderapay'] = [
                    'name' => 'NODERA PAY',
                    'channels' => $cfg['enabled_channels'] ?? ['QRIS'],
                    'record' => $record,
                    'merchant' => $npMerchant,
                ];
            }
        }

        // 3. Tripay
        $tpSvc = new TripayService(null);
        if ($tpSvc->isConfigured()) {
            $record = $records->get('tripay');
            $cfg = $record?->config_json ?? [];
            if ($record === null || ($record->is_active ?? true)) {
                $active['tripay'] = [
                    'name' => 'Tripay',
                    'channels' => $cfg['enabled_channels'] ?? ['QRIS'],
                    'record' => $record,
                ];
            }
        }

        // 4. Midtrans
        $mtSvc = new MidtransService(null);
        if ($mtSvc->isConfigured()) {
            $record = $records->get('midtrans');
            $cfg = $record?->config_json ?? [];
            if ($record === null || ($record->is_active ?? true)) {
                $active['midtrans'] = [
                    'name' => 'Midtrans',
                    'channels' => $cfg['enabled_channels'] ?? ['QRIS'],
                    'record' => $record,
                ];
            }
        }

        // 5. Duitku
        $dkSvc = app(DuitkuService::class);
        if (method_exists($dkSvc, 'isConfigured') ? $dkSvc->isConfigured() : false) {
            $record = $records->get('duitku');
            $cfg = $record?->config_json ?? [];
            if ($record === null || ($record->is_active ?? true)) {
                $active['duitku'] = [
                    'name' => 'Duitku',
                    'channels' => $cfg['enabled_channels'] ?? ['QRIS'],
                    'record' => $record,
                ];
            }
        }

        // 6. Xendit
        $xdSvc = app(XenditService::class);
        if (method_exists($xdSvc, 'isConfigured') ? $xdSvc->isConfigured() : false) {
            $record = $records->get('xendit');
            $cfg = $record?->config_json ?? [];
            if ($record === null || ($record->is_active ?? true)) {
                $active['xendit'] = [
                    'name' => 'Xendit',
                    'channels' => $cfg['enabled_channels'] ?? ['QRIS'],
                    'record' => $record,
                ];
            }
        }

        // 7. DOKU
        $dokuSvc = new DokuService(null);
        if ($dokuSvc->isConfigured()) {
            $record = $records->get('doku');
            $cfg = $record?->config_json ?? [];
            if ($record === null || ($record->is_active ?? true)) {
                $active['doku'] = [
                    'name' => 'DOKU',
                    'channels' => $cfg['enabled_channels'] ?? ['QRIS'],
                    'record' => $record,
                ];
            }
        }

        return $active;
    }

    /**
     * Resolve the primary active gateway according to Superadmin settings or auto-detection.
     */
    public static function resolveDefaultGateway(): ?string
    {
        $activeGateways = self::getActiveGateways();
        if (empty($activeGateways)) {
            return null;
        }

        // Check configured default gateway in _platform_payment_settings
        $usageRecord = PaymentGateway::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('gateway', '_platform_payment_settings')
            ->first();
        $usageCfg = $usageRecord?->config_json ?? [];
        $preferred = strtolower($usageCfg['default_gateway'] ?? '');

        if (!empty($preferred) && isset($activeGateways[$preferred])) {
            return $preferred;
        }

        // Auto-detection priority order
        $priority = ['wijayapay', 'noderapay', 'tripay', 'midtrans', 'doku', 'duitku', 'xendit'];
        foreach ($priority as $gw) {
            if (isset($activeGateways[$gw])) {
                return $gw;
            }
        }

        // Return first active gateway
        return array_key_first($activeGateways);
    }

    /**
     * Create a universal platform payment transaction (for Topup, Register, Shop, Addons).
     *
     * @param array $params [
     *   'ref_id'          => string (required),
     *   'amount'          => float|int (required),
     *   'payment_method'  => string (optional, default 'qris'),
     *   'customer_name'   => string (optional),
     *   'customer_email'  => string (optional),
     *   'customer_phone'  => string (optional),
     *   'description'     => string (optional),
     *   'callback_url'    => string (optional),
     *   'return_url'      => string (optional),
     *   'preferred_gw'    => string (optional),
     * ]
     * @return array [
     *   'success'         => bool,
     *   'gateway'         => string,
     *   'ref_id'          => string,
     *   'trx_reference'   => string,
     *   'total_amount'    => float,
     *   'dynamic_qris'    => ?string,
     *   'checkout_url'    => ?string,
     *   'qr_image'        => ?string,
     *   'expires_at'      => ?Carbon,
     *   'bank_destination'=> string,
     *   'admin_note'      => ?string,
     *   'message'         => ?string,
     *   'raw'             => array,
     * ]
     */
    public static function createPlatformTransaction(array $params): array
    {
        $refId = trim($params['ref_id'] ?? ('PLT-' . date('ymdHis') . rand(100, 999)));
        $amount = (float) ($params['amount'] ?? 0);
        $method = strtolower(trim($params['payment_method'] ?? 'qris'));
        $name = trim($params['customer_name'] ?? 'Pelanggan');
        $email = trim($params['customer_email'] ?? '');
        $phone = trim($params['customer_phone'] ?? '');
        $desc = trim($params['description'] ?? "Transaksi Platform #{$refId}");

        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Nominal transaksi tidak valid.',
            ];
        }

        $activeGateways = self::getActiveGateways();
        if (empty($activeGateways)) {
            return [
                'success' => false,
                'message' => 'Tidak ada Payment Gateway aktif di platform.',
            ];
        }

        // Determine gateway to use
        $preferred = strtolower($params['preferred_gw'] ?? '');
        $selectedGw = (!empty($preferred) && isset($activeGateways[$preferred]))
            ? $preferred
            : self::resolveDefaultGateway();

        // Order gateways to try (selected first, then others as fallback)
        $gatewaysToTry = array_unique(array_merge([$selectedGw], array_keys($activeGateways)));

        foreach ($gatewaysToTry as $gwKey) {
            if (!isset($activeGateways[$gwKey])) {
                continue;
            }

            $timeoutMinutes = (int) ($activeGateways[$gwKey]['record']->config_json['qris_timeout_minutes'] ?? (PaymentGateway::withoutGlobalScopes()->whereNull('tenant_id')->where('gateway', '_platform_payment_settings')->value('config_json')['qris_timeout_minutes'] ?? 5));
            if ($timeoutMinutes <= 0 || $timeoutMinutes > 120) {
                $timeoutMinutes = 5;
            }

            try {
                switch ($gwKey) {
                    case 'wijayapay':
                        $wpCode = self::mapToWijayapayChannel($method);
                        $svc = new WijayaPayService(null);
                        $wpRes = $svc->createTransaction([
                            'ref_id'       => $refId,
                            'nominal'      => (int) $amount,
                            'code_payment' => $wpCode,
                            'callback_url' => $params['callback_url'] ?? url('/api/webhook/wijayapay'),
                        ]);

                        if (($wpRes['success'] ?? false) || !empty($wpRes['qr_string']) || !empty($wpRes['data']['qr_string']) || !empty($wpRes['data']['checkout_url']) || !empty($wpRes['data']['pay_code'])) {
                            $qrData = $wpRes['data'] ?? $wpRes;
                            $qrString = $wpRes['qr_string'] ?? ($qrData['qr_string'] ?? null);
                            $totalBayar = (float) ($wpRes['total_bayar'] ?? ($qrData['total_bayar'] ?? $amount));
                            $trxRef = $wpRes['trx_reference'] ?? ($qrData['trx_reference'] ?? $refId);
                            $payCode = $qrData['pay_code'] ?? ($qrData['va_number'] ?? ($qrData['payment_code'] ?? null));
                            $checkoutUrl = $qrData['checkout_url'] ?? ($wpRes['checkout_url'] ?? null);

                            return [
                                'success'          => true,
                                'gateway'          => 'wijayapay',
                                'ref_id'           => $refId,
                                'trx_reference'    => $trxRef,
                                'total_amount'     => $totalBayar,
                                'payment_method'   => $method,
                                'channel_code'     => $wpCode,
                                'pay_code'         => $payCode,
                                'dynamic_qris'     => $qrString,
                                'checkout_url'     => $checkoutUrl,
                                'qr_image'         => $qrData['qr_image'] ?? null,
                                'expires_at'       => now()->addMinutes($timeoutMinutes),
                                'bank_destination' => 'WijayaPay (' . strtoupper($wpCode) . ')',
                                'admin_note'       => "Ref: {$trxRef}" . ($payCode ? " | Kode: {$payCode}" : '') . ($checkoutUrl ? " | URL: {$checkoutUrl}" : ''),
                                'instructions'     => $qrData['instructions'] ?? null,
                                'raw'              => $wpRes,
                            ];
                        }
                        break;

                    case 'noderapay':
                        $engine = app(NoderaPayEngineService::class);
                        $merchant = $activeGateways['noderapay']['merchant'] ?? null;
                        if ($merchant) {
                            $npRes = $engine->createTransaction($merchant, [
                                'ref_id'         => $refId,
                                'amount'         => $amount,
                                'payment_method' => $method,
                                'customer_name'  => $name,
                                'customer_email' => $email,
                                'customer_phone' => $phone,
                                'callback_url'   => $params['callback_url'] ?? url('/api/v1/noderapay/webhook'),
                            ]);

                            if (($npRes['status'] === 'success' || ($npRes['success'] ?? false)) && !empty($npRes['data'])) {
                                $qrData = $npRes['data'];
                                $qrString = $qrData['qr_string'] ?? null;
                                $totalBayar = (float) ($qrData['total_bayar'] ?? $amount);
                                $trxRef = $qrData['trx_reference'] ?? $refId;
                                $payCode = $qrData['pay_code'] ?? ($qrData['va_number'] ?? null);
                                $checkoutUrl = $qrData['checkout_url'] ?? null;

                                return [
                                    'success'          => true,
                                    'gateway'          => 'noderapay',
                                    'ref_id'           => $refId,
                                    'trx_reference'    => $trxRef,
                                    'total_amount'     => $totalBayar,
                                    'payment_method'   => $method,
                                    'channel_code'     => strtoupper($method),
                                    'pay_code'         => $payCode,
                                    'dynamic_qris'     => $qrString,
                                    'checkout_url'     => $checkoutUrl,
                                    'qr_image'         => $qrData['qr_image'] ?? null,
                                    'expires_at'       => now()->addMinutes($timeoutMinutes),
                                    'bank_destination' => 'NODERA PAY (' . strtoupper($method) . ')',
                                    'admin_note'       => "Ref: {$trxRef}" . ($payCode ? " | Kode: {$payCode}" : '') . ($checkoutUrl ? " | URL: {$checkoutUrl}" : ''),
                                    'instructions'     => $qrData['instructions'] ?? null,
                                    'raw'              => $npRes,
                                ];
                            }
                        }
                        break;

                    case 'tripay':
                        $tpCode = self::mapToTripayChannel($method);
                        $svc = new TripayService(null);
                        $tpRes = $svc->createTransaction([
                            'method'         => $tpCode,
                            'merchant_ref'   => $refId,
                            'amount'         => (int) $amount,
                            'customer_name'  => $name,
                            'customer_email' => $email,
                            'customer_phone' => $phone,
                            'order_items'    => [
                                [
                                    'sku'      => $refId,
                                    'name'     => $desc,
                                    'price'    => (int) $amount,
                                    'quantity' => 1,
                                ],
                            ],
                            'return_url'     => $params['return_url'] ?? url('/'),
                        ]);

                        if (!empty($tpRes['data']['qr_string']) || !empty($tpRes['data']['checkout_url']) || !empty($tpRes['data']['pay_code'])) {
                            $tpData = $tpRes['data'];
                            $qrString = $tpData['qr_string'] ?? null;
                            $trxRef = $tpData['reference'] ?? $refId;
                            $payCode = $tpData['pay_code'] ?? null;
                            $checkoutUrl = $tpData['checkout_url'] ?? ($tpData['pay_url'] ?? null);

                            return [
                                'success'          => true,
                                'gateway'          => 'tripay',
                                'ref_id'           => $refId,
                                'trx_reference'    => $trxRef,
                                'total_amount'     => (float) ($tpData['amount'] ?? $amount),
                                'payment_method'   => $method,
                                'channel_code'     => $tpCode,
                                'pay_code'         => $payCode,
                                'dynamic_qris'     => $qrString,
                                'checkout_url'     => $checkoutUrl,
                                'qr_image'         => $tpData['qr_url'] ?? null,
                                'expires_at'       => !empty($tpData['expired_time']) ? Carbon::createFromTimestamp($tpData['expired_time']) : now()->addMinutes($timeoutMinutes),
                                'bank_destination' => 'Tripay (' . strtoupper($tpCode) . ')',
                                'admin_note'       => "Ref: {$trxRef}" . ($payCode ? " | Kode: {$payCode}" : '') . ($checkoutUrl ? " | URL: {$checkoutUrl}" : ''),
                                'instructions'     => $tpData['instructions'] ?? null,
                                'raw'              => $tpRes,
                            ];
                        }
                        break;

                    case 'midtrans':
                        $svc = new MidtransService(null);
                        $snapRes = $svc->createSnapTransaction([
                            'transaction_details' => [
                                'order_id'     => $refId,
                                'gross_amount' => (int) $amount,
                            ],
                            'customer_details' => [
                                'first_name' => $name,
                                'email'      => $email,
                                'phone'      => $phone,
                            ],
                        ]);

                        if (!empty($snapRes['token']) || !empty($snapRes['redirect_url'])) {
                            return [
                                'success'          => true,
                                'gateway'          => 'midtrans',
                                'ref_id'           => $refId,
                                'trx_reference'    => $snapRes['token'] ?? $refId,
                                'total_amount'     => $amount,
                                'payment_method'   => $method,
                                'channel_code'     => 'SNAP',
                                'pay_code'         => null,
                                'dynamic_qris'     => $snapRes['redirect_url'] ?? null,
                                'checkout_url'     => $snapRes['redirect_url'] ?? null,
                                'snap_token'       => $snapRes['token'] ?? null,
                                'snap_js_url'      => $snapRes['snap_js_url'] ?? ($svc->isProduction() ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js'),
                                'client_key'       => $snapRes['client_key'] ?? $svc->getClientKey(),
                                'qr_image'         => null,
                                'expires_at'       => now()->addMinutes(15),
                                'bank_destination' => 'Midtrans (Multi-Channel Snap)',
                                'admin_note'       => 'Snap Token: ' . ($snapRes['token'] ?? '') . ' | URL: ' . ($snapRes['redirect_url'] ?? ''),
                                'raw'              => $snapRes,
                            ];
                        }
                        break;

                    case 'doku':
                        $svc = new DokuService(null);
                        $dokuRes = $svc->createPayment([
                            'order_id'       => $refId,
                            'amount'         => (int) $amount,
                            'customer_name'  => $name,
                            'customer_email' => $email,
                            'customer_phone' => $phone,
                            'item_name'      => $desc,
                            'callback_url'   => $params['callback_url'] ?? url('/superadmin/billing'),
                        ]);

                        if (!empty($dokuRes['checkout_url'])) {
                            return [
                                'success'          => true,
                                'gateway'          => 'doku',
                                'ref_id'           => $refId,
                                'trx_reference'    => $dokuRes['reference'] ?? $refId,
                                'total_amount'     => $amount,
                                'payment_method'   => $method,
                                'channel_code'     => 'CHECKOUT',
                                'pay_code'         => null,
                                'dynamic_qris'     => $dokuRes['checkout_url'],
                                'checkout_url'     => $dokuRes['checkout_url'],
                                'doku_js_url'      => $dokuRes['js_url'] ?? '',
                                'token_id'         => $dokuRes['token_id'] ?? '',
                                'qr_image'         => null,
                                'expires_at'       => now()->addMinutes(60),
                                'bank_destination' => 'DOKU Checkout',
                                'admin_note'       => 'DOKU Token: ' . ($dokuRes['token_id'] ?? '') . ' | URL: ' . ($dokuRes['checkout_url'] ?? ''),
                                'raw'              => $dokuRes,
                            ];
                        }
                        break;

                    case 'duitku':
                        $svc = app(DuitkuService::class);
                        $dkRes = $svc->createTransaction([
                            'ref_id'         => $refId,
                            'amount'         => (int) $amount,
                            'customer_name'  => $name,
                            'customer_email' => $email,
                            'customer_phone' => $phone,
                            'item_name'      => $desc,
                            'method'         => $method,
                        ]);

                        if (!empty($dkRes['link'])) {
                            return [
                                'success'          => true,
                                'gateway'          => 'duitku',
                                'ref_id'           => $refId,
                                'trx_reference'    => $dkRes['reference'] ?? $refId,
                                'total_amount'     => $amount,
                                'payment_method'   => $method,
                                'channel_code'     => strtoupper($method),
                                'pay_code'         => null,
                                'dynamic_qris'     => $dkRes['link'],
                                'checkout_url'     => $dkRes['link'],
                                'qr_image'         => null,
                                'expires_at'       => now()->addMinutes(30),
                                'bank_destination' => 'Duitku (Online Checkout)',
                                'admin_note'       => 'Ref: ' . ($dkRes['reference'] ?? ''),
                                'raw'              => $dkRes,
                            ];
                        }
                        break;

                    case 'xendit':
                        $svc = app(XenditService::class);
                        $xdRes = $svc->createInvoice([
                            'external_id'      => $refId,
                            'amount'           => (int) $amount,
                            'payer_email'      => $email,
                            'description'      => $desc,
                            'invoice_duration' => 1800,
                        ]);

                        if (!empty($xdRes['link'])) {
                            return [
                                'success'          => true,
                                'gateway'          => 'xendit',
                                'ref_id'           => $refId,
                                'trx_reference'    => $xdRes['reference'] ?? $refId,
                                'total_amount'     => $amount,
                                'payment_method'   => $method,
                                'channel_code'     => 'INVOICE',
                                'pay_code'         => null,
                                'dynamic_qris'     => $xdRes['link'],
                                'checkout_url'     => $xdRes['link'],
                                'qr_image'         => null,
                                'expires_at'       => now()->addMinutes(30),
                                'bank_destination' => 'Xendit Invoice',
                                'admin_note'       => 'Ref: ' . ($xdRes['reference'] ?? ''),
                                'raw'              => $xdRes,
                            ];
                        }
                        break;
                }
            } catch (\Throwable $e) {
                Log::warning("[PlatformPaymentService] Gateway {$gwKey} error on {$refId}: " . $e->getMessage());
            }
        }

        return [
            'success' => false,
            'message' => 'Gagal memproses transaksi melalui Payment Gateway aktif.',
        ];
    }

    /**
     * Map platform payment method to WijayaPay channel code.
     */
    public static function mapToWijayapayChannel(string $method): string
    {
        $m = strtolower(trim($method));
        return match ($m) {
            'bca', 'bca_va', 'bcava' => 'BCAVA',
            'bni', 'bni_va', 'bniva' => 'BNIVA',
            'bri', 'bri_va', 'briva' => 'BRIVA',
            'mandiri', 'mandiri_va', 'mandiriva' => 'MANDIRIVA',
            'permata', 'permata_va', 'permatava' => 'PERMATAVA',
            'cimb', 'cimb_va', 'cimbva' => 'CIMBVA',
            'bsi', 'bsi_va', 'bsiva' => 'BSIVA',
            'danamon', 'danamon_va', 'danamonva' => 'DANAMONVA',
            'alfamart', 'alfa' => 'ALFAMART',
            'indomaret', 'indo' => 'INDOMARET',
            'alfamidi' => 'ALFAMIDI',
            'ovo' => 'OVO',
            'dana' => 'DANA',
            'shopeepay' => 'SHOPEEPAY',
            default => 'QRIS',
        };
    }

    /**
     * Map platform payment method to Tripay channel code.
     */
    public static function mapToTripayChannel(string $method): string
    {
        $m = strtolower(trim($method));
        return match ($m) {
            'bca', 'bca_va', 'bcava' => 'BCAVA',
            'bni', 'bni_va', 'bniva' => 'BNIVA',
            'bri', 'bri_va', 'briva' => 'BRIVA',
            'mandiri', 'mandiri_va', 'mandiriva' => 'MANDIRIVA',
            'permata', 'permata_va', 'permatava' => 'PERMATAVA',
            'cimb', 'cimb_va', 'cimbva' => 'CIMBVA',
            'bsi', 'bsi_va', 'bsiva' => 'BSIVA',
            'danamon', 'danamon_va', 'danamonva' => 'DANAMONVA',
            'alfamart', 'alfa' => 'ALFAMART',
            'indomaret', 'indo' => 'INDOMARET',
            'alfamidi' => 'ALFAMIDI',
            'ovo' => 'OVO',
            'dana' => 'DANA',
            'shopeepay' => 'SHOPEEPAY',
            'qris2' => 'QRIS2',
            default => 'QRIS',
        };
    }
}
