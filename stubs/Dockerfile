FROM dunglas/frankenphp:1-php8.5-bookworm AS php-base

ARG APP_ENV=production

ENV APP_ENV=${APP_ENV} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    OCTANE_SERVER=frankenphp \
    SUPERVISOR_PHP_COMMAND="php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000 --admin-port=2019"

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        postgresql-client \
        supervisor \
        unzip \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        redis \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


FROM php-base AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist


FROM node:22-bookworm-slim AS node-base


FROM php-base AS assets

WORKDIR /app

COPY --from=node-base /usr/local/ /usr/local/
COPY package*.json ./

RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

COPY . .
COPY --from=vendor /app/vendor /app/vendor

RUN php artisan package:discover --ansi \
    && npm run build


FROM php-base AS runtime

WORKDIR /var/www/html

COPY --from=vendor /app/vendor /var/www/html/vendor
COPY . /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/99-core-panel.ini
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY .docker/supervisor/octane.conf /etc/supervisor/conf.d/octane.conf
COPY .docker/supervisor/horizon.conf /etc/supervisor/conf.d/horizon.conf
COPY .docker/supervisor/scheduler.conf /etc/supervisor/conf.d/scheduler.conf

RUN php artisan package:discover --ansi \
    && mkdir -p /var/log/supervisor /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8000 2019

HEALTHCHECK --interval=15s --timeout=5s --retries=5 CMD curl -fsS http://127.0.0.1:8000/up || exit 1

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000", "--admin-port=2019"]
