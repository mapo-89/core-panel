#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ "${DB_CONNECTION:-pgsql}" != "pgsql" ]; then
    php artisan optimize:clear
    exit 0
fi

export PGPASSWORD="${DB_PASSWORD:-core_panel}"

until pg_isready \
    -h "${DB_HOST:-postgres}" \
    -p "${DB_PORT:-5432}" \
    -U "${DB_USERNAME:-core_panel}" \
    -d postgres
do
    sleep 2
done

if ! psql \
    -h "${DB_HOST:-postgres}" \
    -p "${DB_PORT:-5432}" \
    -U "${DB_USERNAME:-core_panel}" \
    -d postgres \
    -Atqc "SELECT 1 FROM pg_database WHERE datname = '${DB_DATABASE:-core_panel}'" | grep -q 1
then
    psql \
        -h "${DB_HOST:-postgres}" \
        -p "${DB_PORT:-5432}" \
        -U "${DB_USERNAME:-core_panel}" \
        -d postgres \
        -c "CREATE DATABASE \"${DB_DATABASE:-core_panel}\""
fi

if ! psql \
    -h "${DB_HOST:-postgres}" \
    -p "${DB_PORT:-5432}" \
    -U "${DB_USERNAME:-core_panel}" \
    -d postgres \
    -Atqc "SELECT 1 FROM pg_database WHERE datname = '${DB_DATABASE_TEST:-core_panel_test}'" | grep -q 1
then
    psql \
        -h "${DB_HOST:-postgres}" \
        -p "${DB_PORT:-5432}" \
        -U "${DB_USERNAME:-core_panel}" \
        -d postgres \
        -c "CREATE DATABASE \"${DB_DATABASE_TEST:-core_panel_test}\""
fi

php artisan optimize:clear
