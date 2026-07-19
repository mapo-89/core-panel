#!/usr/bin/env sh
set -eu

APP_ROOT="${APP_ROOT:-/var/www/html}"
APP_RUNTIME_USER="${APP_RUNTIME_USER:-www-data}"
APP_RUNTIME_GROUP="${APP_RUNTIME_GROUP:-www-data}"
DOCKER_PHP_ENTRYPOINT_BIN="${DOCKER_PHP_ENTRYPOINT_BIN:-docker-php-entrypoint}"
ENTRYPOINT_SCRIPT="${ENTRYPOINT_SCRIPT:-/usr/local/bin/entrypoint.sh}"

mkdir -p \
    "${APP_ROOT}/storage/app/public" \
    "${APP_ROOT}/storage/framework/cache" \
    "${APP_ROOT}/storage/framework/sessions" \
    "${APP_ROOT}/storage/framework/views" \
    "${APP_ROOT}/storage/logs" \
    "${APP_ROOT}/bootstrap/cache"

if [ "$(id -u)" -eq 0 ]; then
    chown -R "${APP_RUNTIME_USER}:${APP_RUNTIME_GROUP}" \
        "${APP_ROOT}/storage" \
        "${APP_ROOT}/bootstrap/cache"
fi

chmod -R u+rwX,g+rwX,o+rX \
    "${APP_ROOT}/storage" \
    "${APP_ROOT}/bootstrap/cache"

if [ -x "${ENTRYPOINT_SCRIPT}" ]; then
    exec "${ENTRYPOINT_SCRIPT}" docker-php-entrypoint "$@"
fi

exec "${DOCKER_PHP_ENTRYPOINT_BIN}" "$@"
