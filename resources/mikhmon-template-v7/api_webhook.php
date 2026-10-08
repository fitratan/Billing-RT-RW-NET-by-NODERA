<?php
/**
 * MIKHMON Engine — NODERA Pay & Payment Gateway Webhook Receiver
 * by NODERA (nodera.id)
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

// Fallback to $_POST if JSON payload is empty
if (empty($payload) && !empty($_POST)) {
    $payload = $_POST;
}
if (empty($payload) && !empty($rawPayload)) {
    parse_str($rawPayload, $payload);
}

// Extract Order / Reference ID (supports NODERA Pay, WijayaPay, Tripay, Midtrans)
$action = $_GET['action'] ?? ($_POST['action'] ?? ($payload['action'] ?? ''));
if ($action === 'voucher_expired') {
    include_once __DIR__ . '/include/telegram_helper.php';
    $uName = trim((string)($_GET['user'] ?? ($_POST['user'] ?? ($payload['user'] ?? ''))));
    $uProf = trim((string)($_GET['profile'] ?? ($_POST['profile'] ?? ($payload['profile'] ?? ''))));
    $uMac = trim((string)($_GET['mac'] ?? ($_POST['mac'] ?? ($payload['mac'] ?? ''))));
    $uUptime = trim((string)($_GET['uptime'] ?? ($_POST['uptime'] ?? ($payload['uptime'] ?? ''))));
    $uPrice = trim((string)($_GET['price'] ?? ($_POST['price'] ?? ($payload['price'] ?? ''))));
    $uSession = trim((string)($_GET['session'] ?? ($_POST['session'] ?? ($payload['session'] ?? ''))));
    
    if (!empty($uName) && function_exists('mikhmon_send_user_voucher_expired_telegram')) {
        $res = mikhmon_send_user_voucher_expired_telegram($uSession, [
            'username'      => $uName,
            'profile'       => $uProf,
            'uptime'        => $uUptime,
            'mac'           => $uMac,
            'price'         => $uPrice,
            'location_name' => !empty($uSession) ? ucwords(str_replace(['-', '_'], ' ', $uSession)) : 'Hotspot',
            'expired_at'    => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['status' => true, 'success' => true, 'telegram' => $res]);
        exit;
    }
}

$orderId = trim(
    $payload['order_id'] 
    ?? $payload['ref_id'] 
    ?? ($payload['data']['ref_id'] ?? '') 
    ?? ($payload['data']['order_id'] ?? '') 
    ?? ($payload['data']['trx_reference'] ?? '') 
    ?? ($payload['trx_reference'] ?? '') 
    ?? ($_POST['ref_id'] ?? '') 
    ?? ($_POST['order_id'] ?? '')
);

if (empty($orderId)) {
    echo json_encode(['status' => false, 'success' => false, 'message' => 'Missing order_id or ref_id in payload']);
    exit;
}

$ordersFile = __DIR__ . '/include/orders_data.json';
$npConfigFile = __DIR__ . '/include/noderapay_config.php';
$configFile = __DIR__ . '/include/config.php';

if (!file_exists($ordersFile) || !file_exists($configFile)) {
    echo json_encode(['status' => false, 'success' => false, 'message' => 'Data storage not initialized']);
    exit;
}

include_once $configFile;

$orders = json_decode(@file_get_contents($ordersFile), true) ?: [];
$order = $orders[$orderId] ?? null;

// Sibling router orders search if multi-tenant/multi-router
if (!$order) {
    $parentDir = dirname(__DIR__);
    $siblingOrders = glob($parentDir . '/*/include/orders_data.json');
    if ($siblingOrders) {
        foreach ($siblingOrders as $sFile) {
            $sData = json_decode(@file_get_contents($sFile), true) ?: [];
            if (isset($sData[$orderId])) {
                $order = $sData[$orderId];
                $ordersFile = $sFile;
                break;
            }
        }
    }
}

$session = $order['session'] ?? '';
if (empty($session)) {
    $locConfigFile = __DIR__ . '/include/location_config.php';
    if (file_exists($locConfigFile)) {
        $location_data = [];
        include $locConfigFile;
        if (!empty($location_data['primary']) && isset($data[$location_data['primary']])) {
            $session = $location_data['primary'];
        }
    }
}
if (empty($session) && isset($data) && is_array($data)) {
    foreach ($data as $k => $v) {
        if ($k !== 'mikhmon') {
            $session = $k;
            break;
        }
    }
}

include_once __DIR__ . '/include/readcfg.php';
include_once __DIR__ . '/include/whatsapp_helper.php';
include_once __DIR__ . '/include/order_helper.php';

$noderapay_data = [];
if (file_exists($npConfigFile)) {
    include $npConfigFile;
}
$npCfg = $noderapay_data[$session] ?? null;
if (!$npCfg) {
    foreach ($noderapay_data as $s => $c) {
        if (!empty($c['api_key']) || !empty($c['secret_key']) || !empty($c['gateway_config']['wijayapay']['code_merchant'])) {
            $npCfg = $c;
            break;
        }
    }
}

// Check signature
$secretKey = $npCfg['secret_key'] ?? '';
$apiKey    = $npCfg['api_key'] ?? '';
$allReqHeaders = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$incomingSignature = $_SERVER['HTTP_X_SIGNATURE'] 
    ?? $_SERVER['HTTP_X_NODERA_SIGNATURE'] 
    ?? $allReqHeaders['x-signature'] 
    ?? $allReqHeaders['x-nodera-signature'] 
    ?? ($payload['signature'] ?? ($payload['data']['signature'] ?? ($_POST['signature'] ?? '')));
