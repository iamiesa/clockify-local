# syntax=docker/dockerfile:1
# Production image: PHP 8.4 + nginx (serversideup/php), with Composer and Vite builds in separate stages.

# ---- 1. PHP dependencies -------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---- 2. Front-end assets ---------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
# Tailwind scans Blade views shipped inside vendor (pagination, Livewire).
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---- 3. Runtime ------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx

# AUTORUN runs `migrate --force`, storage:link and config/route/view/event caching on boot.
ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

USER www-data
RUN php artisan package:discover --ansi

EXPOSE 8080
