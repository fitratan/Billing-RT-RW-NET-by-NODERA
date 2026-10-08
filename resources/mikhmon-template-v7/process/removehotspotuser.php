<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Modified by NODERA (nodera.id)
 */
session_start();
error_reporting(0);

include_once __DIR__ . '/../include/telegram_helper.php';

// Helper to reliably find hotspot user by .id or name in RouterOS
if (!function_exists('mikhmon_find_hotspot_user_for_removal')) {
    function mikhmon_find_hotspot_user_for_removal($API, $idOrName) {
        if (empty($idOrName) || !$API) return [];
        $idOrName = trim((string)$idOrName);

        // Try direct name query first if not starting with *
        if (!str_starts_with($idOrName, '*')) {
            $byName = $API->comm("/ip/hotspot/user/print", ["?name" => $idOrName]);
            if (!empty($byName[0]['name'])) {
                return $byName[0];
            }
        }

        // Match in user list
        $all = $API->comm("/ip/hotspot/user/print", [
            ".proplist" => ".id,name,profile,comment,uptime,mac-address,caller-id,limit-uptime,disabled"
        ]);
        if (is_array($all)) {
            foreach ($all as $u) {
                if (($u['.id'] ?? '') === $idOrName || ($u['name'] ?? '') === $idOrName) {
                    return $u;
                }
            }
        }
        return [];
    }
}

if ($removehotspotusers != "") {
	$uids = explode("~", $removehotspotusers);
	$nuids = count($uids);

	for ($i = 0; $i < $nuids; $i++) {
		$targetId = trim((string)($uids[$i] ?? ''));
		if (empty($targetId)) continue;

		$uData = mikhmon_find_hotspot_user_for_removal($API, $targetId);
		$name = $uData['name'] ?? '';
		$profile = $uData['profile'] ?? '';
		$comment = $uData['comment'] ?? '';
		$uptime = $uData['uptime'] ?? '';
		$mac = $uData['mac-address'] ?? ($uData['caller-id'] ?? '');

		if (!empty($name) && function_exists('mikhmon_send_user_voucher_expired_telegram')) {
			mikhmon_send_user_voucher_expired_telegram($session, [
				'username'      => $name,
				'profile'       => $profile,
				'uptime'        => $uptime,
				'mac'           => $mac,
				'location_name' => $identity ?: ($session ?: 'Hotspot'),
				'expired_at'    => date('Y-m-d H:i:s'),
			], 'Rp', null, true);
		}

		if (!empty($name)) {
			$getscr = $API->comm("/system/script/print", array("?name" => "$name"));
			$scr = $getscr[0]['.id'] ?? '';
			if (!empty($scr)) {
				$API->comm("/system/script/remove", array(".id" => "$scr"));
			}

			$getsch = $API->comm("/system/scheduler/print", array("?name" => "$name"));
			$sch = $getsch[0]['.id'] ?? '';
			if (!empty($sch)) {
				$API->comm("/system/scheduler/remove", array(".id" => "$sch"));
			}
		}

		$realId = !empty($uData['.id']) ? $uData['.id'] : $targetId;
		$API->comm("/ip/hotspot/user/remove", array(".id" => "$realId"));
		
		if (!empty($name)) {
			$getAct = $API->comm("/ip/hotspot/active/print", array("?user" => "$name"));
			if (is_array($getAct)) {
				foreach ($getAct as $act) {
					if (!empty($act['.id'])) {
						$API->comm("/ip/hotspot/active/remove", array(".id" => $act['.id']));
					}
				}
			}
		}
	}

	if ($_SESSION['ubp'] != "") {
		echo "<script>window.location='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'</script>";
	} elseif ($_SESSION['ubc'] != "") {
		echo "<script>window.location='./?hotspot=users&comment=" . $_SESSION['ubc'] . "&session=" . $session . "'</script>";
	} else {
		echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "'</script>";
	}
} else {
	$targetId = trim((string)$removehotspotuser);
	$uData = mikhmon_find_hotspot_user_for_removal($API, $targetId);
	$name = $uData['name'] ?? '';
	$profile = $uData['profile'] ?? '';
	$comment = $uData['comment'] ?? '';
	$uptime = $uData['uptime'] ?? '';
	$mac = $uData['mac-address'] ?? ($uData['caller-id'] ?? '');

	if (!empty($name) && function_exists('mikhmon_send_user_voucher_expired_telegram')) {
		mikhmon_send_user_voucher_expired_telegram($session, [
			'username'      => $name,
			'profile'       => $profile,
			'uptime'        => $uptime,
			'mac'           => $mac,
			'location_name' => $identity ?: ($session ?: 'Hotspot'),
			'expired_at'    => date('Y-m-d H:i:s'),
		], 'Rp', null, true);
	}

	if (!empty($name)) {
		$getscr = $API->comm("/system/script/print", array("?name" => "$name"));
		$scr = $getscr[0]['.id'] ?? '';
		if (!empty($scr)) {
			$API->comm("/system/script/remove", array(".id" => "$scr"));
		}

		$getsch = $API->comm("/system/scheduler/print", array("?name" => "$name"));
		$sch = $getsch[0]['.id'] ?? '';
		if (!empty($sch)) {
			$API->comm("/system/scheduler/remove", array(".id" => "$sch"));
		}
	}

	$realId = !empty($uData['.id']) ? $uData['.id'] : $targetId;
	$API->comm("/ip/hotspot/user/remove", array(".id" => "$realId"));

	if (!empty($name)) {
		$getAct = $API->comm("/ip/hotspot/active/print", array("?user" => "$name"));
		if (is_array($getAct)) {
			foreach ($getAct as $act) {
				if (!empty($act['.id'])) {
					$API->comm("/ip/hotspot/active/remove", array(".id" => $act['.id']));
				}
			}
		}
	}

	if ($_SESSION['ubp'] != "") {
		echo "<script>window.location='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'</script>";
	} elseif ($_SESSION['ubc'] != "") {
		echo "<script>window.location='./?hotspot=users&comment=" . $_SESSION['ubc'] . "&session=" . $session . "'</script>";
	} else {
		echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "'</script>";
	}
}
?>
