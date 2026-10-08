<?php

namespace App\Services;

use App\Models\MikhmonSubscription;
use App\Models\NoderaPaySubscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MikhmonProvisioner
{
    /**
     * Deploy template Mikhmon ke folder subdomain + tulis license.
     * Lokasi: {config('mikhmon.base_path')}/{subdomain}/
     */
    public function deploy(MikhmonSubscription $sub, ?Carbon $expiresAt = null): array
    {
        $subdomain = $sub->subdomain;
        $target = $this->targetPath($subdomain);

        try {
            // 1. Salin template (tidak menimpa config user bila sudah ada)
            //    ROS6 → mikhmon-template (Mikhmon lama)
            //    ROS7 → mikhmon-template-v7 (mikhmon-agent, alijayanet/mikhmon-agent)
            $isRos7 = ($sub->ros_version ?? '6') === '7';
            $template = resource_path($isRos7 ? 'mikhmon-template-v7' : 'mikhmon-template');
            if (! File::isDirectory($template)) {
                return ['success' => false, 'error' => "Template Mikhmon " . ($isRos7 ? 'v7 (mikhmon-agent)' : 'v6') . " tidak ditemukan di {$template}"];
            }

            File::ensureDirectoryExists($target);

            // Backup file konfigurasi user sebelum copy (biar ga ketimpa template)
            $userFiles = [
                'include/config.php', 
                'include/location_config.php',
                'include/quickbt.php', 
                'include/telegram_config.php', 
                'include/voucher_config.php',
                'include/whatsapp_config.php',
                'include/noderapay_config.php',
                'include/orders_data.json',
                'include/warung_data.json',
                'include/warung_config.php'
            ];
            $backup = [];
            foreach ($userFiles as $uf) {
                $src = $target . '/' . $uf;
                if (File::exists($src)) {
                    $backup[$uf] = File::get($src);
                }
            }

            // Jika folder target tidak memiliki router di config.php, cari dari folder kandidat (mikhmon-*, hotspot-*, dll)
            $targetHasRouters = !empty($backup['include/config.php']) && preg_match('/\$data\[[\'"](?!mikhmon)[^\'"]+[\'"]\]/i', $backup['include/config.php']);
            if (!$targetHasRouters) {
                $siblingConfigs = $this->findExistingUserConfigFiles($subdomain);
                if (!empty($siblingConfigs)) {
                    $backup = array_merge($backup, $siblingConfigs);
                }
            }

            File::copyDirectory($template, $target);

            // Pulihkan konfigurasi user (password admin, router sessions, QR/logo)
            foreach ($backup as $uf => $content) {
                File::ensureDirectoryExists(dirname($target . '/' . $uf));
                File::put($target . '/' . $uf, $content);
            }

            // Auto-heal konfigurasi jika masih kosong
            $this->healAndRestoreUserConfigs($target, $subdomain);

            // Enforce Indonesian language setting for Mikhmon instances
            File::ensureDirectoryExists($target . '/include');
            File::put($target . '/include/lang.php', '<?php $langid="id";?>');
            // Ensure location config is populated from sessions if empty
            $this->ensureLocationConfig($target);

            // Ensure WhatsApp Gateway config is populated and synchronized
            $this->ensureWhatsAppConfig($target, $subdomain);

            // Ensure Telegram Bot config is populated and synchronized
            $this->ensureTelegramConfig($target, $subdomain);

            // Ensure NODERA Pay config is populated from subscriptions
            $this->ensureNoderaPayConfig($target, $subdomain);

            // 2. Tulis license (masa aktif + branding)
            $this->writeLicense($target, $sub, $expiresAt);

            return ['success' => true, 'path' => $target];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function writeLicense(string $target, MikhmonSubscription $sub, ?Carbon $expiresAt = null): void
    {
        $expiry = ($expiresAt ?? $sub->expires_at ?? now()->addMonth())->format('Y-m-d');
        $status = $sub->status ?? 'ACTIVE';
        $content = "<?php\n"
            . "/**\n"
            . " * LICENSE — ditulis otomatis oleh NODERA (dgtlnetsolution.com).\n"
            . " * Jangan edit manual.\n"
            . " */\n"
            . "define('MIKHMON_STATUS', " . var_export($status, true) . ");\n"
            . "define('MIKHMON_EXPIRY', " . var_export($expiry, true) . "); // format Y-m-d — masa aktif berlangganan\n"
            . "define('MIKHMON_BRAND', 'by dgtlnetsolution.com');\n"
            . "define('MIKHMON_SUBDOMAIN', " . var_export($sub->subdomain, true) . ");\n";

        File::ensureDirectoryExists($target . '/config');
        File::put($target . '/config/license.php', $content);
    }

    public function writeDefaultLicense(string $target, string $subdomain, ?string $expiry = null, string $status = 'ACTIVE'): void
    {
        $expiry = $expiry ?: now()->addMonth()->format('Y-m-d');
        $content = "<?php\n"
            . "/**\n"
            . " * LICENSE — ditulis otomatis oleh NODERA (dgtlnetsolution.com).\n"
            . " * Jangan edit manual.\n"
            . " */\n"
            . "define('MIKHMON_STATUS', " . var_export($status, true) . ");\n"
            . "define('MIKHMON_EXPIRY', " . var_export($expiry, true) . ");\n"
            . "define('MIKHMON_BRAND', 'by dgtlnetsolution.com');\n"
            . "define('MIKHMON_SUBDOMAIN', " . var_export($subdomain, true) . ");\n";

        File::ensureDirectoryExists($target . '/config');
        File::put($target . '/config/license.php', $content);
    }

    /**
     * Pastikan file include/location_config.php terisi nama lokasi untuk semua session di config.php
     * dan primary session terpilih sehingga nama lokasi tidak pernah kosong.
     */
    public function ensureLocationConfig(string $target): void
    {
        $configFile = $target . '/include/config.php';
        $locFile = $target . '/include/location_config.php';

        if (!File::exists($configFile)) {
            return;
        }

        // Proteksi mutlak: Jika file location_config.php sudah ada dan berisi konfigurasi user, jangan disentuh sama sekali!
        if (File::exists($locFile) && filesize($locFile) > 20) {
            return;
        }

        $locationData = ['primary' => '', 'locations' => []];
        if (File::exists($locFile)) {
            $parsed = $this->safelyReadConfigFile($locFile, 'location_data');
            if (is_array($parsed)) {
                $locationData = array_merge($locationData, $parsed);
            }
        }
        if (!isset($locationData['locations']) || !is_array($locationData['locations'])) {
            $locationData['locations'] = [];
        }

        $sessionsFound = [];
        try {
            $lines = file($configFile);
            foreach ($lines as $line) {
                if (preg_match('/\$data\[[\'"]([^\'"]+)[\'"]\]\s*=\s*array\s*\((.*)\);/s', $line, $m)) {
                    $sName = $m[1];
                    if ($sName !== 'mikhmon') {
                        $hsName = '';
                        if (preg_match('/%([^%\'"]*)/', $m[2], $hsm)) {
                            $hsName = trim($hsm[1]);
                        }
                        $cleanHs = (strtolower($hsName) === 'dns' || empty($hsName)) ? '' : $hsName;
                        $sessionsFound[$sName] = $cleanHs ?: ucwords(str_replace(['-', '_'], ' ', $sName));
                    }
                }
            }
        } catch (\Throwable $e) {}

        if (!empty($sessionsFound)) {
            $cleanedLocations = [];
            $changed = false;
            foreach ($sessionsFound as $sName => $defaultName) {
                $existing = trim($locationData['locations'][$sName] ?? '');
                if (!empty($existing) && strtolower($existing) !== 'dns') {
                    $cleanedLocations[$sName] = $existing;
                } else {
                    $cleanedLocations[$sName] = $defaultName;
                    $changed = true;
                }
            }
            if ($cleanedLocations !== ($locationData['locations'] ?? [])) {
                $changed = true;
            }
            $locationData['locations'] = $cleanedLocations;

            if (empty($locationData['primary']) || !isset($sessionsFound[$locationData['primary']])) {
                $locationData['primary'] = array_key_first($sessionsFound);
                $changed = true;
            }

            if ($changed || !File::exists($locFile)) {
                File::ensureDirectoryExists(dirname($locFile));
                $locOut = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"location_config.php\"){header(\"Location:./\");};\n\$location_data = " . var_export($locationData, true) . ";\n";
                File::put($locFile, $locOut);
            }
        }
    }

    /**
     * Pastikan file include/noderapay_config.php otomatis terisi API Key, Secret Key, dan API URL
     * dari langganan NODERA Pay / Mikhmon milik user sehingga user tidak perlu input manual.
     */
    public function ensureNoderaPayConfig(string $target, ?string $subdomain = null): void
    {
        $configFile = $target . '/include/config.php';
        $npConfigFile = $target . '/include/noderapay_config.php';

        if (!File::exists($configFile)) {
            return;
        }

        $subname = $subdomain ?: basename($target);
        $cleanSlug = preg_replace('/^(mikhmon|hotspot)[-_]/', '', strtolower(trim($subname)));

        // 1. Cari data langganan Mikhmon & NODERA Pay
        $mikhmonSub = null;
        try {
            $mikhmonSub = MikhmonSubscription::where('subdomain', $subname)
                ->orWhere('subdomain', $cleanSlug)
                ->orWhere('subdomain', 'mikhmon-' . $cleanSlug)
                ->orWhere('subdomain', 'hotspot-' . $cleanSlug)
                ->first();
        } catch (\Throwable $e) {}

        $tenant = null;
        try {
            $tenant = Tenant::where('slug', $cleanSlug)
                ->orWhere('slug', $subname)
                ->first();
        } catch (\Throwable $e) {}

        $npSub = null;
        try {
            if ($mikhmonSub) {
                $npSub = NoderaPaySubscription::where('mikhmon_subscription_id', $mikhmonSub->id)
                    ->where('status', 'ACTIVE')
                    ->first();
                if (!$npSub && $mikhmonSub->vpn_user_id) {
                    $npSub = NoderaPaySubscription::where('vpn_user_id', $mikhmonSub->vpn_user_id)
                        ->where('status', 'ACTIVE')
                        ->latest()
                        ->first();
                }
                if (!$npSub && $mikhmonSub->tenant_id) {
                    $npSub = NoderaPaySubscription::where('tenant_id', $mikhmonSub->tenant_id)
                        ->where('status', 'ACTIVE')
                        ->latest()
                        ->first();
                }
            }
            if (!$npSub && $tenant) {
                $npSub = NoderaPaySubscription::where('tenant_id', $tenant->id)
                    ->where('status', 'ACTIVE')
                    ->latest()
                    ->first();
                if (!$npSub && $tenant->vpn_user_id) {
                    $npSub = NoderaPaySubscription::where('vpn_user_id', $tenant->vpn_user_id)
                        ->where('status', 'ACTIVE')
                        ->latest()
                        ->first();
                }
            }
            if (!$npSub && !empty($cleanSlug)) {
                $npSub = NoderaPaySubscription::where('status', 'ACTIVE')
                    ->where(function ($q) use ($cleanSlug, $subname) {
                        $q->where('name', 'like', "%{$cleanSlug}%")
                          ->orWhere('name', 'like', "%{$subname}%");
                    })
                    ->latest()
                    ->first();
            }

            // Jika belum ada subscription sama sekali, buatkan otomatis
            if (!$npSub) {
                $userId = $mikhmonSub?->vpn_user_id ?? $tenant?->vpn_user_id;
                $tenantId = $mikhmonSub?->tenant_id ?? $tenant?->id;
                $name = $tenant?->name ?? ($mikhmonSub ? "Hotspot " . ucfirst($cleanSlug) : "Hotspot " . ucfirst($cleanSlug ?: 'NODERA'));
                $apiKey = 'np_live_' . Str::random(36);
                $secretKey = 'sec_' . Str::random(32);

                $rawString = null;
                $imagePath = null;
                $merchantName = $name;

                $npSub = NoderaPaySubscription::create([
                    'vpn_user_id'             => $userId,
                    'tenant_id'               => $tenantId,
                    'package_type'            => 'bundle',
                    'mikhmon_subscription_id' => $mikhmonSub?->id,
                    'name'                    => $name,
                    'merchant_name'           => $merchantName,
                    'merchant_city'           => $merchantInfo['merchant_city'] ?? null,
                    'nmid'                    => $merchantInfo['nmid'] ?? null,
                    'qris_raw_string'         => $rawString,
                    'qris_image_path'         => $imagePath,
                    'api_key'                 => $apiKey,
                    'secret_key'              => $secretKey,
                    'price'                   => 0,
                    'status'                  => 'ACTIVE',
                    'expires_at'              => now()->addYears(5),
                    'order_date'              => now(),
                    'last_billed_at'          => now(),
                ]);
            }
        } catch (\Throwable $e) {}

        if (!$npSub || empty($npSub->api_key)) {
            return;
        }

        // 2. Baca sessions dan hotspot names dari config.php
        $sessionsFound = [];
        $sessionHotspotNames = [];
        try {
            $lines = file($configFile);
            foreach ($lines as $line) {
                if (preg_match('/\$data\[[\'"]([^\'"]+)[\'"]\]\s*=\s*array\s*\((.*)\);/s', $line, $m)) {
                    $sName = $m[1];
                    if ($sName !== 'mikhmon') {
                        $sessionsFound[] = $sName;
                        $hsName = '';
                        if (preg_match('/%([^%\'"]*)/', $m[2], $hsm)) {
                            $hsName = trim($hsm[1]);
                        }
                        $cleanHs = (strtolower($hsName) === 'dns' || empty($hsName)) ? '' : $hsName;
                        $sessionHotspotNames[$sName] = $cleanHs ?: ucwords(str_replace(['-', '_'], ' ', $sName));
                    }
                }
            }
        } catch (\Throwable $e) {}

        if (empty($sessionsFound)) {
            $sessionsFound[] = 'default';
        }

        // 3. Baca existing noderapay_config.php
        $noderapayData = [];
        if (File::exists($npConfigFile)) {
            $parsed = $this->safelyReadConfigFile($npConfigFile, 'noderapay_data');
            if (is_array($parsed)) {
                $noderapayData = $parsed;
            }
        }

        $defaultApiUrl = "https://gateway.dgtlnetsolution.com/api/v1/noderapay";

        $tag59Merchant = null;
        if (!empty($npSub->qris_raw_string)) {
            $merchantInfo = QrisDynamicService::extractMerchantInfo($npSub->qris_raw_string);
            if (!empty($merchantInfo['merchant_name'])) {
                $tag59Merchant = $merchantInfo['merchant_name'];
            }
        }
        $excludedMerchants = [
            'NODERA Pay Billing', 'NODERA Pay', 'NODERA HOTSPOT', 'WiFi Hotspot', 'Wave Merchant',
            'CV. Digital Network Solut', 'CV. DIGITAL NETWORK SOLUT', 'CV. Digital Network Solution',
            'CV. DIGITAL NETWORK SOLUTION', 'DIGITAL NETWORK SOLUTION', 'DgtlNet', 'NODERA', 'by dgtlnetsolution.com'
        ];
        if (in_array(trim($tag59Merchant ?? ''), $excludedMerchants)) {
            $tag59Merchant = null;
        }

        $bestSubMerchant = $tag59Merchant ?: ((!empty($npSub->merchant_name) && !in_array($npSub->merchant_name, $excludedMerchants)) ? $npSub->merchant_name : null);

        $cleanedNpData = [];
        if (!empty($noderapayData['default']) && is_array($noderapayData['default'])) {
            $cleanedNpData['default'] = $noderapayData['default'];
        }

        foreach ($sessionsFound as $sName) {
            $curr = (isset($noderapayData[$sName]) && is_array($noderapayData[$sName])) ? $noderapayData[$sName] : [];
            $defaultSessionHs = $sessionHotspotNames[$sName] ?? ucwords(str_replace(['-', '_'], ' ', $sName));
            $resolvedMerchant = (!empty($curr['merchant_name']) && !in_array($curr['merchant_name'], $excludedMerchants))
                ? $curr['merchant_name']
                : ($bestSubMerchant ?: $defaultSessionHs);

            $merged = array_merge([
                'enabled'          => 'yes',
                'payment_mode'     => 'noderapay',
                'portal_theme'     => 'stripe',
                'gateway_provider' => 'tripay',
                'gateway_env'      => 'sandbox',
                'gateway_config'   => [],
                'wave_static_qr'   => '',
                'api_key'          => $npSub->api_key,
                'secret_key'       => $npSub->secret_key,
                'api_url'          => $defaultApiUrl,
                'merchant_name'    => $resolvedMerchant,
                'store_title'      => 'Voucher WiFi Online',
                'store_subtitle'   => 'Internet Cepat, Murah & Aktif Otomatis',
                'stock_mode'       => 'unused_pool',
                'voucher_prefix'   => 'VC-',
                'char_length'      => '6',
                'profile_mode'     => 'all',
                'allowed_profiles' => [],
            ], $curr);

            if (empty($merged['api_key']) && !empty($npSub->api_key)) {
                $merged['api_key'] = $npSub->api_key;
            }
            if (empty($merged['secret_key']) && !empty($npSub->secret_key)) {
                $merged['secret_key'] = $npSub->secret_key;
            }

            $cleanedNpData[$sName] = $merged;
        }

        // Pastikan 'default' juga ada
        $primaryHsName = !empty($sessionHotspotNames) ? reset($sessionHotspotNames) : 'WiFi Hotspot';
        $defaultMerchant = $bestSubMerchant
            ?: (!empty($cleanedNpData['default']['merchant_name']) && $cleanedNpData['default']['merchant_name'] !== 'NODERA Pay Billing' && $cleanedNpData['default']['merchant_name'] !== 'NODERA Pay'
                ? $cleanedNpData['default']['merchant_name']
                : $primaryHsName);

        if (empty($cleanedNpData['default'])) {
            $cleanedNpData['default'] = [
                'enabled'          => 'yes',
                'payment_mode'     => 'noderapay',
                'portal_theme'     => 'stripe',
                'gateway_provider' => 'tripay',
                'gateway_env'      => 'sandbox',
                'gateway_config'   => [],
                'wave_static_qr'   => '',
                'api_key'          => $npSub->api_key,
                'secret_key'       => $npSub->secret_key,
                'api_url'          => $defaultApiUrl,
                'merchant_name'    => $defaultMerchant,
                'store_title'      => 'Voucher WiFi Online',
                'store_subtitle'   => 'Internet Cepat, Murah & Aktif Otomatis',
                'stock_mode'       => 'unused_pool',
                'voucher_prefix'   => 'VC-',
                'char_length'      => '6',
                'profile_mode'     => 'all',
                'allowed_profiles' => [],
            ];
        } else {
            $cleanedNpData['default'] = array_merge([
                'payment_mode'     => 'noderapay',
                'portal_theme'     => 'stripe',
                'gateway_provider' => 'tripay',
                'gateway_env'      => 'sandbox',
                'gateway_config'   => [],
                'wave_static_qr'   => '',
            ], $cleanedNpData['default']);
            if (empty($cleanedNpData['default']['merchant_name'])) {
                $cleanedNpData['default']['merchant_name'] = $defaultMerchant;
            }
        }

        $noderapayData = $cleanedNpData;

        File::ensureDirectoryExists(dirname($npConfigFile));
        $out = "<?php\n";
        $out .= 'if(substr($_SERVER["REQUEST_URI"], -20) == "noderapay_config.php"){header("Location:./");};' . "\n";
        $out .= '$noderapay_data = ' . var_export($noderapayData, true) . ";\n";
        File::put($npConfigFile, $out);
    }

    /**
     * Pastikan WhatsApp Gateway tidak pernah kosong / hilang saat switch session atau deploy.
     */
    public function ensureWhatsAppConfig(string $target, ?string $subdomain = null): void
    {
        $configFile = $target . '/include/config.php';
        $waFile = $target . '/include/whatsapp_config.php';

        // 🔒 STRICT: Jika file whatsapp_config.php sudah ada dan valid, JANGAN PERNAH sentuh atau modifikasi
        if (File::exists($waFile) && filesize($waFile) > 20) {
            return;
        }

        if (!File::exists($configFile)) {
            return;
        }

        $subname = $subdomain ?: basename($target);
        $cleanSlug = preg_replace('/^(mikhmon|hotspot)[-_]/', '', strtolower(trim($subname)));
        $tenant = null;
        try {
            $tenant = Tenant::where('slug', $cleanSlug)->orWhere('slug', $subname)->first();
        } catch (\Throwable $e) {}
        $tenantId = $tenant?->id;

        // Ambil Global SuperAdmin WA Token & Tenant WA Token dari DB untuk validasi isolasi
        $globalToken = null;
        $tenantToken = null;
        $tenantUrl = null;
        try {
            $globalToken = \Illuminate\Support\Facades\DB::table('settings')
                ->where('key', 'WHATSAPP_TOKEN')
                ->whereNull('tenant_id')
                ->value('value');
            if (empty($globalToken)) {
                $globalToken = env('WHATSAPP_TOKEN') ?: env('FONNTE_TOKEN') ?: config('services.whatsapp.token');
            }

            if ($tenantId) {
                $tenantToken = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'WHATSAPP_TOKEN')
                    ->where('tenant_id', $tenantId)
                    ->value('value');

                $tenantUrl = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'WHATSAPP_API_URL')
                    ->where('tenant_id', $tenantId)
                    ->value('value');
            }
        } catch (\Throwable $e) {}

        $waData = [];
        if (File::exists($waFile)) {
            $parsed = $this->safelyReadConfigFile($waFile, 'wa_data');
            if (is_array($parsed)) {
                $waData = $parsed;
            }
        }

        // 🔒 PURGE LEAKED SUPERADMIN TOKEN:
        // Jika file whatsapp_config.php yang ada berisi SuperAdmin token padahal tenant tidak mengonfigurasinya
        if (!empty($globalToken) && !empty($waData) && ($tenantToken !== $globalToken)) {
            $purged = false;
            foreach ($waData as $s => $c) {
                if (is_array($c) && isset($c['api_token']) && $c['api_token'] === $globalToken) {
                    $waData[$s]['api_token'] = $tenantToken ?: '';
                    if (empty($tenantToken)) {
                        $waData[$s]['enabled'] = 'no';
                    }
                    $purged = true;
                }
            }
            if ($purged) {
                File::ensureDirectoryExists(dirname($waFile));
                $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"whatsapp_config.php\"){header(\"Location:./\");};\n\$wa_data = " . var_export($waData, true) . ";\n";
                File::put($waFile, $out);
            }
        }

        // Jika file sudah ada di disk dan valid (>20 bytes), jangan override setting manual tenant
        if (File::exists($waFile) && filesize($waFile) > 20) {
            return;
        }

        // Jika config belum ada di disk, HANYA generate jika tenant memiliki setting WA sendiri
        if (!empty($tenantToken)) {
            $validCfg = [
                'enabled'            => 'yes',
                'provider'           => 'fonnte',
                'api_url'            => $tenantUrl ?: 'https://api.fonnte.com/send',
                'api_token'          => $tenantToken,
                'admin_phone'        => '',
                'cs_phone'           => '',
                'brand_name'         => $tenant?->name ?? 'NODERA WIFI',
                'notif_admin_sale'   => 'yes',
                'notif_daily_report' => 'yes',
                'buyer_msg_template' => "*PEMBAYARAN BERHASIL — {brand}*\n----------------------------------------\nTerima kasih telah membeli voucher WiFi.\n\nPaket : {package}\nTotal Bayar : Rp {price}\n\nDETAIL LOGIN :\n{login_detail}\n\nSimpan pesan ini jika sewaktu-waktu perangkat Anda terputus.",
            ];

            $sessionsFound = [];
            try {
                $lines = file($configFile);
                foreach ($lines as $line) {
                    if (preg_match('/\$data\[[\'"]([^\'"]+)[\'"]\]\s*=\s*array\s*\((.*)\);/s', $line, $m)) {
                        $sName = $m[1];
                        if ($sName !== 'mikhmon') $sessionsFound[] = $sName;
                    }
                }
            } catch (\Throwable $e) {}

            $cleanedWaData = ['_global' => $validCfg];
            foreach ($sessionsFound as $sName) {
                $cleanedWaData[$sName] = $validCfg;
            }

            File::ensureDirectoryExists(dirname($waFile));
            $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"whatsapp_config.php\"){header(\"Location:./\");};\n\$wa_data = " . var_export($cleanedWaData, true) . ";\n";
            File::put($waFile, $out);
        }
    }

    /**
     * Pastikan Telegram Bot tidak pernah kosong / hilang saat switch session atau deploy.
     */
    public function ensureTelegramConfig(string $target, ?string $subdomain = null): void
    {
        $configFile = $target . '/include/config.php';
        $tgFile = $target . '/include/telegram_config.php';

        // 🔒 STRICT: Jika file telegram_config.php sudah ada dan valid, JANGAN PERNAH sentuh atau modifikasi
        if (File::exists($tgFile) && filesize($tgFile) > 20) {
            return;
        }

        if (!File::exists($configFile)) {
            return;
        }

        $subname = $subdomain ?: basename($target);
        $cleanSlug = preg_replace('/^(mikhmon|hotspot)[-_]/', '', strtolower(trim($subname)));
        $tenant = null;
        try {
            $tenant = Tenant::where('slug', $cleanSlug)->orWhere('slug', $subname)->first();
        } catch (\Throwable $e) {}
        $tenantId = $tenant?->id;

        // Ambil Global SuperAdmin Telegram Token & Tenant Token dari DB
        $globalTgToken = null;
        $tenantTgToken = null;
        $tenantChatIds = null;
        try {
            $globalTgToken = \Illuminate\Support\Facades\DB::table('settings')
                ->where('key', 'TELEGRAM_BOT_TOKEN')
                ->whereNull('tenant_id')
                ->value('value');
            if (empty($globalTgToken)) {
                $globalTgToken = env('TELEGRAM_BOT_TOKEN') ?: config('services.telegram.bot_token');
            }

            if ($tenantId) {
                $tenantTgToken = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'TELEGRAM_BOT_TOKEN')
                    ->where('tenant_id', $tenantId)
                    ->value('value');

                $tenantChatIds = \Illuminate\Support\Facades\DB::table('settings')
                    ->where('key', 'TELEGRAM_ADMIN_CHAT_IDS')
                    ->where('tenant_id', $tenantId)
                    ->value('value');
            }
        } catch (\Throwable $e) {}

        $tgData = [];
        if (File::exists($tgFile)) {
            $parsed = $this->safelyReadConfigFile($tgFile, 'tg_data');
            if (is_array($parsed)) {
                $tgData = $parsed;
            }
        }

        // 🔒 PURGE LEAKED SUPERADMIN TELEGRAM TOKEN:
        if (!empty($globalTgToken) && !empty($tgData) && ($tenantTgToken !== $globalTgToken)) {
            $purged = false;
            foreach ($tgData as $s => $c) {
                if (is_array($c) && isset($c['bot_token']) && $c['bot_token'] === $globalTgToken) {
                    $tgData[$s]['bot_token'] = $tenantTgToken ?: '';
                    if (empty($tenantTgToken)) {
                        $tgData[$s]['enabled'] = 'no';
                    }
                    $purged = true;
                }
            }
            if ($purged) {
                File::ensureDirectoryExists(dirname($tgFile));
                $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"telegram_config.php\"){header(\"Location:./\");};\n\$tg_data = " . var_export($tgData, true) . ";\n";
                File::put($tgFile, $out);
            }
        }

        if (File::exists($tgFile) && filesize($tgFile) > 20) {
            return;
        }

        if (!empty($tenantTgToken)) {
            $chatId = trim(explode(',', (string)$tenantChatIds)[0] ?? '');
            $validCfg = [
                'enabled'            => 'yes',
                'bot_token'          => $tenantTgToken,
                'mode'               => 'chat',
                'chat_id'            => $chatId,
                'group_chat_id'      => str_starts_with($chatId, '-') ? $chatId : '',
                'topic_id'           => '',
                'admin_chat_id'      => $chatId,
                'brand_name'         => $tenant?->name ?? 'NODERA',
                'bank_info'          => "Bank BCA: 1234567890 (a.n Digital Solution)\nDANA / GoPay: 081234567890",
                'notif_login'        => 'yes',
                'notif_logout'       => 'no',
                'notif_daily_report' => 'yes',
                'notif_manual_order' => 'yes',
            ];

            $sessionsFound = [];
            try {
                $lines = file($configFile);
                foreach ($lines as $line) {
                    if (preg_match('/\$data\[[\'"]([^\'"]+)[\'"]\]\s*=\s*array\s*\((.*)\);/s', $line, $m)) {
                        $sName = $m[1];
                        if ($sName !== 'mikhmon') $sessionsFound[] = $sName;
                    }
                }
            } catch (\Throwable $e) {}

            $cleanedTgData = ['_global' => $validCfg];
            foreach ($sessionsFound as $sName) {
                $cleanedTgData[$sName] = $validCfg;
            }

            File::ensureDirectoryExists(dirname($tgFile));
            $out = "<?php\nif(substr(\$_SERVER[\"REQUEST_URI\"], -19) == \"telegram_config.php\"){header(\"Location:./\");};\n\$tg_data = " . var_export($cleanedTgData, true) . ";\n";
            File::put($tgFile, $out);
        }
    }

    /**
     * Safely parse a PHP array variable from a config file without executing
     * raw includes or triggering function redeclaration collisions / side effects.
     */
    public function safelyReadConfigFile(string $filePath, string $varName): ?array
    {
        if (!File::exists($filePath)) {
            return null;
        }

        try {
            $isolatedReader = function (string $file, string $var): ?array {
                $content = @file_get_contents($file);
                if ($content === false || empty($content)) {
                    return null;
                }
                
                // Extract array definition safely inside isolated sandbox
                $data = null;
                @include $file;
                return isset($$var) && is_array($$var) ? $$var : null;
            };

            return $isolatedReader($filePath, $varName);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[MikhmonProvisioner] Failed to read config file {$filePath}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync updated application template code files to an existing deployment
     * while preserving user config, sessions, and license.
     */
    public function syncCode(string $target, ?string $rosVersion = null): void
    {
        $subname = basename($target);
        if ($rosVersion === null) {
            $sub = MikhmonSubscription::where('subdomain', $subname)->first();
            $rosVersion = $sub->ros_version ?? '6';
        }

        $isRos7 = ($rosVersion === '7');
        $template = resource_path($isRos7 ? 'mikhmon-template-v7' : 'mikhmon-template');
        if (! File::isDirectory($template) || ! File::isDirectory($target)) {
            return;
        }

        $codeDirs = ['api', 'dashboard', 'dhcp', 'hotspot', 'hotspot7', 'include', 'lang', 'lib', 'logs', 'pages', 'process', 'report', 'report-v7', 'scripts', 'settings', 'status', 'system', 'traffic', 'voucher', 'agent', 'agent-admin', 'css', 'js', 'img'];
        foreach ($codeDirs as $dir) {
            $srcDir = $template . '/' . $dir;
            $dstDir = $target . '/' . $dir;
            if (File::isDirectory($srcDir)) {
                File::ensureDirectoryExists($dstDir);
                if ($dir === 'include') {
                    // File user yang TIDAK boleh ditimpa dari template
                    $userFilesToKeep = ['config.php', 'location_config.php', 'quickbt.php', 'telegram_config.php', 'voucher_config.php', 'whatsapp_config.php', 'noderapay_config.php', 'logo_config.php', 'orders_data.json', 'warung_data.json', 'warung_config.php', 'autoclean_config.php'];

                    // Backup config.php jika berisi router (untuk recovery)
                    $cfgPath = $dstDir . '/config.php';
                    if (File::exists($cfgPath)) {
                        $cfgContent = File::get($cfgPath);
                        if (preg_match('/\$data\[[\'"](?!mikhmon)[^\'"]+[\'"]\]/i', $cfgContent)) {
                            @File::put($dstDir . '/config.php.bak', $cfgContent);
                        }
                    }

                    // Copy file template satu per satu — skip file milik user
                    foreach (File::allFiles($srcDir) as $srcFile) {
                        $relativePath = $srcFile->getRelativePathname();
                        $baseName = $srcFile->getFilename();
                        // Jangan timpa file config user
                        if (in_array($baseName, $userFilesToKeep) || str_ends_with($baseName, '_config.php') || str_ends_with($baseName, '.json')) {
                            // Hanya copy jika belum ada di target (fresh install)
                            if (!File::exists($dstDir . '/' . $relativePath)) {
                                File::ensureDirectoryExists(dirname($dstDir . '/' . $relativePath));
                                File::copy($srcFile->getPathname(), $dstDir . '/' . $relativePath);
                            }
                            continue;
                        }
                        File::ensureDirectoryExists(dirname($dstDir . '/' . $relativePath));
                        File::copy($srcFile->getPathname(), $dstDir . '/' . $relativePath);
                    }
                } elseif ($dir === 'img') {
                    // JANGAN PERNAH timpa foto/gambar/QR upload tenant
                    foreach (File::allFiles($srcDir) as $srcFile) {
                        $relativePath = $srcFile->getRelativePathname();
                        $dstFilePath = $dstDir . '/' . $relativePath;
                        // Hanya copy aset template jika file belum ada di target
                        if (!File::exists($dstFilePath)) {
                            File::ensureDirectoryExists(dirname($dstFilePath));
                            File::copy($srcFile->getPathname(), $dstFilePath);
                        }
                    }
                } else {
                    File::copyDirectory($srcDir, $dstDir);
                }
            }

        }

        // Sync root code files
        $rootFiles = ['index.php', 'admin.php', 'buy.php', 'buy_process.php', 'warung.php', 'api_webhook.php', 'telegram_webhook.php', 'callback.php', 'favicon.ico', 'manifest.json', 'manifest-buy.json', 'manifest-warung.json', 'sw.js'];
        foreach ($rootFiles as $rf) {
            $srcFile = $template . '/' . $rf;
            if (File::exists($srcFile)) {
                File::copy($srcFile, $target . '/' . $rf);
            }
        }

        // Enforce Indonesian language setting for Mikhmon instances
        File::ensureDirectoryExists($target . '/include');
        File::put($target . '/include/lang.php', '<?php $langid="id";?>');

        // Ensure license file exists
        if (!File::exists($target . '/config/license.php')) {
            $this->writeDefaultLicense($target, basename($target));
        }

        // Auto-heal user configuration if routers are missing in target
        $this->healAndRestoreUserConfigs($target, $subname ?: basename($target));

        // Ensure location configuration is populated for all sessions
        $this->ensureLocationConfig($target);

        // Ensure WhatsApp Gateway config is populated and synchronized
        $this->ensureWhatsAppConfig($target, $subname ?: basename($target));

        // Ensure Telegram Bot config is populated and synchronized
        $this->ensureTelegramConfig($target, $subname ?: basename($target));

        // Ensure NODERA Pay configuration is populated for all sessions
        $this->ensureNoderaPayConfig($target, $subname ?: basename($target));

        @touch($target . '/index.php');
    }

    /**
     * Cari konfigurasi user yang sudah ada dari semua variasi direktori (mikhmon-*, hotspot-*, dll).
     */
    public function findExistingUserConfigFiles(string $subdomain): array
    {
        // STRICT ISOLATION: Return empty to prevent any cross-tenant router or credential leakage
        return [];
        $clean = preg_replace('/^(mikhmon|hotspot)[-_]/', '', strtolower(trim($subdomain)));
        if (empty($clean)) {
            return [];
        }
        $cleanUnderscore = str_replace('-', '_', $clean);
        $cleanHyphen = str_replace('_', '-', $clean);

        $candidates = array_unique(array_filter([
            $subdomain,
            'mikhmon-' . $clean,
            'mikhmon_' . $clean,
            'mikhmon-' . $cleanHyphen,
            'mikhmon_' . $cleanUnderscore,
            'hotspot-' . $clean,
            'hotspot_' . $clean,
            'hotspot-' . $cleanHyphen,
            'hotspot_' . $cleanUnderscore,
            $clean,
            $cleanHyphen,
            $cleanUnderscore,
        ]));

        try {
            $sub = MikhmonSubscription::where('subdomain', $subdomain)
                ->orWhere('subdomain', $clean)
                ->orWhere('subdomain', 'mikhmon-' . $clean)
                ->orWhere('subdomain', 'hotspot-' . $clean)
                ->first();
            if ($sub && !empty($sub->user_id)) {
                $siblingSubs = MikhmonSubscription::where('user_id', $sub->user_id)->pluck('subdomain')->all();
                $candidates = array_unique(array_merge($candidates, $siblingSubs));
            }
        } catch (\Throwable $e) {}

        $baseDirs = array_unique(array_filter([
            config('mikhmon.base_path') ? rtrim(config('mikhmon.base_path'), '/') : null,
            public_path(),
            base_path(),
            base_path('public'),
        ]));

        $userFiles = [
            'include/config.php', 
            'include/location_config.php',
            'include/quickbt.php', 
            'include/telegram_config.php', 
            'include/voucher_config.php',
            'include/whatsapp_config.php',
            'include/noderapay_config.php',
            'include/orders_data.json',
            'include/warung_data.json',
            'include/warung_config.php'
        ];

        $configCheckNames = [
            'include/config.php',
            'include/config.php.bak',
            'include/config.php.old',
            'include/config.php.backup',
        ];

        foreach ($candidates as $cName) {
            if (empty($cName)) continue;
            foreach ($baseDirs as $bd) {
                $candidatePath = $bd . '/' . $cName;
                if (!File::isDirectory($candidatePath)) continue;

                $foundValid = false;
                $mainCfgContent = '';

                foreach ($configCheckNames as $cfgRel) {
                    $testPath = $candidatePath . '/' . $cfgRel;
                    if (File::exists($testPath)) {
                        $content = File::get($testPath);
                        if (preg_match('/\$data\[\s*[\'"](?!mikhmon)[^\'"]+[\'"]\s*\]/i', $content)) {
                            $foundValid = true;
                            $mainCfgContent = $content;
                            break;
                        }
                    }
                }

                if ($foundValid) {
                    $backup = ['include/config.php' => $mainCfgContent];
                    foreach ($userFiles as $uf) {
                        if ($uf === 'include/config.php') continue;
                        $src = $candidatePath . '/' . $uf;
                        if (File::exists($src)) {
                            $backup[$uf] = File::get($src);
                        }
                    }
                    return $backup;
                }
            }
        }

        return [];
    }

    /**
     * Check if a PHP code string has valid syntax using PHP token parser (zero execution).
     */
    public function isPhpSyntaxValid(string $code): bool
    {
        if (trim($code) === '') {
            return true;
        }
        try {
            @token_get_all($code, TOKEN_PARSE);
            return true;
        } catch (\ParseError | \CompileError | \Throwable $e) {
            return false;
        }
    }

    /**
     * Sanitize, repair, and rebuild config.php if corrupted or containing broken syntax lines.
     * Extracts all valid $data['...'] = array(...) lines and reconstructs valid PHP code.
     */
    public function sanitizeAndRebuildConfigPhp(string $rawContent): ?string
    {
        if (empty(trim($rawContent))) {
            return null;
        }

        // If raw content is already 100% valid PHP syntax and contains valid mikhmon config, return as is
        if ($this->isPhpSyntaxValid($rawContent) && str_contains($rawContent, "data['mikhmon']") && !preg_match("/^\s*'\d+'\s*=>/m", $rawContent)) {
            return $rawContent;
        }

        $validEntries = [];
        $hasMikhmon = false;

        // 1. Line-by-line / statement extraction for $data['...'] or $data["..."]
        if (preg_match_all('/\$data\[\s*[\'"]([^\'"]+)[\'"]\s*\]\s*=\s*(array\s*\([^;]+\)|\[[^;]+\])\s*;/is', $rawContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $sesKey = trim($m[1]);
                $arrBody = trim($m[2]);
                $testCode = "<?php \n\$tmp = " . $arrBody . ";\n";
                if ($this->isPhpSyntaxValid($testCode)) {
                    $validEntries[$sesKey] = $arrBody;
                    if ($sesKey === 'mikhmon') {
                        $hasMikhmon = true;
                    }
                }
            }
        }

        // 2. If $data['mikhmon'] was missing or invalid, extract admin credentials or fallback to default
        if (!$hasMikhmon) {
            $useradm = 'nodera';
            $passadm = 'pqCWnaOT';
            if (preg_match('/mikhmon<\|<([^,\'")\s]+)/', $rawContent, $um)) {
                $useradm = trim($um[1]);
            }
            if (preg_match('/mikhmon>\|>([^,\'")\s]+)/', $rawContent, $pm)) {
                $passadm = trim($pm[1]);
            }
            $validEntries['mikhmon'] = "array('1' => 'mikhmon<|<" . addslashes($useradm) . "', '2' => 'mikhmon>|>" . addslashes($passadm) . "')";
        }

        // 3. Construct clean PHP code
        $out = "<?php \nif(substr(\$_SERVER[\"REQUEST_URI\"], -10) == \"config.php\"){header(\"Location:./\");}; \n";
        $mikhVal = $validEntries['mikhmon'] ?? "array('1' => 'mikhmon<|<nodera', '2' => 'mikhmon>|>pqCWnaOT')";
        $out .= "\$data['mikhmon'] = " . $mikhVal . ";\n";

        foreach ($validEntries as $k => $v) {
            if ($k === 'mikhmon') continue;
            $out .= "\$data['" . addslashes($k) . "'] = " . $v . ";\n";
        }

        if ($this->isPhpSyntaxValid($out)) {
            return $out;
        }

        return null;
    }

    /**
     * Auto-heal konfigurasi user: Jika folder target saat ini kosong dari router sessions di config.php,
     * secara otomatis pulihkan dari sibling directory atau backup.
     */
    public function healAndRestoreUserConfigs(string $target, string $subdomain): void
    {
        $cfgPath = $target . '/include/config.php';
        $cfgBak = $target . '/include/config.php.bak';

        $hasRouters = false;
        $isSyntaxValid = false;

        if (File::exists($cfgPath)) {
            $content = File::get($cfgPath);
            if ($this->isPhpSyntaxValid($content) && str_contains($content, "data['mikhmon']") && !preg_match("/^\s*'\d+'\s*=>/m", $content)) {
                $isSyntaxValid = true;
            } else {
                // Try repairing syntax corruption (e.g. unexpected => or orphaned lines)
                $repaired = $this->sanitizeAndRebuildConfigPhp($content);
                if ($repaired !== null) {
                    File::put($cfgPath, $repaired);
                    $content = $repaired;
                    $isSyntaxValid = true;
                } else {
                    $isSyntaxValid = false;
                }
            }

            if ($isSyntaxValid && preg_match('/\$data\[\s*[\'"](?!mikhmon)[^\'"]+[\'"]\s*\]/i', $content)) {
                $hasRouters = true;
                @File::put($cfgBak, $content);
            }
        }

        if ((!$hasRouters || !$isSyntaxValid) && File::exists($cfgBak)) {
            $bakContent = File::get($cfgBak);
            $repairedBak = $this->sanitizeAndRebuildConfigPhp($bakContent);
            if ($repairedBak !== null && preg_match('/\$data\[\s*[\'"](?!mikhmon)[^\'"]+[\'"]\s*\]/i', $repairedBak)) {
                File::ensureDirectoryExists(dirname($cfgPath));
                File::put($cfgPath, $repairedBak);
                $hasRouters = true;
                $isSyntaxValid = true;
            }
        }

        if (!$hasRouters || !$isSyntaxValid) {
            $existing = $this->findExistingUserConfigFiles($subdomain);
            if (!empty($existing)) {
                foreach ($existing as $uf => $content) {
                    $dst = $target . '/' . $uf;
                    File::ensureDirectoryExists(dirname($dst));
                    if ($uf === 'include/config.php') {
                        $repairedExt = $this->sanitizeAndRebuildConfigPhp($content);
                        if ($repairedExt !== null) {
                            File::put($dst, $repairedExt);
                            @File::put($cfgBak, $repairedExt);
                            $hasRouters = true;
                            $isSyntaxValid = true;
                            continue;
                        }
                    }
                    File::put($dst, $content);
                }
                if (!empty($existing['include/config.php']) && !$isSyntaxValid) {
                    $repairedExt = $this->sanitizeAndRebuildConfigPhp($existing['include/config.php']);
                    if ($repairedExt !== null) {
                        File::put($cfgPath, $repairedExt);
                        @File::put($cfgBak, $repairedExt);
                        $hasRouters = true;
                        $isSyntaxValid = true;
                    }
                }
            }
        }

        // Jika config.php masih kosong atau rusak syntax-nya, tulis config default mikhmon bersih
        if (!File::exists($cfgPath) || !$isSyntaxValid) {
            File::ensureDirectoryExists(dirname($cfgPath));
            $defaultCfg = "<?php \nif(substr(\$_SERVER[\"REQUEST_URI\"], -10) == \"config.php\"){header(\"Location:./\");}; \n\$data['mikhmon'] = array ('1'=>'mikhmon<|<nodera','mikhmon>|>pqCWnaOT');\n";
            File::put($cfgPath, $defaultCfg);
        }

        // Auto-heal other include PHP files if any were corrupted
        $otherPhpFiles = glob($target . '/include/*.php') ?: [];
        foreach ($otherPhpFiles as $opFile) {
            $baseName = basename($opFile);
            if ($baseName === 'config.php') continue;
            $opCode = @file_get_contents($opFile);
            if ($opCode !== false && !$this->isPhpSyntaxValid($opCode)) {
                @rename($opFile, $opFile . '.corrupted.' . time());
            }
        }

        $this->ensureLocationConfig($target);
    }

    /**
     * Hapus seluruh deployment Mikhmon di server (folder {base}/{subdomain}).
     * DIPANGGIL saat subdomain dihapus — tanpa ini, .htaccess rewrite tetap
     * melayani panel Mikhmon yang sudah "dihapus" dari database.
     */
    public function removeDeployment(string $subdomain): bool
    {
        $possiblePaths = array_unique(array_filter([
            $this->targetPath($subdomain),
            public_path($subdomain),
            base_path($subdomain),
            base_path('public/' . $subdomain),
            config('mikhmon.base_path') ? rtrim(config('mikhmon.base_path'), '/') . '/' . $subdomain : null,
        ]));

        $deleted = false;
        foreach ($possiblePaths as $target) {
            if (File::isDirectory($target)) {
                try {
                    if (File::deleteDirectory($target)) {
                        $deleted = true;
                    }
                } catch (\Throwable $e) {}
            }
        }

        return $deleted;
    }

    /**
     * Reset password admin Mikhmon ke default (nodera/nodera) di config.php
     * tanpa menyentuh konfigurasi router sessions.
     */
    public function resetAdminPassword(string $target): bool
    {
        $possiblePaths = [
            $target,
            $this->targetPath($target),
            public_path($target),
            base_path($target),
            config('mikhmon.base_path') ? rtrim(config('mikhmon.base_path'), '/') . '/' . $target : null,
        ];

        $configFile = null;
        foreach (array_unique(array_filter($possiblePaths)) as $p) {
            if (File::exists($p . '/include/config.php')) {
                $configFile = $p . '/include/config.php';
                break;
            }
        }

        if (! $configFile) {
            return false;
        }

        $content = File::get($configFile);

        // ganti user & password admin (pola: mikhmon<|<... dan mikhmon>|>...)
        $content = preg_replace('/mikhmon<\|<[^\']*/', 'mikhmon<|<nodera', $content);
        $content = preg_replace('/mikhmon>\|>[^\']*/', 'mikhmon>|>pqCWnaOT', $content);

        File::put($configFile, $content);

        return true;
    }

    /**
     * Rename folder deployment Mikhmon ke subdomain baru dan update lisensi.
     */
     public function renameDeployment(string $oldSubdomain, string $newSubdomain, ?MikhmonSubscription $sub = null): array
     {
         $oldSubdomain = trim($oldSubdomain);
         $newSubdomain = trim($newSubdomain);
 
         if ($oldSubdomain === $newSubdomain) {
             return ['success' => true, 'path' => $this->targetPath($newSubdomain)];
         }
 
         $possibleOldPaths = array_unique(array_filter([
             $this->targetPath($oldSubdomain),
             public_path($oldSubdomain),
             base_path($oldSubdomain),
             base_path('public/' . $oldSubdomain),
             config('mikhmon.base_path') ? rtrim(config('mikhmon.base_path'), '/') . '/' . $oldSubdomain : null,
         ]));
 
         $newTarget = $this->targetPath($newSubdomain);
         File::ensureDirectoryExists(dirname($newTarget));
 
         $foundOld = null;
         foreach ($possibleOldPaths as $p) {
             if (File::isDirectory($p)) {
                 $foundOld = $p;
                 break;
             }
         }
 
         try {
             if ($foundOld) {
                 if (File::isDirectory($newTarget) && $foundOld !== $newTarget) {
                     File::deleteDirectory($newTarget);
                 }
                 File::moveDirectory($foundOld, $newTarget);
 
                 // Bersihkan path lama lainnya jika ada
                 foreach ($possibleOldPaths as $p) {
                     if ($p !== $newTarget && File::isDirectory($p)) {
                         try { File::deleteDirectory($p); } catch (\Throwable $e) {}
                     }
                 }
             } else {
                 if ($sub) {
                     return $this->deploy($sub);
                 }
             }
 
             if ($sub) {
                 $this->writeLicense($newTarget, $sub);
             } else {
                 $this->writeDefaultLicense($newTarget, $newSubdomain);
             }
 
             return ['success' => true, 'path' => $newTarget];
         } catch (\Throwable $e) {
             \Illuminate\Support\Facades\Log::error("Failed to rename Mikhmon deployment from {$oldSubdomain} to {$newSubdomain}: " . $e->getMessage());
             return ['success' => false, 'error' => $e->getMessage()];
         }
     }
 
    public function targetPath(string $subdomain): string
    {
        $base = rtrim(config('mikhmon.base_path', public_path()), '/');
        return $base . '/' . $subdomain;
    }

    /**
     * Synchronize or create a MikhmonSubscription for a Tenant, deploying/updating its files and configs.
     */
    public static function syncForTenant(Tenant $tenant, ?\App\Models\TenantAddon $tenantAddon = null, ?string $rosVersion = null): MikhmonSubscription
    {
        $rosVer = $rosVersion ?? (string) ($tenant->settings['mikhmon_ros_version'] ?? '6');
        if (!in_array($rosVer, ['6', '7'])) {
            $rosVer = '6';
        }

        $targetSubdomain = 'hotspot-' . $tenant->slug;

        $sub = MikhmonSubscription::where('tenant_id', $tenant->id)
            ->orWhere('subdomain', $targetSubdomain)
            ->orWhere('subdomain', 'mikhmon-' . $tenant->slug)
            ->orWhere('subdomain', $tenant->slug)
            ->first();

        $isExpired = $tenantAddon ? $tenantAddon->isExpired() : $tenant->isExpired();
        $isActive = (bool) $tenant->is_active && (!$tenantAddon || $tenantAddon->is_active);
        $status = $isExpired ? 'EXPIRED' : ($isActive ? 'ACTIVE' : 'SUSPENDED');
        $expiresAt = $tenantAddon?->expired_at ?? $tenant->expired_at ?? now()->addMonth();
        $price = $tenantAddon && (float)$tenantAddon->total_amount > 0 
            ? (float)$tenantAddon->total_amount 
            : (float) config('mikhmon.monthly_price', 10000);

        if (!$sub) {
            $sub = MikhmonSubscription::create([
                'tenant_id'      => $tenant->id,
                'vpn_user_id'    => $tenant->vpn_user_id ?? ($tenant->settings['vpn_user_id'] ?? null),
                'subdomain'      => $targetSubdomain,
                'ros_version'    => $rosVer,
                'price'          => $price,
                'expires_at'     => $expiresAt,
                'status'         => $status,
                'auto_renew'     => (bool) ($tenantAddon?->auto_renew ?? false),
                'order_date'     => now(),
                'last_billed_at' => now(),
            ]);
        } else {
            $oldSubdomain = $sub->subdomain;
            if ($oldSubdomain && $oldSubdomain !== $targetSubdomain && str_starts_with($oldSubdomain, 'mikhmon-')) {
                // Automatically migrate legacy mikhmon-{slug} to hotspot-{slug} on disk & in DB
                (new self())->renameDeployment($oldSubdomain, $targetSubdomain, $sub);
                $sub->subdomain = $targetSubdomain;
            }

            $sub->update([
                'tenant_id'      => $tenant->id,
                'vpn_user_id'    => $sub->vpn_user_id ?: ($tenant->vpn_user_id ?? ($tenant->settings['vpn_user_id'] ?? null)),
                'subdomain'      => $sub->subdomain ?: $targetSubdomain,
                'ros_version'    => $rosVer,
                'status'         => $status,
                'price'          => (float)$sub->price > 0 ? $sub->price : $price,
                'expires_at'     => $expiresAt ?: $sub->expires_at,
                'auto_renew'     => (bool) ($tenantAddon?->auto_renew ?? $sub->auto_renew),
            ]);
        }

        $provisioner = new self();
        $provisioner->deploy($sub, $expiresAt);

        return $sub;
    }

    /**
     * Buat slug subdomain unik dari username: hotspot-{username}.
     * Prefix 'hotspot-' atau 'mikhmon-' yang sudah ada di input dibuang dulu biar ga dobel.
     */
    public static function makeSubdomain(string $username): string
    {
        $slug = strtolower(trim($username));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug);
        // buang prefix mikhmon- atau hotspot- kalau user sudah mengetiknya (frontend juga prepend)
        $slug = preg_replace('/^(mikhmon|hotspot)-+/', '', $slug);

        if (empty($slug)) {
            $slug = 'user';
        }

        // Prefix 'hotspot-' (8 chars) + max 18 chars for slug = max 26 chars (fits comfortably within 30-char limit)
        $slug = substr($slug, 0, 18);
        $slug = trim($slug, '-');
        if (empty($slug)) {
            $slug = 'user';
        }

        $base = 'hotspot-' . $slug;

        $candidate = $base;
        $maxAttempts = 50;
        $i = 1;

        while ($i <= $maxAttempts && !SubdomainValidationService::checkAvailability($candidate)['available']) {
            $candidate = substr($base, 0, 25) . '-' . $i;
            $i++;
        }

        if (!SubdomainValidationService::checkAvailability($candidate)['available']) {
            $candidate = 'hotspot-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }

        return $candidate;
    }
}
