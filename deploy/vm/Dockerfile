FROM composer:2@sha256:0c8e663f14c94d07b3c45b175c51f5a14718508f21fe4dd7e54f09488dd0ed71 AS composer
FROM php:8.4-fpm-alpine@sha256:511af6fa3ef55c4ea0c68bb47096ba4b81771ab4949bde7813807bf177efda11
ARG APK_MIRROR=""
RUN cp /etc/apk/repositories /tmp/seiem-apk-repositories \
    && if [ -n "$APK_MIRROR" ]; then sed -i "s|https://dl-cdn.alpinelinux.org|$APK_MIRROR|g" /etc/apk/repositories; fi \
    && apk add --no-cache icu-libs libzip ca-certificates \
    && apk add --no-cache --virtual .seiem-build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install -j2 pdo_mysql intl zip \
    && apk del .seiem-build-deps \
    && mv /tmp/seiem-apk-repositories /etc/apk/repositories
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/expedientes
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-progress
ARG SOURCE_REVISION=unknown
LABEL org.opencontainers.image.revision=$SOURCE_REVISION
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
