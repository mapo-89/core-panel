# Package Installation

Use this path when you already have a Laravel 13 app and want to add CorePanel to it.

## Install

```bash
composer require mapo-89/core-panel
php artisan core-panel:install
```

## Installer prompts

The current installer asks for:

- `APP_URL`
- `pgsql` or `mysql`
- DB host
- DB port
- DB database
- DB username
- DB password
- test database name
- default locale
- fallback locale
- whether to create an admin user
- whether to publish frontend components
- whether to run migrations
- whether to run seeders
- whether to install frontend dependencies and build assets
- whether to install the tenancy addon
- whether `.env` should be synchronized

If tenancy is enabled, it also asks for:

- central domain

The central domain defaults to the host part of `APP_URL`.

## Important defaults

- API auth defaults to `both`
- light mode is the default UI mode
- PrimeVue theme scaffolding is always part of the baseline
- Horizon is always enabled in the scaffold
- social login stays disabled until configured

## Example non-interactive install

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

## Force reapply

```bash
php artisan core-panel:install --force
```

Use this when you intentionally want to resync scaffolded files.

## Local path repository

For package development:

```bash
composer config repositories.core-panel '{"type":"path","url":"/absolute/path/to/core-panel","options":{"symlink":true}}'
composer require mapo-89/core-panel:dev-main
php artisan core-panel:install --force
```

## Tenancy addon

If you answer `yes` to tenancy installation:

- CorePanel will run `core-panel:tenancy:install` when the addon is already available.
- In local development, if the addon exists under `packages/core-panel-tenancy`, the installer can add the local path dependency automatically.

The tenancy addon package name is:

```text
mapo-89/core-panel-tenancy
```

## PostgreSQL and MySQL

- `mysql`: database creation is straightforward when the user has sufficient privileges.
- `pgsql`: the installer prepares the database before migrations, but the PostgreSQL user still needs permission to create the target database.

## After installation

If you skipped the frontend build in the installer:

```bash
npm install
npm run build
```

Then:

```bash
php artisan optimize:clear
```
