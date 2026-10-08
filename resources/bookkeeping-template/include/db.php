<?php

if (!function_exists('getBkDataDir')) {
    function getBkDataDir() {
        $dir = __DIR__ . '/../db';
        if (!file_exists($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('getBkDataFile')) {
    function getBkDataFile() {
        global $dataFile;
        if (!empty($dataFile)) {
            $GLOBALS['dataFile'] = $dataFile;
            return $dataFile;
        }
        if (!empty($GLOBALS['dataFile'])) {
            return $GLOBALS['dataFile'];
        }
        $path = getBkDataDir() . '/data.json';
        $GLOBALS['dataFile'] = $path;
        return $path;
    }
}

if (!function_exists('getBkSqliteFile')) {
    function getBkSqliteFile() {
        global $sqliteFile;
        if (!empty($sqliteFile)) {
            $GLOBALS['sqliteFile'] = $sqliteFile;
            return $sqliteFile;
        }
        if (!empty($GLOBALS['sqliteFile'])) {
            return $GLOBALS['sqliteFile'];
        }
        $path = getBkDataDir() . '/bookkeeping.sqlite';
        $GLOBALS['sqliteFile'] = $path;
        return $path;
    }
}

if (!function_exists('formatRupiah')) {
    function formatRupiah($amount) {
        $num = (float)$amount;
        if ($num < 0) {
            return '-Rp ' . number_format(abs($num), 0, ',', '.');
        }
        return 'Rp ' . number_format($num, 0, ',', '.');
    }
}

if (!function_exists('getBkPdo')) {
    function getBkPdo() {
        global $pdo, $useSqlite;
        if ($pdo instanceof \PDO) {
            $GLOBALS['pdo'] = $pdo;
            $GLOBALS['useSqlite'] = true;
            return $pdo;
        }
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
            $pdo = $GLOBALS['pdo'];
            $useSqlite = true;
            return $pdo;
        }

        $sqliteFile = getBkSqliteFile();
        if (class_exists('PDO') && in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            try {
                $instance = new \PDO('sqlite:' . $sqliteFile);
                $instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $instance->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

                $instance->exec("
                    CREATE TABLE IF NOT EXISTS categories (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        type TEXT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS transactions (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        type TEXT NOT NULL,
                        category TEXT NOT NULL,
                        amount REAL NOT NULL,
                        description TEXT,
                        receipt_path TEXT,
                        transaction_date DATE NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS activity_log (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        message TEXT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS chat_history (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        text TEXT NOT NULL,
                        is_user INTEGER NOT NULL DEFAULT 0,
                        is_html INTEGER NOT NULL DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS settings (
                        key TEXT PRIMARY KEY,
                        value TEXT NOT NULL
                    );
                ");

                $stmtCount = $instance->query("SELECT COUNT(*) as cnt FROM categories");
                $row = $stmtCount->fetch();
                if (($row['cnt'] ?? 0) == 0) {
                    $instance->exec("
                        INSERT INTO categories (name, type) VALUES
                        ('Penjualan Voucher WiFi', 'income'),
                        ('Pembayaran Tagihan ISP', 'income'),
                        ('Service & Jasa Teknik', 'income'),
                        ('Pembelian Bandwidth', 'expense'),
                        ('Biaya Operasional & Listrik', 'expense'),
                        ('Gaji & Honor Teknisi', 'expense');
                    ");
                }

                $pdo = $instance;
                $useSqlite = true;
                $GLOBALS['pdo'] = $instance;
                $GLOBALS['useSqlite'] = true;
                return $instance;
            } catch (\Throwable $e) {
                $pdo = null;
                $useSqlite = false;
                $GLOBALS['pdo'] = null;
                $GLOBALS['useSqlite'] = false;
                return null;
            }
        }

        $pdo = null;
        $useSqlite = false;
        $GLOBALS['pdo'] = null;
        $GLOBALS['useSqlite'] = false;
        return null;
    }
}

$dataDir = getBkDataDir();
$sqliteFile = getBkSqliteFile();
$dataFile = getBkDataFile();
$pdo = getBkPdo();
$useSqlite = ($pdo !== null);
$GLOBALS['dataDir'] = $dataDir;
$GLOBALS['sqliteFile'] = $sqliteFile;
$GLOBALS['dataFile'] = $dataFile;
$GLOBALS['pdo'] = $pdo;
$GLOBALS['useSqlite'] = $useSqlite;

if (!function_exists('getBkData')) {
    function getBkData() {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $cats = $pdo->query("SELECT * FROM categories ORDER BY type ASC, name ASC")->fetchAll();
                $txs  = $pdo->query("SELECT * FROM transactions ORDER BY transaction_date DESC, id DESC")->fetchAll();
                $logs = $pdo->query("SELECT * FROM activity_log ORDER BY id DESC LIMIT 100")->fetchAll();
                // Build activity_log as [timestamp => message] map for template use
                $logMap = [];
                foreach ($logs as $l) { $logMap[$l['created_at']] = $l['message']; }
                return [
                    'categories'   => $cats ?: [],
                    'transactions' => $txs  ?: [],
                    'activity_log' => $logMap,
                ];
            } catch (\Throwable $e) {
                // fallback below
            }
        }

        $dataFile = getBkDataFile();
        if (!file_exists($dataFile)) {
            $default = [
                'categories' => [
                    ['id' => 1, 'name' => 'Penjualan Voucher WiFi', 'type' => 'income'],
                    ['id' => 2, 'name' => 'Pembayaran Tagihan ISP', 'type' => 'income'],
                    ['id' => 3, 'name' => 'Service & Jasa Teknik', 'type' => 'income'],
                    ['id' => 4, 'name' => 'Pembelian Bandwidth', 'type' => 'expense'],
                    ['id' => 5, 'name' => 'Biaya Operasional & Listrik', 'type' => 'expense'],
                    ['id' => 6, 'name' => 'Gaji & Honor Teknisi', 'type' => 'expense'],
                ],
                'transactions' => [],
                'activity_log' => [],
            ];
            if (!empty($dataFile)) {
                @file_put_contents($dataFile, json_encode($default, JSON_PRETTY_PRINT));
            }
            return $default;
        }
        $content = @file_get_contents($dataFile);
        $data = json_decode($content, true);
        if (!is_array($data)) return ['categories' => [], 'transactions' => [], 'activity_log' => []];
        if (!isset($data['activity_log'])) $data['activity_log'] = [];
        return $data;
    }
}

if (!function_exists('logBkActivity')) {
    function logBkActivity($message) {
        $ts = date('Y-m-d H:i:s');
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_log (message, created_at) VALUES (?, ?)");
                $stmt->execute([$message, $ts]);
                // Keep only last 200
                $pdo->exec("DELETE FROM activity_log WHERE id NOT IN (SELECT id FROM activity_log ORDER BY id DESC LIMIT 200)");
                return;
            } catch (\Throwable $e) {}
        }
        $data = getBkData();
        $data['activity_log'] = array_slice([$ts => $message] + ($data['activity_log'] ?? []), 0, 200);
        saveBkData($data);
    }
}

if (!function_exists('clearBkLog')) {
    function clearBkLog() {
        $pdo = getBkPdo();
        if ($pdo) {
            try { $pdo->exec("DELETE FROM activity_log"); return; } catch (\Throwable $e) {}
        }
        $data = getBkData();
        $data['activity_log'] = [];
        saveBkData($data);
    }
}

if (!function_exists('addBkTransaction')) {
    function addBkTransaction($type, $category, $amount, $description, $date, $receiptPath = null) {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                // Check if column exists, if not alter table (for backward compatibility)
                try { $pdo->exec("ALTER TABLE transactions ADD COLUMN receipt_path TEXT"); } catch (\Throwable $e) {}
                
                $stmt = $pdo->prepare("INSERT INTO transactions (type, category, amount, description, receipt_path, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$type, $category, $amount, $description, $receiptPath, $date]);
                $label = $type === 'income' ? 'Pemasukan' : 'Pengeluaran';
                logBkActivity("Tambah transaksi: {$label} — {$category} — Rp " . number_format($amount,0,',','.') . " ({$date})");
                return;
            } catch (\Throwable $e) {}
        }

        $data = getBkData();
        array_unshift($data['transactions'], [
            'id' => time() . rand(100, 999),
            'type' => $type,
            'category' => $category,
            'amount' => (float)$amount,
            'description' => $description,
            'receipt_path' => $receiptPath,
            'transaction_date' => $date,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        saveBkData($data);
        logBkActivity("Tambah transaksi: {$type} — {$category} — Rp " . number_format($amount,0,',','.'));
    }
}

if (!function_exists('editBkTransaction')) {
    function editBkTransaction($id, $type, $category, $amount, $description, $date, $receiptPath = null) {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                if ($receiptPath) {
                    $stmt = $pdo->prepare("UPDATE transactions SET type=?, category=?, amount=?, description=?, transaction_date=?, receipt_path=? WHERE id=?");
                    $stmt->execute([$type, $category, $amount, $description, $date, $receiptPath, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE transactions SET type=?, category=?, amount=?, description=?, transaction_date=? WHERE id=?");
                    $stmt->execute([$type, $category, $amount, $description, $date, $id]);
                }
                $label = $type === 'income' ? 'Pemasukan' : 'Pengeluaran';
                logBkActivity("Edit transaksi: {$label} — {$category} — Rp " . number_format($amount,0,',','.') . " ({$date})");
                return;
            } catch (\Throwable $e) {}
        }

        $data = getBkData();
        foreach ($data['transactions'] as &$tx) {
            if ($tx['id'] == $id) {
                $tx['type'] = $type;
                $tx['category'] = $category;
                $tx['amount'] = (float)$amount;
                $tx['description'] = $description;
                $tx['transaction_date'] = $date;
                if ($receiptPath) $tx['receipt_path'] = $receiptPath;
                break;
            }
        }
        saveBkData($data);
        logBkActivity("Edit transaksi: {$type} — {$category} — Rp " . number_format($amount,0,',','.'));
    }
}

if (!function_exists('deleteBkTransaction')) {
    function deleteBkTransaction($id) {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                // Fetch first for log
                $row = $pdo->prepare("SELECT * FROM transactions WHERE id=?");
                $row->execute([(int)$id]); $tx = $row->fetch();
                $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
                $stmt->execute([(int)$id]);
                if ($tx) {
                    if (!empty($tx['receipt_path']) && file_exists(__DIR__.'/../../'.$tx['receipt_path'])) {
                        @unlink(__DIR__.'/../../'.$tx['receipt_path']);
                    }
                    logBkActivity("Hapus transaksi: {$tx['category']} — Rp " . number_format($tx['amount'],0,',','.') . " ({$tx['transaction_date']})");
                }
                return;
            } catch (\Throwable $e) {}
        }

        $data = getBkData();
        $data['transactions'] = array_values(array_filter($data['transactions'], function($tx) use ($id) {
            if ((string)$tx['id'] === (string)$id) {
                if (!empty($tx['receipt_path']) && file_exists(__DIR__.'/../'.$tx['receipt_path'])) {
                    @unlink(__DIR__.'/../'.$tx['receipt_path']);
                }
                return false;
            }
            return true;
        }));
        saveBkData($data);
    }
}

if (!function_exists('deleteBkMonth')) {
    function deleteBkMonth($month) {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM transactions WHERE strftime('%Y-%m', transaction_date) = ?");
                $stmt->execute([$month]);
                logBkActivity("Hapus seluruh transaksi bulan: {$month}");
                return;
            } catch (\Throwable $e) {}
        }

        $data = getBkData();
        $data['transactions'] = array_values(array_filter($data['transactions'], function($tx) use ($month) {
            if (substr($tx['transaction_date'], 0, 7) === $month) {
                if (!empty($tx['receipt_path']) && file_exists(__DIR__.'/../'.$tx['receipt_path'])) {
                    @unlink(__DIR__.'/../'.$tx['receipt_path']);
                }
                return false;
            }
            return true;
        }));
        saveBkData($data);
    }
}

if (!function_exists('exportBkBackup')) {
    function exportBkBackup() {
        $data = getBkData();
        return [
            'app'           => 'NODERA_BOOKKEEPING_BACKUP',
            'version'       => '1.0',
            'exported_at'   => date('Y-m-d H:i:s'),
            'subdomain'     => defined('BOOKKEEPING_SUBDOMAIN') ? BOOKKEEPING_SUBDOMAIN : 'kas',
            'categories'    => $data['categories'] ?? [],
            'transactions'  => $data['transactions'] ?? [],
            'activity_log'  => $data['activity_log'] ?? [],
        ];
    }
}

if (!function_exists('restoreBkBackup')) {
    function restoreBkBackup($jsonString) {
        $parsed = json_decode($jsonString, true);
        if (!is_array($parsed) || !isset($parsed['categories'])) return false;

        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $pdo->exec("DELETE FROM categories");
                $pdo->exec("DELETE FROM transactions");
                
                $stmtCat = $pdo->prepare("INSERT INTO categories (name, type) VALUES (?, ?)");
                foreach (($parsed['categories'] ?? []) as $c) {
                    $stmtCat->execute([$c['name'], $c['type']]);
                }
                
                $stmtTx = $pdo->prepare("INSERT INTO transactions (type, category, amount, description, receipt_path, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
                foreach (($parsed['transactions'] ?? []) as $t) {
                    $stmtTx->execute([$t['type'], $t['category'], $t['amount'], $t['description']??'', $t['receipt_path']??null, $t['transaction_date']]);
                }
                logBkActivity("Restore data backup (" . count($parsed['transactions'] ?? []) . " transaksi)");
                return true;
            } catch (\Throwable $e) {}
        }

        saveBkData([
            'categories'   => $parsed['categories'] ?? [],
            'transactions' => $parsed['transactions'] ?? [],
            'activity_log' => $parsed['activity_log'] ?? []
        ]);
        logBkActivity("Restore data backup (" . count($parsed['transactions'] ?? []) . " transaksi)");
        return true;
    }
}

