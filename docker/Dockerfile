# Multi-stage image for the Platform (plan D22).
#
# Stages:
#   base      - PHP 8.4-FPM + the extensions and system tools every environment needs
#   node-base - base + Node 22 (needed by both dev and assets)
#   dev       - node-base + Xdebug (local development only)
#   assets    - node-base, builds the Vite client bundle, the SSR bundle, and the widget
#               (needs `php` on PATH too: Wayfinder generates route/action types
#               during the build, so this extends node-base rather than a plain
#               Node image)
#   demo      - application code + built assets, no dev tools, no Node, no tests
#   prod      - same as demo (kept separate so production can diverge later without touching demo)

FROM php:8.4-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libsodium-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-eng \
        unzip \
        git \
        curl \
        ca-certificates \
        gnupg \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl sodium gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/99-platform.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
FROM base AS node-base

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
FROM node-base AS dev

RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY docker/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

# ---------------------------------------------------------------------------
FROM node-base AS assets

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build:ssr

# ---------------------------------------------------------------------------
FROM base AS demo

COPY . .
COPY --from=assets /var/www/html/public/build ./public/build
COPY --from=assets /var/www/html/bootstrap/ssr ./bootstrap/ssr

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

# ---------------------------------------------------------------------------
FROM demo AS prod
