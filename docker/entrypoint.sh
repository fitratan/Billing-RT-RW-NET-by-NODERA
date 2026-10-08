#!/bin/sh
set -e

# Ensure permissions and directories
mkdir -p /var/log/supervisor /var/log/nginx /var/run /var/www/html/storage/logs /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/log/supervisor /var/log/nginx
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache


# Wait for DB if needed
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database connection at $DB_HOST:$DB_PORT..."
    while ! nc -z "$DB_HOST" "${DB_PORT:-3306}"; do
        sleep 1
    done
    echo "Database ready!"
fi

# Run migrations & optimize
php /var/www/html/artisan migrate --force --no-interaction || true
php /var/www/html/artisan optimize:clear
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

exec "$@"
