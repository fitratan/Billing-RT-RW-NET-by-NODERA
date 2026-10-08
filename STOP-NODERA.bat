@echo off
title NODERA BILLING - Stop Server
color 0C

echo.
echo Menghentikan service NODERA Billing...

:: Hentikan proses PHP artisan serve pada port 8080 jika ada
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8080') do (
    taskkill /F /PID %%a >nul 2>&1
)

:: Hentikan docker jika berjalan
docker info >nul 2>&1
if %errorlevel% equ 0 (
    docker compose stop >nul 2>&1
)

echo.
echo Service NODERA Billing telah dihentikan.
timeout /t 2 /nobreak >nul
