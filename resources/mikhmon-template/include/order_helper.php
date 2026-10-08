<?php
/**
 * MIKHMON Engine — Order Fulfillment & Router Helper
 * by NODERA (nodera.id)
 */
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -16) == "order_helper.php") {
    header("Location:./");
    exit;
}

if (!function_exists('mikhmon_get_router_api')) {
    function mikhmon_get_router_api($iphost, $userhost, $passwdhost) {
        include_once __DIR__ . '/../lib/routeros_api.class.php';
        $API = new RouterosAPI();
        $API->debug = false;
        $API->timeout = 5;
        $API->attempts = 2;
        $API->delay = 1;

        // Strip Mikhmon delimiters if passed raw
        $rawIp = trim((string)$iphost);
        if (strpos($rawIp, '!') !== false) {
            $rawIp = explode('!', $rawIp)[1] ?? $rawIp;
        }

        $rawUser = trim((string)$userhost);
        if (strpos($rawUser, '@|@') !== false) {
            $rawUser = explode('@|@', $rawUser)[1] ?? $rawUser;
        }

        $rawPass = trim((string)$passwdhost);
        if (strpos($rawPass, '#|#') !== false) {
            $rawPass = explode('#|#', $rawPass)[1] ?? $rawPass;
        }

        $ip = $rawIp;
        if (strpos($rawIp, ':') !== false) {
            $parts = explode(':', $rawIp);
            $ip = $parts[0];
            if (!empty($parts[1]) && is_numeric($parts[1])) {
                $API->port = (int)$parts[1];
            }
        }

        $passwd = function_exists('mikhmon_decrypt') ? mikhmon_decrypt($rawPass) : $rawPass;
        if ($API->connect($ip, $rawUser, $passwd)) {
            return $API;
        }
        // Fallback: try raw unencrypted password if different from decrypted string
        if ($passwd !== $rawPass && $API->connect($ip, $rawUser, $rawPass)) {
            return $API;
        }
        return null;
    }
}

