#!/bin/bash
# deploy.sh — Upload project ke shared hosting via rsync, lalu siapkan runtime produksi.
#
# Usage: ./deploy.sh user@host:/path/to/project      (root project, berisi folder public + artisan)
#   atau: ./deploy.sh user@host:absolute  (untuk folder public_html: gunakan ./deploy.sh user@host:/home/user/... )
#
# CATATAN: Website berjalan dari dalam folder `public/`, jadi target sebaiknya = instalasi Laravel
# (folder yang berisi `artisan`, `public/`, `storage/`), BUKAN folder public_html langsung.

set -euo pipefail

TARGET="${1:?Usage: ./deploy.sh user@host:/path/to/laravel}"

echo "🚀 Deploy ke $TARGET ..."

# 1. Build assets frontend (jalankan bila ingin bundle) — aktifkan bila perlu
# npm ci && npm run build

# 2. Sync files (exclude hal yang hanya ada di server)
rsync -avz --delete \
  --exclude '.env' \
  --exclude 'node_modules' \
  --exclude '.git' \
  --exclude '.hermes' \
  --exclude 'storage/app/public' \
  --exclude 'storage/framework/cache' \
  --exclude 'storage/logs' \
  --exclude 'public/storage' \
  --exclude '.env.production' \
  ./ "$TARGET/"

HOST="${TARGET%:*}"
BASE="${TARGET#*:}"

# 3. Setup runtime di server (permission, cache, migrate, link, queue/cron)
ssh "$HOST" "set -e
cd '$BASE'

echo '── Permission storage & bootstrap/cache ──'
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache
chown -R \$(stat -c '%U' .) storage bootstrap/cache 2>/dev/null || true

echo '── Clear stale caches ──'
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

echo '── Migrate ──'
php artisan migrate --force

echo '── Link storage ──'
php artisan storage:link 2>/dev/null || true

echo '── Re-build produksi caches ──'
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo '── Optimize (realpath+) ──'
php artisan optimize 2>/dev/null || true

echo ''
echo '⏰ PASTIKAN SELANJUTNYA DI SERVER (cron):'
echo '   1) Scheduler Jalankan tiap menit:'
echo \"      * * * * * /usr/bin/php '$BASE/artisan' schedule:run >> /dev/null 2>&1\"
echo '   2) Queue worker (QUEUE_CONNECTION=database):'
echo \"      * * * * * /usr/bin/php '$BASE/artisan' queue:work --stop-when-empty --max-time=55 -q >> /dev/null 2>&1\"
echo '   3) Restart service bila pakai php-fpm: sudo systemctl reload php-fpm'
echo '✅ Deploy selesai!'"