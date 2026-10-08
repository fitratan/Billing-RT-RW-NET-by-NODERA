@echo off
title NODERA BILLING - System Updater
color 0E
echo Memeriksa pembaruan versi NODERA Billing...
docker compose pull
docker compose up -d
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
echo.
echo Pembaruan selesai!
pause
