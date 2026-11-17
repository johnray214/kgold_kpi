# ==============================================================================
# Stage 1: PHP Dependencies (Composer)
# ==============================================================================
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ==============================================================================
# Stage 2: Frontend Assets (Tailwind CSS v4 + Vite 8)
# ==============================================================================
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ==============================================================================
# Stage 3: Production Runtime (PHP 8.3-FPM + Nginx + Laravel Scheduler)
# ==============================================================================
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    gettext-base \
    ca-certificates \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        opcache \
        bcmath \
        pcntl \
        zip \
        gd \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

# Production PHP + OPcache tuning
RUN { \
    echo "memory_limit=512M"; \
    echo "max_execution_time=120"; \
    echo "upload_max_filesize=20M"; \
    echo "post_max_size=20M"; \
    echo "expose_php=Off"; \
    echo "opcache.enable=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.memory_consumption=192"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "realpath_cache_size=4096K"; \
    echo "realpath_cache_ttl=600"; \
} > /usr/local/etc/php/conf.d/99-production.ini

# PHP-FPM worker pool (preserves Railway env vars + handles concurrent requests)
RUN { \
    echo "[www]"; \
    echo "clear_env = no"; \
    echo "pm = dynamic"; \
    echo "pm.max_children = 15"; \
    echo "pm.start_servers = 4"; \
    echo "pm.min_spare_servers = 2"; \
    echo "pm.max_spare_servers = 6"; \
    echo "pm.max_requests = 500"; \
} > /usr/local/etc/php-fpm.d/zz-railway.conf

WORKDIR /var/www/html

# Copy application code + prebuilt vendor & Vite assets
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# Regenerate optimized Composer classmap with app files present
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts \
    && rm -f bootstrap/cache/*.php

# Copy Nginx, Supervisor (Web + Scheduler), and Entrypoint configs
COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN sed -i 's/\r$//' /entrypoint.sh \
    && chmod +x /entrypoint.sh \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        public/avatars \
    && chown -R www-data:www-data storage bootstrap/cache public/avatars

ENV PORT=8080 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
