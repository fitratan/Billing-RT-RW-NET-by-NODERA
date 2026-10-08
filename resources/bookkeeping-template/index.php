<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

// 1. License Check
$licenseFile = __DIR__ . '/config/license.php';
if (file_exists($licenseFile)) {
    require_once $licenseFile;
}

if ((defined('BOOKKEEPING_STATUS') && BOOKKEEPING_STATUS !== 'ACTIVE') ||
    (defined('BOOKKEEPING_EXPIRY') && BOOKKEEPING_EXPIRY && date('Y-m-d') > BOOKKEEPING_EXPIRY)) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 2. Auth Check & Remember Me Auto-Login
if (empty($_SESSION['bk_logged_in']) && !empty($_COOKIE['bk_remember_token'])) {
    $config = require __DIR__ . '/include/config.php';
    $validUser = $config['admin_user'] ?? 'nodera';
    $validPassHash = $config['admin_pass'] ?? '';
    $tokenHash = hash('sha256', $validUser . ($validPassHash ?: 'nodera'));
    if (hash_equals($tokenHash, $_COOKIE['bk_remember_token'])) {
        $_SESSION['bk_logged_in'] = true;
        $_SESSION['bk_username']  = $validUser;
    }
}

if (empty($_SESSION['bk_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 3. Database Connection
require_once __DIR__ . '/include/db.php';
$config = require __DIR__ . '/include/config.php';

// Export Backup JSON
if (isset($_GET['action']) && $_GET['action'] === 'export_backup') {
    $backupData = exportBkBackup();
    $sub = defined('BOOKKEEPING_SUBDOMAIN') ? BOOKKEEPING_SUBDOMAIN : 'kas';
    $filename = 'backup-pembukuan-' . $sub . '-' . date('Y-m-d') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Process POST actions BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_chat_message') {
        $text = trim($_POST['text'] ?? '');
        $isUser = (int)($_POST['is_user'] ?? 0);
        $isHtml = (int)($_POST['is_html'] ?? 0);
        if ($text) {
            addBkChatMessage($text, $isUser, $isHtml);
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'clear_chat_history') {
        clearBkChatHistory();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_chat_autodelete') {
        $val = $_POST['value'] ?? 'never';
        setBkSetting('chat_autodelete', $val);
        pruneBkChatHistory($val);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'save_ai_config') {
        $provider = trim($_POST['provider'] ?? 'none');
        $apiKey   = trim($_POST['api_key'] ?? '');
        $baseUrl  = trim($_POST['base_url'] ?? '');
        $model    = trim($_POST['model'] ?? '');

        setBkSetting('ai_provider', $provider);
        setBkSetting('ai_api_key', $apiKey);
        setBkSetting('ai_base_url', $baseUrl);
        setBkSetting('ai_model', $model);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'ask_ai_llm') {
        $provider = getBkSetting('ai_provider', 'none');
        $apiKey   = getBkSetting('ai_api_key', '');
        $baseUrl  = getBkSetting('ai_base_url', '');
        $model    = getBkSetting('ai_model', '');
        $userMsg  = trim($_POST['message'] ?? '');

        if ($provider === 'none' || empty($apiKey) || empty($baseUrl) || empty($userMsg)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'fallback']);
            exit;
        }

        $data = getBkData();
        $txCount = count($data['transactions']);
        $categoriesList = array_column($data['categories'], 'name');
        $categoriesStr = implode(', ', $categoriesList);
        
        $totalInc = 0; $totalExp = 0;
        foreach ($data['transactions'] as $tx) {
            if ($tx['type'] === 'income') $totalInc += (float)$tx['amount'];
            else $totalExp += (float)$tx['amount'];
        }
        $netBal = $totalInc - $totalExp;

        $systemPrompt = "Anda adalah Asisten Pembukuan Pintar (NODERA AI).\n"
            . "Tugas Anda: menjawab pertanyaan keuangan pengguna secara ramah, sopan, ringkas, dan akurat.\n"
            . "Status Keuangan Pengguna saat ini:\n"
            . "- Total Pemasukan: Rp " . number_format($totalInc,0,',','.') . "\n"
            . "- Total Pengeluaran: Rp " . number_format($totalExp,0,',','.') . "\n"
            . "- Saldo Bersih: Rp " . number_format($netBal,0,',','.') . "\n"
            . "- Kategori Tersedia: {$categoriesStr}\n\n"
            . "Aturan:\n"
            . "1. Gunakan Bahasa Indonesia yang natural, singkat, dan mudah dipahami.\n"
            . "2. Jika pengguna meminta mencatat transaksi, berikan respon konfirmasi ramah.\n"
            . "3. Jika pengguna bertanya tentang statistik keuangan, jawab langsung angkanya berdasarkan data di atas.";

        $endpoint = rtrim($baseUrl, '/') . '/chat/completions';

        $payload = [
            'model' => $model ?: 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMsg]
            ],
            'temperature' => 0.7
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTPCODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            $aiReply = $resData['choices'][0]['message']['content'] ?? '';
            if ($aiReply) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'reply' => $aiReply, 'provider' => $provider]);
                exit;
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'fallback', 'error' => $curlError ?: ("HTTP " . $httpCode)]);
        exit;
    }

    if ($action === 'restore_backup') {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $jsonString = file_get_contents($_FILES['backup_file']['tmp_name']);
            if (restoreBkBackup($jsonString)) {
                header("Location: index.php?page=settings&msg=restore_success");
                exit;
            }
        }
        header("Location: index.php?page=settings&msg=restore_error");
        exit;
    }
    
    if ($action === 'add_transaction' || $action === 'edit_transaction') {
        $id = $_POST['tx_id'] ?? '';
        $type = $_POST['type'] ?? 'income';
        $category = trim($_POST['category'] ?? 'Umum');
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['transaction_date'] ?? date('Y-m-d');
        
        $receiptPath = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/receipts/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            // Security: block PHP execution in uploads folder
            $htaccess = __DIR__ . '/uploads/.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "Options -Indexes\nphp_flag engine off\n<FilesMatch \\.php$>\n  deny from all\n</FilesMatch>\n");
            }
            $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'bmp', 'jfif', 'gif', 'pdf'];
            if (in_array($ext, $allowedExts)) {
                $filename = 'receipt_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['receipt']['tmp_name'], $uploadDir . $filename)) {
                    $receiptPath = 'uploads/receipts/' . $filename;
                }
            }
        }

        if ($amount > 0) {
            if ($action === 'edit_transaction' && $id) {
                editBkTransaction($id, $type, $category, $amount, $description, $date, $receiptPath);
            } else {
                addBkTransaction($type, $category, $amount, $description, $date, $receiptPath);
            }
            // For ajax requests
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'receipt_path' => $receiptPath
                ]);
                exit;
            }
            
            $targetRef = $_POST['redirect_url'] ?? $_SERVER['HTTP_REFERER'] ?? '';
            $targetPage = 'dashboard';
            if (strpos($targetRef, 'page=transactions') !== false) {
                $targetPage = 'transactions';
            } else if (strpos($targetRef, 'page=reports') !== false) {
                $targetPage = 'reports';
            }
            
            header("Location: index.php?page=" . $targetPage . "&msg=success");
            exit;
        }
    }

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'income';
        if (!empty($name)) {
            addBkCategory($name, $type);
            header("Location: index.php?page=categories&msg=cat_added");
            exit;
        }
    }

    if ($action === 'change_password') {
        $newPass = $_POST['new_password'] ?? '';
        if (!empty($newPass)) {
            $config['admin_pass'] = password_hash($newPass, PASSWORD_DEFAULT);
            @file_put_contents(__DIR__ . '/include/config.php', "<?php\nreturn " . var_export($config, true) . ";\n");
            header("Location: index.php?page=settings&msg=pass_success");
            exit;
        }
    }
}

