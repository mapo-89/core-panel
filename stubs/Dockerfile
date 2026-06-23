FROM node:24-bookworm AS node

FROM php:8.5-fpm-bookworm AS php-extension-base

WORKDIR /var/www/html

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
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

FROM php-extension-base AS php-build-base

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && rm -rf /var/lib/apt/lists/*

FROM php-build-base AS composer-base

COPY composer.json composer.lock ./

FROM composer-base AS vendor-prod

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

FROM composer-base AS vendor-dev

RUN composer install \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

FROM php-build-base AS frontend-build

COPY package.json package-lock.json ./

RUN npm ci

COPY . .
COPY --from=vendor-prod /var/www/html/vendor /var/www/html/vendor

RUN php artisan package:discover --ansi \
    && php artisan wayfinder:generate --no-interaction \
    && npm run build

FROM php-extension-base AS php-runtime-base

COPY . /var/www/html
COPY .docker/bin/php-entrypoint.sh /usr/local/bin/core-panel-php-entrypoint
COPY .docker/php/banner.sh /usr/local/bin/banner.sh
COPY .docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/99-core-panel.ini
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY .docker/php-fpm/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

RUN mkdir -p \
        /var/www/html/storage/app \
        /var/www/html/storage/framework/cache \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
    && chmod +x /usr/local/bin/banner.sh /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /usr/local/bin/core-panel-php-entrypoint

FROM php-runtime-base AS php-prod

COPY --from=vendor-prod /var/www/html/vendor /var/www/html/vendor
COPY --from=frontend-build /var/www/html/public/build /var/www/html/public/build

RUN php artisan package:discover --ansi \
    && php artisan wayfinder:generate --no-interaction

EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=5s --retries=5 CMD php-fpm -t || exit 1

ENTRYPOINT ["core-panel-php-entrypoint"]
CMD ["php-fpm", "-F"]

FROM php-runtime-base AS php-dev

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor-dev /var/www/html/vendor /var/www/html/vendor
COPY --from=frontend-build /var/www/html/public/build /var/www/html/public/build

RUN php artisan package:discover --ansi \
    && php artisan wayfinder:generate --no-interaction

EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=5s --retries=5 CMD php-fpm -t || exit 1

ENTRYPOINT ["core-panel-php-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.31-alpine AS nginx-runtime

WORKDIR /var/www/html

COPY --from=php-prod /var/www/html/public /var/www/html/public
COPY .docker/nginx/default.conf /etc/nginx/conf.d/default.conf
