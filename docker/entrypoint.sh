#!/bin/bash
# ============================================================
# Entrypoint — Sistema Garantías
# Runs on container start, before supervisor takes over
# ============================================================

set -e

# 1. Ensure storage directories exist and have correct permissions
mkdir -p /var/www/html/storage/framework/{cache,sessions,testing,views}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/app/public

# 2. Fix permissions (www-data needs to write)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# 3. Regenerate package manifest (dev cache may be stale)
php /var/www/html/artisan package:discover --quiet 2>/dev/null || true

# 4. Create storage symlink if it doesn't exist
if [ ! -L /var/www/html/public/storage ]; then
    php /var/www/html/artisan storage:link --quiet --force
fi

# 5. Cache Laravel config + routes for production
php /var/www/html/artisan config:cache --quiet 2>/dev/null || true
php /var/www/html/artisan route:cache --quiet 2>/dev/null || true
php /var/www/html/artisan view:cache --quiet 2>/dev/null || true

# 5. Add Laravel scheduler to crontab
echo "* * * * * www-data php /var/www/html/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/laravel-scheduler
chmod 0644 /etc/cron.d/laravel-scheduler
crontab /etc/cron.d/laravel-scheduler

# 7. Start supervisor (manages nginx + php-fpm + cron)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