if (!function_exists('mikhmon_get_orders')) {
    function mikhmon_get_orders($file) {
        if (!file_exists($file)) {
            return [];
        }
        $content = @file_get_contents($file);
        $orders = json_decode($content, true) ?: [];
        
        // Auto-prune pending/abandoned orders older than 24 hours
        $now = time();
        $changed = false;
        foreach ($orders as $k => $o) {
            $status = strtolower(trim((string)($o['status'] ?? '')));
            if ($status === 'pending' || $status === 'unpaid' || empty($status)) {
                $rawCreated = $o['created_at'] ?? ($o['timestamp'] ?? 0);
            $created = is_numeric($rawCreated) ? (int)$rawCreated : (!empty($rawCreated) ? strtotime($rawCreated) : 0);
                if ($created > 0 && ($now - $created) > 86400) {
                    unset($orders[$k]);
                    $changed = true;
                }
            }
        }
        if ($changed) {
            @file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        return $orders;
    }
}

if (!function_exists('mikhmon_save_order')) {
    function mikhmon_save_order($file, $order) {
        $orders = mikhmon_get_orders($file);
        if (empty($order['created_at'])) {
            $order['created_at'] = date('Y-m-d H:i:s');
        }
        if (empty($order['timestamp'])) {
            $order['timestamp'] = is_numeric($order['created_at']) ? (int)$order['created_at'] : strtotime($order['created_at']);
        }
        $orders[$order['order_id']] = $order;
        if (count($orders) > 500) {
            $orders = array_slice($orders, -500, null, true);
        }
        @file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('mikhmon_get_sold_usernames')) {
    function mikhmon_get_sold_usernames($file) {
        $orders = mikhmon_get_orders($file);
        $sold = [];
        foreach ($orders as $o) {
            if (!empty($o['voucher']['username'])) {
                $sold[strtolower(trim((string)$o['voucher']['username']))] = true;
            }
        }
        return $sold;
    }
}

if (!function_exists('mikhmon_is_unused_voucher')) {
    function mikhmon_is_unused_voucher($u, $soldUsernames = []) {
        $uName = strtolower(trim((string)($u['name'] ?? '')));
        if (empty($uName) || $uName === 'default' || $uName === 'admin') {
            return false;
        }

        $uComment = (string)($u['comment'] ?? '');
        $isAlreadySold = isset($soldUsernames[$uName])
            || stripos($uComment, '-BUY-') !== false
            || stripos($uComment, '-AUTO-') !== false
            || stripos($uComment, 'Online Buy') !== false
            || stripos($uComment, 'SOLD') !== false;

        if ($isAlreadySold) {
            return false;
        }

        // If comment is already an expiration date (meaning it has already been logged in and activated)
        if (preg_match('/^(?:[a-zA-Z]{3}\/\d{1,2}\/\d{2,4}|\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}-\d{2}-\d{2})\s+\d{2}:\d{2}/', trim($uComment))) {
            return false;
        }

        $uptime = trim((string)($u['uptime'] ?? ''));
        $bytesIn = trim((string)($u['bytes-in'] ?? ''));
        $bytesOut = trim((string)($u['bytes-out'] ?? ''));

        // Support RouterOS v6 ("00:00:00"), v7 ("0s"), empty, or interval zero formats
        $hasNoUptime = empty($uptime)
            || in_array($uptime, ['0s', '0', '00:00:00', '00:00:00s'], true)
            || preg_match('/^(0[wdhms]\s*)+$/i', $uptime)
            || preg_match('/^(0[wdhms]\s*)*00:00:00$/i', $uptime);

        $hasNoBytesIn = empty($bytesIn)
            || in_array($bytesIn, ['0', '0B', '0 B', '0.00 B', '0kib', '0mib', '0gib'], true);

        $hasNoBytesOut = empty($bytesOut)
            || in_array($bytesOut, ['0', '0B', '0 B', '0.00 B', '0kib', '0mib', '0gib'], true);

        return $hasNoUptime && $hasNoBytesIn && $hasNoBytesOut;
    }
}

if (!function_exists('mikhmon_fulfill_voucher')) {
    function mikhmon_fulfill_voucher($order, $iphost, $userhost, $passwdhost, $npCfg, $ordersFile = null) {
        $API = mikhmon_get_router_api($iphost, $userhost, $passwdhost);
        if (!$API) {
            return null;
        }

        $profile = trim((string)($order['profile'] ?? ''));
        $allocatedUser = null;

        // 1. ALWAYS search for available pre-generated unused voucher in MikroTik user pool first
        $soldUsernames = $ordersFile ? mikhmon_get_sold_usernames($ordersFile) : [];

        $users = $API->comm("/ip/hotspot/user/print", [
            "?disabled" => "false",
        ]);
        $users = is_array($users) ? $users : [];

        foreach ($users as $u) {
            $uProf = trim((string)($u['profile'] ?? ''));
            // Profile matching (case-insensitive & trimmed)
            if (strcasecmp($uProf, $profile) !== 0) {
                continue;
            }

            if (mikhmon_is_unused_voucher($u, $soldUsernames)) {
                $allocatedUser = [
                    'username' => $u['name'],
                    'password' => $u['password'] ?? $u['name'],
                    'validity' => $order['validity'] ?? '',
                ];

                // DO NOT overwrite or modify the voucher comment!
                // Keep the original comment intact (e.g. vc-xxx / up-xxx) so that
                // when the customer logs in for the first time, MikroTik's on-login script
                // recognizes the "vc"/"up" code and automatically computes the expiration date.
                break;
            }
        }

        // 2. Fallback: Only generate a new voucher on-demand if NO pre-generated voucher is available in the pool
        if (!$allocatedUser) {
            $prefix = $npCfg['voucher_prefix'] ?? 'VC-';
            $charLen = intval($npCfg['char_length'] ?? 6);
            $randomCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, $charLen));
            $username = $prefix . $randomCode;
            $password = $username;

            // Follow standard Mikhmon comment format: vc-<rand>-<date>-web so MikroTik on-login calculates expiry
            $comment = 'vc-' . rand(100, 999) . '-' . date('m.d.y') . '-web';
            $API->comm("/ip/hotspot/user/add", [
                "server"   => "all",
                "name"     => $username,
                "password" => $password,
                "profile"  => $profile,
                "comment"  => $comment,
            ]);

            $allocatedUser = [
                'username' => $username,
                'password' => $password,
                'validity' => $order['validity'] ?? '',
            ];
        }

        $API->disconnect();
        return $allocatedUser;
    }
}

