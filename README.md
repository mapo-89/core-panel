# Laravel CorePanel

`mapo-89/core-panel` is a Laravel 13 admin package and scaffold built around Inertia v3, Vue 3, PrimeVue, Fortify, Passport, Socialite, Horizon, and Wayfinder.

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

## v1.0.0 Release Rehearsal

For `v1.0.0`, do not test against an old local prototype database. The schema has been consolidated to a new baseline with UUID users and cleaned initial migrations.

Use this flow:

1. Start with a fresh Laravel app.
2. Install `mapo-89/core-panel`.
3. Run `php artisan core-panel:install`.
4. If you want tenancy, answer `yes` to the tenancy installer prompt.
5. Build assets and verify the central app.
6. If tenancy is enabled, create a tenant and verify tenant login, uploads, branding, settings, and social login.

Detailed runbook:

- [docs/release-v1-checklist.md](./docs/release-v1-checklist.md)

## Local Package Development

For local development with a path repository:

```bash
composer create-project laravel/laravel core-panel-app
cd core-panel-app
composer config repositories.core-panel '{"type":"path","url":"/home/manue/projects/packages/core-panel/packages/core-panel","options":{"symlink":true}}'
composer require mapo-89/core-panel:dev-main
php artisan core-panel:install
```

If you are developing from the monorepo and want the addon too, register both path repositories:

```bash
composer config repositories.core-panel '{"type":"path","url":"/home/manue/projects/packages/core-panel/packages/core-panel","options":{"symlink":true}}'
composer config repositories.core-panel-tenancy '{"type":"path","url":"/home/manue/projects/packages/core-panel/packages/core-panel-tenancy","options":{"symlink":true}}'
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

## Documentation

Start here:

- [docs/installation.md](./docs/installation.md)
- [docs/package-installation.md](./docs/package-installation.md)
- [docs/create-project.md](./docs/create-project.md)
- [docs/release-v1-checklist.md](./docs/release-v1-checklist.md)
- [docs/testing.md](./docs/testing.md)
- [docs/updating.md](./docs/updating.md)

Reference docs:

- [docs/architecture.md](./docs/architecture.md)
- [docs/auth.md](./docs/auth.md)
- [docs/passport.md](./docs/passport.md)
- [docs/permissions.md](./docs/permissions.md)
- [docs/settings.md](./docs/settings.md)
- [docs/activity-logs.md](./docs/activity-logs.md)
- [docs/file-manager.md](./docs/file-manager.md)
- [docs/form-builder.md](./docs/form-builder.md)
- [docs/table-builder.md](./docs/table-builder.md)
- [docs/tab-builder.md](./docs/tab-builder.md)
- [docs/api-response-builder.md](./docs/api-response-builder.md)
- [docs/security.md](./docs/security.md)
- [docs/horizon.md](./docs/horizon.md)
- [docs/generators.md](./docs/generators.md)
- [docs/i18n.md](./docs/i18n.md)
- [docs/ui-primevue-theme.md](./docs/ui-primevue-theme.md)
- [docs/deployment-docker-octane.md](./docs/deployment-docker-octane.md)

## License

CorePanel is released under the [MIT license](./LICENSE.md).
