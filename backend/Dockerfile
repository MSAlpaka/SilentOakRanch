# syntax=docker/dockerfile:1

FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        git \
        unzip \
        libpq \
        libpng \
        libwebp \
        libxpm \
        libxml2 \
        libxslt \
        libmemcached \
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
        mbstring \
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

FROM base AS production

COPY --from=builder /var/www/backend /var/www/backend

RUN composer install --no-dev --prefer-dist --no-scripts --no-progress

RUN if [ -f bin/console ]; then \
        php bin/console cache:warmup --env=prod || \
        echo "Skipping cache warmup during build"; \
    fi

FROM base AS runtime

WORKDIR /var/www/backend

RUN apk add --no-cache \
        libpq \
        icu \
        nginx \
        supervisor \
        curl \
    && mkdir -p /run/nginx /var/log/supervisor /var/cache/nginx

COPY docker/ /docker/
RUN cp /docker/app.conf /etc/nginx/conf.d/app.conf \
    && cp /docker/supervisord.conf /etc/supervisord.conf \
    && chmod +x /docker/entrypoint.sh
COPY --from=production /var/www/backend /var/www/backend

EXPOSE 8080
HEALTHCHECK CMD curl -fsS http://127.0.0.1:8080/ || exit 1
ENTRYPOINT ["/docker/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
