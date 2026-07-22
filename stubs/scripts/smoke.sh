#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "${repo_root}"

if [[ ! -f .env ]]; then
    echo "Missing .env. Copy .env.example before running smoke." >&2
    exit 1
fi

php artisan config:clear >/dev/null
php artisan route:list --except-vendor >/dev/null
php artisan route:list --name=core-panel.users.index --except-vendor | grep -q 'core-panel.users.index'
php artisan route:list --path=login --except-vendor | grep -q 'login'
php artisan migrate --force >/dev/null
php artisan test tests/Feature/CorePanelInstallationTest.php --compact

if [[ -f config/tenancy.php ]]; then
    php artisan route:list --name=tenant.core-panel.users.index --except-vendor | grep -q 'tenant.core-panel.users.index'
    php artisan config:show tenancy.database.central_connection | grep -q 'pgsql'

    if [[ -f tests/Feature/CorePanelTenancyHostTest.php ]]; then
        php artisan test tests/Feature/CorePanelTenancyHostTest.php --compact
    fi
fi
