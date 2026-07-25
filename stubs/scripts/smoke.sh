#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "${repo_root}"

run() {
    echo "==> $*"
    "$@"
}

if [[ ! -f .env ]]; then
    echo "Missing .env. Copy .env.example before running smoke." >&2
    exit 1
fi

run php artisan config:clear
run php artisan route:list --except-vendor
run php artisan route:list --name=core-panel.users.index
php artisan route:list --name=core-panel.users.index | grep -q 'core-panel.users.index'
run php artisan route:list --path=login
php artisan route:list --path=login | grep -q 'login'
run php artisan migrate --force --no-interaction
run php artisan test tests/Feature/CorePanelInstallationTest.php --compact

if [[ -f config/tenancy.php ]]; then
    run php artisan route:list --name=tenant.core-panel.users.index
    php artisan route:list --name=tenant.core-panel.users.index | grep -q 'tenant.core-panel.users.index'
    run php artisan config:show tenancy.database.central_connection
    php artisan config:show tenancy.database.central_connection | grep -q 'pgsql'

    if [[ -f tests/Feature/CorePanelTenancyHostTest.php ]]; then
        run php artisan test tests/Feature/CorePanelTenancyHostTest.php --compact
    fi
fi
