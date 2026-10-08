<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    private ?string $apiUrl;
    private ?string $token;
    private ?string $provider = 'fonnte';
    private ?string $senderPhone = null;
    private ?string $lastError = null;
    private bool $enabled = false;
    private ?Client $httpClient = null;
    private ?int $tenantId = null;

    /** Broadcast rate limit: microseconds between sends (1 second = 1_000_000). */
    private const BROADCAST_INTERVAL_US = 1_000_000;

    public function __construct(?int $tenantId = null, bool $forceGlobal = false)
    {
        $this->tenantId = $forceGlobal ? null : ($tenantId ?? \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id'));
        $defaultUrl = 'https://api.fonnte.com/send';

        $apiUrl = null;
        $token = null;
        $provider = null;
        $senderPhone = null;

        try {
            if ($forceGlobal) {
                // ==========================================
                // 🛡️ SUPERADMIN / PLATFORM WHATSAPP GATEWAY ONLY
                // Explicitly requested via WhatsappService::forSuperadmin()
                // Used for SaaS OTP, Registrasi Tenant, Deposit Saldo, etc.
                // ==========================================
                $apiUrl = Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_API_URL')->whereNull('tenant_id')->value('value');
                $token = Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_TOKEN')->whereNull('tenant_id')->value('value');
                $provider = Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_PROVIDER')->whereNull('tenant_id')->value('value');
                $senderPhone = Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_SENDER_PHONE')->whereNull('tenant_id')->value('value');

                // Fallback via Setting::apiValue helper
                if (empty($token)) {
                    $token = Setting::apiValue('WHATSAPP_TOKEN', '');
                }
                if (empty($apiUrl)) {
                    $apiUrl = Setting::apiValue('WHATSAPP_API_URL', '');
                }
                if (empty($provider)) {
                    $provider = Setting::apiValue('WHATSAPP_PROVIDER', '');
                }
                if (empty($senderPhone)) {
                    $senderPhone = Setting::apiValue('WHATSAPP_SENDER_PHONE', '');
                }

                // If not in Setting, load from configured provider in PaymentGateway table
                if (!empty($provider) && (empty($token) || empty($apiUrl))) {
                    $savedRecord = \App\Models\PaymentGateway::withoutGlobalScopes()
                        ->whereNull('tenant_id')
                        ->where('gateway', '_wa_provider_' . $provider)
                        ->first();
                    $savedCfg = $savedRecord?->config_json ?? [];
                    if (empty($token)) {
                        $token = $savedCfg['api_token'] ?? ($savedCfg['api_key'] ?? ($savedCfg['device_id'] ?? ''));
                    }
                    if (empty($apiUrl) && !empty($savedCfg['api_url'])) {
                        $apiUrl = $savedCfg['api_url'];
                    }
                    if (empty($senderPhone) && !empty($savedCfg['session_id'])) {
                        $senderPhone = $savedCfg['session_id'];
                    }
                }

                // Fallback ke Environment Variables (.env)
                if (empty($token)) {
                    $token = env('WHATSAPP_TOKEN') ?: env('FONNTE_TOKEN') ?: env('WA_TOKEN') ?: config('services.whatsapp.token');
                }
                if (empty($apiUrl)) {
                    $apiUrl = env('WHATSAPP_API_URL') ?: env('FONNTE_API_URL') ?: config('services.whatsapp.url');
                }
                if (empty($provider)) {
                    $provider = env('WHATSAPP_PROVIDER', 'nodera-gateway');
                }
            } elseif ($this->tenantId !== null) {
                // ==========================================
                // 🔒 STRICT TENANT-ISOLATED WHATSAPP GATEWAY
                // Tenant MUST use their own configured token.
                // NEVER fallback to SuperAdmin / .env token!
                // ==========================================
                $apiUrl = Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_API_URL')->where('tenant_id', $this->tenantId)->value('value');
                $token = Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_TOKEN')->where('tenant_id', $this->tenantId)->value('value');
                $provider = Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_PROVIDER')->where('tenant_id', $this->tenantId)->value('value');
                $senderPhone = Setting::withoutGlobalScopes()->where('key', 'WHATSAPP_SENDER_PHONE')->where('tenant_id', $this->tenantId)->value('value');

                if (empty($apiUrl)) {
                    $apiUrl = $defaultUrl;
                }
                if (empty($provider)) {
                    $provider = 'fonnte';
                }
            } else {
                $token = null;
                $this->lastError = 'Tenant WhatsApp Gateway tidak dikonfigurasi.';
            }
        } catch (\Throwable $e) {
            // Fallback gracefully if database or setting table is not available
        }

        $this->apiUrl = !empty($apiUrl) ? trim($apiUrl) : $defaultUrl;
        $this->token = !empty($token) ? trim($token) : null;
        $this->provider = !empty($provider) ? strtolower(trim($provider)) : 'fonnte';
        $this->senderPhone = $senderPhone;

        // Auto-detect and prioritize active local NODERA WhatsApp Gateway device or master platform
        try {
            $masterApiKey = config('services.wa_gateway.api_key', env('WA_GATEWAY_API_KEY', 'nodera_wa_secret_key_2026'));
            $masterPortUrl = config('services.wa_gateway.url', env('WA_GATEWAY_URL', 'http://127.0.0.1:3022'));
            $deviceQuery = \App\Models\WhatsappDevice::withoutGlobalScopes();
            if ($this->tenantId !== null) {
                $deviceQuery->where('tenant_id', $this->tenantId);
            } else {
                $deviceQuery->whereNull('tenant_id');
            }
            $activeDevice = $deviceQuery->orderBy('is_default', 'desc')->first();

            if ($activeDevice && ($this->provider === 'nodera-gateway' || $this->provider === 'local' || empty($this->token))) {
                $this->apiUrl = rtrim($masterPortUrl, '/') . '/api/send';
                $this->token = $activeDevice->api_key ?: $masterApiKey;
                $this->provider = 'nodera-gateway';
                $this->senderPhone = $activeDevice->session_id ?: 'wa_master_platform';
            } elseif ($forceGlobal && (empty($this->token) || $this->provider === 'nodera-gateway' || $this->provider === 'local' || $this->provider === 'nodera')) {
                // Default fallback for SuperAdmin to local WhatsApp Gateway microservice
                $this->apiUrl = rtrim($masterPortUrl, '/') . '/api/send';
                $this->token = $masterApiKey;
                $this->provider = 'nodera-gateway';
                $this->senderPhone = 'wa_master_platform';
            }
        } catch (\Throwable $e) {}

        if (!empty($this->token)) {
            $this->enabled = true;
            $headers = [
                'Authorization' => $this->token,
                'X-Api-Key'     => $this->token,
                'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                'Accept'        => 'application/json, text/plain, */*',
            ];

            $this->httpClient = new Client([
                'timeout'         => 15,
                'connect_timeout' => 5,
                'verify'          => false,
                'headers'         => $headers,
            ]);
        } else {
            $this->lastError = 'Token WhatsApp belum diisi di Pengaturan WhatsApp.';
        }
    }

    /**
     * Create an instance explicitly targeting global / SuperAdmin WhatsApp settings.
     */
    public static function forSuperadmin(): self
    {
        return new self(null, true);
    }

    /**
     * Check if the WhatsApp service is configured and enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->token);
    }

    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->token);
    }

    /**
     * Ambil pesan error terakhir jika pengiriman gagal.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Format phone number: convert 08xxx to 628xxx (Indonesia format) or support international (+225, etc.).
     */
    public function formatPhone(string $phone): string
    {
        // Clean all non-digit characters (+, spaces, dashes, etc.)
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return '';
        }

        if (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        // Indonesia (+62) format
        if (str_starts_with($clean, '08')) {
            return '62' . substr($clean, 1);
        }

        if (str_starts_with($clean, '8') && strlen($clean) >= 9 && strlen($clean) <= 13) {
            return '62' . $clean;
        }

        // Côte d'Ivoire (+225) format if 10-digit starting with 01, 05, 07, etc.
        if (preg_match('/^(0[157]|2[157])\d{8}$/', $clean)) {
            return '225' . $clean;
        }

        return $clean;
    }

    /**
     * Send a generic text message.
     */
    public function sendMessage(string $phone, ?string $message): bool
    {
        $this->lastError = null;

        if (empty(trim((string) $message))) {
            $this->lastError = 'Pengiriman notifikasi WhatsApp dilewati karena pesan kosong atau template dinonaktifkan.';
            return false;
        }

        if (!$this->enabled || empty($this->token)) {
            $this->lastError = 'WhatsApp Gateway belum aktif / Token API belum diisi di Pengaturan.';
            Log::warning('[WhatsappService] sendMessage failed: Gateway not enabled or token empty.');
            return false;
        }

        if (empty(trim($phone))) {
            $this->lastError = 'Nomor WhatsApp tujuan kosong.';
            return false;
        }

        $formattedPhone = $this->formatPhone($phone);
        if (strlen($formattedPhone) < 8) {
            $this->lastError = 'Nomor WhatsApp tidak valid: ' . $phone;
            return false;
        }

        $data = [
            'phone'    => $formattedPhone,
            'message'  => $message,
            'token'    => $this->token,
            'typing'   => true,
            'presence' => 'composing',
            'delay'    => 2,
        ];

        return $this->send($data);
    }

    /**
     * Format period string to human readable format (e.g. "Agustus 2026").
     * Supports single period ('2026-08'), arrays/JSON periods_breakdown, or fallback to due_date / now.
     */
    public static function formatPeriodString(?string $rawPeriod, ?string $dueDate = null, mixed $breakdown = null): string
    {
        // 1. If breakdown is provided and non-empty, use all periods
        if (!empty($breakdown)) {
            $periods = is_string($breakdown) ? json_decode($breakdown, true) : $breakdown;
            if (is_array($periods) && count($periods) > 0) {
                $formattedList = [];
                foreach ($periods as $p) {
                    $itemPeriod = is_array($p) ? ($p['period'] ?? $p['label'] ?? null) : $p;
                    if (!empty($itemPeriod)) {
                        $f = self::formatSinglePeriod((string) $itemPeriod);
                        if (!empty($f) && !in_array($f, $formattedList)) {
                            $formattedList[] = $f;
                        }
                    }
                }
                if (!empty($formattedList)) {
                    return implode(', ', $formattedList);
                }
            }
        }

        // 2. If single rawPeriod is provided
        if (!empty($rawPeriod)) {
            $formatted = self::formatSinglePeriod($rawPeriod);
            if (!empty($formatted)) {
                return $formatted;
            }
        }

        // 3. Fallback to due_date if valid
        if (!empty($dueDate)) {
            try {
                $c = \Carbon\Carbon::parse($dueDate)->setTimezone(config('app.timezone', 'Asia/Jakarta'));
                return self::formatSinglePeriod($c->format('Y-m'));
            } catch (\Throwable $e) {}
        }

        // 4. Default to current month & year
        return self::formatSinglePeriod(now()->format('Y-m'));
    }

    private static function formatSinglePeriod(string $p): string
    {
        $p = trim($p);
        if (empty($p)) return '';

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        // Format: YYYY-MM or YYYY-M
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $p, $m)) {
            $year = $m[1];
            $month = (int) $m[2];
            return ($months[$month] ?? $month) . ' ' . $year;
        }

        // Format: MM-YYYY or M-YYYY
        if (preg_match('/^(\d{1,2})[-|\/](\d{4})$/', $p, $m)) {
            $month = (int) $m[1];
            $year = $m[2];
            return ($months[$month] ?? $month) . ' ' . $year;
        }

        // If already Indonesian month string (e.g. "Agustus 2026")
        foreach ($months as $num => $name) {
            if (stripos($p, $name) !== false) {
                return $p;
            }
        }

        // Fallback with Carbon
        try {
            $c = \Carbon\Carbon::parse($p);
            return ($months[$c->month] ?? $c->format('F')) . ' ' . $c->year;
        } catch (\Throwable $e) {
            return $p;
        }
    }

    /**
     * Check if a WhatsApp template is active for this tenant context.
     */
    public function isTemplateActive(string $nameOrKey): bool
    {
        try {
            $searchKey = strtolower(trim($nameOrKey));
            $aliases = [$searchKey];

            if (in_array($searchKey, ['bukti pembayaran lunas', 'pembayaran lunas', 'pembayaran diterima', 'bukti pembayaran', 'payment receipt', 'kwitansi', 'struk pembayaran'], true)) {
                $aliases = ['bukti pembayaran lunas', 'pembayaran lunas', 'pembayaran diterima', 'bukti pembayaran', 'payment receipt', 'kwitansi', 'struk pembayaran'];
            } elseif (in_array($searchKey, ['tagihan baru', 'invoice baru', 'new invoice', 'pemberitahuan tagihan', 'tagihan'], true)) {
                $aliases = ['tagihan baru', 'invoice baru', 'new invoice', 'pemberitahuan tagihan', 'tagihan'];
            } elseif (in_array($searchKey, ['pengingat tagihan', 'reminder tagihan', 'invoice reminder', 'jatuh tempo', 'pengingat'], true)) {
                $aliases = ['pengingat tagihan', 'reminder tagihan', 'invoice reminder', 'jatuh tempo', 'pengingat'];
            } elseif (in_array($searchKey, ['pemberitahuan isolir', 'isolir', 'isolir layanan', 'suspension', 'peringatan isolir'], true)) {
                $aliases = ['pemberitahuan isolir', 'isolir', 'isolir layanan', 'suspension', 'peringatan isolir'];
            } elseif (in_array($searchKey, ['pendaftaran baru', 'nouvelle inscription', 'registrasi baru', 'new registration', 'pendaftaran pending'], true)) {
                $aliases = ['pendaftaran baru', 'nouvelle inscription', 'registrasi baru', 'new registration', 'pendaftaran pending'];
            } elseif (in_array($searchKey, ['pendaftaran disetujui', 'inscription approuvée', 'registrasi disetujui', 'registration approved', 'akun aktif'], true)) {
                $aliases = ['pendaftaran disetujui', 'inscription approuvée', 'registrasi disetujui', 'registration approved', 'akun aktif'];
            } elseif (in_array($searchKey, ['member baru', 'nouveau membre', 'welcome member', 'panel welcome', 'registrasi member'], true)) {
                $aliases = ['member baru', 'nouveau membre', 'welcome member', 'panel welcome', 'registrasi member'];
            } elseif (in_array($searchKey, ['topup pending', 'recharge en attente', 'topup baru', 'deposit pending'], true)) {
                $aliases = ['topup pending', 'recharge en attente', 'topup baru', 'deposit pending'];
            } elseif (in_array($searchKey, ['topup berhasil', 'recharge validée', 'topup sukses', 'topup diverifikasi', 'deposit sukses'], true)) {
                $aliases = ['topup berhasil', 'recharge validée', 'topup sukses', 'topup diverifikasi', 'deposit sukses'];
            } elseif (in_array($searchKey, ['topup ditolak', 'recharge refusée', 'deposit ditolak'], true)) {
                $aliases = ['topup ditolak', 'recharge refusée', 'deposit ditolak'];
            } elseif (in_array($searchKey, ['topup kedaluwarsa', 'topup expired', 'recharge expirée', 'deposit expired', 'deposit kedaluwarsa'], true)) {
                $aliases = ['topup kedaluwarsa', 'topup expired', 'recharge expirée', 'deposit expired', 'deposit kedaluwarsa'];
            }

            $q = DB::table('whatsapp_templates')
                ->where(function ($w) use ($nameOrKey, $aliases) {
                    $w->where('name', $nameOrKey)
                      ->orWhereIn(DB::raw('LOWER(name)'), $aliases);
                });

            if (!empty($this->tenantId)) {
                $q->where(function ($sub) {
                    $sub->where('tenant_id', $this->tenantId)
                        ->orWhereNull('tenant_id');
                })->orderByRaw('tenant_id IS NULL ASC');
            } else {
                $q->whereNull('tenant_id');
            }

            $row = $q->first();
            if ($row && isset($row->is_active)) {
                return (bool) $row->is_active;
            }
            return true;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Render template: Uses Superadmin or Tenant template from DB if configured, or default system template.
     * All dynamic variables are safely substituted.
     * Returns null if the template is explicitly disabled.
     */
    private function renderTemplate(string $nameOrKey, string $defaultTemplate, array $replacements): ?string
    {
        $custom = null;
        try {
            $searchKey = strtolower(trim($nameOrKey));
            $aliases = [$searchKey];

            if (in_array($searchKey, ['bukti pembayaran lunas', 'pembayaran lunas', 'pembayaran diterima', 'bukti pembayaran', 'payment receipt', 'kwitansi', 'struk pembayaran'], true)) {
                $aliases = ['bukti pembayaran lunas', 'pembayaran lunas', 'pembayaran diterima', 'bukti pembayaran', 'payment receipt', 'kwitansi', 'struk pembayaran'];
            } elseif (in_array($searchKey, ['tagihan baru', 'invoice baru', 'new invoice', 'pemberitahuan tagihan', 'tagihan'], true)) {
                $aliases = ['tagihan baru', 'invoice baru', 'new invoice', 'pemberitahuan tagihan', 'tagihan'];
            } elseif (in_array($searchKey, ['pengingat tagihan', 'reminder tagihan', 'invoice reminder', 'jatuh tempo', 'pengingat'], true)) {
                $aliases = ['pengingat tagihan', 'reminder tagihan', 'invoice reminder', 'jatuh tempo', 'pengingat'];
            } elseif (in_array($searchKey, ['pemberitahuan isolir', 'isolir', 'isolir layanan', 'suspension', 'peringatan isolir'], true)) {
                $aliases = ['pemberitahuan isolir', 'isolir', 'isolir layanan', 'suspension', 'peringatan isolir'];
            } elseif (in_array($searchKey, ['pendaftaran baru', 'nouvelle inscription', 'registrasi baru', 'new registration', 'pendaftaran pending'], true)) {
                $aliases = ['pendaftaran baru', 'nouvelle inscription', 'registrasi baru', 'new registration', 'pendaftaran pending'];
            } elseif (in_array($searchKey, ['pendaftaran disetujui', 'inscription approuvée', 'registrasi disetujui', 'registration approved', 'akun aktif'], true)) {
                $aliases = ['pendaftaran disetujui', 'inscription approuvée', 'registrasi disetujui', 'registration approved', 'akun aktif'];
            } elseif (in_array($searchKey, ['pendaftaran ditolak', 'inscription refusée', 'registrasi ditolak', 'registration rejected'], true)) {
                $aliases = ['pendaftaran ditolak', 'inscription refusée', 'registrasi ditolak', 'registration rejected'];
            } elseif (in_array($searchKey, ['pendaftaran kedaluwarsa', 'pendaftaran expired', 'inscription expirée', 'registrasi expired', 'registration expired'], true)) {
                $aliases = ['pendaftaran kedaluwarsa', 'pendaftaran expired', 'inscription expirée', 'registrasi expired', 'registration expired'];
            } elseif (in_array($searchKey, ['member baru', 'nouveau membre', 'welcome member', 'panel welcome', 'registrasi member'], true)) {
                $aliases = ['member baru', 'nouveau membre', 'welcome member', 'panel welcome', 'registrasi member'];
            } elseif (in_array($searchKey, ['topup pending', 'recharge en attente', 'topup baru', 'deposit pending'], true)) {
                $aliases = ['topup pending', 'recharge en attente', 'topup baru', 'deposit pending'];
            } elseif (in_array($searchKey, ['topup berhasil', 'recharge validée', 'topup sukses', 'topup diverifikasi', 'deposit sukses'], true)) {
                $aliases = ['topup berhasil', 'recharge validée', 'topup sukses', 'topup diverifikasi', 'deposit sukses'];
            } elseif (in_array($searchKey, ['topup ditolak', 'recharge refusée', 'deposit ditolak'], true)) {
                $aliases = ['topup ditolak', 'recharge refusée', 'deposit ditolak'];
            } elseif (in_array($searchKey, ['topup kedaluwarsa', 'topup expired', 'recharge expirée', 'deposit expired', 'deposit kedaluwarsa'], true)) {
                $aliases = ['topup kedaluwarsa', 'topup expired', 'recharge expirée', 'deposit expired', 'deposit kedaluwarsa'];
            }

            $q = DB::table('whatsapp_templates')
                ->where(function ($w) use ($nameOrKey, $aliases) {
                    $w->where('name', $nameOrKey)
                      ->orWhereIn(DB::raw('LOWER(name)'), $aliases);
                });

            if (!empty($this->tenantId)) {
                $q->where(function ($sub) {
                    $sub->where('tenant_id', $this->tenantId)
                        ->orWhereNull('tenant_id');
                })->orderByRaw('tenant_id IS NULL ASC');
            } else {
                $q->whereNull('tenant_id');
            }

            $row = $q->first();
            if ($row) {
                if (isset($row->is_active) && !$row->is_active) {
                    // Template disabled for this tenant scope
                    return null;
                }
                if (!empty($row->message)) {
                    $custom = $row->message;
                }
            }
        } catch (\Throwable $e) {
            // fallback gracefully
        }

        $text = !empty($custom) ? $custom : $defaultTemplate;

        foreach ($replacements as $var => $val) {
            $strVal = (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) ? (string) $val : '';
            $text = str_replace('{' . $var . '}', $strVal, $text);
            $text = str_ireplace('{' . $var . '}', $strVal, $text);
        }

        return $text;
    }

    /**
     * Ambil nama perusahaan yang aman dengan fallback.
     */
    private function getCompanyName(): string
    {
        if (class_exists(\App\Models\Setting::class) && method_exists(\App\Models\Setting::class, 'getCompanyName')) {
            try {
                return \App\Models\Setting::getCompanyName($this->tenantId);
            } catch (\Throwable $e) {
                // fallback below
            }
        }
        return config('app.name', 'NODERA Billing');
    }

    /**
     * Send a new invoice notification.
     */
    public function sendInvoice(array $customer, array $invoice): bool
    {
        if (empty($customer['phone'])) {
            $this->lastError = 'Nomor WhatsApp pelanggan kosong.';
            return false;
        }

        $period = self::formatPeriodString($invoice['period'] ?? null, $invoice['due_date'] ?? null, $invoice['periods_breakdown'] ?? null);
        $amount = number_format((float) ($invoice['amount'] ?? 0), 0, ',', '.');
        $dueDate = isset($invoice['due_date']) ? \Carbon\Carbon::parse($invoice['due_date'])->setTimezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d F Y') : '-';
        $invNumber = $invoice['invoice_number'] ?? ('INV-' . str_pad((string) ($invoice['id'] ?? 0), 6, '0', STR_PAD_LEFT));
        $receiptUrl = url('/receipt/' . ($invoice['invoice_number'] ?? ($invoice['id'] ?? 0)));
        $loginUrl = url('/portal/login');
        $companyName = $this->getCompanyName();
        $packageName = $invoice['package_name'] ?? ($customer['package']['name'] ?? 'Internet');
        $routerName = $customer['router_name'] ?? ($customer['router']['name'] ?? 'NOC');

        $defaultMsg = "*TAGIHAN INTERNET BARU*\n"
            . "{perusahaan}\n\n"
            . "Yth. {nama},\n"
            . "Tagihan internet Anda untuk periode *{periode}* telah terbit dengan rincian:\n\n"
            . "--------------------------------\n"
            . "No. Invoice : {invoice}\n"
            . "Paket       : {paket}\n"
            . "Server/NOC  : {router}\n"
            . "Total Bayar : *Rp {jumlah}*\n"
            . "Jatuh Tempo : {jatuh_tempo}\n"
            . "--------------------------------\n\n"
            . "Cek Tagihan & Struk Digital:\n"
            . "{link_struk}\n\n"
            . "Login Portal Pelanggan:\n"
            . "{login_url}\n\n"
            . "Mohon lakukan pembayaran sebelum tanggal jatuh tempo untuk menghindari isolir otomatis.\n"
            . "Terima kasih telah berlangganan bersama {perusahaan}.";

        $msg = $this->renderTemplate('Tagihan Baru', $defaultMsg, [
            'nama' => $customer['name'] ?? 'Pelanggan',
            'customer_name' => $customer['name'] ?? 'Pelanggan',
            'periode' => $period,
            'period' => $period,
            'invoice' => $invNumber,
            'no_invoice' => $invNumber,
            'nomor_invoice' => $invNumber,
            'paket' => $packageName,
            'layanan' => $packageName,
            'status' => 'BELUM BAYAR',
            'jumlah' => $amount,
            'tagihan' => $amount,
            'total' => $amount,
            'jatuh_tempo' => $dueDate,
            'due_date' => $dueDate,
            'link_struk' => $receiptUrl,
            'link_bukti' => $receiptUrl,
            'lihat_bukti' => $receiptUrl,
            'link' => $receiptUrl,
            'receipt_url' => $receiptUrl,
            'bukti_pembayaran' => $receiptUrl,
            'login_url' => $loginUrl,
            'portal_url' => $loginUrl,
            'router' => $routerName,
            'server' => $routerName,
            'perusahaan' => $companyName,
            'tenant_name' => $companyName,
        ]);

        return $this->sendMessage($customer['phone'], $msg);
    }

    /**
     * Send a payment success notification.
     */
    public function sendPaymentSuccess(array $customer, array $invoice): bool
    {
        if (empty($customer['phone'])) {
            $this->lastError = 'Nomor WhatsApp pelanggan kosong.';
            return false;
        }

        $period = self::formatPeriodString($invoice['period'] ?? null, $invoice['due_date'] ?? null, $invoice['periods_breakdown'] ?? null);
        $amount = number_format((float) ($invoice['amount'] ?? 0), 0, ',', '.');
        $date = now()->format('d M Y H:i');
        $invNumber = $invoice['invoice_number'] ?? ('INV-' . str_pad((string) ($invoice['id'] ?? 0), 6, '0', STR_PAD_LEFT));
        $receiptUrl = url('/receipt/' . ($invoice['invoice_number'] ?? ($invoice['id'] ?? 0)));
        $loginUrl = url('/portal/login');
        $companyName = $this->getCompanyName();
        $packageName = $invoice['package_name'] ?? ($customer['package']['name'] ?? 'Internet');
        $routerName = $customer['router_name'] ?? ($customer['router']['name'] ?? 'NOC');

        $defaultMsg = "*BUKTI PEMBAYARAN LUNAS*\n"
            . "{perusahaan}\n\n"
            . "Yth. {nama},\n"
            . "Terima kasih, pembayaran tagihan internet Anda telah kami terima dan diverifikasi.\n\n"
            . "--------------------------------\n"
            . "No. Invoice : {invoice}\n"
            . "Status      : *LUNAS*\n"
            . "Paket       : {paket}\n"
            . "Jumlah      : Rp {jumlah}\n"
            . "Waktu Bayar : {waktu}\n"
            . "--------------------------------\n\n"
            . "Lihat & Unduh Struk Resmi:\n"
            . "{link_struk}\n\n"
            . "Akses Portal Pelanggan:\n"
            . "{login_url}\n\n"
            . "Layanan internet Anda ({paket}) pada router {router} aktif tanpa kendala. Terima kasih atas kepercayaan Anda bersama {perusahaan}.";

        $msg = $this->renderTemplate('Bukti Pembayaran Lunas', $defaultMsg, [
            'nama' => $customer['name'] ?? 'Pelanggan',
            'customer_name' => $customer['name'] ?? 'Pelanggan',
            'name' => $customer['name'] ?? 'Pelanggan',
            'pelanggan' => $customer['name'] ?? 'Pelanggan',
            'periode' => $period,
            'period' => $period,
            'bulan' => $period,
            'invoice' => $invNumber,
            'no_invoice' => $invNumber,
            'nomor_invoice' => $invNumber,
            'invoice_number' => $invNumber,
            'status' => 'LUNAS',
            'status_bayar' => 'LUNAS',
            'status_tagihan' => 'LUNAS',
            'paket' => $packageName,
            'layanan' => $packageName,
            'package' => $packageName,
            'jumlah' => $amount,
            'tagihan' => $amount,
            'total' => $amount,
            'nominal' => $amount,
            'total_bayar' => $amount,
            'amount' => $amount,
            'waktu' => $date,
            'waktu_bayar' => $date,
            'tanggal' => $date,
            'tgl_bayar' => $date,
            'paid_at' => $date,
            'date' => $date,
            'link_struk' => $receiptUrl,
            'link_bukti' => $receiptUrl,
            'lihat_bukti' => $receiptUrl,
            'link' => $receiptUrl,
            'receipt_url' => $receiptUrl,
            'bukti_pembayaran' => $receiptUrl,
            'struk' => $receiptUrl,
            'url_struk' => $receiptUrl,
            'login_url' => $loginUrl,
            'portal_url' => $loginUrl,
            'url_portal' => $loginUrl,
            'router' => $routerName,
            'server' => $routerName,
            'router_name' => $routerName,
            'perusahaan' => $companyName,
            'tenant_name' => $companyName,
            'company_name' => $companyName,
            'nama_perusahaan' => $companyName,
        ]);

        return $this->sendMessage($customer['phone'], $msg);
    }

    /**
     * Send a payment receipt (alias for sendPaymentSuccess).
     */
    public function sendPaymentReceipt(object|array $invoice): bool
    {
        $invoiceArr = is_array($invoice) ? $invoice : $invoice->toArray();
        $customer = null;
        if ($invoice instanceof \App\Models\Invoice && $invoice->customer) {
            $customer = $invoice->customer;
        } elseif (isset($invoiceArr['customer_id'])) {
            $customer = \App\Models\Customer::find($invoiceArr['customer_id']);
        }
        $customerArr = $customer ? $customer->toArray() : ($invoiceArr['customer'] ?? []);

        return $this->sendPaymentSuccess($customerArr, $invoiceArr);
    }

    /**
     * Send an invoice reminder (for overdue/unpaid invoices).
     *
     * @param object|array $customer Customer data
     * @param object|array|null $invoice Invoice data
     * @return bool
     */
    public function sendInvoiceReminder(object|array $customer, object|array $invoice = null): bool
    {
        if ($invoice === null && $customer instanceof \App\Models\Invoice) {
            $invoice = $customer;
            $customer = $invoice->customer;
        }

        $customerArr = is_array($customer) ? $customer : ($customer ? $customer->toArray() : []);
        $invoiceArr = is_array($invoice) ? $invoice : ($invoice ? $invoice->toArray() : []);

        if (empty($customerArr['phone'])) {
            $this->lastError = 'Nomor WhatsApp pelanggan kosong.';
            return false;
        }

        $period = self::formatPeriodString($invoiceArr['period'] ?? null, $invoiceArr['due_date'] ?? null, $invoiceArr['periods_breakdown'] ?? null);
        $amount = number_format((float) ($invoiceArr['amount'] ?? 0), 0, ',', '.');
        $dueDate = isset($invoiceArr['due_date'])
            ? \Carbon\Carbon::parse($invoiceArr['due_date'])->setTimezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d F Y')
            : '-';
        $invNumber = $invoiceArr['invoice_number'] ?? ('INV-' . str_pad((string) ($invoiceArr['id'] ?? 0), 6, '0', STR_PAD_LEFT));
        $receiptUrl = url('/receipt/' . ($invoiceArr['invoice_number'] ?? ($invoiceArr['id'] ?? 0)));
        $loginUrl = url('/portal/login');
        $companyName = $this->getCompanyName();
        $packageName = $invoiceArr['package_name'] ?? ($customerArr['package']['name'] ?? 'Internet');
        $routerName = $customerArr['router_name'] ?? ($customerArr['router']['name'] ?? 'NOC');

        $defaultMsg = "*PENGINGAT TAGIHAN INTERNET*\n"
            . "{perusahaan}\n\n"
            . "Yth. {nama},\n\n"
            . "Kami menginformasikan bahwa tagihan layanan internet Anda saat ini *belum dibayar*:\n\n"
            . "--------------------------------\n"
            . "No. Invoice : {invoice}\n"
            . "Paket       : {paket}\n"
            . "Periode     : {periode}\n"
            . "Total Bayar : *Rp {jumlah}*\n"
            . "Jatuh Tempo : {jatuh_tempo}\n"
            . "--------------------------------\n\n"
            . "Rincian Tagihan & Pembayaran:\n"
            . "{link_struk}\n\n"
            . "Portal Layanan:\n"
            . "{login_url}\n\n"
            . "Mohon segera lakukan pembayaran sebelum tanggal jatuh tempo untuk menghindari penghentian/isolir layanan otomatis.\n"
            . "Abaikan pesan ini jika Anda sudah melakukan pembayaran.\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('Pengingat Tagihan', $defaultMsg, [
            'nama' => $customerArr['name'] ?? 'Pelanggan',
            'customer_name' => $customerArr['name'] ?? 'Pelanggan',
            'periode' => $period,
            'period' => $period,
            'invoice' => $invNumber,
            'no_invoice' => $invNumber,
            'nomor_invoice' => $invNumber,
            'status' => 'BELUM BAYAR',
            'paket' => $packageName,
            'layanan' => $packageName,
            'jumlah' => $amount,
            'tagihan' => $amount,
            'total' => $amount,
            'jatuh_tempo' => $dueDate,
            'due_date' => $dueDate,
            'link_struk' => $receiptUrl,
            'link_bukti' => $receiptUrl,
            'lihat_bukti' => $receiptUrl,
            'link' => $receiptUrl,
            'receipt_url' => $receiptUrl,
            'bukti_pembayaran' => $receiptUrl,
            'login_url' => $loginUrl,
            'portal_url' => $loginUrl,
            'router' => $routerName,
            'server' => $routerName,
            'perusahaan' => $companyName,
            'tenant_name' => $companyName,
        ]);

        return $this->sendMessage($customerArr['phone'], $msg);
    }

    /**
     * Send an isolation (service suspended) notification.
     */
    public function sendIsolation(array $customer): bool
    {
        if (empty($customer['phone'])) {
            $this->lastError = 'Nomor WhatsApp pelanggan kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $loginUrl = url('/portal/login');

        $defaultMsg = "*PEMBERITAHUAN ISOLIR LAYANAN*\n"
            . "{perusahaan}\n\n"
            . "Yth. {nama},\n"
            . "Kami memberitahukan bahwa akses layanan internet ({paket}) pada router {router} saat ini *Terisolir (Non-Aktif)* karena melewati batas waktu pembayaran jatuh tempo.\n\n"
            . "--------------------------------\n"
            . "Status      : TERISOLIR (OFF)\n"
            . "Akses Portal: {login_url}\n"
            . "--------------------------------\n\n"
            . "Mohon segera lakukan pembayaran tagihan Anda agar koneksi internet dapat aktif kembali secara otomatis.\n"
            . "Abaikan pemberitahuan ini jika Anda sudah menyelesaikan pembayaran.\n"
            . "Pusat Bantuan & Layanan: {perusahaan}";

        $msg = $this->renderTemplate('Pemberitahuan Isolir', $defaultMsg, [
            'nama' => $customer['name'] ?? 'Pelanggan',
            'paket' => $customer['package_name'] ?? ($customer['package']['name'] ?? 'Internet'),
            'login_url' => $loginUrl,
            'portal_url' => $loginUrl,
            'router' => $customer['router_name'] ?? ($customer['router']['name'] ?? 'NOC'),
            'perusahaan' => $companyName,
            'tenant_name' => $companyName,
        ]);

        return $this->sendMessage($customer['phone'], $msg);
    }

    /**
     * Send a broadcast message to a list of phone numbers with rate limiting.
     *
     * Each recipient receives the message with a 1-second delay between sends
     * to avoid being flagged for spam.
     *
     * @param array  $phoneList Array of phone numbers (strings)
     * @param string $message   Message text to send
     * @return array{success: int, failed: int, errors: array}
     */
    public function sendBroadcast(array $phoneList, string $message): array
    {
        $result = [
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        if (!$this->enabled || empty($phoneList) || empty($message)) {
            return $result;
        }

        foreach ($phoneList as $i => $phone) {
            $phone = trim((string) $phone);
            if (empty($phone)) {
                continue;
            }

            $ok = $this->sendMessage($phone, $message);

            if ($ok) {
                $result['success']++;
            } else {
                $result['failed']++;
                $result['errors'][] = [
                    'phone' => $phone,
                    'index' => $i,
                ];
            }

            // Jeda Acak Manusia (Humanized Random Delay 3 - 7 detik per pesan) untuk mencegah blokir WA
            if ($i < count($phoneList) - 1) {
                $randomDelayUs = rand(3_000_000, 7_000_000);
                usleep($randomDelayUs);
            }
        }

        Log::info('WhatsappService: broadcast completed', [
            'total'   => count($phoneList),
            'success' => $result['success'],
            'failed'  => $result['failed'],
        ]);

        return $result;
    }

    /**
     * Send a notification when a new tenant registration is received (pending payment/review).
     * Note: Disabled per user requirement — only notify on success (approved), failure (rejected), or expiration.
     */
    public function sendRegistrationPending(object|array $registration): bool
    {
        return false;
    }

    /**
     * Send a notification when a tenant registration request is rejected.
     */
    public function sendRegistrationRejected(object|array $registration, ?string $reason = null): bool
    {
        $reg = is_array($registration) ? (object) $registration : $registration;
        $phone = $reg->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp pendaftaran kosong.';
            return false;
        }

        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: (request()?->getHost() ?: 'nodera.id'));
        $companyName = $this->getCompanyName();
        $subdomain = ($reg->slug ?? '-') . '.' . $baseDomain;
        $rejectReason = $reason ?: ($reg->notes ?: 'Tidak memenuhi persyaratan verifikasi.');

        $defaultMsg = "*PENDAFTARAN DITOLAK*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Mohon maaf, permohonan pendaftaran instansi Anda belum dapat kami setujui.\n\n"
            . "--------------------------------\n"
            . "Instansi  : {nama_instansi}\n"
            . "Subdomain : {subdomain}\n"
            . "Alasan    : {alasan}\n"
            . "--------------------------------\n\n"
            . "Jika Anda memiliki pertanyaan lebih lanjut, silakan hubungi tim support kami.\n\n"
            . "Terima kasih atas pengertian Anda.";

        $msg = $this->renderTemplate('Pendaftaran Ditolak', $defaultMsg, [
            'nama'          => $reg->name ?? 'Calon Mitra',
            'nama_instansi' => $reg->company ?? $reg->name ?? 'Instansi ISP',
            'perusahaan'    => $companyName,
            'subdomain'     => $subdomain,
            'alasan'        => $rejectReason,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send a notification when a tenant registration request payment expires.
     */
    public function sendRegistrationExpired(object|array $registration): bool
    {
        $reg = is_array($registration) ? (object) $registration : $registration;
        $phone = $reg->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp pendaftaran kosong.';
            return false;
        }

        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: (request()?->getHost() ?: 'nodera.id'));
        $companyName = $this->getCompanyName();
        $subdomain = ($reg->slug ?? '-') . '.' . $baseDomain;
        $pkgName = is_object($reg->package ?? null) ? $reg->package->name : ($reg->package_name ?? 'Paket ISP');

        $defaultMsg = "*PENDAFTARAN KEDALUWARSA*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Batas waktu pembayaran untuk pendaftaran instansi Anda telah kedaluwarsa:\n\n"
            . "--------------------------------\n"
            . "Instansi  : {nama_instansi}\n"
            . "Subdomain : {subdomain}\n"
            . "Paket     : {paket}\n"
            . "Status    : *KEDALUWARSA / EXPIRED*\n"
            . "--------------------------------\n\n"
            . "Jika Anda masih berminat untuk bergabung, silakan lakukan pendaftaran ulang melalui website resmi {perusahaan}.\n\n"
            . "Terima kasih!";

        $msg = $this->renderTemplate('Pendaftaran Kedaluwarsa', $defaultMsg, [
            'nama'          => $reg->name ?? 'Calon Mitra',
            'nama_instansi' => $reg->company ?? $reg->name ?? 'Instansi ISP',
            'perusahaan'    => $companyName,
            'subdomain'     => $subdomain,
            'paket'         => $pkgName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send a notification when a tenant registration is approved / activated.
     */
    public function sendRegistrationApproved(object|array $registration, ?string $loginUrl = null, ?string $username = null, ?string $password = null): bool
    {
        $reg = is_array($registration) ? (object) $registration : $registration;
        $phone = $reg->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp pendaftaran kosong.';
            return false;
        }

        $baseDomain = config('app.base_domain', parse_url(config('app.url'), PHP_URL_HOST) ?: (request()?->getHost() ?: 'nodera.id'));
        $companyName = $this->getCompanyName();
        $url = $loginUrl ?: "https://{$reg->slug}.{$baseDomain}/login";
        $user = $username ?: ($reg->username ?: 'admin_' . $reg->slug);
        $pass = $password ?: '(sesuai pendaftaran)';
        $pkgName = is_object($reg->package ?? null) ? $reg->package->name : ($reg->package_name ?? 'Paket ISP');
        $duration = $reg->duration ?? 1;

        $defaultMsg = "*AKUN INSTANSI TELAH AKTIF!*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Kabar baik! Pendaftaran instansi Anda telah diverifikasi dan disetujui oleh admin. Akun Anda telah aktif dan siap digunakan.\n\n"
            . "--------------------------------\n"
            . "Instansi  : {nama_instansi}\n"
            . "Login URL : {login_url}\n"
            . "Username  : {username}\n"
            . "Password  : {password}\n"
            . "Paket     : {paket} ({durasi} Bulan)\n"
            . "--------------------------------\n\n"
            . "Silakan login melalui link di atas untuk mulai mengelola router MikroTik, pelanggan, dan voucher WiFi Anda.\n\n"
            . "Terima kasih telah bergabung bersama {perusahaan}!";

        $msg = $this->renderTemplate('Pendaftaran Disetujui', $defaultMsg, [
            'nama'          => $reg->name ?? 'Mitra ISP',
            'nama_instansi' => $reg->company ?? $reg->name ?? 'Instansi ISP',
            'perusahaan'    => $companyName,
            'login_url'     => $url,
            'username'      => $user,
            'password'      => $pass,
            'paket'         => $pkgName,
            'durasi'        => $duration,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send a welcome notification when a new member registers on the VPN / Member panel.
     */
    public function sendPanelWelcome(object|array $user, ?string $refCode = null): bool
    {
        $u = is_array($user) ? (object) $user : $user;
        $phone = $u->phone ?? null;
        if (empty($phone)) {
            return false;
        }

        $companyName = $this->getCompanyName();
        $saldo = number_format((float) ($u->bonus_saldo ?? $u->saldo ?? 0), 0, ',', '.');
        $loginUrl = url('/login');

        $defaultMsg = "*SELAMAT DATANG DI {perusahaan}*\n\n"
            . "Halo {nama},\n"
            . "Akun member Anda telah berhasil didaftarkan di portal {perusahaan}.\n\n"
            . "--------------------------------\n"
            . "Nama   : {nama}\n"
            . "Email  : {email}\n"
            . "No. WA : {phone}\n"
            . "Saldo  : Rp {saldo}\n"
            . "--------------------------------\n\n"
            . "Akses Dashboard Member:\n"
            . "{login_url}\n\n"
            . "Melalui portal ini, Anda dapat mengelola layanan VPN Remote, Mikhmon Online, Top-up saldo, dan layanan lainnya.\n\n"
            . "Terima kasih telah bergabung bersama kami!";

        $msg = $this->renderTemplate('Member Baru', $defaultMsg, [
            'nama'       => $u->name ?? 'Member',
            'email'      => $u->email ?? '-',
            'phone'      => $phone,
            'saldo'      => $saldo,
            'login_url'  => $loginUrl,
            'perusahaan' => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send a notification when a top-up request is created (pending payment).
     * Note: Disabled per user requirement — only notify on success or expiration/rejection.
     */
    public function sendTopupPending(object|array $topup, object|array|null $user = null): bool
    {
        return false;
    }

    /**
     * Send a notification when a top-up request is verified and saldo is credited.
     */
    public function sendTopupSuccess(object|array $topup, object|array|null $user = null, ?float $currentBalance = null): bool
    {
        $t = is_array($topup) ? (object) $topup : $topup;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($t->user ?? null);
        $phone = $u?->phone ?? $t->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $amount = number_format((float) ($t->amount_received ?? $t->amount ?? 0), 0, ',', '.');
        $invNumber = $t->invoice_number ?? '-';
        $bank = $t->bank_destination ?? 'QRIS / Transfer';
        $balance = $currentBalance !== null ? $currentBalance : (float) ($u?->total_saldo ?? $u?->saldo ?? 0);
        $balanceFmt = number_format($balance, 0, ',', '.');
        $time = now()->format('d/m/Y H:i');
        $dashboardUrl = url('/dashboard');

        $defaultMsg = "*TOP-UP SALDO BERHASIL!*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Deposit saldo Anda telah berhasil diverifikasi dan ditambahkan ke akun Anda!\n\n"
            . "--------------------------------\n"
            . "No. Invoice    : {invoice}\n"
            . "Nominal Masuk  : *Rp {nominal}*\n"
            . "Metode Bayar   : {metode_bayar}\n"
            . "Total Saldo    : *Rp {saldo_sekarang}*\n"
            . "Status         : *BERHASIL / LUNAS*\n"
            . "Waktu          : {waktu}\n"
            . "--------------------------------\n\n"
            . "Saldo Anda sudah aktif dan dapat digunakan untuk VPN Remote, Mikhmon Online, maupun perpanjangan layanan lainnya.\n\n"
            . "Akses Dashboard:\n"
            . "{login_url}\n\n"
            . "Terima kasih atas kepercayaan Anda bersama {perusahaan}!";

        $msg = $this->renderTemplate('Topup Berhasil', $defaultMsg, [
            'nama'           => $u?->name ?? 'Member',
            'invoice'        => $invNumber,
            'nominal'        => $amount,
            'metode_bayar'   => $bank,
            'saldo_sekarang' => $balanceFmt,
            'waktu'          => $time,
            'login_url'      => $dashboardUrl,
            'perusahaan'     => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send a notification when a top-up request expires or is cancelled.
     */
    public function sendTopupExpired(object|array $topup, object|array|null $user = null): bool
    {
        $t = is_array($topup) ? (object) $topup : $topup;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($t->user ?? null);
        $phone = $u?->phone ?? $t->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $amount = number_format((float) ($t->total_amount ?? $t->amount ?? 0), 0, ',', '.');
        $invNumber = $t->invoice_number ?? '-';
        $topupUrl = url('/topup');

        $defaultMsg = "*PERMINTAAN TOP-UP KEDALUWARSA*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Permintaan deposit saldo Anda dengan No. Invoice *{invoice}* sebesar *Rp {nominal}* telah kedaluwarsa / dibatalkan karena batas waktu pembayaran telah habis.\n\n"
            . "Jika Anda masih membutuhkan saldo, silakan buat permintaan topup baru di menu Topup Saldo:\n"
            . "{topup_url}\n\n"
            . "Terima kasih telah menggunakan layanan {perusahaan}!";

        $msg = $this->renderTemplate('Topup Kedaluwarsa', $defaultMsg, [
            'nama'       => $u?->name ?? 'Member',
            'invoice'    => $invNumber,
            'nominal'    => $amount,
            'topup_url'  => $topupUrl,
            'perusahaan' => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send a notification when a top-up request is rejected by admin.
     */
    public function sendTopupRejected(object|array $topup, object|array|null $user = null, ?string $reason = null): bool
    {
        $t = is_array($topup) ? (object) $topup : $topup;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($t->user ?? null);
        $phone = $u?->phone ?? $t->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $amount = number_format((float) ($t->amount ?? 0), 0, ',', '.');
        $invNumber = $t->invoice_number ?? '-';
        $note = $reason ?: ($t->admin_note ?? 'Bukti pembayaran tidak sesuai atau belum diterima.');

        $defaultMsg = "*PERMINTAAN TOP-UP DITOLAK*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Mohon maaf, permintaan deposit saldo Anda dengan No. Invoice *{invoice}* sebesar *Rp {nominal}* belum dapat disetujui.\n\n"
            . "Catatan Admin: {alasan}\n\n"
            . "Jika Anda sudah melakukan pembayaran, silakan hubungi tim support kami dengan melampirkan bukti transfer yang valid.\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('Topup Ditolak', $defaultMsg, [
            'nama'       => $u?->name ?? 'Member',
            'invoice'    => $invNumber,
            'nominal'    => $amount,
            'alasan'     => $note,
            'perusahaan' => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a VPN account is created/activated.
     */
    public function sendVpnCreated(object|array $account, object|array|null $user = null): bool
    {
        $acc = is_array($account) ? (object) $account : $account;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($acc->vpnUser ?? null);
        $phone = $u?->phone ?? $acc->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $server = $acc->server ?? null;
        $host = is_object($server)
            ? (!empty($server->server_domain) ? $server->server_domain : (!empty($server->host) ? $server->host : ($server->server_ip ?? '-')))
            : (!empty($server['server_domain']) ? $server['server_domain'] : (!empty($server['host']) ? $server['host'] : ($server['server_ip'] ?? '-')));

        $serverName = is_object($server) ? ($server->name ?? $host) : ($server['name'] ?? $host);
        $protoVal = $acc->protocol ?? 'L2TP';
        $protocol = strtoupper(is_array($protoVal) ? ($protoVal[0] ?? 'L2TP') : (string) $protoVal);
        $type = strtoupper($acc->type ?? 'REMOT');
        $pkgName = $acc->package ?? "VPN {$type}";
        $username = $acc->vpn_username ?? '-';
        $password = $acc->vpn_password ?? '-';
        $ipStatic = $acc->ip_static ?? '-';
        $expDate = !empty($acc->expires_at) ? \Carbon\Carbon::parse($acc->expires_at)->format('d/m/Y H:i') : '-';
        $detailUrl = url('/akun/' . ($acc->id ?? ''));

        // Build ports formatted string
        $rawPorts = $acc->ports ?? [];
        $portLines = [];
        $portCount = is_array($rawPorts) ? count($rawPorts) : 0;
        $targetList = ($portCount === 1) ? [8291] : (($portCount === 2) ? [8291, 8728] : [80, 8291, 8728, 22, 8132]);
        $portNames = [80 => 'WEB', 8291 => 'WINBOX', 8728 => 'API', 22 => 'SSH', 8132 => 'OLT'];

        if (is_array($rawPorts)) {
            foreach ($rawPorts as $idx => $publicPort) {
                if (is_array($publicPort)) {
                    $target = (int)($publicPort['target'] ?? $publicPort['target_port'] ?? 80);
                    $pub = (int)($publicPort['port'] ?? $publicPort['public'] ?? $publicPort['public_port'] ?? 80);
                } else {
                    $target = $targetList[$idx] ?? (8000 + $idx);
                    $pub = $publicPort;
                }
                $label = $portNames[$target] ?? ("PORT " . $target);
                $isWeb = str_contains(strtolower($label), 'web') || str_contains(strtolower($label), 'olt') || $target === 80 || $target === 8132;
                $url = $isWeb ? "http://{$host}:{$pub}" : "{$host}:{$pub}";
                $portLines[] = "• {$label}: {$url}";
            }
        }
        $portsText = !empty($portLines) ? implode("\n", $portLines) : "-";

        $companyName = $this->getCompanyName();

        $defaultMsg = "*AKUN VPN REMOTE BERHASIL DIAKTIFKAN*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Layanan VPN Remote Anda telah berhasil dibuat dan langsung aktif!\n\n"
            . "--------------------------------\n"
            . "Paket       : {paket}\n"
            . "Server      : {server_name}\n"
            . "Host/Domain : {host}\n"
            . "Username    : *{username}*\n"
            . "Password    : *{password}*\n"
            . "Protokol    : {protocol}\n"
            . "IP Static   : {ip_static}\n"
            . "Masa Aktif  : s/d {expires_at}\n"
            . "--------------------------------\n"
            . "Akses Port Remote :\n"
            . "{ports}\n"
            . "--------------------------------\n\n"
            . "Lihat detail akun & script MikroTik :\n"
            . "{detail_url}\n\n"
            . "Terima kasih telah menggunakan layanan {perusahaan}!";

        $msg = $this->renderTemplate('VPN Created', $defaultMsg, [
            'nama'        => $u?->name ?? 'Member',
            'paket'       => $pkgName,
            'server_name' => $serverName,
            'host'        => $host,
            'username'    => $username,
            'password'    => $password,
            'protocol'    => $protocol,
            'ip_static'   => $ipStatic,
            'expires_at'  => $expDate,
            'ports'       => $portsText,
            'detail_url'  => $detailUrl,
            'perusahaan'  => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a VPN account is extended/renewed.
     */
    public function sendVpnRenewed(object|array $account, object|array|null $user = null, float $price = 0, ?float $currentBalance = null): bool
    {
        $acc = is_array($account) ? (object) $account : $account;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($acc->vpnUser ?? null);
        $phone = $u?->phone ?? $acc->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $username = $acc->vpn_username ?? '-';
        $pkgName = $acc->package ?? 'VPN Remote';
        $expDate = !empty($acc->expires_at) ? \Carbon\Carbon::parse($acc->expires_at)->format('d/m/Y H:i') : '-';
        $amount = number_format($price, 0, ',', '.');
        $balance = $currentBalance !== null ? $currentBalance : (float) ($u?->total_saldo ?? $u?->saldo ?? 0);
        $balanceFmt = number_format($balance, 0, ',', '.');

        $defaultMsg = "*PERPANJANGAN VPN REMOTE BERHASIL*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Masa aktif akun VPN Remote *{username}* telah berhasil diperpanjang!\n\n"
            . "--------------------------------\n"
            . "Akun VPN    : {username}\n"
            . "Paket       : {paket}\n"
            . "Biaya       : *Rp {nominal}*\n"
            . "Masa Aktif  : s/d *{expires_at}*\n"
            . "Sisa Saldo  : Rp {saldo_sekarang}\n"
            . "--------------------------------\n\n"
            . "Terima kasih telah berlangganan di {perusahaan}!";

        $msg = $this->renderTemplate('VPN Renewed', $defaultMsg, [
            'nama'           => $u?->name ?? 'Member',
            'username'       => $username,
            'paket'          => $pkgName,
            'nominal'        => $amount,
            'expires_at'     => $expDate,
            'saldo_sekarang' => $balanceFmt,
            'perusahaan'     => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification reminder before VPN account expires (H-3, H-1).
     */
    public function sendVpnExpiringReminder(object|array $account, object|array|null $user = null, int $daysLeft = 3): bool
    {
        $acc = is_array($account) ? (object) $account : $account;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($acc->vpnUser ?? null);
        $phone = $u?->phone ?? $acc->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $username = $acc->vpn_username ?? '-';
        $pkgName = $acc->package ?? 'VPN Remote';
        $expDate = !empty($acc->expires_at) ? \Carbon\Carbon::parse($acc->expires_at)->format('d/m/Y H:i') : '-';
        $renewUrl = url('/akun/' . ($acc->id ?? ''));
        $balance = (float) ($u?->total_saldo ?? $u?->saldo ?? 0);
        $balanceFmt = number_format($balance, 0, ',', '.');
        $autoRenew = !empty($acc->auto_renew) ? 'AKTIF (Otomatis potong saldo)' : 'NONAKTIF';

        $daysText = $daysLeft <= 0 ? 'hari ini' : "{$daysLeft} hari lagi";

        $defaultMsg = "*PENGINGAT MASA AKTIF VPN REMOTE*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Masa aktif akun VPN Remote *{username}* akan berakhir dalam *{sisa_hari}* (pada {expires_at}).\n\n"
            . "--------------------------------\n"
            . "Akun VPN       : {username}\n"
            . "Paket          : {paket}\n"
            . "Masa Aktif     : {expires_at}\n"
            . "Auto-Renew     : {auto_renew}\n"
            . "Saldo Anda     : Rp {saldo_sekarang}\n"
            . "--------------------------------\n\n"
            . "Pastikan saldo Anda mencukupi untuk perpanjangan otomatis atau perpanjang secara manual melalui tautan berikut:\n"
            . "{link_perpanjang}\n\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('VPN Expiring Reminder', $defaultMsg, [
            'nama'            => $u?->name ?? 'Member',
            'username'        => $username,
            'paket'           => $pkgName,
            'sisa_hari'       => $daysText,
            'expires_at'      => $expDate,
            'auto_renew'      => $autoRenew,
            'saldo_sekarang'  => $balanceFmt,
            'link_perpanjang' => $renewUrl,
            'perusahaan'      => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a VPN account is expired / suspended.
     */
    public function sendVpnExpired(object|array $account, object|array|null $user = null): bool
    {
        $acc = is_array($account) ? (object) $account : $account;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($acc->vpnUser ?? null);
        $phone = $u?->phone ?? $acc->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $username = $acc->vpn_username ?? '-';
        $expDate = !empty($acc->expires_at) ? \Carbon\Carbon::parse($acc->expires_at)->format('d/m/Y H:i') : '-';
        $renewUrl = url('/akun/' . ($acc->id ?? ''));

        $defaultMsg = "*LAYANAN VPN REMOTE TELAH KEDALUWARSA*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Masa aktif akun VPN Remote *{username}* telah habis pada {expires_at} dan saat ini dinonaktifkan.\n\n"
            . "--------------------------------\n"
            . "Akun VPN   : {username}\n"
            . "Status     : *EXPIRED (NONAKTIF)*\n"
            . "Kedaluwarsa: {expires_at}\n"
            . "--------------------------------\n\n"
            . "Untuk mengaktifkan kembali koneksi remote router Anda, silakan top up saldo dan perpanjang akun melalui:\n"
            . "{link_perpanjang}\n\n"
            . "Akun yang tidak diperpanjang dalam 7 hari akan dihapus permanen dari server.\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('VPN Expired', $defaultMsg, [
            'nama'            => $u?->name ?? 'Member',
            'username'        => $username,
            'expires_at'      => $expDate,
            'link_perpanjang' => $renewUrl,
            'perusahaan'      => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a Mikhmon Online instance is created.
     */
    public function sendMikhmonCreated(object|array $sub, object|array|null $user = null): bool
    {
        $s = is_array($sub) ? (object) $sub : $sub;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($s->user ?? null);
        $phone = $u?->phone ?? $s->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $subdomain = $s->subdomain ?? '-';
        $rawUrl = $s->admin_url ?? ($s->url ? rtrim($s->url, '/') . '/login' : ("https://" . $subdomain . "." . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'airnetsolution.com') . '/login'));
        if (!str_ends_with($rawUrl, '/login') && !str_contains($rawUrl, '/login')) {
            $rawUrl = rtrim($rawUrl, '/') . '/login';
        }
        $url = $rawUrl;
        $rosVersion = $s->ros_version ?? '6';
        $expDate = !empty($s->expires_at) ? \Carbon\Carbon::parse($s->expires_at)->format('d/m/Y H:i') : '-';

        $defaultMsg = "*LAYANAN MIKHMON ONLINE BERHASIL DIAKTIFKAN*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Instance Mikhmon Online Anda telah berhasil dibuat dan siap digunakan!\n\n"
            . "--------------------------------\n"
            . "Subdomain  : *{subdomain}*\n"
            . "🌐 URL Web : {url}\n"
            . "👤 User    : *{username}*\n"
            . "🔑 Pass    : *{password}*\n"
            . "RouterOS   : v{ros_version}\n"
            . "Masa Aktif : s/d {expires_at}\n"
            . "--------------------------------\n\n"
            . "Silakan login ke link di atas untuk menghubungkan router MikroTik dan mulai mencetak voucher hotspot.\n\n"
            . "Terima kasih telah menggunakan {perusahaan}!";

        $msg = $this->renderTemplate('Mikhmon Created', $defaultMsg, [
            'nama'         => $u?->name ?? 'Member',
            'subdomain'    => $subdomain,
            'url'          => $url,
            'username'     => 'admin',
            'password'     => '',
            'user'         => 'admin',
            'pass'         => '',
            'user_mikhmon' => 'admin',
            'pass_mikhmon' => '',
            'ros_version'  => $rosVersion,
            'expires_at'   => $expDate,
            'perusahaan'   => $companyName,
        ]);

        if (!empty($msg)) {
            // Auto-heal legacy saved database templates that had hardcoded mikhmon / 1234
            $msg = str_replace(
                ['*mikhmon*', '*1234*', 'User    : mikhmon', 'Pass    : 1234'],
                ['*nodera*', '*nodera*', 'User    : nodera', 'Pass    : nodera'],
                $msg
            );

            // Auto-fix URL ending to ensure it points to /login rather than root buy.php
            if (!empty($subdomain) && $subdomain !== '-') {
                $msg = preg_replace_callback('/(https?:\/\/' . preg_quote($subdomain, '/') . '\.[a-zA-Z0-9.-]+)\/?(?!\w)/i', function ($m) {
                    return rtrim($m[1], '/') . '/login';
                }, $msg);
            }
        }

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a Mikhmon Online instance is renewed.
     */
    public function sendMikhmonRenewed(object|array $sub, object|array|null $user = null, float $price = 0, ?float $currentBalance = null): bool
    {
        $s = is_array($sub) ? (object) $sub : $sub;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($s->user ?? null);
        $phone = $u?->phone ?? $s->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $subdomain = $s->subdomain ?? '-';
        $rawUrl = $s->admin_url ?? ($s->url ? rtrim($s->url, '/') . '/login' : ("https://" . $subdomain . "." . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'airnetsolution.com') . '/login'));
        if (!str_ends_with($rawUrl, '/login') && !str_contains($rawUrl, '/login')) {
            $rawUrl = rtrim($rawUrl, '/') . '/login';
        }
        $url = $rawUrl;
        $expDate = !empty($s->expires_at) ? \Carbon\Carbon::parse($s->expires_at)->format('d/m/Y H:i') : '-';
        $amount = number_format($price, 0, ',', '.');
        $balance = $currentBalance !== null ? $currentBalance : (float) ($u?->total_saldo ?? $u?->saldo ?? 0);
        $balanceFmt = number_format($balance, 0, ',', '.');

        $defaultMsg = "*PERPANJANGAN MIKHMON ONLINE BERHASIL*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Masa aktif layanan Mikhmon Online *{subdomain}* telah berhasil diperpanjang!\n\n"
            . "--------------------------------\n"
            . "Subdomain   : {subdomain}\n"
            . "🌐 URL Web  : {url}\n"
            . "Biaya       : *Rp {nominal}*\n"
            . "Masa Aktif  : s/d *{expires_at}*\n"
            . "Sisa Saldo  : Rp {saldo_sekarang}\n"
            . "--------------------------------\n\n"
            . "Terima kasih telah berlangganan di {perusahaan}!";

        $msg = $this->renderTemplate('Mikhmon Renewed', $defaultMsg, [
            'nama'           => $u?->name ?? 'Member',
            'subdomain'      => $subdomain,
            'url'            => $url,
            'nominal'        => $amount,
            'expires_at'     => $expDate,
            'saldo_sekarang' => $balanceFmt,
            'perusahaan'     => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send expiring reminder notifications (H-3, Hari-H) before Mikhmon instances expire.
     */
    public function sendMikhmonExpiringReminder(object|array $sub, object|array|null $user = null, int $daysLeft = 3): bool
    {
        $s = is_array($sub) ? (object) $sub : $sub;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($s->user ?? null);
        $phone = $u?->phone ?? $s->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $subdomain = $s->subdomain ?? '-';
        $expDate = !empty($s->expires_at) ? \Carbon\Carbon::parse($s->expires_at)->format('d/m/Y H:i') : '-';
        $renewUrl = url('/mikhmon');
        $balance = (float) ($u?->total_saldo ?? $u?->saldo ?? 0);
        $balanceFmt = number_format($balance, 0, ',', '.');
        $autoRenew = !empty($s->auto_renew) ? 'AKTIF (Otomatis potong saldo)' : 'NONAKTIF';

        $daysText = $daysLeft <= 0 ? 'hari ini' : "{$daysLeft} hari lagi";

        $defaultMsg = "*PENGINGAT MASA AKTIF MIKHMON ONLINE*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Masa aktif server Mikhmon Online Anda (*{subdomain}*) akan berakhir dalam *{sisa_hari}* (pada {expires_at}).\n\n"
            . "--------------------------------\n"
            . "Subdomain      : {subdomain}\n"
            . "Masa Aktif     : {expires_at}\n"
            . "Auto-Renew     : {auto_renew}\n"
            . "Saldo Anda     : Rp {saldo_sekarang}\n"
            . "--------------------------------\n\n"
            . "Pastikan saldo Anda mencukupi untuk perpanjangan otomatis atau lakukan perpanjangan manual melalui tautan berikut:\n"
            . "{link_perpanjang}\n\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('Mikhmon Expiring Reminder', $defaultMsg, [
            'nama'            => $u?->name ?? 'Member',
            'subdomain'       => $subdomain,
            'sisa_hari'       => $daysText,
            'expires_at'      => $expDate,
            'auto_renew'      => $autoRenew,
            'saldo_sekarang'  => $balanceFmt,
            'link_perpanjang' => $renewUrl,
            'perusahaan'      => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a Mikhmon Online instance is expired.
     */
    public function sendMikhmonExpired(object|array $sub, object|array|null $user = null): bool
    {
        $s = is_array($sub) ? (object) $sub : $sub;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($s->user ?? null);
        $phone = $u?->phone ?? $s->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp user kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $subdomain = $s->subdomain ?? '-';
        $expDate = !empty($s->expires_at) ? \Carbon\Carbon::parse($s->expires_at)->format('d/m/Y H:i') : '-';
        $renewUrl = url('/mikhmon');

        $defaultMsg = "*LAYANAN MIKHMON ONLINE TELAH KEDALUWARSA*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Masa aktif server Mikhmon Online Anda (*{subdomain}*) telah berakhir pada {expires_at}.\n\n"
            . "--------------------------------\n"
            . "Subdomain   : {subdomain}\n"
            . "Status      : *EXPIRED (NONAKTIF)*\n"
            . "Kedaluwarsa : {expires_at}\n"
            . "--------------------------------\n\n"
            . "Untuk mengaktifkan kembali server Mikhmon Anda, silakan lakukan perpanjangan di:\n"
            . "{link_perpanjang}\n\n"
            . "Data voucher dan sesi Anda tetap tersimpan selama masa tenggang 7 hari.\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('Mikhmon Expired', $defaultMsg, [
            'nama'            => $u?->name ?? 'Member',
            'subdomain'       => $subdomain,
            'expires_at'      => $expDate,
            'link_perpanjang' => $renewUrl,
            'perusahaan'      => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a Referral Partner application is approved.
     */
    public function sendReferralApproved(object|array $partner, object|array|null $user = null): bool
    {
        $p = is_array($partner) ? (object) $partner : $partner;
        $u = $user ? (is_array($user) ? (object) $user : $user) : ($p->user ?? null);
        $phone = $p->phone ?? $u?->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp mitra kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $refCode = $p->referral_code ?? '-';
        $rate = $p->commission_rate ?? '10';
        $refLink = url('/ref/' . $refCode);
        $portalUrl = url('/referral');

        $defaultMsg = "*SELAMAT! KEMITRAAN REFERRAL ANDA DISETUJUI*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama},\n"
            . "Pengajuan Anda sebagai Mitra Program Referral {perusahaan} telah *DISETUJUI*!\n\n"
            . "--------------------------------\n"
            . "Kode Referral : *{kode_referral}*\n"
            . "Komisi Anda   : *{rate}%* dari setiap topup downline\n"
            . "🔗 Link Promo : {link_referral}\n"
            . "--------------------------------\n\n"
            . "Bagikan link atau kode referral Anda kepada rekan ISP / Teknisi Jaringan. Setiap kali member yang Anda ajak melakukan deposit, komisi otomatis masuk ke saldo mitra Anda!\n\n"
            . "Pantau statistik & komisi Anda di:\n"
            . "{portal_url}\n\n"
            . "Sukses selalu bersama {perusahaan}!";

        $msg = $this->renderTemplate('Referral Disetujui', $defaultMsg, [
            'nama'          => $p->name ?? $u?->name ?? 'Mitra',
            'kode_referral' => $refCode,
            'rate'          => $rate,
            'link_referral' => $refLink,
            'portal_url'    => $portalUrl,
            'perusahaan'    => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a Referral Commission is credited.
     */
    public function sendReferralCommissionEarned(object|array $commission, object|array|null $partner = null, object|array|null $downlineUser = null): bool
    {
        $c = is_array($commission) ? (object) $commission : $commission;
        $p = $partner ? (is_array($partner) ? (object) $partner : $partner) : ($c->partner ?? null);
        $u = $downlineUser ? (is_array($downlineUser) ? (object) $downlineUser : $downlineUser) : ($c->referredUser ?? null);

        $phone = $p?->phone ?? $p?->user?->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp mitra kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $downlineName = $u?->name ?? 'Member Downline';
        $topupAmount = number_format((float) ($c->topup_amount ?? 0), 0, ',', '.');
        $commAmount = number_format((float) ($c->commission_amount ?? 0), 0, ',', '.');
        $rate = $c->commission_rate ?? ($p?->commission_rate ?? 10);
        $balance = number_format((float) ($p?->fresh()?->commission_balance ?? $p?->commission_balance ?? 0), 0, ',', '.');
        $portalUrl = url('/referral');

        $defaultMsg = "*KOMISI REFERRAL DITERIMA !*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama_mitra},\n"
            . "Kabar gembira! Anda baru saja mendapatkan komisi dari aktivitas deposit downline Anda:\n\n"
            . "--------------------------------\n"
            . "Downline       : {downline}\n"
            . "Top-Up         : Rp {nominal_topup}\n"
            . "Rate Komisi    : {rate}%\n"
            . "Komisi Masuk   : *+Rp {nominal_komisi}*\n"
            . "Saldo Komisi   : *Rp {saldo_komisi}*\n"
            . "--------------------------------\n\n"
            . "Komisi dapat ditarik ke rekening bank / e-wallet atau langsung dikonversi ke saldo utama Anda.\n\n"
            . "Cek saldo komisi Anda di:\n"
            . "{portal_url}\n\n"
            . "Terima kasih atas kerja sama Anda!";

        $msg = $this->renderTemplate('Komisi Referral Diterima', $defaultMsg, [
            'nama_mitra'     => $p?->name ?? 'Mitra',
            'downline'       => $downlineName,
            'nominal_topup'  => $topupAmount,
            'rate'           => $rate,
            'nominal_komisi' => $commAmount,
            'saldo_komisi'   => $balance,
            'portal_url'     => $portalUrl,
            'perusahaan'     => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a referral withdrawal is paid / transferred.
     */
    public function sendReferralWithdrawalPaid(object|array $withdrawal, object|array|null $partner = null): bool
    {
        $w = is_array($withdrawal) ? (object) $withdrawal : $withdrawal;
        $p = $partner ? (is_array($partner) ? (object) $partner : $partner) : ($w->partner ?? null);
        $phone = $p?->phone ?? $p?->user?->phone ?? $w->user?->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp mitra kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $amount = number_format((float) ($w->amount ?? 0), 0, ',', '.');
        $bank = $w->bank_name ?? '-';
        $accNumber = $w->account_number ?? '-';
        $accName = $w->account_name ?? '-';
        $notes = $w->admin_notes ?: 'Ditransfer oleh Admin';

        $defaultMsg = "*PENCAIRAN KOMISI REFERRAL TELAH DITRANSFER*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama_mitra},\n"
            . "Permintaan penarikan dana komisi referral Anda telah berhasil diproses dan ditransfer!\n\n"
            . "--------------------------------\n"
            . "Jumlah Dana   : *Rp {nominal}*\n"
            . "Bank / Tujuan : {bank}\n"
            . "No. Rekening  : *{rekening}*\n"
            . "Atas Nama     : {atas_nama}\n"
            . "Status        : *SELESAI / DITRANSFER*\n"
            . "Catatan       : {catatan}\n"
            . "--------------------------------\n\n"
            . "Silakan cek mutasi rekening Anda. Terima kasih atas kerja sama Anda yang luar biasa bersama {perusahaan}!";

        $msg = $this->renderTemplate('Pencairan Referral Selesai', $defaultMsg, [
            'nama_mitra' => $p?->name ?? 'Mitra',
            'nominal'    => $amount,
            'bank'       => $bank,
            'rekening'   => $accNumber,
            'atas_nama'  => $accName,
            'catatan'    => $notes,
            'perusahaan' => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a referral withdrawal is rejected.
     */
    public function sendReferralWithdrawalRejected(object|array $withdrawal, object|array|null $partner = null, ?string $reason = null): bool
    {
        $w = is_array($withdrawal) ? (object) $withdrawal : $withdrawal;
        $p = $partner ? (is_array($partner) ? (object) $partner : $partner) : ($w->partner ?? null);
        $phone = $p?->phone ?? $p?->user?->phone ?? $w->user?->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp mitra kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $amount = number_format((float) ($w->amount ?? 0), 0, ',', '.');
        $note = $reason ?: ($w->admin_notes ?: 'Data rekening tidak sesuai.');

        $defaultMsg = "*PENARIKAN KOMISI REFERRAL DITOLAK*\n"
            . "{perusahaan}\n\n"
            . "Halo {nama_mitra},\n"
            . "Permintaan penarikan komisi referral Anda sebesar *Rp {nominal}* tidak dapat diproses.\n\n"
            . "--------------------------------\n"
            . "Alasan Penolakan : {alasan}\n"
            . "Status Dana      : *Dikembalikan ke Saldo Komisi*\n"
            . "--------------------------------\n\n"
            . "Saldo komisi telah dikembalikan ke akun Anda. Silakan periksa kembali nomor rekening / informasi pembayaran dan ajukan ulang.\n\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('Penarikan Referral Ditolak', $defaultMsg, [
            'nama_mitra' => $p?->name ?? 'Mitra',
            'nominal'    => $amount,
            'alasan'     => $note,
            'perusahaan' => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification reminder for tenant SaaS subscription expiring (H-7, H-3, H-1).
     */
    public function sendTenantSubscriptionReminder(object|array $tenant, int $daysLeft = 3): bool
    {
        $t = is_array($tenant) ? (object) $tenant : $tenant;
        $phone = $t->phone ?? $t->vpnUser?->phone ?? ($t->settings['phone'] ?? null);
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp tenant kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $name = $t->name ?? 'Tenant';
        $slug = $t->slug ?? '-';
        $expDate = !empty($t->expired_at) ? \Carbon\Carbon::parse($t->expired_at)->format('d/m/Y') : '-';
        $daysText = $daysLeft <= 0 ? 'hari ini' : "{$daysLeft} hari lagi";
        $renewUrl = url('/dashboard');

        $defaultMsg = "*PENGINGAT MASA AKTIF LANGGANAN CLOUD SAAS*\n"
            . "{perusahaan}\n\n"
            . "Halo Admin *{nama_instansi}*,\n"
            . "Masa aktif langganan Cloud SaaS Billing Anda akan berakhir dalam *{sisa_hari}* (pada {expires_at}).\n\n"
            . "--------------------------------\n"
            . "Instance   : {nama_instansi} ({slug})\n"
            . "Masa Aktif : s/d {expires_at}\n"
            . "--------------------------------\n\n"
            . "Untuk memastikan sistem billing, isolir otomatis, dan voucher hotspot pelanggan Anda tetap berjalan tanpa gangguan, pastikan saldo akun Anda mencukupi atau lakukan perpanjangan di:\n"
            . "{link_perpanjang}\n\n"
            . "Terima kasih atas kepercayaan Anda bermitra dengan {perusahaan}!";

        $msg = $this->renderTemplate('SaaS Subscription Reminder', $defaultMsg, [
            'nama_instansi'   => $name,
            'slug'            => $slug,
            'sisa_hari'       => $daysText,
            'expires_at'      => $expDate,
            'link_perpanjang' => $renewUrl,
            'perusahaan'      => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a tenant SaaS subscription is renewed.
     */
    public function sendTenantSubscriptionRenewed(object|array $tenant, float $amount = 0): bool
    {
        $t = is_array($tenant) ? (object) $tenant : $tenant;
        $phone = $t->phone ?? $t->vpnUser?->phone ?? ($t->settings['phone'] ?? null);
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp tenant kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $name = $t->name ?? 'Tenant';
        $slug = $t->slug ?? '-';
        $expDate = !empty($t->expired_at) ? \Carbon\Carbon::parse($t->expired_at)->format('d/m/Y') : '-';
        $amountFmt = number_format($amount, 0, ',', '.');

        $defaultMsg = "*PERPANJANGAN LANGGANAN CLOUD SAAS BERHASIL*\n"
            . "{perusahaan}\n\n"
            . "Halo Admin *{nama_instansi}*,\n"
            . "Langganan Cloud SaaS Billing Anda telah berhasil diperpanjang!\n\n"
            . "--------------------------------\n"
            . "Instance       : {nama_instansi} ({slug})\n"
            . "Biaya          : Rp {nominal}\n"
            . "Masa Aktif Baru: s/d *{expires_at}*\n"
            . "Status         : *AKTIF*\n"
            . "--------------------------------\n\n"
            . "Seluruh layanan operasional billing dan isolir pelanggan berjalan normal.\n"
            . "Terima kasih telah mempercayakan sistem Anda pada {perusahaan}!";

        $msg = $this->renderTemplate('SaaS Subscription Renewed', $defaultMsg, [
            'nama_instansi' => $name,
            'slug'          => $slug,
            'nominal'       => $amountFmt,
            'expires_at'    => $expDate,
            'perusahaan'    => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a tenant SaaS subscription is expired.
     */
    public function sendTenantSubscriptionExpired(object|array $tenant): bool
    {
        $t = is_array($tenant) ? (object) $tenant : $tenant;
        $phone = $t->phone ?? $t->vpnUser?->phone ?? ($t->settings['phone'] ?? null);
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp tenant kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $name = $t->name ?? 'Tenant';
        $slug = $t->slug ?? '-';
        $expDate = !empty($t->expired_at) ? \Carbon\Carbon::parse($t->expired_at)->format('d/m/Y') : '-';
        $renewUrl = url('/dashboard');

        $defaultMsg = "*LANGGANAN CLOUD SAAS TELAH KEDALUWARSA*\n"
            . "{perusahaan}\n\n"
            . "Halo Admin *{nama_instansi}*,\n"
            . "Masa aktif langganan Cloud SaaS Billing Anda (*{slug}*) telah habis pada {expires_at}.\n\n"
            . "--------------------------------\n"
            . "Instance    : {nama_instansi}\n"
            . "Status      : *NONAKTIF (EXPIRED)*\n"
            . "Kedaluwarsa : {expires_at}\n"
            . "--------------------------------\n\n"
            . "Untuk mengaktifkan kembali akses panel admin dan pemrosesan billing pelanggan Anda, silakan lakukan perpanjangan di:\n"
            . "{link_perpanjang}\n\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('SaaS Subscription Expired', $defaultMsg, [
            'nama_instansi'   => $name,
            'slug'            => $slug,
            'expires_at'      => $expDate,
            'link_perpanjang' => $renewUrl,
            'perusahaan'      => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a WA Gateway merchant credit balance is critically low (<= Rp 5.000).
     */
    public function sendWaMerchantLowBalance(object|array $merchant): bool
    {
        $m = is_array($merchant) ? (object) $merchant : $merchant;
        $phone = $m->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp merchant kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $name = $m->name ?? $m->owner_name ?? 'Merchant';
        $code = $m->merchant_code ?? '-';
        $balance = (float) ($m->credit_balance ?? 0);
        $balanceFmt = number_format($balance, 0, ',', '.');
        $deviceLimit = (int) ($m->device_limit ?? 1);
        $topupUrl = 'https://wa.dgtlnetsolution.com/billing';

        $defaultMsg = "⚠️ *PERINGATAN SALDO WA GATEWAY HAMPIR HABIS*\n"
            . "{perusahaan}\n\n"
            . "Halo *{nama}*,\n"
            . "Saldo kredit WhatsApp Gateway Anda saat ini tersisa *Rp {saldo_tersisa}*.\n\n"
            . "--------------------------------\n"
            . "Merchant        : {nama} ({kode})\n"
            . "Sisa Saldo      : Rp {saldo_tersisa}\n"
            . "Batas Perangkat : {limit_perangkat} Perangkat\n"
            . "--------------------------------\n\n"
            . "Segera lakukan isi ulang (topup) saldo agar pengiriman notifikasi, OTP, dan broadcast pesan WhatsApp bisnis Anda tidak terhenti.\n\n"
            . "Topup Saldo: {link_topup}\n\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('WA Gateway Low Balance', $defaultMsg, [
            'nama'            => $name,
            'kode'            => $code,
            'saldo_tersisa'   => $balanceFmt,
            'limit_perangkat' => (string) $deviceLimit,
            'link_topup'      => $topupUrl,
            'perusahaan'      => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Send notification when a WA Gateway merchant is downgraded to FREE tier (balance 0).
     */
    public function sendWaMerchantTierDowngraded(object|array $merchant): bool
    {
        $m = is_array($merchant) ? (object) $merchant : $merchant;
        $phone = $m->phone ?? null;
        if (empty($phone)) {
            $this->lastError = 'Nomor WhatsApp merchant kosong.';
            return false;
        }

        $companyName = $this->getCompanyName();
        $name = $m->name ?? $m->owner_name ?? 'Merchant';
        $code = $m->merchant_code ?? '-';
        $topupUrl = 'https://wa.dgtlnetsolution.com/billing';

        $defaultMsg = "ℹ️ *PEMBERITAHUAN TIER WA GATEWAY: FREE*\n"
            . "{perusahaan}\n\n"
            . "Halo *{nama}*,\n"
            . "Saldo kredit WhatsApp Gateway Anda telah *habis (Rp 0)*. Status akun Anda otomatis beralih ke *Tier FREE* dengan ketentuan:\n\n"
            . "--------------------------------\n"
            . "Merchant    : {nama} ({kode})\n"
            . "Tier Akun   : FREE (1 Perangkat Aktif)\n"
            . "Batas Kuota : 500 Pesan / Bulan\n"
            . "--------------------------------\n\n"
            . "Perangkat tambahan (ke-2 s/d ke-5) dinonaktifkan sementara. Untuk mengaktifkan kembali multi-perangkat dan pengiriman pesan tanpa batas, silakan lakukan topup saldo di:\n"
            . "{link_topup}\n\n"
            . "Terima kasih.";

        $msg = $this->renderTemplate('WA Gateway Tier Free', $defaultMsg, [
            'nama'       => $name,
            'kode'       => $code,
            'link_topup' => $topupUrl,
            'perusahaan' => $companyName,
        ]);

        return $this->sendMessage($phone, $msg);
    }

    /**
     * Fallback to local NODERA WhatsApp Gateway microservice on port 3022.
     */
    private function tryLocalFallback(string $phone, string $message): bool
    {
        try {
            $fallbackUrl = 'http://127.0.0.1:3022/api/send';
            $fallbackKey = config('services.wa_gateway.api_key', 'nodera_wa_secret_key_2026');
            $fbResp = (new Client(['timeout' => 3, 'connect_timeout' => 1, 'verify' => false]))->post($fallbackUrl, [
                'headers' => [
                    'X-Api-Key'    => $fallbackKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'session_id' => 'wa_master_platform',
                    'phone'      => $phone,
                    'message'    => $message,
                ],
            ]);
            if ($fbResp->getStatusCode() >= 200 && $fbResp->getStatusCode() < 300) {
                Log::info("[WhatsappService] Successfully delivered to {$phone} via local gateway fallback!");
                return true;
            }
        } catch (\Throwable $fbEx) {
            Log::warning("[WhatsappService] Local fallback gateway error: " . $fbEx->getMessage());
        }
        return false;
    }

    /**
     * Low-level send via Guzzle supporting Fonnte, Wablas, Starsender, WhaCenter, and Custom Gateways.
     */
    private function send(array $data): bool
    {
        $phone = $data['phone'] ?? $data['target'] ?? '';
        $message = $data['message'] ?? '';

        if (!$this->httpClient || empty($this->token)) {
            if ($this->tryLocalFallback($phone, $message)) {
                return true;
            }
            $this->lastError = 'Koneksi HTTP Gateway WhatsApp belum diinisialisasi / Token kosong.';
            Log::error('[WhatsappService] ' . $this->lastError);
            return false;
        }

        try {
            $isNodera = str_contains($this->apiUrl, 'wa.dgtlnetsolution.com') || str_contains($this->apiUrl, '127.0.0.1:3022') || $this->provider === 'nodera' || $this->provider === 'nodera-gateway';
            $isFonnte = str_contains($this->apiUrl, 'fonnte.com') || $this->provider === 'fonnte';
            $isMhwa = str_contains($this->apiUrl, 'mhwa.biz.id') || $this->provider === 'mhwa';
            $isKirimi = str_contains($this->apiUrl, 'kirimi.id') || $this->provider === 'kirimi';
            $isWablas = str_contains($this->apiUrl, 'wablas.com') || $this->provider === 'wablas';
            $isStarsender = str_contains($this->apiUrl, 'starsender') || $this->provider === 'starsender';
            $isWhaCenter = str_contains($this->apiUrl, 'whacenter') || $this->provider === 'whacenter';

            if ($isNodera) {
                // Official NODERA WhatsApp Gateway (https://wa.dgtlnetsolution.com/api/send-message or local 3022)
                $authHeader = str_starts_with($this->token, 'Bearer ') ? $this->token : 'Bearer ' . $this->token;
                $payload = [
                    'phone'   => $phone,
                    'message' => $message,
                ];
                if (!empty($this->senderPhone)) {
                    $payload['device_id'] = $this->senderPhone;
                    $payload['session_id'] = $this->senderPhone;
                }
                if (!empty($data['media_url'])) {
                    $payload['media_url'] = $data['media_url'];
                }

                $response = $this->httpClient->post($this->apiUrl, [
                    'headers' => [
                        'Authorization' => $authHeader,
                        'X-Api-Key'     => $this->token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                $statusCode = $response->getStatusCode();
                $bodyStr = (string) $response->getBody();
                Log::info("NODERA WA Response ({$phone}): " . $bodyStr);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $json = json_decode($bodyStr, true);
                    if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false')) {
                        $this->lastError = "NODERA WA: " . ($json['message'] ?? $bodyStr);
                        return $this->tryLocalFallback($phone, $message);
                    }
                    return true;
                }

                $this->lastError = "Server NODERA WA merespons HTTP {$statusCode}";
                return $this->tryLocalFallback($phone, $message);
            }

            if ($isFonnte) {
                // Official Fonnte WA Gateway format
                $payload = [
                    'target' => $phone,
                    'message' => $message,
                    'countryCode' => '62',
                    'delay' => $data['delay'] ?? rand(2, 4),
                ];

                $response = $this->httpClient->post($this->apiUrl, [
                    'headers' => [
                        'Authorization' => $this->token,
                    ],
                    'form_params' => $payload,
                ]);

                $statusCode = $response->getStatusCode();
                $bodyStr = (string) $response->getBody();
                Log::info("Fonnte WA Response ({$phone}): " . $bodyStr);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $json = json_decode($bodyStr, true);
                    if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false' || $json['status'] === 0)) {
                        $rawReason = $json['reason'] ?? $json['text'] ?? $json['message'] ?? $json['detail'] ?? $bodyStr;
                        $this->lastError = "Fonnte: {$rawReason}";
                        return $this->tryLocalFallback($phone, $message);
                    }
                    return true;
                }

                $this->lastError = "Server Fonnte merespons HTTP {$statusCode}";
                return $this->tryLocalFallback($phone, $message);
            }

            if ($isMhwa) {
                // Official MHWA Gateway format (https://mhwa.biz.id/api/message/send)
                $sessionId = !empty($this->senderPhone) ? $this->senderPhone : 'default';
                $response = $this->httpClient->post($this->apiUrl, [
                    'headers' => [
                        'x-api-key'     => $this->token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json, text/plain, */*',
                    ],
                    'json' => [
                        'session_id' => $sessionId,
                        'to'         => $phone,
                        'message'    => $message,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $bodyStr = (string) $response->getBody();
                Log::info("MHWA WA Response ({$phone}): " . $bodyStr);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $json = json_decode($bodyStr, true);
                    if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false' || $json['status'] === 'failed' || $json['status'] === 'error')) {
                        $this->lastError = "MHWA: " . ($json['message'] ?? $json['reason'] ?? $bodyStr);
                        return $this->tryLocalFallback($phone, $message);
                    }
                    if (isset($json['error']) && !empty($json['error'])) {
                        $err = is_string($json['error']) ? $json['error'] : json_encode($json['error']);
                        $this->lastError = "MHWA: " . $err;
                        return $this->tryLocalFallback($phone, $message);
                    }
                    return true;
                }

                $this->lastError = "Server MHWA merespons HTTP {$statusCode}";
                return $this->tryLocalFallback($phone, $message);
            }

            if ($isKirimi) {
                // Official Kirimi.id Gateway (https://api.kirimi.id/v1/send-message)
                $userCode = null;
                $deviceId = null;
                $secret = $this->token;

                $senderPhoneRaw = trim((string)$this->senderPhone);
                if (str_contains($senderPhoneRaw, ':')) {
                    [$uCode, $dId] = explode(':', $senderPhoneRaw, 2);
                    $userCode = trim($uCode);
                    $deviceId = trim($dId);
                } elseif (str_contains($senderPhoneRaw, '|')) {
                    [$uCode, $dId] = explode('|', $senderPhoneRaw, 2);
                    $userCode = trim($uCode);
                    $deviceId = trim($dId);
                } else {
                    $deviceId = $senderPhoneRaw;
                }

                if (empty($userCode) && str_contains((string)$this->token, ':')) {
                    [$uCode, $sec] = explode(':', (string)$this->token, 2);
                    $userCode = trim($uCode);
                    $secret = trim($sec);
                } elseif (empty($userCode) && str_contains((string)$this->token, '|')) {
                    [$uCode, $sec] = explode('|', (string)$this->token, 2);
                    $userCode = trim($uCode);
                    $secret = trim($sec);
                }

                $payload = [
                    'user_code' => $userCode ?: 'default',
                    'device_id' => $deviceId ?: 'default',
                    'secret'    => $secret,
                    'receiver'  => $phone,
                    'message'   => $message,
                ];

                if (!empty($data['media_url'])) {
                    $payload['media_url'] = $data['media_url'];
                }

                $response = $this->httpClient->post($this->apiUrl, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                $statusCode = $response->getStatusCode();
                $bodyStr = (string) $response->getBody();
                Log::info("Kirimi.id WA Response ({$phone}): " . $bodyStr);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $json = json_decode($bodyStr, true);
                    if (isset($json['success']) && ($json['success'] === false || $json['success'] === 'false' || $json['success'] === 0)) {
                        $this->lastError = "Kirimi.id: " . ($json['message'] ?? $bodyStr);
                        return false;
                    }
                    if (isset($json['status']) && ($json['status'] === 'error' || $json['status'] === 'failed' || $json['status'] === false)) {
                        $this->lastError = "Kirimi.id: " . ($json['message'] ?? $bodyStr);
                        return false;
                    }
                    return true;
                }

                $this->lastError = "Server Kirimi.id merespons HTTP {$statusCode}";
                return false;
            }

            if ($isWablas) {
                // Wablas Gateway format
                $response = $this->httpClient->post($this->apiUrl, [
                    'headers' => [
                        'Authorization' => $this->token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'phone' => $phone,
                        'message' => $message,
                        'data' => [
                            ['phone' => $phone, 'message' => $message]
                        ],
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $bodyStr = (string) $response->getBody();
                Log::info("Wablas WA Response ({$phone}): " . $bodyStr);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $json = json_decode($bodyStr, true);
                    if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false' || $json['status'] === 'failed')) {
                        $msg = $json['message'] ?? $json['reason'] ?? $bodyStr;
                        $this->lastError = "Wablas: " . $msg;
                        return false;
                    }
                    return true;
                }

                $this->lastError = "Server Wablas merespons HTTP {$statusCode}";
                return false;
            }

            if ($isStarsender) {
                // Starsender Gateway format
                $response = $this->httpClient->post($this->apiUrl, [
                    'headers' => [
                        'apikey' => $this->token,
                        'Authorization' => $this->token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'messageType' => 'text',
                        'to' => $phone,
                        'body' => $message,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $bodyStr = (string) $response->getBody();
                Log::info("Starsender WA Response ({$phone}): " . $bodyStr);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $json = json_decode($bodyStr, true);
                    if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false' || $json['status'] === 'failed')) {
                        $this->lastError = "Starsender: " . ($json['message'] ?? $json['reason'] ?? $bodyStr);
                        return false;
                    }
                    if (isset($json['success']) && ($json['success'] === false || $json['success'] === 'false')) {
                        $this->lastError = "Starsender: " . ($json['message'] ?? $bodyStr);
                        return false;
                    }
                    return true;
                }

                $this->lastError = "Server Starsender merespons HTTP {$statusCode}";
                return false;
            }

            if ($isWhaCenter) {
                // WhaCenter Gateway format
                $response = $this->httpClient->post($this->apiUrl, [
                    'form_params' => [
                        'device_id' => $this->senderPhone ?? '',
                        'number' => $phone,
                        'message' => $message,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $bodyStr = (string) $response->getBody();
                Log::info("WhaCenter WA Response ({$phone}): " . $bodyStr);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $json = json_decode($bodyStr, true);
                    if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false' || $json['status'] === 'failed')) {
                        $this->lastError = "WhaCenter: " . ($json['message'] ?? $json['reason'] ?? $bodyStr);
                        return false;
                    }
                    return true;
                }

                $this->lastError = "Server WhaCenter merespons HTTP {$statusCode}";
                return false;
            }

            // Generic Universal WA Gateway (MPWA, Baileys, WhatsVA, RuangWA, Watsap.id, NodeJS, PHP, etc.)
            $isBearer = str_starts_with(strtolower($this->token), 'bearer ');
            $rawToken = $isBearer ? trim(substr($this->token, 7)) : $this->token;
            $authHeader = $isBearer ? $this->token : (strlen($rawToken) > 45 || str_contains($rawToken, '.') ? 'Bearer ' . $rawToken : $rawToken);

            $universalPayload = array_merge($data, [
                'target'       => $phone,
                'phone'        => $phone,
                'number'       => $phone,
                'to'           => $phone,
                'receiver'     => $phone,
                'recipient'    => $phone,
                'destination'  => $phone,
                'nohp'         => $phone,
                'no_hp'        => $phone,
                'message'      => $message,
                'text'         => $message,
                'msg'          => $message,
                'pesan'        => $message,
                'body'         => $message,
                'token'        => $rawToken,
                'api_key'      => $rawToken,
                'apikey'       => $rawToken,
                'appkey'       => $rawToken,
                'key'          => $rawToken,
                'secret'       => $rawToken,
                'device_id'    => $this->senderPhone ?: $rawToken,
                'sender'       => $this->senderPhone ?: $rawToken,
                'from'         => $this->senderPhone ?: $rawToken,
                'sender_phone' => $this->senderPhone ?: $rawToken,
                'device'       => $this->senderPhone ?: $rawToken,
                'session'      => $this->senderPhone ?: $rawToken,
            ]);

            // Attempt 1: Form-urlencoded (Standard for MPWA, PHP $_POST, and Express urlencoded)
            try {
                $response = $this->httpClient->post($this->apiUrl, [
                    'headers' => [
                        'Authorization' => $authHeader,
                        'X-Api-Key'     => $rawToken,
                        'Accept'        => 'application/json, text/plain, */*',
                    ],
                    'form_params' => $universalPayload,
                ]);

                $statusCode = $response->getStatusCode();
                $bodyStr = (string) $response->getBody();
                Log::info("Generic WA Gateway Form Response ({$phone}): HTTP {$statusCode} - {$bodyStr}");

                if ($statusCode >= 200 && $statusCode < 300) {
                    $json = json_decode($bodyStr, true);
                    $isFailed = false;
                    if (is_array($json)) {
                        if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false' || $json['status'] === 'failed' || $json['status'] === 0)) $isFailed = true;
                        if (isset($json['success']) && ($json['success'] === false || $json['success'] === 'false' || $json['success'] === 0)) $isFailed = true;
                        if (isset($json['error']) && !empty($json['error']) && $json['error'] !== false) $isFailed = true;
                    }
                    if (!$isFailed) return true;
                }
            } catch (\Throwable $formEx) {
                // Fallback to JSON below
            }

            // Attempt 2: JSON format
            $response = $this->httpClient->post($this->apiUrl, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => $authHeader,
                    'X-Api-Key'     => $rawToken,
                    'Accept'        => 'application/json, text/plain, */*',
                ],
                'json' => $universalPayload,
            ]);

            $statusCode = $response->getStatusCode();
            $bodyStr = (string) $response->getBody();
            Log::info("Generic WA Gateway JSON Response ({$phone}): HTTP {$statusCode} - {$bodyStr}");

            if ($statusCode >= 200 && $statusCode < 300) {
                $json = json_decode($bodyStr, true);
                if (is_array($json)) {
                    if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false' || $json['status'] === 'failed' || $json['status'] === 0)) {
                        $this->lastError = "Gateway: " . ($json['message'] ?? $json['reason'] ?? $json['error'] ?? $bodyStr);
                        return false;
                    }
                    if (isset($json['success']) && ($json['success'] === false || $json['success'] === 'false' || $json['success'] === 0)) {
                        $this->lastError = "Gateway: " . ($json['message'] ?? $json['error'] ?? $bodyStr);
                        return false;
                    }
                    if (isset($json['error']) && !empty($json['error']) && $json['error'] !== false) {
                        $this->lastError = "Gateway: " . (is_string($json['error']) ? $json['error'] : json_encode($json['error']));
                        return false;
                    }
                }
                return true;
            }

            $this->lastError = "Gateway merespons HTTP {$statusCode}";
            return false;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $respBody = '';
            if ($e->hasResponse()) {
                $respBody = (string) $e->getResponse()->getBody();
                $statusCode = $e->getResponse()->getStatusCode();
                $json = json_decode($respBody, true);
                $rawReason = $json['error'] ?? $json['reason'] ?? $json['message'] ?? $json['text'] ?? $json['detail'] ?? $respBody;
                if (is_array($rawReason)) $rawReason = json_encode($rawReason);
                $reasonLower = strtolower((string) $rawReason);
                $humanReason = match(true) {
                    str_contains($reasonLower, 'perangkat tidak ditemukan') || str_contains($reasonLower, 'bukan milik') => 'Perangkat / Session ID tidak ditemukan di MHWA (Pastikan Session ID sesuai dengan sesi di dashboard mhwa.biz.id dan WhatsApp sudah terhubung)',
                    str_contains($reasonLower, 'device disconnected') => 'Perangkat WhatsApp terputus dari gateway (Silakan scan QR ulang di dashboard provider)',
                    str_contains($reasonLower, 'invalid token') || str_contains($reasonLower, 'unauthorized') || $statusCode === 401 || $statusCode === 403 => 'Token API / API Key WhatsApp tidak valid atau telah kedaluwarsa',
                    str_contains($reasonLower, 'not registered') => "Nomor ({$phone}) tidak terdaftar di WhatsApp",
                    str_contains($reasonLower, 'quota') || str_contains($reasonLower, 'limit') => 'Kuota pesan WhatsApp gateway habis',
                    default => $rawReason,
                };
                $this->lastError = "Gateway HTTP {$statusCode}: {$humanReason}";
            } else {
                $rawMsg = $e->getMessage();
                if (str_contains($rawMsg, 'cURL error 28') || str_contains(strtolower($rawMsg), 'timed out')) {
                    $this->lastError = "Gateway Timeout: Server provider WhatsApp ({$this->provider}) tidak merespons dalam 15 detik. Pastikan server provider dan koneksi WhatsApp di ponsel aktif.";
                } else {
                    $this->lastError = "Koneksi gateway gagal: " . $rawMsg;
                }
            }

            Log::error("[WhatsappService] Send failed ({$this->apiUrl}) to {$phone}: " . $this->lastError);

            // Automatic secondary fallback to local Baileys microservice on port 3022
            if (!str_contains($this->apiUrl, '127.0.0.1:3022')) {
                try {
                    $fallbackUrl = 'http://127.0.0.1:3022/api/send';
                    $fallbackKey = config('services.wa_gateway.api_key', 'nodera_wa_secret_key_2026');
                    $fbResp = (new \GuzzleHttp\Client(['timeout' => 3, 'connect_timeout' => 1]))->post($fallbackUrl, [
                        'headers' => [
                            'X-Api-Key'    => $fallbackKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'session_id' => 'wa_master_platform',
                            'phone'      => $phone,
                            'message'    => $message,
                        ],
                    ]);
                    if ($fbResp->getStatusCode() >= 200 && $fbResp->getStatusCode() < 300) {
                        Log::info("[WhatsappService] Delivered to {$phone} via local gateway fallback!");
                        return true;
                    }
                } catch (\Throwable $fbEx) {
                    Log::warning("[WhatsappService] Fallback gateway error: " . $fbEx->getMessage());
                }
            }

            return false;
        } catch (\Throwable $e) {
            $this->lastError = "Kesalahan gateway: " . $e->getMessage();
            Log::error("[WhatsappService] Exception send to {$phone}: " . $e->getMessage());

            // Automatic secondary fallback to local Baileys microservice on port 3022
            if (!str_contains($this->apiUrl, '127.0.0.1:3022')) {
                try {
                    $fallbackUrl = 'http://127.0.0.1:3022/api/send';
                    $fallbackKey = config('services.wa_gateway.api_key', 'nodera_wa_secret_key_2026');
                    $fbResp = (new \GuzzleHttp\Client(['timeout' => 3, 'connect_timeout' => 1]))->post($fallbackUrl, [
                        'headers' => [
                            'X-Api-Key'    => $fallbackKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'session_id' => 'wa_master_platform',
                            'phone'      => $phone,
                            'message'    => $message,
                        ],
                    ]);
                    if ($fbResp->getStatusCode() >= 200 && $fbResp->getStatusCode() < 300) {
                        Log::info("[WhatsappService] Delivered to {$phone} via local gateway fallback!");
                        return true;
                    }
                } catch (\Throwable $fbEx) {
                    Log::warning("[WhatsappService] Fallback gateway error: " . $fbEx->getMessage());
                }
            }

            return false;
        }
    }

    /**
     * Active Live Device & Session Status Check across all supported providers
     * (MHWA, Fonnte, Wablas, Starsender, Kirimi, WhaCenter, NODERA WA Gateway, RuangWA, etc.)
     */
    public function checkDeviceStatus(): array
    {
        if (empty($this->token)) {
            return [
                'is_configured' => false,
                'is_connected'  => false,
                'status'        => 'unconfigured',
                'status_label'  => 'Belum Dikonfigurasi',
                'provider'      => strtoupper($this->provider ?: 'NONE'),
                'sender_id'     => $this->senderPhone ?: '-',
                'details'       => 'Token atau konfigurasi WhatsApp Gateway belum diisi di menu Pengaturan.',
                'checked_at'    => now()->format('H:i:s, d M Y'),
            ];
        }

        $isNodera = str_contains($this->apiUrl, 'wa.dgtlnetsolution.com') || str_contains($this->apiUrl, '127.0.0.1:3022') || $this->provider === 'nodera' || $this->provider === 'nodera-gateway';
        $isFonnte = str_contains($this->apiUrl, 'fonnte.com') || $this->provider === 'fonnte';
        $isMhwa = str_contains($this->apiUrl, 'mhwa.biz.id') || $this->provider === 'mhwa';
        $isKirimi = str_contains($this->apiUrl, 'kirimi.id') || $this->provider === 'kirimi';
        $isWablas = str_contains($this->apiUrl, 'wablas.com') || $this->provider === 'wablas';
        $isStarsender = str_contains($this->apiUrl, 'starsender') || $this->provider === 'starsender';
        $isWhaCenter = str_contains($this->apiUrl, 'whacenter') || $this->provider === 'whacenter';

        $client = new \GuzzleHttp\Client([
            'timeout'         => 8,
            'connect_timeout' => 4,
            'verify'          => false,
            'http_errors'     => false,
            'headers'         => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                'Accept'     => 'application/json, text/plain, */*',
            ],
        ]);

        try {
            // 1. MHWA Gateway (Official endpoint: GET /api/devices)
            if ($isMhwa) {
                $sessionId = !empty($this->senderPhone) ? trim($this->senderPhone) : 'default';
                $baseUrl = preg_replace('#/api/.*$#', '', $this->apiUrl) ?: 'https://mhwa.biz.id';
                
                $resp = $client->get("{$baseUrl}/api/devices", [
                    'headers' => [
                        'x-api-key'    => $this->token,
                        'Content-Type' => 'application/json',
                    ],
                ]);

                $body = json_decode((string) $resp->getBody(), true);
                $statusCode = $resp->getStatusCode();

                if ($statusCode === 200 && is_array($body) && isset($body['data']) && is_array($body['data'])) {
                    $devices = $body['data'];
                    // Search matching device by session_id or device_name
                    $targetDevice = null;
                    foreach ($devices as $d) {
                        if (($d['session_id'] ?? '') === $sessionId || ($d['device_name'] ?? '') === $sessionId) {
                            $targetDevice = $d;
                            break;
                        }
                    }

                    // Fallback to first device if only 1 device exists and sessionId was empty
                    if (!$targetDevice && count($devices) === 1 && $sessionId === 'default') {
                        $targetDevice = $devices[0];
                    }

                    if ($targetDevice) {
                        $devStatus = strtolower((string) ($targetDevice['status'] ?? ''));
                        $devName = $targetDevice['device_name'] ?? $sessionId;
                        $realSession = $targetDevice['session_id'] ?? $sessionId;
                        $isConnected = in_array($devStatus, ['connected', 'active', 'authenticated', 'ready', 'online']);

                        if ($isConnected) {
                            return [
                                'is_configured' => true,
                                'is_connected'  => true,
                                'status'        => 'connected',
                                'status_label'  => 'Terhubung',
                                'provider'      => 'MHWA',
                                'sender_id'     => $realSession,
                                'details'       => "Sesi WhatsApp '{$devName}' ({$realSession}) aktif dan terhubung di server MHWA.",
                                'checked_at'    => now()->format('H:i:s, d M Y'),
                            ];
                        } else {
                            return [
                                'is_configured' => true,
                                'is_connected'  => false,
                                'status'        => 'disconnected',
                                'status_label'  => 'Terputus / Sesi Mati',
                                'provider'      => 'MHWA',
                                'sender_id'     => $realSession,
                                'details'       => "Sesi '{$devName}' ({$realSession}) terputus (Status: {$devStatus}) di MHWA. Silakan scan ulang QR di dashboard MHWA.",
                                'checked_at'    => now()->format('H:i:s, d M Y'),
                            ];
                        }
                    } else {
                        // Device not found in account
                        $availableList = array_map(fn($d) => ($d['device_name'] ?? '') . ' [' . ($d['session_id'] ?? '') . ']', $devices);
                        $availStr = !empty($availableList) ? implode(', ', $availableList) : 'Tidak ada device';
                        return [
                            'is_configured' => true,
                            'is_connected'  => false,
                            'status'        => 'disconnected',
                            'status_label'  => 'Session ID Tidak Ditemukan',
                            'provider'      => 'MHWA',
                            'sender_id'     => $sessionId,
                            'details'       => "Session ID '{$sessionId}' tidak ditemukan pada akun MHWA Anda. Device terdaftar: {$availStr}.",
                            'checked_at'    => now()->format('H:i:s, d M Y'),
                        ];
                    }
                }

                $errorMsg = $body['message'] ?? $body['error'] ?? "Server MHWA merespons HTTP {$statusCode}";
                return [
                    'is_configured' => true,
                    'is_connected'  => false,
                    'status'        => 'disconnected',
                    'status_label'  => 'Gagal Cek Status MHWA',
                    'provider'      => 'MHWA',
                    'sender_id'     => $sessionId,
                    'details'       => "Gagal menghubungi MHWA: {$errorMsg}",
                    'checked_at'    => now()->format('H:i:s, d M Y'),
                ];
            }

            // 2. Fonnte (https://api.fonnte.com/device)
            if ($isFonnte) {
                $resp = $client->post('https://api.fonnte.com/device', [
                    'headers' => [
                        'Authorization' => $this->token,
                    ],
                ]);
                $body = json_decode((string) $resp->getBody(), true);
                $deviceStatus = strtolower((string) ($body['device_status'] ?? ''));
                $devicePhone = $body['device'] ?? $body['sender'] ?? $this->senderPhone;

                if ($resp->getStatusCode() === 200 && ($deviceStatus === 'connect' || $deviceStatus === 'connected' || ($body['status'] ?? false) === true)) {
                    return [
                        'is_configured' => true,
                        'is_connected'  => true,
                        'status'        => 'connected',
                        'status_label'  => 'Terhubung',
                        'provider'      => 'FONNTE',
                        'sender_id'     => $devicePhone ?: 'Fonnte Connected',
                        'details'       => 'Perangkat WhatsApp terhubung ke server Fonnte.',
                        'checked_at'    => now()->format('H:i:s, d M Y'),
                    ];
                }

                $reason = $body['reason'] ?? $body['message'] ?? 'Perangkat Fonnte terputus atau token tidak valid';
                return [
                    'is_configured' => true,
                    'is_connected'  => false,
                    'status'        => 'disconnected',
                    'status_label'  => 'Terputus',
                    'provider'      => 'FONNTE',
                    'sender_id'     => $this->senderPhone ?: '-',
                    'details'       => "Fonnte: {$reason}. Pastikan WhatsApp di ponsel Anda aktif.",
                    'checked_at'    => now()->format('H:i:s, d M Y'),
                ];
            }

            // 3. NODERA WhatsApp Gateway (Port 3022 or wa.dgtlnetsolution.com)
            if ($isNodera) {
                $statusUrl = preg_replace('#/api/.*$#', '', $this->apiUrl) . '/api/status';
                $resp = $client->get($statusUrl, [
                    'headers' => [
                        'X-Api-Key'     => $this->token,
                        'Authorization' => 'Bearer ' . $this->token,
                        'Accept'        => 'application/json',
                    ],
                ]);
                $body = json_decode((string) $resp->getBody(), true);
                $st = strtolower((string) ($body['status'] ?? ''));
                $isConnected = ($st === 'connected' || $st === 'ready' || ($body['connected'] ?? false) === true);

                return [
                    'is_configured' => true,
                    'is_connected'  => $isConnected,
                    'status'        => $isConnected ? 'connected' : 'disconnected',
                    'status_label'  => $isConnected ? 'Terhubung' : 'Terputus',
                    'provider'      => 'NODERA GATEWAY',
                    'sender_id'     => $body['phone'] ?? $this->senderPhone ?? 'Baileys',
                    'details'       => $isConnected ? 'Sesi Baileys aktif dan terhubung.' : ($body['message'] ?? 'Sesi gateway belum tersambung.'),
                    'checked_at'    => now()->format('H:i:s, d M Y'),
                ];
            }

            // 4. Wablas
            if ($isWablas) {
                $domain = parse_url($this->apiUrl, PHP_URL_HOST) ?: 'kudus.wablas.com';
                $scheme = parse_url($this->apiUrl, PHP_URL_SCHEME) ?: 'https';
                $resp = $client->get("{$scheme}://{$domain}/api/device/info?token={$this->token}");
                $body = json_decode((string) $resp->getBody(), true);
                $st = strtolower((string) ($body['data']['status'] ?? ''));
                $isConnected = ($st === 'connected' || ($body['status'] ?? false) === true);

                return [
                    'is_configured' => true,
                    'is_connected'  => $isConnected,
                    'status'        => $isConnected ? 'connected' : 'disconnected',
                    'status_label'  => $isConnected ? 'Terhubung' : 'Terputus',
                    'provider'      => 'WABLAS',
                    'sender_id'     => $body['data']['sender'] ?? $this->senderPhone ?? 'Wablas',
                    'details'       => $isConnected ? 'Perangkat Wablas online.' : 'Perangkat Wablas offline / disconnected.',
                    'checked_at'    => now()->format('H:i:s, d M Y'),
                ];
            }

            // 5. Starsender
            if ($isStarsender) {
                $resp = $client->get('https://starsender.online/api/device/status', [
                    'headers' => ['apikey' => $this->token],
                ]);
                $body = json_decode((string) $resp->getBody(), true);
                $isConnected = ($body['status'] ?? false) === true || strtolower((string)($body['data']['status'] ?? '')) === 'connected';

                return [
                    'is_configured' => true,
                    'is_connected'  => $isConnected,
                    'status'        => $isConnected ? 'connected' : 'disconnected',
                    'status_label'  => $isConnected ? 'Terhubung' : 'Terputus',
                    'provider'      => 'STARSENDER',
                    'sender_id'     => $this->senderPhone ?: '-',
                    'details'       => $isConnected ? 'Perangkat Starsender online.' : 'Starsender offline.',
                    'checked_at'    => now()->format('H:i:s, d M Y'),
                ];
            }

            // 6. Generic Universal Gateway Fallback
            return [
                'is_configured' => true,
                'is_connected'  => true,
                'status'        => 'connected',
                'status_label'  => 'Terkonfigurasi',
                'provider'      => strtoupper($this->provider ?: 'GATEWAY'),
                'sender_id'     => $this->senderPhone ?: 'Custom Gateway',
                'details'       => 'Konfigurasi aktif. Silakan lakukan pengujian kirim pesan tes.',
                'checked_at'    => now()->format('H:i:s, d M Y'),
            ];
        } catch (\Throwable $e) {
            return [
                'is_configured' => true,
                'is_connected'  => false,
                'status'        => 'disconnected',
                'status_label'  => 'Gagal Terhubung',
                'provider'      => strtoupper($this->provider ?: 'GATEWAY'),
                'sender_id'     => $this->senderPhone ?: '-',
                'details'       => 'Tidak dapat menjangkau server provider WhatsApp: ' . $e->getMessage(),
                'checked_at'    => now()->format('H:i:s, d M Y'),
            ];
        }
    }
}

