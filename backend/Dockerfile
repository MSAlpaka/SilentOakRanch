# syntax=docker/dockerfile:1

FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        git \
        unzip \
        libpq \
        libzip-dev \
        icu-dev \
        bash \
        make \
        oniguruma-dev \
        zlib-dev \
        postgresql-dev \
        freetype-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libsodium-dev \
        build-base \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        intl \
        gd \
        zip \
        sodium \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

FROM base AS builder

COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-scripts --no-progress

COPY . .

RUN if [ -f bin/console ]; then \
        php bin/console doctrine:migrations:migrate --no-interaction || \
        echo "Skipping migrations during build"; \
    fi

FROM builder AS production

RUN composer install --no-dev --prefer-dist --no-scripts --no-progress

RUN if [ -f bin/console ]; then \
        php bin/console cache:warmup --env=prod || \
        echo "Skipping cache warmup during build"; \
    fi

FROM php:8.2-fpm-alpine AS runtime

WORKDIR /var/www/backend

RUN apk add --no-cache libpq icu

COPY --from=base /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=base /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=production /var/www/backend /var/www/backend

EXPOSE 9000
HEALTHCHECK CMD ["php-fpm", "-t"]
CMD ["php-fpm", "-F"]