if (!function_exists('mikhmon_safe_fulfill_order')) {
    function mikhmon_safe_fulfill_order($orderId, $session, $iphost, $userhost, $passwdhost, $hotspotname, $dnsname, $npCfg, $ordersFile) {
        if (empty($orderId)) {
            return null;
        }

        $lockFilePath = sys_get_temp_dir() . '/mikh_ord_lock_' . md5($orderId);
        $lockFp = @fopen($lockFilePath, 'c+');
        if (!$lockFp) {
            return null;
        }

        // Acquire exclusive blocking lock on this order_id
        if (!flock($lockFp, LOCK_EX)) {
            fclose($lockFp);
            return null;
        }

        // 1. Re-read orders under lock to get absolutely fresh disk state
        $orders = mikhmon_get_orders($ordersFile);
        $order = $orders[$orderId] ?? null;

        if (!$order) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            return null;
        }

        // 2. If order already fulfilled (by concurrent Webhook or Polling), return existing voucher immediately
        if ($order['status'] === 'paid' && !empty($order['voucher'])) {
            $existingVoucher = $order['voucher'];
            $needTg = empty($order['tg_sent']);
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            if ($needTg) {
                include_once __DIR__ . '/telegram_helper.php';
                if (function_exists('mikhmon_send_active_voucher_telegram')) {
                    $tgRes = mikhmon_send_active_voucher_telegram($session, $order, $existingVoucher, 'Rp', null);
                    if ($tgRes['ok'] ?? false) {
                        $order['tg_sent'] = true;
                        $order['tg_sent_at'] = date('Y-m-d H:i:s');
                        mikhmon_save_order($ordersFile, $order);
                    }
                }
            }
            return $existingVoucher;
        }

        // 3. Fulfill voucher from router
        $voucherData = mikhmon_fulfill_voucher($order, $iphost, $userhost, $passwdhost, $npCfg, $ordersFile);
        if (!$voucherData) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            return null;
        }

        // 4. Update order state immediately
        $order['status'] = 'paid';
        $order['paid_at'] = date('Y-m-d H:i:s');
        $order['voucher'] = $voucherData;

        $needSendWa = empty($order['wa_sent']);
        $order['wa_sent'] = true;
        $order['wa_sent_at'] = date('Y-m-d H:i:s');

        mikhmon_save_order($ordersFile, $order);

        // Release file lock BEFORE external network call (WhatsApp dispatch)
        flock($lockFp, LOCK_UN);
        fclose($lockFp);

        // 5. Send WhatsApp notification ONCE
        if ($needSendWa) {
            $locName = $order['location_name'] ?? '';
            if (empty($locName) || strtolower($locName) === 'dns') {
                $locConfigFile = __DIR__ . '/location_config.php';
                if (file_exists($locConfigFile)) {
                    $location_data = [];
                    include $locConfigFile;
                    $locName = $location_data['locations'][$session] ?? '';
                }
            }
            if (empty($locName) || strtolower($locName) === 'dns') {
                $locName = (!empty($hotspotname) && strtolower($hotspotname) !== 'dns') ? $hotspotname : ucwords(str_replace(['-', '_'], ' ', $session));
            }

            $merchantBrand = trim((string)($npCfg['merchant_name'] ?? ''));
            $resolvedBrand = (!empty($merchantBrand) && strtolower($merchantBrand) !== 'dns')
                ? $merchantBrand
                : ((!empty($locName) && strtolower($locName) !== 'dns') ? $locName : ((!empty($hotspotname) && strtolower($hotspotname) !== 'dns') ? $hotspotname : 'WiFi Hotspot'));

            $waParams = [
                'order_id'      => $order['order_id'],
                'brand'         => $resolvedBrand,
                'location_name' => $locName,
                'package'       => $order['profile'],
                'price'         => $order['total_amount'] ?? $order['price'],
                'validity'      => $order['validity'] ?? '',
                'username'      => $voucherData['username'],
                'password'      => $voucherData['password'],
                'dnsname'       => $dnsname,
            ];

            @mikhmon_send_voucher_wa($session, $order['phone'], $waParams);
        }

        // 6. Send Telegram notification ONCE for fulfilled orders (active voucher ready to forward)
        $needSendTg = empty($order['tg_sent']);
        if ($needSendTg) {
            include_once __DIR__ . '/telegram_helper.php';
            if (function_exists('mikhmon_send_active_voucher_telegram')) {
                $tgRes = mikhmon_send_active_voucher_telegram($session, $order, $voucherData, 'Rp', null);
                if ($tgRes['ok'] ?? false) {
                    $order['tg_sent'] = true;
                    $order['tg_sent_at'] = date('Y-m-d H:i:s');
                    mikhmon_save_order($ordersFile, $order);
                }
            }
        }

        // 7. Check and alert admin if remaining unused voucher stock is low
        if (!empty($order['profile'])) {
            @mikhmon_check_and_alert_low_stock($session, $order['profile'], $iphost, $userhost, $passwdhost, $ordersFile, $hotspotname);
        }

        return $voucherData;
    }
}