if (!function_exists('addBkCategory')) {
    function addBkCategory($name, $type) {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, type) VALUES (?, ?)");
                $stmt->execute([$name, $type]);
                logBkActivity("Tambah kategori: {$name} ({$type})");
                return;
            } catch (\Throwable $e) {}
        }

        $data = getBkData();
        $data['categories'][] = ['id' => time(), 'name' => $name, 'type' => $type];
        saveBkData($data);
        logBkActivity("Tambah kategori: {$name} ({$type})");
    }
}

if (!function_exists('deleteBkCategory')) {
    function deleteBkCategory($id) {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $row = $pdo->prepare("SELECT name FROM categories WHERE id=?");
                $row->execute([(int)$id]); $cat = $row->fetch();
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([(int)$id]);
                if ($cat) logBkActivity("Hapus kategori: {$cat['name']}");
                return;
            } catch (\Throwable $e) {}
        }

        $data = getBkData();
        $data['categories'] = array_values(array_filter($data['categories'], function($cat) use ($id) {
            return (string)$cat['id'] !== (string)$id;
        }));
        saveBkData($data);
        logBkActivity("Hapus kategori ID: {$id}");
    }
}

if (!function_exists('saveBkData')) {
    function saveBkData($data) {
        $dataFile = getBkDataFile();
        if (!empty($dataFile)) {
            @file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
}

if (!function_exists('getBkSetting')) {
    function getBkSetting($key, $default = '') {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
                $stmt->execute([$key]);
                $val = $stmt->fetchColumn();
                return $val !== false ? $val : $default;
            } catch (\Throwable $e) {}
        }
        $setFile = getBkDataDir() . '/settings.json';
        if (!file_exists($setFile)) return $default;
        $data = json_decode(file_get_contents($setFile), true) ?: [];
        return $data[$key] ?? $default;
    }
}

