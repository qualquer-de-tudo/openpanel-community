FROM node:24-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources resources
COPY lang lang
COPY vite.package.config.js ./
RUN npm run build:package

FROM php:8.5-fpm-alpine AS runtime
RUN apk add --no-cache sqlite-dev libzip-dev oniguruma-dev icu-dev linux-headers \
    && docker-php-ext-install -j$(nproc) pdo_sqlite mbstring intl pcntl zip \
    && apk add --no-cache git unzip \
    && addgroup -g 1000 panel && adduser -D -u 1000 -G panel panel
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /opt/qopenpanel
COPY . .
COPY --from=assets /app/resources/dist resources/dist
RUN composer create-project laravel/laravel /app '^13.0' --no-dev --no-interaction --no-scripts \
    && cd /app \
    && cp .env.example .env \
    && touch database/database.sqlite \
    && composer config repositories.qopenpanel '{"type":"path","url":"/opt/qopenpanel","options":{"symlink":false,"versions":{"qualquer-de-tudo/qopenpanel-community":"dev-main"}}}' \
    && composer require qualquer-de-tudo/qopenpanel-community:dev-main --update-no-dev --no-interaction --optimize-autoloader \
    && php artisan vendor:publish --tag=qopenpanel-assets --force \
    && cp /opt/qopenpanel/docker/standalone-web.php routes/web.php \
    && mkdir -p docker \
    && cp /opt/qopenpanel/docker/entrypoint.sh docker/entrypoint.sh \
    && sed -i 's/\r$//' docker/*.sh \
    && mkdir -p /data storage/framework/sessions storage/framework/views storage/framework/cache \
    && chown -R panel:panel /app /data
WORKDIR /app
USER panel
EXPOSE 9000
ENTRYPOINT ["sh", "docker/entrypoint.sh"]

FROM caddy:2-alpine AS web
COPY --from=runtime /app/public /app/public
COPY docker/Caddyfile /etc/caddy/Caddyfile
