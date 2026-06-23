#!/usr/bin/env sh
set -eu

mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

chmod -R u+rwX,g+rwX,o+rX \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

if [ -x /usr/local/bin/entrypoint.sh ]; then
    exec /usr/local/bin/entrypoint.sh docker-php-entrypoint "$@"
fi

exec docker-php-entrypoint "$@"