if (!function_exists('setBkSetting')) {
    function setBkSetting($key, $value) {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
                $stmt->execute([$key, (string)$value]);
                return;
            } catch (\Throwable $e) {}
        }
        $setFile = getBkDataDir() . '/settings.json';
        $data = file_exists($setFile) ? (json_decode(file_get_contents($setFile), true) ?: []) : [];
        $data[$key] = (string)$value;
        @file_put_contents($setFile, json_encode($data, JSON_PRETTY_PRINT));
    }
}

if (!function_exists('pruneBkChatHistory')) {
    function pruneBkChatHistory($config = 'never') {
        if ($config === 'never') return;
        
        $days = 0;
        if ($config === '1d') $days = 1;
        elseif ($config === '1w') $days = 7;
        elseif ($config === '1m') $days = 30;
        if ($days <= 0) return;

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM chat_history WHERE created_at < ?");
                $stmt->execute([$cutoff]);
            } catch (\Throwable $e) {}
        } else {
            $chatFile = getBkDataDir() . '/chat_history.json';
            if (file_exists($chatFile)) {
                $list = json_decode(file_get_contents($chatFile), true) ?: [];
                $cutoffTime = strtotime("-{$days} days");
                $filtered = array_filter($list, function($item) use ($cutoffTime) {
                    return isset($item['timestamp']) && ($item['timestamp'] / 1000) >= $cutoffTime;
                });
                @file_put_contents($chatFile, json_encode(array_values($filtered), JSON_PRETTY_PRINT));
            }
        }
    }
}

