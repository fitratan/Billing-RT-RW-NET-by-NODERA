<?php
/**
 * NODERA PROPRIETARY COMPILER & OBFUSCATION BUILDER FOR NODERA BILLING
 * (c) 2026 NODERA. All rights reserved.
 * 
 * Compiles Laravel backend service files into multi-layer obfuscated, compressed,
 * and anti-tamper protected runtime binaries for standalone release packages.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

$baseDir = dirname(__DIR__);
$srcBackupDir = $baseDir . '/.src_backup';

$isRestore = in_array('--restore', $argv, true);

if ($isRestore) {
    echo "========================================================\n";
    echo "  RESTORE PRISTINE SOURCE CODE FROM BACKUP              \n";
    echo "========================================================\n\n";

    if (!is_dir($srcBackupDir)) {
        die("[!] Backup directory does not exist: {$srcBackupDir}\n");
    }

    $restored = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcBackupDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $rel = substr($item->getPathname(), strlen($srcBackupDir) + 1);
            $target = $baseDir . '/' . $rel;
            copy($item->getPathname(), $target);
            echo "[+] Restored: {$rel}\n";
            $restored++;
        }
    }
    echo "\n[OK] {$restored} files restored successfully.\n";
    exit(0);
}

if (!is_dir($srcBackupDir)) {
    mkdir($srcBackupDir, 0755, true);
}

echo "========================================================\n";
echo "  NODERA BILLING PROPRIETARY CODE BUILDER v2.0          \n";
echo "========================================================\n\n";

function obfuscatePhpFile(string $sourceCode, string $description = ''): string {
    // 1. Strip comments and extra whitespace using temporary file
    $tmpFile = tempnam(sys_get_temp_dir(), 'ndr_bil_');
    file_put_contents($tmpFile, $sourceCode);
    $stripped = php_strip_whitespace($tmpFile);
    @unlink($tmpFile);

    // Remove opening <?php tag from stripped code to prepare for eval
    $cleanCode = preg_replace('/^<\?(php)?/i', '', trim($stripped));
    $cleanCode = preg_replace('/\?>$/', '', trim($cleanCode));

    // 2. Compress payload using raw deflate
    $compressed = gzdeflate($cleanCode, 9);
    if ($compressed === false) {
        throw new RuntimeException("Gzdeflate failed on source code.");
    }

    // 3. Generate dynamic 16-byte cryptographically secure XOR key
    $key = random_bytes(16);
    $keyLen = strlen($key);

    // 4. Encrypt compressed stream with XOR
    $cipher = '';
    $compLen = strlen($compressed);
    for ($i = 0; $i < $compLen; $i++) {
        $cipher .= chr(ord($compressed[$i]) ^ ord($key[$i % $keyLen]));
    }

    // 5. Calculate SHA-256 integrity checksum
    $checksum = hash('sha256', $cipher);

    $b64Cipher = base64_encode($cipher);
    $b64Key = base64_encode($key);

    // 6. Generate random variable identifiers
    $vCipher = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vKey    = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vKLen   = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vDec    = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vIdx    = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vHash   = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);

    $banner = "<?php\n"
        . "/**\n"
        . " * ============================================================================\n"
        . " * NODERA DIGITAL NETWORK — " . (!empty($description) ? strtoupper($description) : "PROPRIETARY COMPONENT") . "\n"
        . " * SECURE RUNTIME BINARY & CLOUD-LOCKED ENGINE\n"
        . " * ----------------------------------------------------------------------------\n"
        . " * (c) 2026 NODERA. All rights reserved.\n"
        . " * This file contains encrypted intellectual property. Any unauthorized\n"
        . " * decompilation, reverse engineering, or modification is strictly prohibited.\n"
        . " * ============================================================================\n"
        . " */\n";

    $stub = $banner
        . "{$vCipher}=base64_decode('{$b64Cipher}');\n"
        . "{$vKey}=base64_decode('{$b64Key}');\n"
        . "{$vHash}='{$checksum}';\n"
        . "if(hash('sha256',{$vCipher})!=={$vHash}){die('NODERA Security: Integrity verification failed (0x8F).');}\n"
        . "{$vKLen}=strlen({$vKey});\n"
        . "{$vDec}='';\n"
        . "for({$vIdx}=0;{$vIdx}<strlen({$vCipher});{$vIdx}++){\n"
        . "    {$vDec}.=chr(ord({$vCipher}[{$vIdx}])^ord({$vKey}[{$vIdx}%{$vKLen}]));\n"
        . "}\n"
        . "eval(gzinflate({$vDec}));\n";

    return $stub;
}

// List of target proprietary files to compile & protect
$targets = [
    'app/Services/LicenseService.php'           => 'NODERA Billing License Engine & Anti-Tamper Guard',
    'app/Http/Middleware/CheckIspLicense.php'   => 'NODERA Billing ISP License & Trial Middleware',
    'app/Services/BillingEngineService.php'     => 'NODERA Billing Calculation & Isolation Engine',
    'app/Services/IsolationService.php'         => 'NODERA Automatic Network Isolation Dispatcher',
    'app/Services/QrisDynamicService.php'       => 'NODERA Dynamic QRIS Generator & Parser',
    'app/Services/NominalUnikService.php'       => 'NODERA Nominal Unik Payment Resolver',
];

$successCount = 0;

foreach ($targets as $relPath => $desc) {
    $fullPath = $baseDir . '/' . $relPath;
    $backupPath = $srcBackupDir . '/' . $relPath;

    if (file_exists($backupPath)) {
        $sourceCode = file_get_contents($backupPath);
    } elseif (file_exists($fullPath)) {
        $sourceCode = file_get_contents($fullPath);
    } else {
        echo "[-] Skipping missing file: {$relPath}\n";
        continue;
    }

    // Save pristine clean source to backup directory
    $backupSubdir = dirname($backupPath);
    if (!is_dir($backupSubdir)) {
        mkdir($backupSubdir, 0755, true);
    }
    file_put_contents($backupPath, $sourceCode);

    // Generate protected binary
    echo "[*] Obfuscating: {$relPath} ({$desc})...\n";
    $obfuscated = obfuscatePhpFile($sourceCode, $desc);
    file_put_contents($fullPath, $obfuscated);

    // Verify syntax
    $lintOut = [];
    $lintCode = 0;
    exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $lintOut, $lintCode);
    if ($lintCode === 0) {
        echo "    [+] Success & Valid Syntax (PHP Lint OK)\n";
        $successCount++;
    } else {
        echo "    [!] Lint Error on {$relPath}: " . implode("\n", $lintOut) . "\n";
        // Restore immediately if error
        file_put_contents($fullPath, $sourceCode);
    }
}

echo "\n========================================================\n";
echo "  COMPLETED: {$successCount} proprietary files protected!\n";
echo "  Pristine source preserved in: {$srcBackupDir}\n";
echo "  To restore original sources, run: php scripts/build_obfuscator.php --restore\n";
echo "========================================================\n";
