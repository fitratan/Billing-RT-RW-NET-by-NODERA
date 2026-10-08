@echo off
setlocal enabledelayedexpansion
title NODERA BILLING - Server Launcher
color 0B

echo.
echo ======================================================
echo    NODERA BILLING - STANDALONE ISP & RT-RW NET
echo             Windows Portable Server Launcher
echo ======================================================
echo.

:: 1. Cari PHP Executable
set "PHP_BIN="

if exist "%~dp0php\php.exe" (
    set "PHP_BIN=%~dp0php\php.exe"
) else if exist "%~dp0bin\php\php.exe" (
    set "PHP_BIN=%~dp0bin\php\php.exe"
) else (
    where php >nul 2>&1
    if !errorlevel! equ 0 (
        set "PHP_BIN=php"
    ) else if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    ) else if exist "C:\laragon\bin\php" (
        for /d %%D in ("C:\laragon\bin\php\php-*") do (
            if exist "%%D\php.exe" set "PHP_BIN=%%D\php.exe"
        )
    )
)

if "%PHP_BIN%"=="" (
    echo [PERHATIAN] PHP tidak ditemukan di sistem Windows Anda.
    echo.
    echo Pilihan:
    echo 1. Unduh PHP Portable otomatis (Direkomendasikan - Langsung Jalan)
    echo 2. Gunakan Docker Desktop
    echo 3. Keluar
    echo.
    set /p "CHOICE=Masukkan pilihan [1/2/3]: "
    if "!CHOICE!"=="1" (
        echo.
        echo Mengunduh PHP Portable for Windows...
        powershell -NoProfile -ExecutionPolicy Bypass -Command "$ProgressPreference = 'SilentlyContinue'; [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/archives/php-8.2.14-Win32-vs16-x64.zip' -OutFile 'php_tmp.zip'; Expand-Archive -Path 'php_tmp.zip' -DestinationPath '%~dp0php' -Force; Remove-Item 'php_tmp.zip' -Force; if (Test-Path '%~dp0php\php.ini-development') { Copy-Item '%~dp0php\php.ini-development' '%~dp0php\php.ini' }; Add-Content '%~dp0php\php.ini' '`nextension_dir = "ext"`nextension=curl`nextension=fileinfo`nextension=mbstring`nextension=openssl`nextension=pdo_sqlite`nextension=sqlite3`nextension=sockets`nextension=gd'; Write-Host '[SUKSES] PHP Portable berhasil diunduh dan dikonfigurasi.'"
        if exist "%~dp0php\php.exe" (
            set "PHP_BIN=%~dp0php\php.exe"
        ) else (
            echo Gagal mengunduh PHP otomatis. Silakan pasang XAMPP atau Laragon.
            pause
            exit /b 1
        )
    ) else if "!CHOICE!"=="2" (
        goto :docker_mode
    ) else (
        exit /b 0
    )
)

:php_mode
:: 2. Cek Konfigurasi .env
if not exist "%~dp0.env" (
    echo [INFO] Menyiapkan konfigurasi database awal...
    copy "%~dp0.env.example" "%~dp0.env" >nul
    
    :: Pastikan direktori database dan database.sqlite ada
    if not exist "%~dp0database" mkdir "%~dp0database"
    if not exist "%~dp0database\database.sqlite" (
        type nul > "%~dp0database\database.sqlite"
    )
    
    :: Inisialisasi App Key dan Database
    "%PHP_BIN%" "%~dp0artisan" key:generate --force
    "%PHP_BIN%" "%~dp0artisan" migrate --force --seed
    "%PHP_BIN%" "%~dp0artisan" config:clear
    echo [INFO] Inisialisasi selesai.
)

:: Dapatkan IP Lokal LAN
set "LOCAL_IP=127.0.0.1"
for /f "tokens=2 delims=:" %%I in ('ipconfig ^| findstr /c:"IPv4 Address" /c:"Alamat IPv4"') do (
    for /f "tokens=1" %%A in ("%%I") do (
        if not "%%A"=="127.0.0.1" set "LOCAL_IP=%%A"
    )
)

echo.
echo ======================================================
echo    SERVER NODERA BILLING AKTIF BERJALAN
echo ======================================================
echo  Akses Lokal Komputer : http://127.0.0.1:8080/login
echo  Akses dari Jaringan  : http://!LOCAL_IP!:8080/login
echo ======================================================
echo.
echo  Gunakan browser untuk membuka alamat di atas.
echo  Untuk menghentikan server, tekan Ctrl + C atau buka STOP-NODERA.bat
echo ======================================================
echo.

:: Buka Browser Otomatis
start http://127.0.0.1:8080/login

:: Jalankan Web Server
"%PHP_BIN%" "%~dp0artisan" serve --host=0.0.0.0 --port=8080
goto :eof

:docker_mode
echo Menjalankan via Docker Desktop...
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo [PERHATIAN] Docker Desktop belum aktif. Buka Docker Desktop terlebih dahulu.
    pause
    exit /b 1
)
docker compose up -d
echo Server berjalan di background.
start http://127.0.0.1:8080/login
pause
goto :eof
