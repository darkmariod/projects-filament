# ============================================================
# Dockerfile — Sistema Garantías (PHP 8.2-FPM + Nginx)
# ============================================================

# ---- Stage 1: Node.js assets build ----
FROM node:22-slim AS node-build

WORKDIR /build

COPY package.json ./
RUN npm install

COPY . .
RUN npm run build

# ---- Stage 2: PHP + Nginx runtime ----
FROM php:8.4-fpm

LABEL maintainer="Sistema Garantías"
LABEL description="PHP 8.4-FPM + Nginx + Supervisor para Laravel"

# -----------------------------------------------------------
# 1. System dependencies
# -----------------------------------------------------------
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    cron \
    curl \
    git \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libfreetype-dev \
    libjpeg62-turbo-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# 2. PHP extensions for Laravel + Filament + DOMPDF + QR
# -----------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    fileinfo

# -----------------------------------------------------------
# 3. Composer (official image)
# -----------------------------------------------------------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# -----------------------------------------------------------
# 4. Nginx: remove default site, copy our config
# -----------------------------------------------------------
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx/default.conf /etc/nginx/sites-available/laravel
RUN ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/laravel

# -----------------------------------------------------------
# 5. PHP production overrides
# -----------------------------------------------------------
COPY docker/php.production.ini /usr/local/etc/php/conf.d/production.ini

# -----------------------------------------------------------
# 6. Supervisor config (nginx + php-fpm + cron)
# -----------------------------------------------------------
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# -----------------------------------------------------------
# 7. Entrypoint
# -----------------------------------------------------------
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# -----------------------------------------------------------
# 8. Application code
# -----------------------------------------------------------
WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

# -----------------------------------------------------------
# 9. Create required storage directories for artisan commands
# -----------------------------------------------------------
RUN mkdir -p /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/storage/app/public \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# -----------------------------------------------------------
# 10. Composer install (no dev, skip artisan scripts that need cache paths)
# -----------------------------------------------------------
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress --no-scripts \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && echo "Dev cache cleared (regenerated at runtime)"

# -----------------------------------------------------------
# 11. Copy built assets from node-build stage
# -----------------------------------------------------------
COPY --from=node-build --chown=www-data:www-data /build/public/build /var/www/html/public/build

# -----------------------------------------------------------
# 12. Health check
# -----------------------------------------------------------
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD curl -f http://localhost/health-check || exit 1

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
