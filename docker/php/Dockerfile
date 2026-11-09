# syntax=docker/dockerfile:1
# Multi-stage: vendor (prod deps) / vendor-dev (+ phpunit, phpstan, cs-fixer) / base (php + ext) / prod / dev

FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize

FROM composer:2 AS vendor-dev
WORKDIR /app
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY backend/ ./
RUN composer dump-autoload --optimize

FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache libzip-dev rabbitmq-c-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql zip pcntl opcache \
    && pecl install amqp \
    && docker-php-ext-enable amqp
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
WORKDIR /var/www/html
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

FROM base AS prod
COPY --from=vendor /app ./
RUN mkdir -p storage/imports storage/images var && chown -R www-data:www-data storage var

FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor-dev /app ./
RUN mkdir -p storage/imports storage/images var && chown -R www-data:www-data storage var
