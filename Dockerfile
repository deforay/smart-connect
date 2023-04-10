#syntax=docker/dockerfile:1.5
FROM php:7.4-fpm-alpine AS base-php
# Blocked by https://github.com/moby/buildkit/issues/1512 for reusing cache mounts in GHA
RUN --mount=type=cache,id=apk,sharing=locked,target=/etc/apk/cache \
    set -eux; \
    # install with .phpize-deps to avoid unnecessary install-delete cycles in docker-php-ext-*
    apk add --update-cache --update --virtual .phpize-deps $PHPIZE_DEPS \
    && apk add --update --virtual .build-deps \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        postgresql-dev \
        libzip-dev \
        zip \
    && apk add --update \
        ca-certificates \
        git \
        su-exec \
    && docker-php-source extract \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install -j"$(getconf _NPROCESSORS_ONLN)" \
        bcmath \
        gd \
        intl \
        mysqli \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        zip \
    && docker-php-source delete \
    && runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
        | tr ',' '\n' \
        | sort -u \
        | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )" \
    && apk del .phpize-deps .build-deps \
    && rm -rf /tmp/pear \
   	&& apk add --virtual .phpext-rundeps $runDeps
COPY --link --from=composer:2.5 /usr/bin/composer /usr/local/bin/composer
RUN mkdir /app && chown www-data:www-data /app
WORKDIR /app

FROM base-php as app
# Caddy writes to XDG_CONFIG_HOME and XDG_DATA_HOME which are set to these dirs in run-caddy. Do not set them image-wide.
RUN mkdir /config && mkdir /data
COPY --link --from=caddy:2.6.4-alpine /usr/bin/caddy /usr/bin/caddy
COPY --link --chmod=0755 .docker/caddy/run-caddy /usr/local/bin/run-caddy
COPY --link .docker/caddy/Caddyfile /etc/caddy/Caddyfile
EXPOSE 80

# numeric ids for www-data since --link can't read prervious layers
ADD --link --chown=82:82 composer.json composer.lock /app/
RUN --mount=type=cache,id=composer,uid=82,target=/home/www-data/.composer/cache \
    su-exec www-data composer install -n --no-ansi \
    && su-exec www-data composer show
ADD --link --chown=82:82 . /app/
RUN su-exec www-data composer dump-autoload -o
