#!/bin/sh
set -eu
umask 077
if [ ! -s /data/app.key ]; then
  php artisan key:generate --show > /data/app.key
fi
export APP_KEY="$(cat /data/app.key)"
touch /data/database.sqlite
php artisan migrate --force
exec php-fpm -F
