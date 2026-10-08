<?php
session_start();
error_reporting(0);
ini_set('max_execution_time', 300);
@set_time_limit(300);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["mikhmon"])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized session.']);
    exit;
}

$session = $_GET['session'] ?? $_POST['session'] ?? '';
if (empty($session)) {
    echo json_encode(['success' => false, 'error' => 'Missing session parameter.']);
    exit;
}

include_once('../include/config.php');
$iphost = explode('!', $data[$session][1] ?? '')[1] ?? '';
$userhost = explode('@|@', $data[$session][2] ?? '')[1] ?? '';
$passwdhost = explode('#|#', $data[$session][3] ?? '')[1] ?? '';

if (empty($iphost) || empty($userhost)) {
    echo json_encode(['success' => false, 'error' => 'Router session configuration not found.']);
    exit;
}

include_once('../lib/routeros_api.class.php');
include_once('../lib/formatbytesbites.php');

$API = new RouterosAPI();
$API->debug = false;
$API->timeout = 15;
$API->attempts = 2;

$passwdhostDecrypted = function_exists('mikhmon_decrypt') ? mikhmon_decrypt($passwdhost) : $passwdhost;
if (!$API->connect($iphost, $userhost, $passwdhostDecrypted)) {
    echo json_encode(['success' => false, 'error' => 'Could not connect to MikroTik router.']);
    exit;
}

if (is_resource($API->socket)) {
    @socket_set_timeout($API->socket, 15);
}

$action = $_POST['action'] ?? '';

if ($action === 'init') {
    $qty = !empty($_POST['qty']) ? min(500, max(1, intval($_POST['qty']))) : 1;
    $server = $_POST['server'] ?? 'all';
    $user = ($_POST['user'] ?? 'vc') === 'up' ? 'up' : 'vc';
    $userl = !empty($_POST['userl']) ? intval($_POST['userl']) : 6;
    if ($userl < 3 || $userl > 8) {
        $userl = 6;
    }
    $prefix = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['prefix'] ?? '');
    $char = $_POST['char'] ?? 'mix1';
    $profile = $_POST['profile'] ?? '';
    $timelimit = $_POST['timelimit'] ?? '';
    $datalimit = $_POST['datalimit'] ?? '';
    $adcomment = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['adcomment'] ?? '');
    $mbgb = intval($_POST['mbgb'] ?? 1048576);

    if ($timelimit == "") {
        $timelimit = "0";
    }
    if ($datalimit == "") {
        $datalimit = "0";
    } else {
        $datalimit = $datalimit * $mbgb;
    }
    if ($adcomment == "") {
        $adcomment = "";
    }

    $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$profile"));
    $ponlogin = $getprofile[0]['on-login'] ?? '';
    $getvalid = explode(",", $ponlogin)[3] ?? '';
    $getprice = explode(",", $ponlogin)[2] ?? '';
    $getsprice = explode(",", $ponlogin)[4] ?? '';
    $getlock = explode(",", $ponlogin)[6] ?? '';
    $_SESSION['ubp'] = $profile;

    $commt = $user . "-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $adcomment;
    $gentemp = $commt . "|~" . $profile . "~" . $getvalid . "~" . $getprice . "!".$getsprice."~" . $timelimit . "~" . $datalimit . "~" . $getlock;
    $gen = '<?php $genu="'.mikhmon_encrypt($gentemp).'";?>';
    $temp = '../voucher/temp.php';
    $handle = @fopen($temp, 'w');
    if ($handle) {
        fwrite($handle, $gen);
        fclose($handle);
    }

    $a = array("1" => "", "", 1, 2, 2, 3, 3, 4);

    $usersList = [];
    $used = [];
    $shuf = max(1, $userl - ($a[$userl] ?? 2));
    $attempts = 0;
    $maxAttempts = $qty * 50;
    $i = 0;

    while ($i < $qty && $attempts < $maxAttempts) {
        $attempts++;
        if ($user == "up") {
            if ($char == "lower") {
                $candU = randLC($userl);
            } elseif ($char == "upper") {
                $candU = randUC($userl);
            } elseif ($char == "upplow") {
                $candU = randULC($userl);
            } elseif ($char == "mix") {
                $candU = randNLC($userl);
            } elseif ($char == "mix1") {
                $candU = randNUC($userl);
            } elseif ($char == "mix2") {
                $candU = randNULC($userl);
            } elseif ($char == "num") {
                $candU = randN($userl);
            } else {
                $candU = randNUC($userl);
            }
            $candP = randN($userl);
            $candName = $prefix . $candU;
            $candPass = $candP;
        } else { // $user == "vc"
            if ($char == "num") {
                $candU = randN($userl);
            } elseif ($char == "mix") {
                $candU = randNLC($userl);
            } elseif ($char == "mix1") {
                $candU = randNUC($userl);
            } elseif ($char == "mix2") {
                $candU = randNULC($userl);
            } elseif ($char == "lower") {
                $candU = randLC($shuf) . randN($userl - $shuf);
            } elseif ($char == "upper") {
                $candU = randUC($shuf) . randN($userl - $shuf);
            } elseif ($char == "upplow") {
                $candU = randULC($shuf) . randN($userl - $shuf);
            } else {
                $candU = randNUC($userl);
            }
            $candName = $prefix . $candU;
            $candPass = $candName;
        }

        if (!isset($used[$candName])) {
            $used[$candName] = true;
            $usersList[] = [
                'name' => $candName,
                'pass' => $candPass,
            ];
            $i++;
        }
    }

    echo json_encode([
        'success' => true,
        'commt' => $commt,
        'server' => $server,
        'profile' => $profile,
        'timelimit' => $timelimit,
        'datalimit' => $datalimit,
        'total' => count($usersList),
        'users' => $usersList,
    ]);
    exit;
}

if ($action === 'batch') {
    $server = $_POST['server'] ?? 'all';
    $profile = $_POST['profile'] ?? '';
    $timelimit = $_POST['timelimit'] ?? '0';
    $datalimit = $_POST['datalimit'] ?? '0';
    $commt = $_POST['commt'] ?? '';
    $rawBatch = $_POST['batch'] ?? '[]';
    $batch = is_array($rawBatch) ? $rawBatch : json_decode($rawBatch, true);

    if (!is_array($batch)) {
        echo json_encode(['success' => false, 'error' => 'Invalid batch data']);
        exit;
    }

    $added = 0;
    foreach ($batch as $item) {
        $uName = $item['name'] ?? '';
        $uPass = $item['pass'] ?? '';
        if (!empty($uName)) {
            $API->comm("/ip/hotspot/user/add", array(
                "server" => "$server",
                "name" => "$uName",
                "password" => "$uPass",
                "profile" => "$profile",
                "limit-uptime" => "$timelimit",
                "limit-bytes-total" => "$datalimit",
                "comment" => "$commt",
            ));
            $added++;
        }
    }

    echo json_encode([
        'success' => true,
        'added' => $added,
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
exit;
