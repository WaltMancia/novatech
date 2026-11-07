# syntax=docker/dockerfile:1
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
COPY backend/src ./src
RUN composer install --no-dev --no-interaction --no-progress --classmap-authoritative

FROM php:8.3-fpm-alpine AS app
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql opcache \
 && rm -rf /var/cache/apk/* /tmp/*
COPY docker/php/php.ini      /usr/local/etc/php/conf.d/zz-novatech.ini
COPY docker/php/www.conf     /usr/local/etc/php-fpm.d/zz-novatech.conf
RUN mkdir -p /var/log/novatech && chown www-data:www-data /var/log/novatech
WORKDIR /var/www/backend
COPY --from=vendor /app/vendor ./vendor
COPY backend/ ./
USER www-data
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD php -r '$s = @fsockopen("127.0.0.1", 9000, $e, $m, 2); exit($s ? 0 : 1);'
