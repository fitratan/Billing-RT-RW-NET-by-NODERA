# Stage 1: Build Frontend Assets
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP Application & Runtime
FROM php:8.3-fpm-alpine

# Easy & Rock-Solid PHP Extension Installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    sockets \
    redis \
    opcache

# Install System Utilities & Nginx & Supervisor
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    netcat-openbsd \
    shadow


# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy backend files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Copy application source
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Setup configs
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissions & log directories
RUN mkdir -p /var/log/supervisor /var/log/nginx /var/run /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/log/supervisor /var/log/nginx \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80


ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
