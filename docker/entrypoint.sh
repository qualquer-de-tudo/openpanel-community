#!/bin/sh
set -eu
umask 077
if [ ! -s /data/app.key ]; then
  php artisan key:generate --show > /data/app.key
fi
export APP_KEY="$(cat /data/app.key)"
php -r '$path = "/app/.env"; $text = file_get_contents($path); $key = trim(file_get_contents("/data/app.key")); $text = preg_replace("/^APP_KEY=.*$/m", "APP_KEY=".$key, $text); file_put_contents($path, $text, LOCK_EX); chmod($path, 0600);'
php artisan config:clear
touch /data/database.sqlite
mkdir -p /data/storage
if [ ! -L storage/app ]; then
  cp -an storage/app/. /data/storage/
  rm -r storage/app
  ln -s /data/storage storage/app
fi
php artisan migrate --force
touch /tmp/openpanel-ready
exec php-fpm -F
