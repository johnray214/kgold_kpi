#!/bin/sh
set -e

export PORT="${PORT:-8080}"

# Render Nginx config with Railway's dynamic $PORT
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

# Ensure runtime directories are writable by www-data
mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /var/www/html/public/avatars
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/avatars

# Discover packages & cache config/routes/views with Railway runtime env vars
su -s /bin/sh www-data -c "php artisan package:discover --ansi || true"
su -s /bin/sh www-data -c "php artisan config:cache && php artisan route:cache && php artisan view:cache"
su -s /bin/sh www-data -c "php artisan migrate --force || true"

echo "[Railway] Starting PHP-FPM + Nginx (port ${PORT}) + KPI 30m Scheduler..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
