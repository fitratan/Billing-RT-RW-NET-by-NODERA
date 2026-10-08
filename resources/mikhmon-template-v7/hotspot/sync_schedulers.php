<?php
/**
 * Auto-Sync All Profile Schedulers & Clean Expired Vouchers
 * Nodera Mikhmon Engine - Multi-Version ROS6 & ROS7 Enhanced
 */
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location: ./?session=" . urlencode($session));
    exit;
}

$synced_profiles = 0;
$removed_users = 0;
$error_msg = "";

if ($API->connected) {
    // 1. Auto-Ensure Clock & NTP Configuration
    $clock = $API->comm("/system/clock/print");
    $clockDate = $clock[0]['date'] ?? date('M/d/Y');
    $clockTime = $clock[0]['time'] ?? date('H:i:s');
    $timeZone = $clock[0]['time-zone-name'] ?? '';

    // Set Timezone to Asia/Jakarta if not set or GMT
    if (empty($timeZone) || $timeZone === 'manual' || strpos($timeZone, 'GMT') !== false) {
        $API->comm("/system/clock/set", ["time-zone-name" => "Asia/Jakarta"]);
    }

    // Attempt NTP activation for ROS6 & ROS7
    $API->comm("/system/ntp/client/set", [
        "enabled" => "yes",
        "primary-ntp" => "162.159.200.1",
        "secondary-ntp" => "103.119.16.2"
    ]);
    $API->comm("/system/ntp/client/set", ["enabled" => "yes"]);
    $API->comm("/system/ntp/client/servers/add", ["address" => "id.pool.ntp.org"]);

    $monthArray = [
        "jan" => 1, "feb" => 2, "mar" => 3, "apr" => 4, "may" => 5, "jun" => 6,
        "jul" => 7, "aug" => 8, "sep" => 9, "oct" => 10, "nov" => 11, "dec" => 12
    ];

    // Helper to convert date string to integer YYYYMMDD
    $parseDateInt = function($dateStr) use ($monthArray) {
        $dateStr = strtolower(trim($dateStr));
        if (empty($dateStr)) return 0;
        
        // Format Mmm/dd/yyyy or Mmm/d/yyyy (ROS6)
        if (strpos($dateStr, '/') !== false) {
            $parts = explode('/', $dateStr);
            if (count($parts) >= 3) {
                $p0 = substr($parts[0], 0, 3);
                if (isset($monthArray[$p0])) {
                    $m = $monthArray[$p0];
                    $d = (int)$parts[1];
                    $y = (int)$parts[2];
                    return (int)sprintf("%04d%02d%02d", $y, $m, $d);
                } elseif (is_numeric($parts[0])) {
                    $m = (int)$parts[0];
                    $d = (int)$parts[1];
                    $y = (int)$parts[2];
                    return (int)sprintf("%04d%02d%02d", $y, $m, $d);
                }
            }
        } elseif (strpos($dateStr, '-') !== false) {
            $parts = explode('-', $dateStr);
            if (count($parts) >= 3) {
                $y = (int)$parts[0];
                $m = (int)$parts[1];
                $d = (int)$parts[2];
                return (int)sprintf("%04d%02d%02d", $y, $m, $d);
            }
        }
        return 0;
    };

    $parseTimeInt = function($timeStr) {
        $parts = explode(':', trim($timeStr));
        $h = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        return ($h * 60) + $m;
    };

    $todayInt = $parseDateInt($clockDate);
    $curtimeInt = $parseTimeInt($clockTime);

    // 2. Fetch all User Profiles & Existing Schedulers
    $profiles = $API->comm("/ip/hotspot/user/profile/print");
    $schedulers = $API->comm("/system/scheduler/print");
    $schedByName = [];
    if (is_array($schedulers)) {
        foreach ($schedulers as $s) {
            if (isset($s['name'])) {
                $schedByName[$s['name']] = $s;
            }
        }
    }

    // Universal Background Monitor Script (Multi-version safe for ROS6 and ROS7)
    $bgservice = ':local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local dateint do={ :local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local montharray ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec","Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"); :local days 0; :local month 0; :local year 0; :local dlen [:len $d]; :if ($dlen >= 10 and [:pick $d 4] = "-" and [:pick $d 7] = "-") do={ :set year [$cleanNum val=[:pick $d 0 4]]; :set month [$cleanNum val=[:pick $d 5 7]]; :set days [$cleanNum val=[:pick $d 8 10]]; } else={ :local s1 [:find $d "/"]; :if ([:typeof $s1] = "num") do={ :local s2 [:find $d "/" ($s1 + 1)]; :if ([:typeof $s2] = "num") do={ :local p1 [:pick $d 0 $s1]; :local p2 [:pick $d ($s1 + 1) $s2]; :local p3 [:pick $d ($s2 + 1) $dlen]; :local mpos [:find $montharray $p1]; :if ([:typeof $mpos] = "num") do={ :set month (($mpos % 12) + 1); :set days [$cleanNum val=$p2]; :set year [$cleanNum val=$p3]; } else={ :set month [$cleanNum val=$p1]; :set days [$cleanNum val=$p2]; :set year [$cleanNum val=$p3]; }; }; }; }; :if ($year > 2000 and $month > 0 and $month <= 12 and $days > 0 and $days <= 31) do={ :local mstr "$month"; :if ($month < 10) do={ :set mstr ("0" . "$month"); }; :local dstr "$days"; :if ($days < 10) do={ :set dstr ("0" . "$days"); }; :return [:tonum ("$year" . "$mstr" . "$dstr")]; } else={ :return 0; }; }; :local timeint do={ :local cleanNum do={ :local s "$val"; :while ([:len $s] > 1 and [:pick $s 0 1] = "0") do={ :set s [:pick $s 1 [:len $s]]; }; :local n [:tonum $s]; :if ([:typeof $n] = "num") do={ :return $n; } else={ :return 0; }; }; :local c1 [:find $t ":"]; :if ([:typeof $c1] = "num") do={ :local c2 [:find $t ":" ($c1 + 1)]; :local h [$cleanNum val=[:pick $t 0 $c1]]; :local m 0; :if ([:typeof $c2] = "num") do={ :set m [$cleanNum val=[:pick $t ($c1 + 1) $c2]]; } else={ :set m [$cleanNum val=[:pick $t ($c1 + 1) [:len $t]]]; }; :return (($h * 60) + $m); } else={ :return 0; }; }; :local curdate [$dateint d=[/system clock get date]]; :local curtime [$timeint t=[/system clock get time]]; :if ($curdate > 20000000) do={ :foreach u in=[/ip hotspot user find where profile="$name"] do={ :local comment [/ip hotspot user get $u comment]; :local ucode [:pick $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $ucode = "NP" or $ucode = "np" or $comment = "") do={} else={ :local sp [:find $comment " "]; :if ([:typeof $sp] = "num") do={ :local expd [$dateint d=[:pick $comment 0 $sp]]; :local expt [$timeint t=[:pick $comment ($sp + 1) [:len $comment]]]; :if ($expd > 0 and ($expd < $curdate or ($expd = $curdate and $expt <= $curtime))) do={ :local uname [/ip hotspot user get $u name]; :do { /system script add name=("EXP-|-" . [/system clock get date] . "-|-" . [/system clock get time] . "-|-" . $uname . "-|-$name-|-expired") owner="mikhmon" source="expired" comment="mikhmon_expired"; } on-error={}; /ip hotspot active remove [find where user=$uname]; /ip hotspot user remove $u; }; }; }; }; };';

    if (is_array($profiles)) {
        foreach ($profiles as $prof) {
            $pname = $prof['name'] ?? '';
            $onLogin = $prof['on-login'] ?? '';
            if (empty($pname) || $pname === 'default') continue;

            // Check if profile uses expiration mode (rem, remc, ntf, ntfc)
            if (preg_match('/,(rem|remc|ntf|ntfc),/i', $onLogin, $matches)) {
                $profBgService = str_replace('$name', $pname, $bgservice);

                if (isset($schedByName[$pname])) {
                    $schId = $schedByName[$pname]['.id'];
                    $API->comm("/system/scheduler/set", [
                        ".id"      => $schId,
                        "on-event" => $profBgService,
                        "disabled" => "no",
                        "interval" => "00:02:00",
                        "policy"   => "read,write,policy,test"
                    ]);
                } else {
                    $API->comm("/system/scheduler/add", [
                        "name"       => $pname,
                        "start-time" => "startup",
                        "interval"   => "00:02:00",
                        "on-event"   => $profBgService,
                        "disabled"   => "no",
                        "policy"     => "read,write,policy,test",
                        "comment"    => "Monitor Profile " . $pname
                    ]);
                }
                $synced_profiles++;
            }
        }
    }

    // 3. Install Master 24/7 Universal Auto-Cleaner on MikroTik
    $masterCleaner = ':foreach u in=[/ip hotspot user find where disabled=no] do={ :local lu [/ip hotspot user get $u limit-uptime]; :local ut [/ip hotspot user get $u uptime]; :if ($lu > 0s and $ut >= $lu) do={ :local uname [/ip hotspot user get $u name]; :local uprof [/ip hotspot user get $u profile]; :do { /system script add name=("EXP-|-" . [/system clock get date] . "-|-" . [/system clock get time] . "-|-" . $uname . "-|-" . $uprof . "-|-expired") owner="mikhmon" source="expired" comment="mikhmon_expired"; } on-error={}; /ip hotspot active remove [find where user=$uname]; /ip hotspot user remove $u; }; };';
    if (isset($schedByName['AutoCleanExpiredVouchers'])) {
        $API->comm("/system/scheduler/set", [
            ".id"      => $schedByName['AutoCleanExpiredVouchers']['.id'],
            "on-event" => $masterCleaner,
            "disabled" => "no",
            "interval" => "00:02:00",
            "policy"   => "read,write,policy,test"
        ]);
    } else {
        $API->comm("/system/scheduler/add", [
            "name"       => "AutoCleanExpiredVouchers",
            "start-time" => "startup",
            "interval"   => "00:02:00",
            "on-event"   => $masterCleaner,
            "disabled"   => "no",
            "policy"     => "read,write,policy,test",
            "comment"    => "Master Auto Clean Expired Vouchers by Nodera"
        ]);
    }

    // 4. Purge ALL Expired Users Immediately
    $users = $API->comm("/ip/hotspot/user/print");
    if (is_array($users)) {
        foreach ($users as $u) {
            $uname = $u['name'] ?? '';
            $ucomment = $u['comment'] ?? '';
            $uid = $u['.id'] ?? '';
            $ulimit = $u['limit-uptime'] ?? '0s';
            $uuptime = $u['uptime'] ?? '0s';

            if (empty($uid) || $uname === 'default-trial') continue;

            $isExpired = false;

            // Check 1: Limit uptime reached or Notice 1s
            if ($ulimit === '1s') {
                $isExpired = true;
            } elseif (!empty($ulimit) && $ulimit !== '0s' && !empty($uuptime) && $uuptime !== '0s') {
                // Parse duration
                $parseSec = function($str) {
                    $sec = 0;
                    if (preg_match('/(\d+)w/', $str, $m)) $sec += (int)$m[1] * 604800;
                    if (preg_match('/(\d+)d/', $str, $m)) $sec += (int)$m[1] * 86400;
                    if (preg_match('/(\d+)h/', $str, $m)) $sec += (int)$m[1] * 3600;
                    if (preg_match('/(\d+)m/', $str, $m)) $sec += (int)$m[1] * 60;
                    if (preg_match('/(\d+)s/', $str, $m)) $sec += (int)$m[1];
                    return $sec;
                };
                if ($parseSec($uuptime) >= $parseSec($ulimit)) {
                    $isExpired = true;
                }
            }

            // Check 2: Date in comment has expired
            if (!$isExpired && !empty($ucomment)) {
                if (preg_match('/([A-Za-z]{3}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4})\s+(\d{1,2}:\d{2}(?::\d{2})?)/', $ucomment, $m)) {
                    $uExpDate = $parseDateInt($m[1]);
                    $uExpTime = $parseTimeInt($m[2]);

                    if ($uExpDate > 0 && ($uExpDate < $todayInt || ($uExpDate === $todayInt && $uExpTime <= $curtimeInt))) {
                        $isExpired = true;
                    }
                } elseif (preg_match('/^(?:exp|ex|rem|ntf)-(\d+)/i', $ucomment, $m)) {
                    // Unix timestamp in comment
                    $ts = (int)$m[1];
                    if ($ts > 0 && $ts <= time()) {
                        $isExpired = true;
                    }
                }
            }

            // Execute immediate removal and send Telegram notification
            if ($isExpired) {
                // Send Telegram Expired Notification if enabled
                include_once __DIR__ . '/../include/telegram_helper.php';
                if (function_exists('mikhmon_send_user_voucher_expired_telegram')) {
                    $uProfile = $u['profile'] ?? '-';
                    $uUptime = $u['uptime'] ?? '-';
                    $uMac = $u['mac-address'] ?? ($u['caller-id'] ?? '-');
                    mikhmon_send_user_voucher_expired_telegram($session, [
                        'username'      => $uname,
                        'profile'       => $uProfile,
                        'uptime'        => $uUptime,
                        'mac'           => $uMac,
                        'location_name' => $identity ?: ($session ?: 'Hotspot'),
                        'expired_at'    => date('Y-m-d H:i:s'),
                    ]);
                }

                $API->comm("/ip/hotspot/user/remove", [".id" => $uid]);
                $activeUsers = $API->comm("/ip/hotspot/active/print", ["?user" => $uname]);
                if (is_array($activeUsers)) {
                    foreach ($activeUsers as $act) {
                        if (isset($act['.id'])) {
                            $API->comm("/ip/hotspot/active/remove", [".id" => $act['.id']]);
                        }
                    }
                }
                $removed_users++;
            }
        }
    }
}
?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header align-middle">
                <h3><i class="fa fa-magic"></i> <?= $_sync_scheduler ?? 'Sinkronisasi Scheduler' ?></h3>
            </div>
            <div class="card-body">
                <div class="box-bordered pd-20 text-center mr-t-10">
                    <div class="mr-b-15">
                        <i class="fa fa-check-circle" style="font-size: 52px; color: #10b981;"></i>
                    </div>
                    <h2 class="mr-b-10"><?= $_sync_schedulers_success ?? 'Sinkronisasi MikroTik Berhasil!'; ?></h2>
                    <p class="mr-b-20" style="font-size: 14px; opacity: 0.85; max-width: 600px; margin-left: auto; margin-right: auto; line-height: 1.6;">
                        Sistem telah memperbarui <b><?= $synced_profiles ?> scheduler monitor profil</b>, mengaktifkan <b>Master Auto-Cleaner 24 Jam</b>, menyelaraskan <b>NTP Clock</b>, dan langsung menghapus <b><?= $removed_users ?> voucher expired</b> dari router MikroTik Anda.
                    </p>
                    <div class="mr-t-20">
                        <a href="./?hotspot=user-profiles&session=<?= urlencode($session) ?>" class="btn bg-primary btn-mrg">
                            <i class="fa fa-arrow-left"></i> <?= $_back_to_user_profiles ?? 'Kembali ke User Profiles'; ?>
                        </a>
                        <a href="./?hotspot=users&session=<?= urlencode($session) ?>" class="btn bg-warning btn-mrg">
                            <i class="fa fa-users"></i> <?= $_view_voucher_list ?? 'Lihat Daftar Voucher'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