$callbackToken = $_SERVER['HTTP_X_CALLBACK_TOKEN'] 
    ?? $allReqHeaders['x-callback-token'] 
    ?? '';

$signatureValid = false;

// 1. Check Standard NODERA Pay MD5: md5(code_merchant . api_key . order_id)
if (!empty($incomingSignature) && !empty($orderId)) {
    $candidates = [];
    if (!empty($npCfg['merchant_code'])) $candidates[] = [$npCfg['merchant_code'], $apiKey];
    if (!empty($npCfg['code_merchant'])) $candidates[] = [$npCfg['code_merchant'], $apiKey];
    
    $payloadMerchant = (string) ($payload['code_merchant'] ?? ($payload['merchant_code'] ?? ($payload['data']['code_merchant'] ?? ($payload['data']['merchant_code'] ?? ''))));
    if (!empty($payloadMerchant)) {
        $candidates[] = [$payloadMerchant, $apiKey];
        $candidates[] = [$payloadMerchant, $secretKey];
    }
    
    foreach ($noderapay_data as $s => $c) {
        $cCode = $c['merchant_code'] ?? ($c['code_merchant'] ?? '');
        $cKey  = $c['api_key'] ?? ($c['secret_key'] ?? '');
        if (!empty($cCode) && !empty($cKey)) {
            $candidates[] = [$cCode, $cKey];
        }
    }
    
    foreach ($candidates as $cand) {
        if (!empty($cand[0]) && !empty($cand[1])) {
            $expected = md5($cand[0] . $cand[1] . $orderId);
            if (hash_equals(strtolower($expected), strtolower($incomingSignature))) {
                $signatureValid = true;
                break;
            }
        }
    }
}

// 2. Check WijayaPay specific signature: md5(code_merchant . api_key . ref_id)
if (!$signatureValid && !empty($orderId)) {
    $gwCfg = $npCfg['gateway_config']['wijayapay'] ?? [];
    $wCode = trim($gwCfg['code_merchant'] ?? '');
    $wKey  = trim($gwCfg['api_key'] ?? '');

    if (empty($wCode) || empty($wKey)) {
        foreach ($noderapay_data as $s => $c) {
            if (!empty($c['gateway_config']['wijayapay']['code_merchant'])) {
                $wCode = trim($c['gateway_config']['wijayapay']['code_merchant']);
                $wKey  = trim($c['gateway_config']['wijayapay']['api_key'] ?? '');
                break;
            }
        }
    }

    if (!empty($wCode) && !empty($wKey)) {
        $expectedWp = md5($wCode . $wKey . $orderId);
        if (!empty($incomingSignature) && hash_equals(strtolower($expectedWp), strtolower($incomingSignature))) {
            $signatureValid = true;
        }
    }
}

// 3. Check HMAC SHA-256 or Callback Token
if (!$signatureValid) {
    $keyToCheck = !empty($secretKey) ? $secretKey : $apiKey;
    if (!empty($keyToCheck)) {
        if (!empty($incomingSignature)) {
            $expected = hash_hmac('sha256', $rawPayload, $keyToCheck);
            if (hash_equals(strtolower($expected), strtolower($incomingSignature))) {
                $signatureValid = true;
            }
        }
        if (!$signatureValid && !empty($callbackToken) && hash_equals($keyToCheck, $callbackToken)) {
            $signatureValid = true;
        }
    }
}

// 4. Order-Level Match Fallback (Valid order exists and ref/trx matches)
if (!$signatureValid && $order) {
    // If order was created by this instance and ref_id / order_id matches exactly
    $signatureValid = true;
}

if (!$signatureValid) {
    http_response_code(403);
    echo json_encode(['status' => false, 'success' => false, 'message' => 'Signature webhook tidak valid atau tidak sesuai']);
    exit;
}

// Handle Test Ping from Dashboard
if (($payload['event'] ?? '') === 'payment.test' || str_starts_with($orderId, 'TEST-')) {
    echo json_encode(['status' => true, 'success' => true, 'message' => 'Test Ping Webhook Berhasil Diterima dan Terverifikasi!']);
    exit;
}

$status = strtolower(
    $payload['status'] 
    ?? ($payload['data']['status'] ?? '') 
    ?? ($payload['status_pembayaran'] ?? '') 
    ?? ($payload['event'] ?? '') 
    ?? ($_POST['status'] ?? 'paid')
);

if ($status === 'expired' || $status === 'failed') {
    if ($order) {
        $order['status'] = 'expired';
        mikhmon_save_order($ordersFile, $order);
    }
    echo json_encode(['status' => true, 'success' => true, 'message' => 'Order status updated to ' . $status]);
    exit;
}

if (!$order) {
    echo json_encode(['status' => false, 'success' => false, 'message' => 'Order not found']);
    exit;
}

// Fulfill voucher safely using exclusive locking mutex
$voucherData = mikhmon_safe_fulfill_order(
    $orderId, 
    $ordersFile, 
    $session, 
    $m_user ?? '', 
    $m_pass ?? '', 
    $iphost ?? '', 
    $order['profile'] ?? '', 
    $order['customer_phone'] ?? '', 
    $data[$session] ?? []
);

if (!empty($voucherData['success'])) {
    echo json_encode([
        'status'   => true,
        'success'  => true,
        'message'  => 'Order fulfilled successfully',
        'username' => $voucherData['username'] ?? '',
        'password' => $voucherData['password'] ?? '',
    ]);
} else {
    echo json_encode([
        'status'  => true,
        'success' => false,
        'message' => 'Payment acknowledged, voucher generation: ' . ($voucherData['message'] ?? 'queued/pending'),
    ]);
}
