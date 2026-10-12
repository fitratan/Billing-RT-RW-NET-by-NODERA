<?php
/*
 * Copyright (C) Nodera Mikhmon Bandwidth Management
 * Quick Actions Processor (Reset Counter, Kick User, Sync FUP Now)
 */

session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

include_once __DIR__ . '/fup_helper.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$session = $_GET['session'] ?? $_POST['session'] ?? '';

if (empty($session)) {
    header("Location:./admin.php?id=sessions");
    exit;
}

if ($action === 'reset_user') {
    $userId = $_GET['user_id'] ?? '';
    $userName = $_GET['user_name'] ?? '';

    if (!empty($userId)) {
        // Reset counters in MikroTik Hotspot User
        $API->comm("/ip/hotspot/user/reset-counters", array(
            ".id" => $userId
        ));

        // If user is currently online, restore their queue rate-limit to normal profile rate
        if (!empty($userName)) {
            $getUser = $API->comm("/ip/hotspot/user/print", array(
                "?.id" => $userId,
                ".proplist" => "profile"
            ));
            $uProfile = $getUser[0]['profile'] ?? '';

            if (!empty($uProfile)) {
                $getProf = $API->comm("/ip/hotspot/user/profile/print", array(
                    "?name" => $uProfile,
                    ".proplist" => "rate-limit"
                ));
                $normRate = $getProf[0]['rate-limit'] ?? '';

                $qName = '<hotspot-' . $userName . '>';
                $getQueue = $API->comm("/queue/simple/print", array(
                    "?name" => $qName,
                    ".proplist" => ".id"
                ));
                if (is_array($getQueue) && !empty($getQueue[0]['.id']) && !empty($normRate)) {
                    $API->comm("/queue/simple/set", array(
                        ".id" => $getQueue[0]['.id'],
                        "max-limit" => $normRate
                    ));
                }
            }
        }

        $_SESSION['fup_notice'] = "Pemakaian kuota akun '" . htmlspecialchars($userName) . "' berhasil direset kembali ke 0 MB.";
    }
} elseif ($action === 'kick_user') {
    $actId = $_GET['active_id'] ?? '';
    $userName = $_GET['user_name'] ?? '';

    if (!empty($actId)) {
        $API->comm("/ip/hotspot/active/remove", array(
            ".id" => $actId
        ));
        $_SESSION['fup_notice'] = "Sesi aktif user '" . htmlspecialchars($userName) . "' berhasil diputuskan (kick).";
    }
} elseif ($action === 'sync_fup_now') {
    $res = fup_sync_enforce_now($session, $API);
    $throttled = $res['throttled'] ?? 0;
    $restored = $res['restored'] ?? 0;
    $_SESSION['fup_notice'] = "Evaluasi FUP selesai: " . $throttled . " user diturunkan kecepatannya, " . $restored . " user dinormalkan.";
}

header("Location:./?bandwidth=top-voucher&session=" . urlencode($session));
exit;
