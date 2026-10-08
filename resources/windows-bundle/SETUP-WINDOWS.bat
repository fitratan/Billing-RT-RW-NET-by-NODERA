@echo off
title NODERA BILLING - Windows Setup Installer
color 0B

echo.
echo  ======================================================
echo     NODERA BILLING - STANDALONE ISP & RT-RW NET
echo              Windows 1-Click Installer
echo  ======================================================
echo     Website : https://example.com
echo     Portal  : https://panel.example.com
echo  ======================================================
echo.

:: 1. Cek Apakah Docker Terinstall
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [PERINGATAN] Docker Desktop belum terinstall di Windows Anda.
    echo.
    echo Pastikan Docker Desktop for Windows sudah terinstall:
    echo Unduh di: https://www.docker.com/products/docker-desktop/
    echo.
    echo Tekan tombol apa saja untuk membuka link download Docker Desktop...
    pause >nul
    start https://www.docker.com/products/docker-desktop/
    exit /b 1
)

:: 2. Cek Apakah Docker Engine sedang Running
echo Memeriksa status Docker Desktop...
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo [PERHATIAN] Aplikasi Docker Desktop terinstall tetapi BELUM BERJALAN.
    echo.
    echo LANGKAH PENYELESAIAN:
    echo 1. Buka aplikasi "Docker Desktop" dari Start Menu Windows Anda.
    echo 2. Tunggu beberapa saat sampai ikon Docker di taskbar berwarna hijau (Engine Running).
    echo 3. Setelah Docker Desktop aktif, jalankan kembali file SETUP-WINDOWS.bat ini.
    echo.
    pause
    exit /b 1
)

:: 3. Download Source Code jika belum ada di folder ini
if not exist "artisan" (
    echo.
    echo Mengunduh berkas lengkap NODERA Billing...
    powershell -Command "try { Invoke-WebRequest -Uri 'https://github.com/fitratan/nodera-billing/archive/refs/heads/main.zip' -OutFile 'nodera.zip'; Expand-Archive -Path 'nodera.zip' -DestinationPath 'temp_src' -Force; Copy-Item -Path 'temp_src\nodera-billing-main\*' -Destination '.' -Recurse -Force; Remove-Item 'nodera.zip', 'temp_src' -Recurse -Force; Write-Host 'Download selesai.' } catch { Write-Host 'Gagal mengunduh otomatis, silakan pastikan koneksi internet aktif.' }"
)

echo.
echo [1/3] Konfigurasi Lisensi & Akses Admin...
echo.
set /p LICENSE_KEY=">> Masukkan License Key NODERA (tekan Enter jika Full Source Code): "
if "%LICENSE_KEY%"=="" (
    set LICENSE_KEY=OFFLINE
)

set /p ADMIN_EMAIL=">> Masukkan Email Login Admin [admin@nodera.local]: "
if "%ADMIN_EMAIL%"=="" set ADMIN_EMAIL=admin@nodera.local

set /p ADMIN_PASS=">> Masukkan Password Login Admin [admin12345]: "
if "%ADMIN_PASS%"=="" set ADMIN_PASS=admin12345

set /p HTTP_PORT=">> Port Web HTTP [8080]: "
if "%HTTP_PORT%"=="" set HTTP_PORT=8080

echo.
echo [2/3] Menyiapkan Berkas Lingkungan (.env)...

(
echo APP_NAME="NODERA BILLING"
echo APP_ENV=production
echo APP_KEY=base64:7qY0d8cK5uU2xQ+8a1B2c3D4e5F6g7H8i9J0k1L2m3N=
echo APP_DEBUG=false
echo APP_URL=http://localhost:%HTTP_PORT%
echo STANDALONE_MODE=true
echo.
echo NODERA_LICENSE_KEY=%LICENSE_KEY%
echo NODERA_LICENSE_SERVER=https://panel.example.com
echo.
echo DB_CONNECTION=mysql
echo DB_HOST=db
echo DB_PORT=3306
echo DB_DATABASE=nodera_billing
echo DB_USERNAME=nodera_user
echo DB_PASSWORD=change_this_db_password
echo DB_ROOT_PASSWORD=nodera_root_pass
echo.
echo CACHE_STORE=redis
echo QUEUE_CONNECTION=redis
echo SESSION_DRIVER=redis
echo REDIS_HOST=redis
echo REDIS_PORT=6379
echo.
echo HTTP_PORT=%HTTP_PORT%
echo ADMIN_INIT_EMAIL=%ADMIN_EMAIL%
echo ADMIN_INIT_PASSWORD=%ADMIN_PASS%
) > .env

echo.
echo [3/3] Menjalankan Service NODERA Billing (Docker)...
docker compose up -d --build
if %errorlevel% neq 0 (
    echo.
    echo [GAGAL] Container Docker gagal dijalankan.
    echo Pastikan Docker Desktop aktif dan koneksi internet stabil saat mengunduh image pertama kali.
    echo.
    pause
    exit /b 1
)

echo.
echo Menyiapkan database dan akun admin otomatis...
timeout /t 10 /nobreak >nul
docker compose exec -T app php artisan key:generate --force >nul 2>&1
docker compose exec -T app php artisan migrate --force >nul 2>&1
docker compose exec -T app php artisan storage:link >nul 2>&1
docker compose exec -T app php artisan optimize:clear >nul 2>&1
docker compose exec -T app php artisan tinker --execute="$u = \App\Models\User::firstOrNew(['email' => '%ADMIN_EMAIL%']); $u->name = 'Admin ISP'; $u->password = \Illuminate\Support\Facades\Hash::make('%ADMIN_PASS%'); $u->role = 'admin'; $u->save();" >nul 2>&1

echo.
echo ========================================================
echo   SELAMAT! NODERA BILLING BERHASIL DIINSTALL DI WINDOWS!
echo ========================================================
echo   URL Web       : http://localhost:%HTTP_PORT%
echo   Email Admin   : %ADMIN_EMAIL%
echo   Password      : %ADMIN_PASS%
echo ========================================================
echo.
echo Membuka browser otomatis...
timeout /t 2 /nobreak >nul
start http://localhost:%HTTP_PORT%

pause


