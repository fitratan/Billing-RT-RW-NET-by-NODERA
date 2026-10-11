<?php
/**
 * MIKHMON Engine — WhatsApp Helper Functions
 * by NODERA (nodera.id)
 */

if (!function_exists('mikhmon_format_phone')) {
    function mikhmon_format_phone($phone) {
        $clean = preg_replace('/[^0-9]/', '', (string)$phone);
        if (empty($clean)) {
            return '';
        }
        // Bersihkan prefix 00 (misal: 00225... atau 0062...)
        if (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        // Nomor Indonesia (08... atau 628...)
        if (str_starts_with($clean, '08')) {
            return '62' . substr($clean, 1);
        }
        if (str_starts_with($clean, '62')) {
            return $clean;
        }

        // Nomor Pantai Gading dengan 225
        if (str_starts_with($clean, '225')) {
            $rest = substr($clean, 3);
            // Jika diawali 7, 5, 1 (9 digit), sisipkan 0 resmi ARCEP (225 + 07... / 05... / 01...)
            if (strlen($rest) === 9 && in_array(substr($rest, 0, 1), ['1', '5', '7'])) {
                return '2250' . $rest;
            }
            return $clean;
        }

        // Nomor Pantai Gading format nasional (10 digit diawali 07, 05, 01, 0X)
        if (str_starts_with($clean, '0')) {
            return '225' . $clean;
        }

        // Nomor Pantai Gading tanpa angka 0 depan (9 digit: 7XXXXXXXX / 5XXXXXXXX / 1XXXXXXXX)
        if (strlen($clean) === 9 && in_array(substr($clean, 0, 1), ['1', '5', '7'])) {
            return '2250' . $clean;
        }

        // Nomor Pantai Gading format lama (8 digit)
        if (strlen($clean) === 8) {
            return '2250' . $clean;
        }

        // Nomor internasional lain yang sudah lengkap (>= 11 digit)
        if (strlen($clean) >= 11) {
            return $clean;
        }

        return '225' . $clean;
    }
}

if (!function_exists('mikhmon_send_whatsapp')) {
    function mikhmon_send_whatsapp($token, $apiUrl, $phone, $message, $provider = 'fonnte', $senderPhone = '') {
        $token = trim((string)$token);
        $phone = mikhmon_format_phone(trim((string)$phone));
        $apiUrl = trim((string)$apiUrl);
        $sender = trim((string)$senderPhone);

        if (empty($token) || empty($phone)) {
            return ['ok' => false, 'description' => 'API Token dan Nomor Tujuan WhatsApp wajib diisi.'];
        }

        $provLower = strtolower(trim((string)$provider));
        $urlLower  = strtolower($apiUrl);

        if (empty($apiUrl)) {
            if ($provLower === 'nodera') {
                $apiUrl = 'https://dgtlnetsolution.com/api/message/send';
            } elseif ($provLower === 'mhwa') {
                $apiUrl = 'https://mhwa.biz.id/api/message/send';
            } elseif ($provLower === 'wablas') {
                $apiUrl = 'https://api.wablas.com/api/send-message';
            } elseif ($provLower === 'starsender') {
                $apiUrl = 'https://api.starsender.online/api/send';
            } elseif ($provLower === 'whacenter') {
                $apiUrl = 'https://app.whacenter.com/api/send';
            } else {
                $apiUrl = 'https://api.fonnte.com/send';
            }
            $urlLower = strtolower($apiUrl);
        }

        $doCurl = function($url, $postPayload, $headers = [], $isJson = false) {
            if (!function_exists('curl_init')) {
                return ['ok' => false, 'http_code' => 0, 'error' => 'Ekstensi PHP cURL tidak aktif di server.', 'response' => ''];
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($isJson) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postPayload) ? json_encode($postPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $postPayload);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postPayload) ? http_build_query($postPayload) : $postPayload);
            }
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'ok'        => empty($err) && $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'error'     => $err,
                'response'  => $res,
            ];
        };

        $isSuccessResponse = function($resStr, $httpCode) {
            if ($httpCode >= 200 && $httpCode < 300) {
                $json = json_decode($resStr, true);
                if (is_array($json)) {
                    if (isset($json['status']) && ($json['status'] === false || $json['status'] === 'false' || $json['status'] === 'failed' || $json['status'] === 0)) {
                        return false;
                    }
                    if (isset($json['success']) && ($json['success'] === false || $json['success'] === 'false' || $json['success'] === 0)) {
                        return false;
                    }
                    if (isset($json['error']) && !empty($json['error']) && $json['error'] !== false) {
                        return false;
                    }
                }
                return true;
            }
            return false;
        };

        $extractError = function($resStr, $httpCode, $curlErr = '') {
            if (!empty($curlErr)) {
                return 'cURL Error: ' . $curlErr;
            }
            $json = json_decode($resStr, true);
            if (is_array($json)) {
                if (!empty($json['error'])) {
                    $err = is_string($json['error']) ? $json['error'] : json_encode($json['error']);
                    if (stripos($err, 'perangkat tidak ditemukan') !== false || stripos($err, 'bukan milik') !== false) {
                        return $err . ' — Pastikan "Session ID Perangkat" yang dimasukkan sama persis dengan nama/ID sesi di dashboard mhwa.biz.id dan status perangkat Connected.';
                    }
                    return $err;
                }
                if (!empty($json['reason'])) return (string)$json['reason'];
                if (!empty($json['message'])) return (string)$json['message'];
                if (!empty($json['msg'])) return (string)$json['msg'];
                if (!empty($json['detail'])) return (string)$json['detail'];
                if (!empty($json['description'])) return (string)$json['description'];
            }
            $cleanRes = trim(preg_replace('/\s+/', ' ', strip_tags((string)$resStr)));
            if (empty($cleanRes)) {
                return "HTTP Error {$httpCode}";
            }
            if (stripos($cleanRes, 'cloudflare') !== false) {
                return "HTTP {$httpCode}: Ditolak oleh Cloudflare WAF Gateway ({$cleanRes})";
            }
            return "HTTP Error {$httpCode}: " . substr($cleanRes, 0, 150);
        };

        // 0. NODERA WhatsApp Gateway (https://dgtlnetsolution.com/api/message/send)
        if ($provLower === 'nodera' || str_contains($urlLower, 'dgtlnetsolution.com') || str_contains($urlLower, 'wagateway')) {
            $auth = str_starts_with(strtolower($token), 'bearer ') ? $token : 'Bearer ' . $token;
            $headers = [
                'Authorization: ' . $auth,
                'Content-Type: application/json',
                'Accept: application/json',
            ];
            $payload = [
                'phone'   => $phone,
                'message' => $message,
            ];
            if (!empty($sender)) {
                $payload['device_id'] = $sender;
                $payload['session_id'] = $sender;
            }
            $cRes = $doCurl($apiUrl, $payload, $headers, true);
            if ($isSuccessResponse($cRes['response'], $cRes['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cRes['response'], true) ?: $cRes['response']];
            }
            return ['ok' => false, 'description' => $extractError($cRes['response'], $cRes['http_code'], $cRes['error'])];
        }

        // 1. Fonnte Provider (Strict token only, NO session_id / device_id)
        if ($provLower === 'fonnte' || str_contains($urlLower, 'fonnte.com')) {
            $auth = str_starts_with(strtolower($token), 'bearer ') ? substr($token, 7) : $token;
            $headers = [
                'Authorization: ' . $auth,
                'Accept: application/json, text/plain, */*',
            ];
            $payload = [
                'target'  => $phone,
                'message' => $message,
                'delay'   => '2',
            ];
            if (!str_starts_with($phone, '62') && !str_starts_with($phone, '225') && !str_starts_with($phone, '+')) {
                $payload['countryCode'] = '62';
            }
            $cRes = $doCurl($apiUrl, $payload, $headers, false);
            if ($isSuccessResponse($cRes['response'], $cRes['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cRes['response'], true) ?: $cRes['response']];
            }
            return ['ok' => false, 'description' => $extractError($cRes['response'], $cRes['http_code'], $cRes['error'])];
        }

        // 2. MHWA Gateway Provider (https://mhwa.biz.id - Requires session_id)
        if ($provLower === 'mhwa' || str_contains($urlLower, 'mhwa.biz.id')) {
            $sessionId = !empty($sender) ? $sender : 'default';
            $headers = [
                'x-api-key: ' . $token,
                'Content-Type: application/json',
                'Accept: application/json, text/plain, */*',
            ];
            $payload = [
                'session_id' => $sessionId,
                'to'         => $phone,
                'message'    => $message,
            ];
            $cRes = $doCurl($apiUrl, $payload, $headers, true);
            if ($isSuccessResponse($cRes['response'], $cRes['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cRes['response'], true) ?: $cRes['response']];
            }
            return ['ok' => false, 'description' => $extractError($cRes['response'], $cRes['http_code'], $cRes['error'])];
        }

        // 3. Wablas Provider (Token only, NO session_id)
        if ($provLower === 'wablas' || str_contains($urlLower, 'wablas.com')) {
            $auth = str_starts_with(strtolower($token), 'bearer ') ? substr($token, 7) : $token;
            $headers = [
                'Authorization: ' . $auth,
                'Content-Type: application/json',
                'Accept: application/json, text/plain, */*',
            ];
            $payload = [
                'phone'   => $phone,
                'message' => $message,
                'data'    => [
                    ['phone' => $phone, 'message' => $message]
                ],
            ];
            $cRes = $doCurl($apiUrl, $payload, $headers, true);
            if ($isSuccessResponse($cRes['response'], $cRes['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cRes['response'], true) ?: $cRes['response']];
            }
            return ['ok' => false, 'description' => $extractError($cRes['response'], $cRes['http_code'], $cRes['error'])];
        }

        // 4. Starsender Provider (apikey only, NO session_id)
        if ($provLower === 'starsender' || str_contains($urlLower, 'starsender')) {
            $headers = [
                'apikey: ' . $token,
                'Content-Type: application/json',
                'Accept: application/json, text/plain, */*',
            ];
            $payload = [
                'messageType' => 'text',
                'to'          => $phone,
                'body'        => $message,
                'apikey'      => $token,
            ];
            $cRes = $doCurl($apiUrl, $payload, $headers, true);
            if ($isSuccessResponse($cRes['response'], $cRes['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cRes['response'], true) ?: $cRes['response']];
            }
            return ['ok' => false, 'description' => $extractError($cRes['response'], $cRes['http_code'], $cRes['error'])];
        }

        // 5. WhaCenter Provider (device_id + number + message)
        if ($provLower === 'whacenter' || str_contains($urlLower, 'whacenter')) {
            $payload = [
                'device_id' => $sender ?: $token,
                'number'    => $phone,
                'message'   => $message,
            ];
            $cRes = $doCurl($apiUrl, $payload, ['Accept: application/json, text/plain, */*'], false);
            if ($isSuccessResponse($cRes['response'], $cRes['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cRes['response'], true) ?: $cRes['response']];
            }
            return ['ok' => false, 'description' => $extractError($cRes['response'], $cRes['http_code'], $cRes['error'])];
        }

        // 6. MPWA / Baileys Gateway (Custom NodeJS / PHP Baileys endpoint)
        if ($provLower === 'mpwa') {
            $auth = str_starts_with(strtolower($token), 'bearer ') ? $token : 'Bearer ' . $token;
            $rawToken = str_starts_with(strtolower($token), 'bearer ') ? trim(substr($token, 7)) : $token;
            $headers = [
                'Authorization' => $auth,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json, text/plain, */*',
            ];
            $payload = [
                'receiver' => $phone,
                'number'   => $phone,
                'target'   => $phone,
                'to'       => $phone,
                'message'  => $message,
                'msg'      => $message,
            ];
            if (!empty($sender)) {
                $payload['sender'] = $sender;
                $payload['session'] = $sender;
                $payload['device_id'] = $sender;
            }
            $cRes = $doCurl($apiUrl, $payload, $headers, true);
            if ($isSuccessResponse($cRes['response'], $cRes['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cRes['response'], true) ?: $cRes['response']];
            }
            // Fallback try form-urlencoded
            $cResForm = $doCurl($apiUrl, $payload, ['Authorization: ' . $auth, 'X-Api-Key: ' . $rawToken, 'Accept: application/json'], false);
            if ($isSuccessResponse($cResForm['response'], $cResForm['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cResForm['response'], true) ?: $cResForm['response']];
            }
            return ['ok' => false, 'description' => $extractError($cRes['response'], $cRes['http_code'], $cRes['error'])];
        }

        // 7. Universal Custom Gateway
        $isBearer = str_starts_with(strtolower($token), 'bearer ');
        $rawToken = $isBearer ? trim(substr($token, 7)) : $token;
        $authHeader = $isBearer ? $token : (strlen($rawToken) > 45 || str_contains($rawToken, '.') ? 'Bearer ' . $rawToken : $rawToken);

        $universalPayload = [
            'number'       => $phone,
            'phone'        => $phone,
            'target'       => $phone,
            'to'           => $phone,
            'receiver'     => $phone,
            'recipient'    => $phone,
            'destination'  => $phone,
            'nohp'         => $phone,
            'no_hp'        => $phone,
            'message'      => $message,
            'msg'          => $message,
            'text'         => $message,
            'pesan'        => $message,
            'body'         => $message,
            'api_key'      => $rawToken,
            'apikey'       => $rawToken,
            'token'        => $rawToken,
            'key'          => $rawToken,
            'appkey'       => $rawToken,
            'secret'       => $rawToken,
        ];
        if (!empty($sender)) {
            $universalPayload['device_id'] = $sender;
            $universalPayload['sender'] = $sender;
            $universalPayload['from'] = $sender;
            $universalPayload['sender_phone'] = $sender;
            $universalPayload['device'] = $sender;
            $universalPayload['session'] = $sender;
            $universalPayload['session_id'] = $sender;
        }

        $customHeadersForm = [
            'Authorization: ' . $authHeader,
            'X-Api-Key: ' . $rawToken,
            'Accept: application/json, text/plain, */*',
        ];
        $customHeadersJson = [
            'Authorization: ' . $authHeader,
            'X-Api-Key: ' . $rawToken,
            'Content-Type: application/json',
            'Accept: application/json, text/plain, */*',
        ];

        // Attempt 1: Try Form-Urlencoded
        $cRes = $doCurl($apiUrl, $universalPayload, $customHeadersForm, false);
        if ($isSuccessResponse($cRes['response'], $cRes['http_code'])) {
            return ['ok' => true, 'response' => json_decode($cRes['response'], true) ?: $cRes['response']];
        }

        // Attempt 2: If Form-Urlencoded returned non-success / 400+, Try JSON format
        $cResJson = $doCurl($apiUrl, $universalPayload, $customHeadersJson, true);
        if ($isSuccessResponse($cResJson['response'], $cResJson['http_code'])) {
            return ['ok' => true, 'response' => json_decode($cResJson['response'], true) ?: $cResJson['response']];
        }

        // Attempt 3: If still non-success and authHeader was not Bearer, try JSON with Bearer header
        if (!$isBearer && !str_starts_with($authHeader, 'Bearer ')) {
            $bearerHeadersJson = [
                'Authorization: Bearer ' . $rawToken,
                'X-Api-Key: ' . $rawToken,
                'Content-Type: application/json',
                'Accept: application/json, text/plain, */*',
            ];
            $cResBearer = $doCurl($apiUrl, $universalPayload, $bearerHeadersJson, true);
            if ($isSuccessResponse($cResBearer['response'], $cResBearer['http_code'])) {
                return ['ok' => true, 'response' => json_decode($cResBearer['response'], true) ?: $cResBearer['response']];
            }
            if (!empty($cResBearer['response'])) {
                $cResJson = $cResBearer;
            }
        }

        $bestRes = !empty($cResJson['response']) ? $cResJson : $cRes;
        return ['ok' => false, 'description' => $extractError($bestRes['response'], $bestRes['http_code'], $bestRes['error'])];
    }
}

if (!function_exists('mikhmon_send_voucher_wa')) {
    function mikhmon_send_voucher_wa($session, $phone, $params = []) {
        $wa_config_file = __DIR__ . '/whatsapp_config.php';
        if (!file_exists($wa_config_file)) {
            return ['ok' => false, 'description' => 'File konfigurasi WhatsApp tidak ditemukan.'];
        }
        $wa_data = [];
        include $wa_config_file;

        $cfg = $wa_data[$session] ?? null;
        if (!$cfg || ($cfg['enabled'] ?? 'no') !== 'yes' || empty($cfg['api_token'])) {
            foreach ($wa_data as $s => $c) {
                if ($s !== '_global' && !empty($c['api_token']) && ($c['enabled'] ?? 'no') === 'yes') {
                    $cfg = $c;
                    break;
                }
            }
        }

        if (!$cfg || empty($cfg['api_token'])) {
            return ['ok' => false, 'description' => 'WhatsApp Gateway belum dikonfigurasi atau dinonaktifkan.'];
        }

        $username = trim((string)($params['username'] ?? ''));
        $password = trim((string)($params['password'] ?? ''));
        $isSameUserPass = empty($password) || ($password === $username);

        if ($isSameUserPass) {
            $loginDetail = "- Kode Voucher: *{$username}*";
        } else {
            $loginDetail = "- Username: *{$username}*\n- Password: *{$password}*";
        }

        $validity = $params['validity'] ?? '-';
        $validityLine = (!empty($validity) && $validity !== '-') ? "Masa Aktif: *{$validity}*\n" : "";

        $brandCfg = trim((string)($cfg['brand_name'] ?? ''));
        $brandParam = trim((string)($params['brand'] ?? ''));
        $locParam = trim((string)($params['location_name'] ?? ''));
        $brand = (!empty($brandCfg) && strtolower($brandCfg) !== 'dns')
            ? $brandCfg 
            : ((!empty($brandParam) && strtolower($brandParam) !== 'dns') ? $brandParam : ((!empty($locParam) && strtolower($locParam) !== 'dns') ? $locParam : 'NODERA WIFI'));
        $package = $params['package'] ?? 'Hotspot Voucher';
        $rawPrice = $params['price'] ?? 0;
        $price = number_format((float)$rawPrice, 0, ',', '.');

        $defaultTemplate = "*PEMBAYARAN BERHASIL — {brand}*\n"
            . "----------------------------------------\n"
            . "Terima kasih telah membeli voucher WiFi.\n\n"
            . "Paket: *{package}*\n"
            . "{validity_line}"
            . "Total Bayar: *Rp {price}*\n\n"
            . "DETAIL LOGIN:\n"
            . "{login_detail}\n\n"
            . "Simpan pesan ini jika sewaktu-waktu perangkat Anda terputus.";

        $template = !empty($cfg['buyer_msg_template']) ? $cfg['buyer_msg_template'] : $defaultTemplate;
        $template = str_ireplace('DETAIL VOUCHER:', 'DETAIL LOGIN:', $template);

        if ($isSameUserPass) {
            $template = preg_replace("/\r?\n[ \t]*[-*•]?[ \t]*(?:<[^>]+>|\*)*[ \t]*Password[ \t]*(?:<[^>]+>|\*|[ \t]|:)*\{password\}(?:<[^>]+>|\*|[ \t])*/i", "", $template);
            $template = preg_replace("/[-*•]?[ \t]*(?:<[^>]+>|\*)*[ \t]*(Kode Voucher \/ User|Username \/ Password|Username)[ \t]*(?:<[^>]+>|\*|[ \t]|:)*\{username\}(?:<[^>]+>|\*|[ \t])*/i", "- Kode Voucher: *{username}*", $template);
        } else {
            $template = preg_replace("/[-*•]?[ \t]*(?:<[^>]+>|\*)*[ \t]*(Kode Voucher \/ User|Username \/ Password)[ \t]*(?:<[^>]+>|\*|[ \t]|:)*\{username\}(?:<[^>]+>|\*|[ \t])*/i", "- Username: *{username}*", $template);
            $template = preg_replace("/[-*•]?[ \t]*(?:<[^>]+>|\*)*[ \t]*Password[ \t]*(?:<[^>]+>|\*|[ \t]|:)*\{password\}(?:<[^>]+>|\*|[ \t])*/i", "- Password: *{password}*", $template);
        }

        $template = preg_replace("/\r?\n[ \t]*(?:<[^>]+>|\*)*[ \t]*LINK LOGIN OTOMATIS:?[ \t]*(?:<[^>]+>|\*)*\r?\n[ \t]*(?:\{login_url\}|https?:\/\/[^\s\n]+)?/i", "", $template);
        $template = str_ireplace('{login_url_section}', '', $template);
        $template = str_ireplace('{login_url}', '', $template);

        $loginUrlSection = "";

        $replacements = [
            '{brand}'             => $brand,
            '{package}'           => $package,
            '{price}'             => $price,
            '{validity}'          => $validity,
            '{validity_line}'     => $validityLine,
            '{username}'          => $username,
            '{password}'          => $password,
            '{login_detail}'      => $loginDetail,
            '{detail_login}'      => $loginDetail,
            '{voucher_detail}'    => $loginDetail,
            '{login_url}'         => $params['login_url'] ?? '',
            '{login_url_section}' => $loginUrlSection,
            '{dnsname}'           => $params['dnsname'] ?? '',
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $template);

        $orderId = trim((string)($params['order_id'] ?? ''));
        $dedupKey = !empty($orderId) ? md5('ord_' . $orderId) : md5($session . '_' . $phone);
        $lockBuyer = sys_get_temp_dir() . '/mikh_wa_b_' . $dedupKey;
        $lockAdmin = sys_get_temp_dir() . '/mikh_wa_a_' . $dedupKey;

        $prov = $cfg['provider'] ?? 'nodera';
        $senderId = '';
        if (in_array($prov, ['nodera', 'mhwa', 'mpwa', 'whacenter', 'custom'])) {
            $senderId = !empty($cfg['session_id']) ? trim($cfg['session_id']) : (!empty($cfg['admin_phone']) && !preg_match('/^[0-9+]+$/', $cfg['admin_phone']) ? trim($cfg['admin_phone']) : '');
        }

        $defaultApiUrl = 'https://dgtlnetsolution.com/api/message/send';
        if ($prov === 'fonnte') {
            $defaultApiUrl = 'https://api.fonnte.com/send';
        } elseif ($prov === 'mhwa') {
            $defaultApiUrl = 'https://mhwa.biz.id/api/message/send';
        } elseif ($prov === 'wablas') {
            $defaultApiUrl = 'https://api.wablas.com/api/send-message';
        } elseif ($prov === 'starsender') {
            $defaultApiUrl = 'https://api.starsender.online/api/send';
        } elseif ($prov === 'whacenter') {
            $defaultApiUrl = 'https://app.whacenter.com/api/send';
        }

        if (!empty($orderId) && file_exists($lockBuyer) && (time() - filemtime($lockBuyer) < 180)) {
            $sendRes = ['ok' => true, 'description' => 'Pesan pembeli sudah pernah dikirim.'];
        } else {
            if (!empty($orderId)) {
                @touch($lockBuyer);
            }
            $sendRes = mikhmon_send_whatsapp($cfg['api_token'], $cfg['api_url'] ?: $defaultApiUrl, $phone, $message, $prov, $senderId);
        }

        // Admin notification
        $adminNotifPhone = !empty($cfg['admin_phone']) && preg_match('/^[0-9+]+$/', $cfg['admin_phone'])
            ? trim($cfg['admin_phone'])
            : (!empty($cfg['cs_phone']) ? trim($cfg['cs_phone']) : '');

        if (($cfg['notif_admin_sale'] ?? 'no') === 'yes' && !empty($adminNotifPhone)) {
            $adminPhone = mikhmon_format_phone($adminNotifPhone);

            if (!empty($orderId) && file_exists($lockAdmin) && (time() - filemtime($lockAdmin) < 180)) {
                // Admin already notified for this order, skip duplicate
            } else {
                if (!empty($orderId)) {
                    @touch($lockAdmin);
                }
                $voucherDetailAdmin = $isSameUserPass 
                    ? "Kode Voucher: `{$username}`" 
                    : "Username: `{$username}`\nPassword: `{$password}`";

                $adminMsg = "*NOTIFIKASI PENJUALAN VOUCHER — {$brand}*\n"
                    . "----------------------------------------\n"
                    . "Pembeli: `{$phone}`\n"
                    . "Paket: *" . ($params['package'] ?? '-') . "*\n"
                    . "Nominal: *Rp {$price}*\n"
                    . $voucherDetailAdmin . "\n"
                    . "Waktu: " . date('d/m/Y H:i:s');
                @mikhmon_send_whatsapp($cfg['api_token'], $cfg['api_url'] ?: $defaultApiUrl, $adminPhone, $adminMsg, $prov, $senderId);
            }
        }

        return $sendRes;
    }
}

if (!function_exists('mikhmon_save_whatsapp_config')) {
    function mikhmon_save_whatsapp_config($file, $allData) {
        $out = "<?php\n";
        $out .= 'if(substr($_SERVER["REQUEST_URI"], -19) == "whatsapp_config.php"){header("Location:./");};' . "\n";
        $out .= '$wa_data = ' . var_export($allData, true) . ";\n";
        return file_put_contents($file, $out) !== false;
    }
}

if (!function_exists('mikhmon_send_failed_whatsapp')) {
    function mikhmon_send_failed_whatsapp($session, $order, $reason = '', $lang = 'id') {
        $waConfigFile = __DIR__ . '/whatsapp_config.php';
        if (!file_exists($waConfigFile)) {
            return ['ok' => false, 'description' => 'Config WA tidak ditemukan.'];
        }
        $wa_data = [];
        include $waConfigFile;
        $cfg = !empty($wa_data[$session]) ? $wa_data[$session] : (!empty($wa_data['default']) ? $wa_data['default'] : (reset($wa_data) ?: []));

        if (($cfg['enabled'] ?? 'no') !== 'yes' || empty($cfg['api_token'])) {
            return ['ok' => false, 'description' => 'WhatsApp gateway tidak aktif.'];
        }

        if (($cfg['notif_failed'] ?? 'yes') !== 'yes') {
            return ['ok' => false, 'description' => 'Notifikasi gagal/ditolak dinonaktifkan di pengaturan.'];
        }

        $phone = trim((string)($order['phone'] ?? ''));
        if (empty($phone)) {
            return ['ok' => false, 'description' => 'Nomor HP tidak valid.'];
        }

        $orderId = trim((string)($order['order_id'] ?? ($order['id'] ?? '')));
        $dedupKey = !empty($orderId) ? md5('fail_' . $orderId) : md5('fail_' . $session . '_' . $phone);
        $lockFile = sys_get_temp_dir() . '/mikh_wa_f_' . $dedupKey;

        if (!empty($orderId) && file_exists($lockFile) && (time() - filemtime($lockFile) < 180)) {
            return ['ok' => true, 'description' => 'Pesan kegagalan sudah pernah dikirim.'];
        }
        if (!empty($orderId)) {
            @touch($lockFile);
        }

        $brand = !empty($cfg['brand_name']) ? $cfg['brand_name'] : ($order['merchant_name'] ?? ($order['merchant'] ?? 'WiFi Hotspot'));
        $package = $order['profile'] ?? ($order['package'] ?? 'Paket WiFi');
        $curr = ($lang === 'fr') ? 'FCFA' : 'Rp';
        $priceVal = $order['total_amount'] ?? ($order['price'] ?? 0);
        $price = number_format($priceVal, 0, ',', ($lang === 'fr' ? ' ' : '.'));
        $timeNow = date('d/m/Y H:i:s');

        if ($lang === 'fr') {
            $reasonText = !empty($reason) ? $reason : 'Paiement non complété ou annulé';
            $message = "❌ *ÉCHEC DU PAIEMENT / COMMANDE ANNULÉE — {$brand}*\n\n"
                     . "Bonjour,\n"
                     . "Votre commande de pass WiFi n'a pas pu aboutir :\n\n"
                     . "├ *N° Commande :* `{$orderId}`\n"
                     . "├ *Forfait :* *{$package}*\n"
                     . "├ *Montant :* *{$price} {$curr}*\n"
                     . "├ *Motif :* {$reasonText}\n"
                     . "└ *Date :* {$timeNow}\n\n"
                     . "Veuillez réessayer votre achat depuis le portail ou contacter le support si vous avez besoin d'aide.";
        } else {
            $reasonText = !empty($reason) ? $reason : 'Pembayaran kedaluwarsa atau ditolak oleh Admin.';
            $message = "❌ *PEMBAYARAN GAGAL / PESANAN DIBATALKAN — {$brand}*\n\n"
                     . "Halo, transaksi pembelian voucher WiFi Anda tidak dapat diproses:\n\n"
                     . "├ *Order ID:* `{$orderId}`\n"
                     . "├ *Paket:* *{$package}*\n"
                     . "├ *Nominal:* *Rp {$price}*\n"
                     . "├ *Keterangan:* {$reasonText}\n"
                     . "└ *Waktu:* {$timeNow}\n\n"
                     . "Silakan lakukan pemesanan ulang melalui portal atau hubungi Customer Service kami jika ada kendala.";
        }

        $prov = $cfg['provider'] ?? 'nodera';
        $senderId = '';
        if (in_array($prov, ['nodera', 'mhwa', 'mpwa', 'whacenter', 'custom'])) {
            $senderId = !empty($cfg['session_id']) ? trim($cfg['session_id']) : '';
        }

        $defaultApiUrl = 'https://dgtlnetsolution.com/api/message/send';
        if ($prov === 'fonnte') {
            $defaultApiUrl = 'https://api.fonnte.com/send';
        } elseif ($prov === 'mhwa') {
            $defaultApiUrl = 'https://mhwa.biz.id/api/message/send';
        } elseif ($prov === 'wablas') {
            $defaultApiUrl = 'https://api.wablas.com/api/send-message';
        } elseif ($prov === 'starsender') {
            $defaultApiUrl = 'https://api.starsender.online/api/send';
        } elseif ($prov === 'whacenter') {
            $defaultApiUrl = 'https://app.whacenter.com/api/send';
        }

        return mikhmon_send_whatsapp($cfg['api_token'], $cfg['api_url'] ?: $defaultApiUrl, $phone, $message, $prov, $senderId);
    }
}

if (!function_exists('mikhmon_send_low_stock_whatsapp')) {
    function mikhmon_send_low_stock_whatsapp($session, $profile, $remainingCount, $threshold = 5, $routerName = '') {
        $waConfigFile = __DIR__ . '/whatsapp_config.php';
        $wa_data = [];
        if (file_exists($waConfigFile)) {
            include $waConfigFile;
        }

        $cfg = !empty($wa_data[$session]) ? $wa_data[$session] : (!empty($wa_data['default']) ? $wa_data['default'] : (reset($wa_data) ?: []));

        if (($cfg['enabled'] ?? 'no') !== 'yes' || empty($cfg['api_token'])) {
            return ['ok' => false, 'description' => 'WhatsApp gateway tidak aktif.'];
        }

        if (($cfg['notif_low_stock'] ?? 'yes') !== 'yes') {
            return ['ok' => false, 'description' => 'Notifikasi stok menipis WhatsApp dinonaktifkan.'];
        }

        $adminPhone = trim((string)($cfg['admin_phone'] ?? ''));
        if (empty($adminPhone) && !empty($cfg['cs_phone'])) {
            $adminPhone = trim((string)$cfg['cs_phone']);
        }

        if (empty($adminPhone)) {
            return ['ok' => false, 'description' => 'Nomor WhatsApp Admin belum diisi.'];
        }

        $brand = !empty($cfg['brand_name']) ? $cfg['brand_name'] : 'NODERA WIFI';
        $rName = !empty($routerName) ? $routerName : (!empty($session) ? $session : 'WiFi Hotspot');
        $timeStr = date('d/m/Y H:i:s');

        $statusTitle = ($remainingCount <= 0) ? '🔴 *PERINGATAN: STOK VOUCHER HABIS*' : '⚠️ *PERINGATAN: STOK VOUCHER MENIPIS*';

        $msg = "{$statusTitle} — {$brand}\n\n"
             . "├ Router: {$rName}\n"
             . "├ Profil: {$profile}\n"
             . "├ Sisa Stok: {$remainingCount} voucher (Batas Min: {$threshold})\n"
             . "└ Waktu: {$timeStr}\n\n"
             . "Stok voucher untuk profil *{$profile}* hampir habis. Segera buat/generate voucher baru di Mikhmon.";

        $prov = $cfg['provider'] ?? 'nodera';
        $senderId = '';
        if (in_array($prov, ['nodera', 'mhwa', 'mpwa', 'whacenter', 'custom'])) {
            $senderId = !empty($cfg['session_id']) ? trim($cfg['session_id']) : '';
        }

        $defaultApiUrl = 'https://dgtlnetsolution.com/api/message/send';
        if ($prov === 'fonnte') {
            $defaultApiUrl = 'https://api.fonnte.com/send';
        } elseif ($prov === 'mhwa') {
            $defaultApiUrl = 'https://mhwa.biz.id/api/message/send';
        } elseif ($prov === 'wablas') {
            $defaultApiUrl = 'https://api.wablas.com/api/send-message';
        } elseif ($prov === 'starsender') {
            $defaultApiUrl = 'https://api.starsender.online/api/send';
        } elseif ($prov === 'whacenter') {
            $defaultApiUrl = 'https://app.whacenter.com/api/send';
        }

        return mikhmon_send_whatsapp($cfg['api_token'], $cfg['api_url'] ?: $defaultApiUrl, $adminPhone, $msg, $prov, $senderId);
    }
}
