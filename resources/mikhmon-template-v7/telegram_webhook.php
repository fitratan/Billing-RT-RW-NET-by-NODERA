<?php
/**
 * MIKHMON Engine — Telegram Bot Webhook (Interactive ACC / Reject & Commands)
 * by NODERA (nodera.id)
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

$configFile = __DIR__ . '/include/config.php';
$tgConfigFile = __DIR__ . '/include/telegram_config.php';
$npConfigFile = __DIR__ . '/include/noderapay_config.php';
$waConfigFile = __DIR__ . '/include/whatsapp_config.php';
$ordersFile = __DIR__ . '/include/orders_data.json';

if (!file_exists($configFile) || !file_exists($tgConfigFile)) {
    echo json_encode(['ok' => false, 'description' => 'Config not found']);
    exit;
}

$data = [];
include_once $configFile;
$tg_data = [];
include_once $tgConfigFile;
include_once __DIR__ . '/include/telegram_helper.php';
include_once __DIR__ . '/include/order_helper.php';
include_once __DIR__ . '/include/whatsapp_helper.php';

$noderapay_data = [];
if (file_exists($npConfigFile)) {
    include $npConfigFile;
}

// 1. Get raw Telegram update payload
$rawInput = file_get_contents('php://input');
if (empty($rawInput) && !empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
    $rawInput = $GLOBALS['HTTP_RAW_POST_DATA'];
}
if (empty($rawInput) && !empty($GLOBALS['LARAVEL_RAW_INPUT'])) {
    $rawInput = $GLOBALS['LARAVEL_RAW_INPUT'];
}

// Log incoming request for diagnostic monitoring
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@file_put_contents($logDir . '/telegram_webhook.log', date('Y-m-d H:i:s') . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '') . ' | RAW: ' . $rawInput . "\n", FILE_APPEND);

if (empty($rawInput)) {
    // If accessed via GET / browser directly, show webhook health status
    echo json_encode([
        'ok'          => true,
        'service'     => 'NODERA Mikhmon Telegram Webhook',
        'status'      => 'ONLINE',
        'time'        => date('Y-m-d H:i:s'),
        'description' => 'Endpoint aktif siap menerima webhook Telegram Bot API.'
    ]);
    exit;
}

$update = json_decode($rawInput, true);
if (!$update || !is_array($update)) {
    echo json_encode(['ok' => false, 'description' => 'Invalid JSON payload']);
    exit;
}

// Helper to determine active bot token strictly for this Mikhmon instance
if (!function_exists('mikhmon_find_active_token')) {
    function mikhmon_find_active_token($session, $tg_data) {
        if (!empty($session) && !empty($tg_data[$session]['bot_token'])) {
            return trim($tg_data[$session]['bot_token']);
        }
        if (is_array($tg_data)) {
            foreach ($tg_data as $s => $cfg) {
                if ($s !== '_global' && is_array($cfg) && !empty($cfg['bot_token'])) {
                    return trim($cfg['bot_token']);
                }
            }
        }
        return '';
    }
}

$paramToken = trim($_GET['token'] ?? '');
$activeToken = !empty($paramToken) ? $paramToken : mikhmon_find_active_token('', $tg_data);

// ── 2. Handle Inline Button Callbacks (ACC / Reject) ──
if (isset($update['callback_query'])) {
    $cq = $update['callback_query'];
    $cqId = $cq['id'] ?? '';
    $from = $cq['from'] ?? [];
    $fromUser = !empty($from['username']) ? '@' . $from['username'] : (trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: 'Admin (ID ' . ($from['id'] ?? '') . ')');
    $callbackData = trim((string)($cq['data'] ?? ''));
    $msgObj = $cq['message'] ?? [];
    $chatId = $msgObj['chat']['id'] ?? ($from['id'] ?? '');
    $messageId = $msgObj['message_id'] ?? 0;
    $timeNow = date('Y-m-d H:i:s');

    // Handle Test Simulation buttons
    if ($callbackData === 'test_acc_sim') {
        mikhmon_answer_callback_query($activeToken, $cqId, "✅ [SIMULASI] Notifikasi Accept berfungsi normal!", true);
        $testMsg = "✅ <b>[SIMULASI] VOUCHER TELAH DI-ACCEPT</b>\n\n"
                 . "┌ Order ID: <code>TEST-001</code>\n"
                 . "├ Status: 🟢 <b>LUNAS &amp; AKTIF (SIMULASI)</b>\n"
                 . "├ Disetujui oleh: " . htmlspecialchars($fromUser, ENT_QUOTES, 'UTF-8') . "\n"
                 . "└ Waktu: " . $timeNow;
        mikhmon_edit_telegram_message($activeToken, $chatId, $messageId, $testMsg, null);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($callbackData === 'test_rej_sim') {
        mikhmon_answer_callback_query($activeToken, $cqId, "❌ [SIMULASI] Notifikasi Reject berfungsi normal!", true);
        $testMsg = "❌ <b>[SIMULASI] PESANAN DI-REJECT</b>\n\n"
                 . "┌ Order ID: <code>TEST-001</code>\n"
                 . "├ Status: 🔴 <b>DITOLAK (SIMULASI)</b>\n"
                 . "├ Ditolak oleh: " . htmlspecialchars($fromUser, ENT_QUOTES, 'UTF-8') . "\n"
                 . "└ Waktu: " . $timeNow;
        mikhmon_edit_telegram_message($activeToken, $chatId, $messageId, $testMsg, null);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Handle Real ACC / Reject Callbacks
    $isAcc = str_starts_with($callbackData, 'acc_') || str_starts_with($callbackData, 'acc:') || str_starts_with($callbackData, 'acc_vch:') || str_starts_with($callbackData, 'acc_vch_');
    $isRej = str_starts_with($callbackData, 'rej_') || str_starts_with($callbackData, 'rej:') || str_starts_with($callbackData, 'rej_vch:') || str_starts_with($callbackData, 'rej_vch_');

    if ($isAcc || $isRej) {
        $orderId = '';
        if ($isAcc) {
            if (str_starts_with($callbackData, 'acc_vch:')) {
                $orderId = substr($callbackData, 8);
            } elseif (str_starts_with($callbackData, 'acc_vch_')) {
                $orderId = substr($callbackData, 8);
            } elseif (str_starts_with($callbackData, 'acc:')) {
                $orderId = substr($callbackData, 4);
            } else {
                $orderId = substr($callbackData, 4);
            }
        } elseif ($isRej) {
            if (str_starts_with($callbackData, 'rej_vch:')) {
                $orderId = substr($callbackData, 8);
            } elseif (str_starts_with($callbackData, 'rej_vch_')) {
                $orderId = substr($callbackData, 8);
            } elseif (str_starts_with($callbackData, 'rej_')) {
                $orderId = substr($callbackData, 4);
            } else {
                $orderId = substr($callbackData, 4);
            }
        }
        $orderId = trim($orderId);

        $orders = mikhmon_get_orders($ordersFile);
        $order = $orders[$orderId] ?? null;

        // Fallback: Check sibling tenant directories if not found in current folder
        if (!$order) {
            $parentDir = dirname(__DIR__);
            $siblingOrders = glob($parentDir . '/*/include/orders_data.json');
            if ($siblingOrders) {
                foreach ($siblingOrders as $sFile) {
                    $sList = mikhmon_get_orders($sFile);
                    if (isset($sList[$orderId])) {
                        $order = $sList[$orderId];
                        $ordersFile = $sFile;
                        $tDir = dirname(dirname($sFile));
                        if (file_exists($tDir . '/include/config.php')) {
                            $configFile = $tDir . '/include/config.php';
                            include $configFile;
                        }
                        if (file_exists($tDir . '/include/telegram_config.php')) {
                            $tgConfigFile = $tDir . '/include/telegram_config.php';
                            include $tgConfigFile;
                        }
                        break;
                    }
                }
            }
        }

        if (!$order) {
            mikhmon_answer_callback_query($activeToken, $cqId, "⚠️ Pesanan " . $orderId . " tidak ditemukan di sistem.", true);
            $notFoundMsg = "⚠️ <b>PESANAN TIDAK DITEMUKAN</b>\n\n"
                         . "┌ Order ID: <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code>\n"
                         . "├ Status: ❌ Tidak ditemukan dalam database\n"
                         . "└ Waktu: " . $timeNow;
            mikhmon_edit_telegram_message($activeToken, $chatId, $messageId, $notFoundMsg, null);
            echo json_encode(['ok' => false, 'description' => 'Order not found']);
            exit;
        }

        $session = $order['session'] ?? '';
        $sessionCfg = (isset($data[$session]) && is_array($data[$session])) ? $data[$session] : null;

        if (!$sessionCfg) {
            // Check location config primary
            $locConfigFile = __DIR__ . '/include/location_config.php';
            if (file_exists($locConfigFile)) {
                $location_data = [];
                @include $locConfigFile;
                if (!empty($location_data['primary']) && isset($data[$location_data['primary']]) && is_array($data[$location_data['primary']])) {
                    $session = $location_data['primary'];
                    $sessionCfg = $data[$session];
                }
            }
        }

        if (!$sessionCfg && isset($data) && is_array($data)) {
            // Pick first router session from config.php if not matched
            foreach ($data as $k => $v) {
                if ($k !== 'mikhmon' && is_array($v)) {
                    $session = $k;
                    $sessionCfg = $v;
                    break;
                }
            }
        }

        // Re-resolve active token with session
        $activeToken = mikhmon_find_active_token($session, $tg_data);

        // Correctly parse and strip Mikhmon delimiters from credentials
        $rawIp = $sessionCfg[1] ?? '';
        $iphost = strpos($rawIp, '!') !== false ? (explode('!', $rawIp)[1] ?? '') : $rawIp;

        $rawUser = $sessionCfg[2] ?? '';
        $userhost = strpos($rawUser, '@|@') !== false ? (explode('@|@', $rawUser)[1] ?? '') : $rawUser;

        $rawPass = $sessionCfg[3] ?? '';
        $passwdhost = strpos($rawPass, '#|#') !== false ? (explode('#|#', $rawPass)[1] ?? '') : $rawPass;

        $rawHotspot = $sessionCfg[4] ?? '';
        $hotspotname = strpos($rawHotspot, '%') !== false ? (explode('%', $rawHotspot)[1] ?? '') : ($order['hotspotname'] ?? 'WiFi Hotspot');

        $rawDns = $sessionCfg[5] ?? '';
        $dnsname = strpos($rawDns, '^') !== false ? (explode('^', $rawDns)[1] ?? '') : ($order['dnsname'] ?? '');

        $rawCurr = $sessionCfg[6] ?? '';
        $currency = strpos($rawCurr, '&') !== false ? (explode('&', $rawCurr)[1] ?? '') : 'Rp';
        $currPrefix = !empty($currency) ? $currency . ' ' : 'Rp ';

        $locConfigFile = __DIR__ . '/include/location_config.php';
        $locName = '';
        if (file_exists($locConfigFile)) {
            $location_data = [];
            include $locConfigFile;
            $locName = trim($location_data['locations'][$session] ?? '');
        }
        $resolvedLoc = (!empty($order['location_name']) && strtolower($order['location_name']) !== 'dns')
            ? $order['location_name']
            : ((!empty($locName) && strtolower($locName) !== 'dns') ? $locName : ((!empty($hotspotname) && strtolower($hotspotname) !== 'dns') ? $hotspotname : ucwords(str_replace(['-', '_'], ' ', $session))));

        $brandCfg = trim((string)($tg_data[$session]['brand_name'] ?? ''));
        $brand = (!empty($brandCfg) && strtolower($brandCfg) !== 'dns') ? $brandCfg : (!empty($resolvedLoc) && strtolower($resolvedLoc) !== 'dns' ? $resolvedLoc : 'NODERA');
        $npCfg = $noderapay_data[$session] ?? reset($noderapay_data) ?: [];

        // ── A. Process ACC / Approve Action ──
        if ($isAcc) {
            if ($order['status'] === 'paid' && !empty($order['voucher'])) {
                mikhmon_answer_callback_query($activeToken, $cqId, "ℹ️ Pesanan ini sudah disetujui sebelumnya.", false);

                $voucherData = $order['voucher'];
                $locDisplay = (!empty($order['location_name']) && strtolower($order['location_name']) !== 'dns') ? $order['location_name'] : $resolvedLoc;
                $formattedPrice = $currPrefix . number_format($order['total_amount'] ?? $order['price'] ?? 0, 0, ',', '.');
                $approvedBy = !empty($order['approved_by']) ? $order['approved_by'] : $fromUser;
                $approvedAt = !empty($order['approved_at']) ? $order['approved_at'] : $timeNow;
                $customerPhone = preg_replace('/[^0-9]/', '', (string)($order['phone'] ?? ''));
                $vUser = trim((string)($voucherData['username'] ?? ''));
                $vPass = trim((string)($voucherData['password'] ?? $vUser));
                $vVal  = $voucherData['validity'] ?? ($order['validity'] ?? 'Standar');
                $isSameUserPass = empty($vPass) || ($vPass === $vUser);

                if ($isSameUserPass) {
                    $waLoginDetail = "• Kode Voucher: " . $vUser;
                    $tgCredDetail  = "│ • <b>Kode Voucher:</b> <code>" . htmlspecialchars($vUser, ENT_QUOTES, 'UTF-8') . "</code>";
                } else {
                    $waLoginDetail = "• Username: " . $vUser . "\n• Password: " . $vPass;
                    $tgCredDetail  = "│ • <b>Username:</b> <code>" . htmlspecialchars($vUser, ENT_QUOTES, 'UTF-8') . "</code>\n"
                                   . "│ • <b>Password:</b> <code>" . htmlspecialchars($vPass, ENT_QUOTES, 'UTF-8') . "</code>";
                }

                $customerWaText = "*PEMBAYARAN BERHASIL — " . $brand . "*\n"
                                . "----------------------------------------\n"
                                . "Terima kasih telah membeli voucher WiFi.\n\n"
                                . "Paket: " . ($order['profile'] ?? '') . "\n"
                                . "Total Bayar: " . $formattedPrice . "\n\n"
                                . "DETAIL LOGIN:\n"
                                . $waLoginDetail . "\n"
                                . "• Masa Aktif: " . $vVal . "\n\n"
                                . "Simpan pesan ini jika sewaktu-waktu perangkat Anda terputus.";

                $updatedMsg = "✅ <b>PESANAN VOUCHER SUDAH DI-ACCEPT &amp; AKTIF — " . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . "</b>\n\n"
                            . "┌ <b>Order ID:</b> <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code>\n"
                            . "├ <b>Router / Lokasi:</b> " . htmlspecialchars($locDisplay, ENT_QUOTES, 'UTF-8') . "\n"
                            . "├ <b>WhatsApp Pelanggan:</b> <code>" . htmlspecialchars($order['phone'] ?? '', ENT_QUOTES, 'UTF-8') . "</code>\n"
                            . "├ <b>Paket Voucher:</b> <b>" . htmlspecialchars($order['profile'] ?? '', ENT_QUOTES, 'UTF-8') . "</b> (" . $formattedPrice . ")\n"
                            . "├ <b>Status:</b> 🟢 <b>LUNAS &amp; AKTIF</b>\n"
                            . "├ <b>KREDENSIAL VOUCHER:</b>\n"
                            . $tgCredDetail . "\n"
                            . "│ • <b>Masa Aktif:</b> " . htmlspecialchars($vVal, ENT_QUOTES, 'UTF-8') . "\n"
                            . "├ <b>Disetujui Oleh:</b> " . htmlspecialchars($approvedBy, ENT_QUOTES, 'UTF-8') . "\n"
                            . "└ <b>Waktu ACC:</b> " . htmlspecialchars($approvedAt, ENT_QUOTES, 'UTF-8');

                $afterKeyboard = null;

                mikhmon_edit_telegram_message($activeToken, $chatId, $messageId, $updatedMsg, $afterKeyboard);
                echo json_encode(['ok' => true]);
                exit;
            }

            if ($order['status'] === 'rejected') {
                mikhmon_answer_callback_query($activeToken, $cqId, "⚠️ Pesanan ini sudah ditolak sebelumnya.", true);

                $locDisplayRej = (!empty($order['location_name']) && strtolower($order['location_name']) !== 'dns') ? $order['location_name'] : $resolvedLoc;
                $formattedPrice = $currPrefix . number_format($order['total_amount'] ?? $order['price'] ?? 0, 0, ',', '.');
                $rejectedBy = !empty($order['rejected_by']) ? $order['rejected_by'] : $fromUser;
                $rejectedAt = !empty($order['rejected_at']) ? $order['rejected_at'] : $timeNow;

                $rejectedMsg = "❌ <b>PESANAN VOUCHER DITOLAK — " . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . "</b>\n\n"
                             . "┌ <b>Order ID:</b> <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code>\n"
                             . "├ <b>Router / Lokasi:</b> " . htmlspecialchars($locDisplayRej, ENT_QUOTES, 'UTF-8') . "\n"
                             . "├ <b>WhatsApp Pelanggan:</b> <code>" . htmlspecialchars($order['phone'] ?? '', ENT_QUOTES, 'UTF-8') . "</code>\n"
                             . "├ <b>Paket Voucher:</b> <b>" . htmlspecialchars($order['profile'] ?? '', ENT_QUOTES, 'UTF-8') . "</b> (" . $formattedPrice . ")\n"
                             . "├ <b>Status:</b> 🔴 <b>DITOLAK (REJECTED)</b>\n"
                             . "├ <b>Ditolak Oleh:</b> " . htmlspecialchars($rejectedBy, ENT_QUOTES, 'UTF-8') . "\n"
                             . "└ <b>Waktu:</b> " . htmlspecialchars($rejectedAt, ENT_QUOTES, 'UTF-8');

                $afterKeyboard = null;

                mikhmon_edit_telegram_message($activeToken, $chatId, $messageId, $rejectedMsg, $afterKeyboard);
                echo json_encode(['ok' => true]);
                exit;
            }

            // Fulfill voucher on MikroTik
            $voucherData = mikhmon_safe_fulfill_order(
                $orderId,
                $session,
                $iphost,
                $userhost,
                $passwdhost,
                $hotspotname,
                $dnsname,
                $npCfg,
                $ordersFile
            );

            if ($voucherData) {
                // Refresh order data
                $freshOrders = mikhmon_get_orders($ordersFile);
                $freshOrder = $freshOrders[$orderId] ?? $order;
                $freshOrder['status'] = 'paid';
                $freshOrder['approved_by'] = $fromUser;
                $freshOrder['approved_at'] = $timeNow;
                $freshOrder['payment_method'] = 'manual';
                mikhmon_save_order($ordersFile, $freshOrder);

            // Dispatch failed/rejected WhatsApp notification to buyer if enabled
            include_once __DIR__ . '/include/whatsapp_helper.php';
            if (function_exists('mikhmon_send_failed_whatsapp')) {
                $reasonRej = ($lang === 'fr') ? "Commande rejetée par l'administrateur (" . $fromUser . ")" : "Pesanan ditolak oleh Admin (" . $fromUser . ")";
                @mikhmon_send_failed_whatsapp($freshOrder['session'] ?? $session, $freshOrder, $reasonRej, 'id');
            }

                $formattedPrice = $currPrefix . number_format($freshOrder['total_amount'] ?? $freshOrder['price'], 0, ',', '.');

                $vUser = trim((string)($voucherData['username'] ?? ''));
                $vPass = trim((string)($voucherData['password'] ?? $vUser));
                $vVal  = $voucherData['validity'] ?? ($freshOrder['validity'] ?? ($order['validity'] ?? 'Standar'));
                $isSameUserPass = empty($vPass) || ($vPass === $vUser);

                // Pop-up Toast
                $toastMsg = $isSameUserPass 
                    ? "✅ PESANAN DI-ACCEPT!\nKode Voucher: " . $vUser
                    : "✅ PESANAN DI-ACCEPT!\nUsername: " . $vUser . "\nPassword: " . $vPass;
                mikhmon_answer_callback_query($activeToken, $cqId, $toastMsg, true);

                $customerPhone = preg_replace('/[^0-9]/', '', (string)($freshOrder['phone'] ?? $order['phone']));

                if ($isSameUserPass) {
                    $waLoginDetail = "• Kode Voucher: " . $vUser;
                    $tgCredDetail  = "│ • <b>Kode Voucher:</b> <code>" . htmlspecialchars($vUser, ENT_QUOTES, 'UTF-8') . "</code>";
                } else {
                    $waLoginDetail = "• Username: " . $vUser . "\n• Password: " . $vPass;
                    $tgCredDetail  = "│ • <b>Username:</b> <code>" . htmlspecialchars($vUser, ENT_QUOTES, 'UTF-8') . "</code>\n"
                                   . "│ • <b>Password:</b> <code>" . htmlspecialchars($vPass, ENT_QUOTES, 'UTF-8') . "</code>";
                }

                $customerWaText = "*PEMBAYARAN BERHASIL — " . $brand . "*\n"
                                . "----------------------------------------\n"
                                . "Terima kasih telah membeli voucher WiFi.\n\n"
                                . "Paket: " . ($freshOrder['profile'] ?? $order['profile']) . "\n"
                                . "Total Bayar: " . $formattedPrice . "\n\n"
                                . "DETAIL LOGIN:\n"
                                . $waLoginDetail . "\n"
                                . "• Masa Aktif: " . $vVal . "\n\n"
                                . "Simpan pesan ini jika sewaktu-waktu perangkat Anda terputus.";

                // Update Telegram message
                $locDisplay = (!empty($freshOrder['location_name']) && strtolower($freshOrder['location_name']) !== 'dns') ? $freshOrder['location_name'] : $resolvedLoc;
                $updatedMsg = "✅ <b>PESANAN VOUCHER TELAH DI-ACCEPT &amp; AKTIF — " . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . "</b>\n\n"
                            . "┌ <b>Order ID:</b> <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code>\n"
                            . "├ <b>Router / Lokasi:</b> " . htmlspecialchars($locDisplay, ENT_QUOTES, 'UTF-8') . "\n"
                            . "├ <b>WhatsApp Pelanggan:</b> <code>" . htmlspecialchars($freshOrder['phone'] ?? $order['phone'], ENT_QUOTES, 'UTF-8') . "</code>\n"
                            . "├ <b>Paket Voucher:</b> <b>" . htmlspecialchars($freshOrder['profile'] ?? $order['profile'], ENT_QUOTES, 'UTF-8') . "</b> (" . $formattedPrice . ")\n"
                            . "├ <b>Status:</b> 🟢 <b>LUNAS &amp; AKTIF</b>\n"
                            . "├ <b>KREDENSIAL VOUCHER:</b>\n"
                            . $tgCredDetail . "\n"
                            . "│ • <b>Masa Aktif:</b> " . htmlspecialchars($vVal, ENT_QUOTES, 'UTF-8') . "\n"
                            . "├ <b>Disetujui Oleh:</b> " . htmlspecialchars($fromUser, ENT_QUOTES, 'UTF-8') . "\n"
                            . "└ <b>Waktu ACC:</b> " . $timeNow;

                $afterKeyboard = null;

                if (!empty($freshOrder['telegram_messages']) && is_array($freshOrder['telegram_messages'])) {
                    foreach ($freshOrder['telegram_messages'] as $mInfo) {
                        if (!empty($mInfo['chat_id']) && !empty($mInfo['message_id']) && ((string)$mInfo['message_id'] !== (string)$messageId || (string)$mInfo['chat_id'] !== (string)$chatId)) {
                            @mikhmon_edit_telegram_message($activeToken, $mInfo['chat_id'], $mInfo['message_id'], $updatedMsg, $afterKeyboard);
                        }
                    }
                }
                mikhmon_edit_telegram_message($activeToken, $chatId, $messageId, $updatedMsg, $afterKeyboard);

                echo json_encode(['ok' => true, 'status' => 'APPROVED', 'voucher' => $voucherData]);
                exit;
            } else {
                mikhmon_answer_callback_query($activeToken, $cqId, "❌ Gagal terhubung ke MikroTik (" . $iphost . "). Pastikan router online dan port API 8728 terbuka.", true);
                echo json_encode(['ok' => false, 'description' => 'Fulfillment failed']);
                exit;
            }
        }

        // ── B. Process Reject / Tolak Action ──
        if ($isRej) {
            $freshOrders = mikhmon_get_orders($ordersFile);
            $freshOrder = $freshOrders[$orderId] ?? $order;

            if ($freshOrder['status'] === 'paid') {
                mikhmon_answer_callback_query($activeToken, $cqId, "⚠️ Pesanan ini sudah disetujui dan tidak dapat ditolak.", true);
                echo json_encode(['ok' => true]);
                exit;
            }

            $freshOrder['status'] = 'rejected';
            $freshOrder['rejected_by'] = $fromUser;
            $freshOrder['rejected_at'] = $timeNow;
            mikhmon_save_order($ordersFile, $freshOrder);

            $formattedPrice = $currPrefix . number_format($freshOrder['total_amount'] ?? $freshOrder['price'], 0, ',', '.');

            mikhmon_answer_callback_query($activeToken, $cqId, "❌ Pesanan " . $orderId . " DITOLAK.", true);

            $locDisplayRej = (!empty($freshOrder['location_name']) && strtolower($freshOrder['location_name']) !== 'dns') ? $freshOrder['location_name'] : $resolvedLoc;
            $rejectedMsg = "❌ <b>PESANAN VOUCHER DITOLAK — " . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . "</b>\n\n"
                         . "┌ <b>Order ID:</b> <code>" . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "</code>\n"
                         . "├ <b>Router / Lokasi:</b> " . htmlspecialchars($locDisplayRej, ENT_QUOTES, 'UTF-8') . "\n"
                         . "├ <b>WhatsApp Pelanggan:</b> <code>" . htmlspecialchars($freshOrder['phone'], ENT_QUOTES, 'UTF-8') . "</code>\n"
                         . "├ <b>Paket Voucher:</b> <b>" . htmlspecialchars($freshOrder['profile'], ENT_QUOTES, 'UTF-8') . "</b> (" . $formattedPrice . ")\n"
                         . "├ <b>Status:</b> 🔴 <b>DITOLAK (REJECTED)</b>\n"
                         . "├ <b>Ditolak Oleh:</b> " . htmlspecialchars($fromUser, ENT_QUOTES, 'UTF-8') . "\n"
                         . "└ <b>Waktu:</b> " . $timeNow;

            $afterKeyboard = null;

            if (!empty($freshOrder['telegram_messages']) && is_array($freshOrder['telegram_messages'])) {
                foreach ($freshOrder['telegram_messages'] as $mInfo) {
                    if (!empty($mInfo['chat_id']) && !empty($mInfo['message_id']) && ((string)$mInfo['message_id'] !== (string)$messageId || (string)$mInfo['chat_id'] !== (string)$chatId)) {
                        @mikhmon_edit_telegram_message($activeToken, $mInfo['chat_id'], $mInfo['message_id'], $rejectedMsg, $afterKeyboard);
                    }
                }
            }
            mikhmon_edit_telegram_message($activeToken, $chatId, $messageId, $rejectedMsg, $afterKeyboard);

            echo json_encode(['ok' => true, 'status' => 'REJECTED']);
            exit;
        }
    }
}

