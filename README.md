# Laravel CorePanel

`mapo-89/core-panel` is a Laravel 13 admin package and scaffold built around Inertia v3, Vue 3, PrimeVue, Fortify, Passport, Socialite, Horizon, and Wayfinder.

> Read-only split repository: this package repository is automatically synchronized from `mapo-89/core-panel-monorepo`.
> Do not open pull requests or make direct changes here. All development happens in the monorepo.

The package is split into:

- `mapo-89/core-panel`
- optional `mapo-89/core-panel-tenancy`

The core package stays tenancy-neutral. Tenant-aware behavior lives in the tenancy addon.

## Stack

- PHP 8.5
- Laravel 13
- Inertia v3
- Vue 3
- Tailwind CSS v4
- PrimeVue
- Fortify
- Passport
- Socialite
- Horizon
- PostgreSQL or MySQL
- Redis
- Wayfinder

## Install

Existing Laravel app:

```bash
composer require mapo-89/core-panel
php artisan core-panel:install
```

Fresh Laravel app:

```bash
composer create-project laravel/laravel core-panel-app
cd core-panel-app
composer require mapo-89/core-panel
php artisan core-panel:install
```

CorePanel also registers the short alias:

```bash
php artisan core:install
```

The installer now asks for:

- `APP_URL`
- database driver: `pgsql` or `mysql`
- database host / port / name / user / password
- test database name
- default locale
- fallback locale
- whether an initial admin user should be created
- whether migrations and seeders should run
- whether frontend dependencies should be installed and built
- whether the tenancy addon should be installed

If tenancy is enabled, it also asks for:

- central domain

The default central domain is derived from the host part of `APP_URL`.

Defaults:

- API auth: `passport`
- light mode by default
- PrimeVue theme always included
- Horizon always enabled
- social login disabled until configured

## Local Package Development

For local development with a path repository:

```bash
composer create-project laravel/laravel core-panel-app
cd core-panel-app
composer config repositories.core-panel '{"type":"path","url":"/home/manue/projects/packages/core-panel/packages/core-panel","options":{"symlink":true,"versions":{"mapo-89/core-panel":"dev-main"}}}'
composer require mapo-89/core-panel:dev-main
php artisan core-panel:install
```

If you are developing from the monorepo and want the addon too, register both path repositories:

```bash
composer config repositories.core-panel '{"type":"path","url":"/home/manue/projects/packages/core-panel/packages/core-panel","options":{"symlink":true,"versions":{"mapo-89/core-panel":"dev-main"}}}'
composer config repositories.core-panel-tenancy '{"type":"path","url":"/home/manue/projects/packages/core-panel/packages/core-panel-tenancy","options":{"symlink":true,"versions":{"mapo-89/core-panel-tenancy":"dev-main"}}}'
```

If tenancy is enabled during install and the addon exists as a sibling package, the installer can add the local addon dependency automatically.

Non-interactive example:

```bash
php artisan core-panel:install \
  --no-interaction \
  --app-url=https://core-panel-app.test \
  --db-connection=pgsql \
  --db-host=127.0.0.1 \
  --db-port=5432 \
  --db-database=core_panel \
  --db-username=core_panel \
  --db-password=core_panel \
  --db-database-test=core_panel_test \
  --default-locale=de \
  --fallback-locale=en \
  --create-admin=true \
  --admin-name="Admin User" \
  --admin-email=admin@example.test \
  --admin-password=secret \
  --run-migrations=true \
  --run-seeders=true \
  --install-frontend=false \
  --install-tenancy=true \
  --central-domain=core-panel-app.test \
  --sync-environment=true
```

If you keep the default PostgreSQL installer values, the PostgreSQL user `core_panel` with password `core_panel` must already exist before running the install command.

Example:

```bash
psql postgres
CREATE ROLE core_panel WITH LOGIN PASSWORD 'core_panel' CREATEDB;
CREATE DATABASE core_panel OWNER core_panel;
CREATE DATABASE core_panel_test OWNER core_panel;
\q
```

## Publish Commands

```bash
php artisan core-panel:publish --tag=config
php artisan core-panel:publish --tag=lang
php artisan core-panel:publish --tag=components
php artisan core-panel:publish --tag=theme
php artisan core-panel:publish --tag=stubs
```

## License

CorePanel is released under the [MIT license](./LICENSE.md).
