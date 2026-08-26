# ---- Stage 1: PHP dependencies ----
# Needed before the front-end build, not just after: resources/js/app.js
# imports Ziggy's Vue plugin straight out of vendor/tightenco/ziggy, so Vite
# can't resolve that import until composer install has actually run.
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# ---- Stage 2: build front-end assets ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---- Stage 3: PHP application ----
FROM php:8.2-cli-alpine AS app

RUN apk add --no-cache postgresql-dev oniguruma-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pgsql mbstring bcmath zip \
    && apk del $PHPIZE_DEPS

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN php artisan package:discover --ansi \
    && chmod -R 775 storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
