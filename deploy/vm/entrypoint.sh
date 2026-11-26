#!/bin/sh
set -eu
update-ca-certificates >/dev/null
mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs bootstrap/cache
mkdir -p storage/app/private
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
chmod 700 storage/app/private
exec docker-php-entrypoint "$@"