if (!function_exists('getBkChatHistory')) {
    function getBkChatHistory() {
        $config = getBkSetting('chat_autodelete', 'never');
        pruneBkChatHistory($config);

        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM chat_history ORDER BY id ASC");
                return $stmt->fetchAll() ?: [];
            } catch (\Throwable $e) {}
        }
        $chatFile = getBkDataDir() . '/chat_history.json';
        if (!file_exists($chatFile)) return [];
        return json_decode(file_get_contents($chatFile), true) ?: [];
    }
}

if (!function_exists('addBkChatMessage')) {
    function addBkChatMessage($text, $isUser = 0, $isHtml = 0) {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO chat_history (text, is_user, is_html, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$text, $isUser ? 1 : 0, $isHtml ? 1 : 0]);
                return;
            } catch (\Throwable $e) {}
        }
        $chatFile = getBkDataDir() . '/chat_history.json';
        $list = file_exists($chatFile) ? (json_decode(file_get_contents($chatFile), true) ?: []) : [];
        $list[] = [
            'id' => count($list) + 1,
            'text' => $text,
            'is_user' => $isUser ? 1 : 0,
            'is_html' => $isHtml ? 1 : 0,
            'timestamp' => round(microtime(true) * 1000)
        ];
        @file_put_contents($chatFile, json_encode($list, JSON_PRETTY_PRINT));
    }
}

if (!function_exists('clearBkChatHistory')) {
    function clearBkChatHistory() {
        $pdo = getBkPdo();
        if ($pdo) {
            try {
                $pdo->exec("DELETE FROM chat_history");
                return;
            } catch (\Throwable $e) {}
        }
        $chatFile = getBkDataDir() . '/chat_history.json';
        @unlink($chatFile);
    }
}
