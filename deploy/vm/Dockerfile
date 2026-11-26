FROM composer:2 AS composer
FROM php:8.4-fpm-alpine
ARG APK_MIRROR=""
RUN cp /etc/apk/repositories /tmp/seiem-apk-repositories \
    && if [ -n "$APK_MIRROR" ]; then sed -i "s|https://dl-cdn.alpinelinux.org|$APK_MIRROR|g" /etc/apk/repositories; fi \
    && apk add --no-cache icu-libs libzip libpq ca-certificates \
    && apk add --no-cache --virtual .seiem-build-deps $PHPIZE_DEPS icu-dev libzip-dev libpq-dev \
    && docker-php-ext-install -j2 pdo_pgsql intl zip \
    && apk del .seiem-build-deps \
    && mv /tmp/seiem-apk-repositories /etc/apk/repositories
RUN cp /etc/apk/repositories /tmp/filiacion-apk-repositories \
    && if [ -n "$APK_MIRROR" ]; then sed -i "s|https://dl-cdn.alpinelinux.org|$APK_MIRROR|g" /etc/apk/repositories; fi \
    && apk add --no-cache --virtual .filiacion-build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j2 pdo_mysql \
    && apk del .filiacion-build-deps \
    && mv /tmp/filiacion-apk-repositories /etc/apk/repositories
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/expedientes
COPY composer.json composer.lock ./
RUN --mount=type=bind,source=deploy/vm/composer-cache,target=/seiem-package-cache \
    mkdir -p /tmp/composer-cache/files \
    && cp -R /seiem-package-cache/. /tmp/composer-cache/files/ \
    && COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-progress \
    && rm -rf /tmp/composer-cache
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY resources ./resources
COPY lang ./lang
COPY public ./public
COPY routes ./routes
COPY artisan ./artisan
RUN mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
COPY deploy/vm/entrypoint.sh /usr/local/bin/expedientes-entrypoint
COPY deploy/vm/php-production.ini /usr/local/etc/php/conf.d/zz-seiem.ini
RUN chmod 755 /usr/local/bin/expedientes-entrypoint
ENTRYPOINT ["expedientes-entrypoint"]
CMD ["php-fpm"]