// 5. Process GET actions BEFORE any HTML output
if (isset($_GET['delete_tx'])) {
    deleteBkTransaction($_GET['delete_tx']);
    $ref = $_GET['ref'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $page = (strpos($ref, 'page=dashboard') !== false) ? 'dashboard' : 'transactions';
    header("Location: index.php?page=" . $page . "&msg=deleted");
    exit;
}

if (isset($_GET['delete_month'])) {
    deleteBkMonth($_GET['delete_month']);
    header("Location: index.php?page=transactions&msg=deleted");
    exit;
}

if (isset($_GET['delete_cat'])) {
    deleteBkCategory($_GET['delete_cat']);
    header("Location: index.php?page=categories&msg=deleted");
    exit;
}

if (isset($_GET['clear_log'])) {
    clearBkLog();
    header("Location: index.php?page=activitylog");
    exit;
}

// 6. Page Router
$page = $_GET['page'] ?? 'dashboard';

switch ($page) {
    case 'transactions':
        require_once __DIR__ . '/pages/transactions.php';
        break;
    case 'reports':
        require_once __DIR__ . '/pages/reports.php';
        break;
    case 'categories':
        require_once __DIR__ . '/pages/categories.php';
        break;
    case 'activitylog':
        require_once __DIR__ . '/pages/activitylog.php';
        break;
    case 'chat':
        require_once __DIR__ . '/pages/chat.php';
        break;
    case 'settings':
        require_once __DIR__ . '/pages/settings.php';
        break;
    case 'dashboard':
    default:
        require_once __DIR__ . '/pages/dashboard.php';
        break;
}

ob_end_flush();
