FROM node:24-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources resources
COPY lang lang
COPY vite.config.js ./
RUN npm run build

FROM php:8.5-fpm-alpine AS runtime
RUN apk add --no-cache sqlite-dev libzip-dev oniguruma-dev icu-dev linux-headers \
    && docker-php-ext-install -j$(nproc) pdo_sqlite mbstring intl pcntl zip \
    && addgroup -g 1000 panel && adduser -D -u 1000 -G panel panel
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --optimize-autoloader \
    && sed -i 's/\r$//' docker/*.sh \
    && mkdir -p /data storage/framework/sessions storage/framework/views storage/framework/cache \
    && chown -R panel:panel /app /data
COPY --from=assets /app/public/build public/build
USER panel
EXPOSE 9000
ENTRYPOINT ["sh", "docker/entrypoint.sh"]

FROM caddy:2-alpine AS web
COPY --from=runtime /app/public /app/public
COPY docker/Caddyfile /etc/caddy/Caddyfile
