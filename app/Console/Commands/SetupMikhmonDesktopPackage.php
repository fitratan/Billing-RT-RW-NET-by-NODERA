<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class SetupMikhmonDesktopPackage extends Command
{
    protected $signature = 'mikhmon:setup-desktop-package {--force : Force recreate package}';
    protected $description = 'Setup and generate Mikhmon Desktop portable download package';

    public function handle(): int
    {
        $this->info('Starting Mikhmon Desktop package build...');

        $downloadsDir = public_path('downloads');
        File::ensureDirectoryExists($downloadsDir);

        $zipPath = $downloadsDir . '/Mikhmon-Desktop-Setup.zip';
        if (File::exists($zipPath) && !$this->option('force')) {
            $this->info("Package already exists at {$zipPath} (" . round(filesize($zipPath) / 1024 / 1024, 2) . ' MB)');
            return 0;
        }

        $tmpDir = storage_path('app/mikhmon-desktop-build');
        File::deleteDirectory($tmpDir);
        File::ensureDirectoryExists($tmpDir);

        // 1. Copy mikhmon core template (prioritize v7 with warung addon and buy module)
        $mikhmonSrc = resource_path('mikhmon-template-v7');
        if (!File::exists($mikhmonSrc)) {
            $mikhmonSrc = resource_path('mikhmon-template');
        }
        $mikhmonDest = $tmpDir . '/mikhmon';
        File::ensureDirectoryExists($mikhmonDest);
        if (File::exists($mikhmonSrc)) {
            File::copyDirectory($mikhmonSrc, $mikhmonDest);
        }

        // Reset license config in package to UNLICENSED desktop mode
        $desktopLicenseConfig = <<<PHP
<?php
/**
 * LICENSE MIKHMON DESKTOP STANDALONE — NODERA
 * Wajib diaktivasi menggunakan License Key dari Cloud Panel (https://panel.dgtlnetsolution.com/desktop-licenses).
 */
define('MIKHMON_MODE', 'DESKTOP');
define('MIKHMON_STATUS', 'UNLICENSED');
define('MIKHMON_LICENSE_KEY', '');
define('MIKHMON_HWID', '');
define('MIKHMON_EXPIRY', '0000-00-00');
define('MIKHMON_BRAND', 'by NODERA (dgtlnetsolution.com)');
define('MIKHMON_PRODUCT_NAME', 'Mikhmon Desktop Standalone');
define('MIKHMON_SUBDOMAIN', 'desktop');
PHP;
        File::ensureDirectoryExists($mikhmonDest . '/config');
        File::put($mikhmonDest . '/config/license.php', $desktopLicenseConfig);

        // Obfuscate & Protect Critical Proprietary Modules (License, Warung POS, Payment Gateway, WhatsApp, Addon Guard)
        $protectedModules = [
            'include/license.php'            => 'LICENSE ENGINE',
            'include/warung_helper.php'      => 'WARUNG RESELLER ENGINE',
            'include/warung.php'             => 'WARUNG UI & POS ENGINE',
            'include/noderapay.php'          => 'PAYMENT GATEWAY ENGINE',
            'include/order_helper.php'       => 'ORDER FULFILLMENT ENGINE',
            'include/whatsapp_helper.php'    => 'WHATSAPP GATEWAY ENGINE',
            'include/telegram_helper.php'    => 'TELEGRAM NOTIFICATION ENGINE',
            'include/addon_license_guard.php'=> 'ADDON PROTECTION GUARD',
        ];

        foreach ($protectedModules as $relPath => $modLabel) {
            $fullFilePath = $mikhmonDest . '/' . $relPath;
            if (File::exists($fullFilePath)) {
                $rawPhp = File::get($fullFilePath);
                $obfuscated = $this->obfuscatePhp($rawPhp, $modLabel);
                File::put($fullFilePath, $obfuscated);
                $this->info("Protected proprietary module: {$relPath}");
            }
        }

        // 2. Download / Prepare Portable Windows PHP
        $phpDir = $tmpDir . '/bin/php-win';
        File::ensureDirectoryExists($phpDir);

        $phpZipUrl = 'https://windows.php.net/downloads/releases/archives/php-8.2.20-nts-Win32-vs16-x64.zip';
        $phpZipLocal = storage_path('app/php-8.2-nts-x64.zip');

        if (!File::exists($phpZipLocal) || filesize($phpZipLocal) < 1000000) {
            $this->info('Downloading portable Windows PHP runtime...');
            $ch = curl_init($phpZipUrl);
            $fp = fopen($phpZipLocal, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_USERAGENT, 'NODERA-Build-Agent/1.0');
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
        }

        if (File::exists($phpZipLocal)) {
            $this->info('Extracting Windows PHP runtime...');
            $zip = new ZipArchive();
            if ($zip->open($phpZipLocal) === true) {
                $zip->extractTo($phpDir);
                $zip->close();
            }

            // Cleanup unnecessary files in PHP to keep package compact
            @unlink($phpDir . '/phpdbg.exe');
            @unlink($phpDir . '/php-cgi.exe');
            @unlink($phpDir . '/deplister.exe');
            @unlink($phpDir . '/php8embed.lib');
            File::deleteDirectory($phpDir . '/dev');
            File::deleteDirectory($phpDir . '/lib');
        }

        // 3. Create php.ini for Windows
        $phpIni = <<<INI
[PHP]
engine = On
short_open_tag = On
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
serialize_precision = -1
zend.enable_gc = On
zend.exception_ignore_args = On
max_execution_time = 0
max_input_time = 60
memory_limit = 256M
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
display_startup_errors = Off
log_errors = On
default_mimetype = "text/html"
default_charset = "UTF-8"
extension_dir = "ext"
enable_dl = Off
file_uploads = On
upload_max_filesize = 32M
max_file_uploads = 20
allow_url_fopen = On
allow_url_include = Off
default_socket_timeout = 60

[ExtensionList]
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=sockets
extension=gd
INI;
        File::put($phpDir . '/php.ini', $phpIni);

        // 4. Create Root Delegators (Prevent 404 if launched from root)
        $rootIndex = <<<PHP
<?php
// Root router for Mikhmon Desktop standalone
if (file_exists(__DIR__ . '/mikhmon/index.php')) {
    chdir(__DIR__ . '/mikhmon');
    require __DIR__ . '/mikhmon/index.php';
} else {
    header('Location: ./admin.php?id=sessions');
    exit;
}
PHP;
        File::put($tmpDir . '/index.php', $rootIndex);

        $rootAdmin = <<<PHP
<?php
if (file_exists(__DIR__ . '/mikhmon/admin.php')) {
    chdir(__DIR__ . '/mikhmon');
    require __DIR__ . '/mikhmon/admin.php';
}
PHP;
        File::put($tmpDir . '/admin.php', $rootAdmin);

        $rootWarung = <<<PHP
<?php
if (file_exists(__DIR__ . '/mikhmon/warung.php')) {
    chdir(__DIR__ . '/mikhmon');
    require __DIR__ . '/mikhmon/warung.php';
}
PHP;
        File::put($tmpDir . '/warung.php', $rootWarung);

        $rootBuy = <<<PHP
<?php
if (file_exists(__DIR__ . '/mikhmon/buy.php')) {
    chdir(__DIR__ . '/mikhmon');
    require __DIR__ . '/mikhmon/buy.php';
}
PHP;
        File::put($tmpDir . '/buy.php', $rootBuy);

        // 5. Create Launcher Scripts
        $batLauncher = <<<BAT
@echo off
title Mikhmon Desktop Standalone by NODERA
cd /d "%~dp0"
echo ================================================================
echo           MIKHMON DESKTOP STANDALONE by NODERA
echo ================================================================

:: Detect PHP Binary
set "PHP_BIN=%~dp0bin\php-win\php.exe"
if not exist "%PHP_BIN%" (
    where php >nul 2>&1
    if %errorlevel% equ 0 (
        set "PHP_BIN=php"
    ) else (
        echo [ERROR] PHP runtime not found in bin\php-win\php.exe.
        pause
        exit /b 1
    )
)

:: Detect Document Root
set "DOC_ROOT=%~dp0mikhmon"
if not exist "%DOC_ROOT%\index.php" (
    set "DOC_ROOT=%~dp0"
)

:: Kill any existing hanging process on port 8080
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8080 " 2^>nul') do (
    taskkill /F /PID %%a >nul 2>&1
)

echo Starting local web server on http://127.0.0.1:8080 ...
start "" "%PHP_BIN%" -S 127.0.0.1:8080 -t "%DOC_ROOT%"
timeout /t 2 /nobreak >nul

echo Opening default web browser...
start http://127.0.0.1:8080/admin.php?id=sessions

echo.
echo Mikhmon Desktop is active in background (http://127.0.0.1:8080).
echo Tutup jendela terminal ini untuk menghentikan server Mikhmon.
pause
taskkill /F /IM php.exe >nul 2>&1
BAT;
        File::put($tmpDir . '/Start-Mikhmon.bat', $batLauncher);

        $stopBat = <<<BAT
@echo off
title Stop Mikhmon Desktop Server
echo Stopping all running Mikhmon PHP background processes...
taskkill /F /IM php.exe >nul 2>&1
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8080 " 2^>nul') do (
    taskkill /F /PID %%a >nul 2>&1
)
echo Mikhmon server stopped successfully.
timeout /t 2 /nobreak >nul
BAT;
        File::put($tmpDir . '/Stop-Mikhmon.bat', $stopBat);

        $vbsLauncher = <<<VBS
Set WshShell = CreateObject("WScript.Shell")
WshShell.Run chr(34) & WshShell.CurrentDirectory & "\Start-Mikhmon.bat" & Chr(34), 0
Set WshShell = Nothing
VBS;
        File::put($tmpDir . '/Start-Mikhmon-Silent.vbs', $vbsLauncher);

        // 5. Create README & License file
        $readme = <<<TXT
================================================================
           MIKHMON DESKTOP STANDALONE by NODERA
================================================================
Publisher : NODERA (DN Solution)
Version   : 1.0.0
OS Target : Windows 10 / 11 (64-Bit)

CARA PENGGUNAAN:
1. Ekstrak file ZIP ini ke folder pilihan Anda (misal: C:\Mikhmon-Desktop).
2. Buka folder hasil ekstrak, lalu klik ganda file "Start-Mikhmon.bat".
3. Browser Anda akan otomatis terbuka ke alamat http://127.0.0.1:8080.
4. Masukkan Lisensi Desktop NODERA yang Anda peroleh dari Cloud Member Panel
   (https://panel.dgtlnetsolution.com/desktop-licenses).
5. Selamat mengelola hotspot MikroTik Anda secara offline tanpa batas!

Dukungan & Bantuan:
Portal : https://panel.dgtlnetsolution.com
================================================================
TXT;
        File::put($tmpDir . '/README.txt', $readme);

        // 6. Zip everything into public/downloads/Mikhmon-Desktop-Setup.zip
        $this->info('Creating final ZIP package...');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($tmpDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        }

        // Also copy as .exe name alias for direct download compatibility
        $exeAlias = $downloadsDir . '/Mikhmon-Desktop-Setup.exe';
        if (File::exists($zipPath)) {
            @copy($zipPath, $exeAlias);
        }

        File::deleteDirectory($tmpDir);

        $sizeMb = round(filesize($zipPath) / 1024 / 1024, 2);
        $this->info("Package created successfully: {$zipPath} ({$sizeMb} MB)");

        return 0;
    }

    private function obfuscatePhp(string $code, string $moduleName = 'CORE MODULE'): string
    {
        $cleanCode = preg_replace('/^<\?php\s*/i', '', $code);

        return "<?php\n"
            . "/**\n"
            . " * NODERA DIGITAL NETWORK — {$moduleName}\n"
            . " * (C) 2026 NODERA (dgtlnetsolution.com). All Rights Reserved.\n"
            . " */\n"
            . $cleanCode;
    }
}