// ── 3. Handle Message Commands (/getid, /status, /help, /start) ──
if (isset($update['message'])) {
    $msg = $update['message'];
    $text = trim((string)($msg['text'] ?? ''));
    $chatId = $msg['chat']['id'] ?? '';
    $chatType = $msg['chat']['type'] ?? 'private';
    $threadId = $msg['message_thread_id'] ?? '';
    $userId = $msg['from']['id'] ?? '';
    $userName = $msg['from']['username'] ?? ($msg['from']['first_name'] ?? 'User');

    if (str_starts_with($text, '/getid') || str_starts_with($text, '/id') || str_starts_with($text, '/myid') || str_starts_with($text, '/chatid')) {
        $reply = "ℹ️ <b>INFORMASI ID TELEGRAM ANDA</b>\n\n"
               . "┌ <b>Chat / Group ID:</b> <code>" . htmlspecialchars($chatId, ENT_QUOTES, 'UTF-8') . "</code>\n"
               . "├ <b>Jenis Chat:</b> <code>" . htmlspecialchars($chatType, ENT_QUOTES, 'UTF-8') . "</code>\n"
               . "├ <b>Topic / Thread ID:</b> <code>" . (!empty($threadId) ? htmlspecialchars($threadId, ENT_QUOTES, 'UTF-8') : "(Tidak ada / General)") . "</code>\n"
               . "├ <b>User ID Anda:</b> <code>" . htmlspecialchars($userId, ENT_QUOTES, 'UTF-8') . "</code>\n"
               . "└ <b>Nama:</b> " . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . "\n\n"
               . "<i>Salin Chat ID atau Topic ID di atas dan masukkan ke menu Pengaturan Telegram di Mikhmon.</i>";

        mikhmon_send_telegram($activeToken, $chatId, !empty($threadId) ? 'topic' : 'chat', $threadId, $reply);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (str_starts_with($text, '/status') || str_starts_with($text, '/stat')) {
        $orders = mikhmon_get_orders($ordersFile);
        $pendingManual = 0;
        $paidToday = 0;
        $todayDate = date('Y-m-d');

        foreach ($orders as $o) {
            if (($o['status'] ?? '') === 'pending' || ($o['status'] ?? '') === 'pending_manual') {
                $pendingManual++;
            }
            if (($o['status'] ?? '') === 'paid' && str_starts_with($o['paid_at'] ?? '', $todayDate)) {
                $paidToday++;
            }
        }

        $reply = "📊 <b>STATUS VOUCHER &amp; BOT TELEGRAM</b>\n\n"
               . "┌ <b>Status:</b> 🟢 ONLINE &amp; AKTIF\n"
               . "├ <b>Pesanan Menunggu:</b> <code>" . $pendingManual . " transaksi</code>\n"
               . "├ <b>Voucher Sukses Hari Ini:</b> <code>" . $paidToday . " transaksi</code>\n"
               . "└ <b>Waktu Server:</b> " . date('Y-m-d H:i:s');

        mikhmon_send_telegram($activeToken, $chatId, !empty($threadId) ? 'topic' : 'chat', $threadId, $reply);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (str_starts_with($text, '/start') || str_starts_with($text, '/help') || str_starts_with($text, '/bantuan')) {
        $reply = "👋 <b>Halo, " . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . "!</b>\n\n"
               . "Bot Notifikasi &amp; Approval Voucher Hotspot Mikhmon (NODERA) siap digunakan.\n\n"
               . "📌 <b>Perintah yang tersedia:</b>\n"
               . "• <code>/getid</code> - Cek ID Chat &amp; Topic grup ini\n"
               . "• <code>/status</code> - Cek rekap pesanan hari ini\n"
               . "• <code>/bantuan</code> - Tampilkan petunjuk ini\n";

        mikhmon_send_telegram($activeToken, $chatId, !empty($threadId) ? 'topic' : 'chat', $threadId, $reply);
        echo json_encode(['ok' => true]);
        exit;
    }
}

echo json_encode(['ok' => true]);