if (!function_exists('mikhmon_convert_emvco_static_to_dynamic')) {
    function mikhmon_convert_emvco_static_to_dynamic($staticQr, $amount, $orderId) {
        $qr = trim($staticQr);
        if (empty($qr)) return '';

        $tags = [];
        $i = 0;
        $len = strlen($qr);
        while ($i < $len) {
            $tag = substr($qr, $i, 2);
            if (strlen($tag) < 2) break;
            $lengthStr = substr($qr, $i + 2, 2);
            if (strlen($lengthStr) < 2 || !is_numeric($lengthStr)) break;
            $length = (int)$lengthStr;
            $val = substr($qr, $i + 4, $length);
            $tags[$tag] = $val;
            $i += 4 + $length;
        }

        // Tag 01: Change from Static (11) to Dynamic (12)
        $tags['01'] = '12';

        // Tag 54: Transaction Amount
        $formattedAmount = (string)intval($amount);
        $tags['54'] = $formattedAmount;

        // Tag 58: Country Code (Indonesia -> ID)
        $tags['58'] = 'ID';

        // Tag 62: Additional Data Field (Order ID)
        $subTag01 = '01' . sprintf('%02d', strlen($orderId)) . $orderId;
        $tags['62'] = $subTag01;

        // Rebuild TLV string in order
        $rebuilt = '';
        foreach ($tags as $t => $v) {
            $rebuilt .= $t . sprintf('%02d', strlen($v)) . $v;
        }

        // Tag 63: Compute CRC16-CCITT Checksum
        $forCrc = $rebuilt . '6304';
        $crc = 0xFFFF;
        for ($cIdx = 0; $cIdx < strlen($forCrc); $cIdx++) {
            $crc ^= (ord($forCrc[$cIdx]) << 8);
            for ($bit = 0; $bit < 8; $bit++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        $crcHex = strtoupper(sprintf('%04X', $crc));

        return $forCrc . $crcHex;
    }
}

if (!function_exists('mikhmon_resolve_african_payment_qr')) {
    function mikhmon_resolve_african_payment_qr($npCfg, $price = 0, $orderId = '', $phone = '', $profile = '') {
        return mikhmon_convert_to_dynamic_wave_qr($npCfg, $orderId, $price, $phone, $profile);
    }
}

if (!function_exists('mikhmon_convert_to_dynamic_wave_qr')) {
    function mikhmon_convert_to_dynamic_wave_qr($npCfg, $orderId, $amount, $phone, $profile) {
        $waveStatic = trim((string)($npCfg['wave_static_qr'] ?? ''));
        $qrImagePath = '';
        $merchantName = trim((string)($npCfg['merchant_name'] ?? 'Wave Merchant'));

        $qrString = '';
        $qrImageUrl = '';
        $checkoutUrl = '';
        $isWave = false;

        // 1. Check if Wave Pay Link (e.g. https://pay.wave.com/m/M_... or https://wave.com/...)
        if (preg_match('#https?://(?:pay\.)?wave\.com/[^\s]+#i', $waveStatic, $matches)) {
            $baseWaveUrl = $matches[0];
            $cleanBase = rtrim(preg_replace('/\?.*/', '', $baseWaveUrl), '/');
            // Format URL Wave dengan parameter amount untuk tombol klik langsung di aplikasi Wave
            $checkoutUrl = $cleanBase . '/?amount=' . rawurlencode($amount);
            $qrString = $baseWaveUrl;

            // Jika ada gambar QR asli yang di-upload, utamakan gambar tersebut
            if (!empty($qrImagePath) && file_exists(dirname(__DIR__) . '/' . $qrImagePath)) {
                $qrImageUrl = './' . $qrImagePath;
            } else {
                $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($baseWaveUrl);
            }
            $isWave = true;
        }
        // 2. Check if EMVCo QR string (starts with 000201)
        elseif (str_starts_with($waveStatic, '000201')) {
            $qrString = mikhmon_convert_emvco_static_to_dynamic($waveStatic, $amount, $orderId);
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($qrString);
        }
        // 3. Check if uploaded QR image exists
        elseif (!empty($qrImagePath) && file_exists(dirname(__DIR__) . '/' . $qrImagePath)) {
            $qrImageUrl = './' . $qrImagePath;
            $qrString = $waveStatic ?: ('ORDER:' . $orderId . '|AMT:' . $amount);
        }
        // 4. If custom text payload
        elseif (!empty($waveStatic)) {
            $qrString = $waveStatic;
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($waveStatic);
        }
        // 5. Default Fallback
        else {
            $qrString = 'WAVE-PAY:' . $orderId . ':' . $amount;
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($qrString);
        }

        return [
            'qr_string'    => $qrString,
            'qr_image_url' => $qrImageUrl,
            'qr_svg'       => '',
            'qr_data_uri'  => $qrImageUrl,
            'qr_png_uri'   => $qrImageUrl,
            'checkout_url' => $checkoutUrl,
            'is_wave'      => $isWave,
        ];
    }
}

if (!function_exists('mikhmon_check_and_alert_low_stock')) {
    function mikhmon_check_and_alert_low_stock($session, $profile, $iphost, $userhost, $passwdhost, $ordersFile = null, $routerName = '') {
        if (empty($session) || empty($profile)) {
            return false;
        }

        // 1. Read Telegram & WhatsApp configs to determine lowest threshold
        $tgConfigFile = __DIR__ . '/telegram_config.php';
        $tg_data = [];
        if (file_exists($tgConfigFile)) {
            include $tgConfigFile;
        }
        $tgCfg = $tg_data[$session] ?? ($tg_data['default'] ?? []);

        $waConfigFile = __DIR__ . '/whatsapp_config.php';
        $wa_data = [];
        if (file_exists($waConfigFile)) {
            include $waConfigFile;
        }
        $waCfg = $wa_data[$session] ?? ($wa_data['default'] ?? []);

        $tgEnabled = ($tgCfg['enabled'] ?? 'yes') === 'yes' && ($tgCfg['notif_low_stock'] ?? 'yes') === 'yes' && !empty($tgCfg['bot_token']);
        $waEnabled = ($waCfg['enabled'] ?? 'yes') === 'yes' && ($waCfg['notif_low_stock'] ?? 'yes') === 'yes' && !empty($waCfg['api_token']);

        if (!$tgEnabled && !$waEnabled) {
            return false;
        }

        $tgThreshold = max(1, intval($tgCfg['low_stock_threshold'] ?? 5));
        $waThreshold = max(1, intval($waCfg['low_stock_threshold'] ?? 5));
        $maxThreshold = max($tgThreshold, $waThreshold);

        // 2. Query router to count unused vouchers for $profile
        $API = mikhmon_get_router_api($iphost, $userhost, $passwdhost);
        if (!$API) {
            return false;
        }

        $soldUsernames = $ordersFile ? mikhmon_get_sold_usernames($ordersFile) : [];
        $users = $API->comm("/ip/hotspot/user/print", [
            "?profile"  => $profile,
            "?disabled" => "false",
        ]);
        $users = is_array($users) ? $users : [];

        $unusedCount = 0;
        foreach ($users as $u) {
            if (mikhmon_is_unused_voucher($u, $soldUsernames)) {
                $unusedCount++;
            }
        }
        $API->disconnect();

        // Check if stock is above maximum threshold
        if ($unusedCount > $maxThreshold) {
            return false;
        }

        // 3. Throttling / Cooldown check (prevent spamming alerts on every transaction)
        $alertLogFile = __DIR__ . '/low_stock_alerts.json';
        $alertLogs = @file_exists($alertLogFile) ? (@json_decode(@file_get_contents($alertLogFile), true) ?: []) : [];
        $logKey = $session . ':' . $profile;
        $now = time();

        $lastLog = $alertLogs[$logKey] ?? null;
        if ($lastLog) {
            $lastTime = $lastLog['time'] ?? 0;
            $lastStock = $lastLog['stock'] ?? -1;
            // Cooldown 2 hours (7200s), unless stock became 0 from previously > 0
            if (($now - $lastTime) < 7200 && !($unusedCount === 0 && $lastStock > 0)) {
                return false;
            }
        }

        // Update alert log
        $alertLogs[$logKey] = [
            'time'  => $now,
            'stock' => $unusedCount,
        ];
        @file_put_contents($alertLogFile, json_encode($alertLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 4. Dispatch Telegram Alert
        if ($tgEnabled && $unusedCount <= $tgThreshold) {
            include_once __DIR__ . '/telegram_helper.php';
            if (function_exists('mikhmon_send_low_stock_telegram')) {
                @mikhmon_send_low_stock_telegram($session, $profile, $unusedCount, $tgThreshold, $routerName);
            }
        }

        // 5. Dispatch WhatsApp Alert
        if ($waEnabled && $unusedCount <= $waThreshold) {
            include_once __DIR__ . '/whatsapp_helper.php';
            if (function_exists('mikhmon_send_low_stock_whatsapp')) {
                @mikhmon_send_low_stock_whatsapp($session, $profile, $unusedCount, $waThreshold, $routerName);
            }
        }

        return true;
    }
}

if (!function_exists('mikhmon_format_validity_unit')) {
    function mikhmon_format_validity_unit($raw, $isFrench = false) {
        $v = trim((string)$raw);
        if (empty($v) || $v === '-' || $v === 'Aktif' || $v === 'Standar' || $v === 'Standard') {
            return '';
        }
        if (preg_match('/^(\d+)\s*([smhdw]|min|hr|day|jour|jam|hari|minggu|bulan|month|mois|heures?|jours?)$/i', $v, $m)) {
            $num = $m[1];
            $unit = strtolower($m[2]);
            if (in_array($unit, ['d', 'day', 'hari', 'jour', 'jours'])) {
                return $isFrench ? $num . ' Jours' : $num . ' Hari';
            }
            if (in_array($unit, ['h', 'hr', 'jam', 'hour', 'heure', 'heures'])) {
                return $isFrench ? $num . ' Heures' : $num . ' Jam';
            }
            if (in_array($unit, ['m', 'min', 'menit'])) {
                return $isFrench ? $num . ' Minutes' : $num . ' Menit';
            }
            if (in_array($unit, ['w', 'week', 'minggu', 'semaine'])) {
                return $isFrench ? ($num * 7) . ' Jours' : ($num * 7) . ' Hari';
            }
            if (in_array($unit, ['bulan', 'month', 'mois'])) {
                return $isFrench ? $num . ' Mois' : $num . ' Bulan';
            }
        }
        return $v;
    }
}

if (!function_exists('mikhmon_infer_validity')) {
    function mikhmon_infer_validity($name, $rawValidity = '', $isFrench = false) {
        $formatted = mikhmon_format_validity_unit($rawValidity, $isFrench);
        if (!empty($formatted)) {
            return $formatted;
        }
        $n = strtolower(trim((string)$name));
        if (preg_match('/(\d+)\s*[-_]?\s*(menit|min|m\b)/i', $n, $m)) {
            return $isFrench ? $m[1] . ' Minutes' : $m[1] . ' Menit';
        }
        if (preg_match('/(\d+)\s*[-_]?\s*(jam|hours?|hrs?|heures?|h\b)/i', $n, $m)) {
            return $isFrench ? $m[1] . ' Heures' : $m[1] . ' Jam';
        }
        if (preg_match('/(\d+)\s*[-_]?\s*(hari|days?|jours?|d\b)/i', $n, $m)) {
            return $isFrench ? $m[1] . ' Jours' : $m[1] . ' Hari';
        }
        if (preg_match('/(\d+)\s*[-_]?\s*(minggu|weeks?|semaines?|w\b)/i', $n, $m)) {
            return $isFrench ? ($m[1] * 7) . ' Jours' : ($m[1] * 7) . ' Hari';
        }
        if (preg_match('/(\d+)\s*[-_]?\s*(bulan|months?|mois)/i', $n, $m)) {
            return $isFrench ? $m[1] . ' Mois' : $m[1] . ' Bulan';
        }
        if (strpos($n, 'bulan') !== false || strpos($n, 'mois') !== false) {
            return $isFrench ? '30 Jours' : '30 Hari';
        }
        if (strpos($n, 'minggu') !== false || strpos($n, 'semaine') !== false) {
            return $isFrench ? '7 Jours' : '7 Hari';
        }
        if (strpos($n, 'hari') !== false || strpos($n, 'jour') !== false) {
            return $isFrench ? '24 Heures' : '24 Jam';
        }
        return $isFrench ? 'Validité Hotspot' : 'Masa Aktif Hotspot';
    }
}

if (!function_exists('mikhmon_resolve_profile_validity')) {
    function mikhmon_resolve_profile_validity($matchedProf, $pName = '', $npCfg = [], $isAfrica = false) {
        $validity = '';
        $onLogin = $matchedProf['on-login'] ?? '';
        if (!empty($onLogin)) {
            $parts = explode(',', (string)$onLogin);
            if (isset($parts[3]) && trim($parts[3]) !== '' && trim($parts[3]) !== '0') {
                $validity = trim($parts[3]);
            }
        }
        if (empty($validity) && !empty($matchedProf['validity']) && $matchedProf['validity'] !== 'Validité Standard' && $matchedProf['validity'] !== 'Masa Aktif Hotspot' && $matchedProf['validity'] !== 'Aktif') {
            $validity = trim($matchedProf['validity']);
        }
        if (empty($validity) && !empty($npCfg['profile_validity'][$pName])) {
            $validity = trim($npCfg['profile_validity'][$pName]);
        }
        return mikhmon_infer_validity($pName ?: ($matchedProf['name'] ?? ''), $validity, $isAfrica);
    }
}
