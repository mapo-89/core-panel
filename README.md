# Laravel CorePanel

[![Latest Stable Version](https://img.shields.io/packagist/v/mapo-89/core-panel.svg)](https://packagist.org/packages/mapo-89/core-panel)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/mapo-89/core-panel.svg)](https://packagist.org/packages/mapo-89/core-panel)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20.svg?logo=laravel&logoColor=white)](https://laravel.com/)
[![GitHub stars](https://img.shields.io/github/stars/mapo-89/core-panel.svg?style=flat)](https://github.com/mapo-89/core-panel/stargazers)
[![Last commit](https://img.shields.io/github/last-commit/mapo-89/core-panel.svg)](https://github.com/mapo-89/core-panel/commits/main)

<a href="https://buymeacoffee.com/mapo"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me a Coffee" height="60"></a>

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

New installations do not require any additional vendor-first migration:

- `core-panel:install` already configures the host so CorePanel frontend building blocks are loaded from `vendor` by default
- `resources/css/app.css` already contains the required Tailwind `@source` entries for `vendor/mapo-89/core-panel/resources/js`
- only publish `components` or `theme` if the host application really needs to own and customize those files locally

CorePanel also registers the short alias:

```bash
php artisan core:install
```

## Generic OpenID Connect login

CorePanel ships a provider-neutral `oidc` Socialite driver. It uses OpenID Connect Discovery and has been validated against authentik. The same provider appears in the login page and the profile connection list; the existing account-linking and optional master-provider flow apply unchanged.

Configure the OIDC application at the identity provider with this redirect URI (replace the domain with the public application URL):

```text
https://panel.example.com/auth/oidc/callback
```

Then configure the host `.env`:

```dotenv
SOCIAL_OIDC_ENABLED=true
OIDC_ISSUER=https://authentik.example.com/application/o/core-panel/
OIDC_CLIENT_ID=your-client-id
OIDC_CLIENT_SECRET=your-client-secret
# Optional; defaults to /auth/oidc/callback and is normalized to APP_URL at runtime.
OIDC_REDIRECT_URI=/auth/oidc/callback
```

`OIDC_ISSUER` must be the exact issuer advertised by the discovery document at `/.well-known/openid-configuration`. For authentik this is normally the application's OpenID Connect issuer URL, not just the base authentik URL.

The default claim mapping is compatible with standard OIDC UserInfo responses:

```dotenv
OIDC_CLAIM_ID=sub
OIDC_CLAIM_EMAIL=email
OIDC_CLAIM_NAME=name
OIDC_CLAIM_NICKNAME=preferred_username
OIDC_CLAIM_AVATAR=picture
```

Change these names only when the provider exposes different claims. The ID claim must be stable and unique for the issuer. An email is required for automatic user matching and creation; if email delivery is unreliable, do not select OIDC as the master provider.

The same configuration is available at runtime in **Settings → Authentication**. Enable “OpenID Connect login” after entering issuer and client credentials. To make OIDC authoritative for email updates during account linking, select `oidc` as the master social provider; otherwise leave the master-provider setting empty.

## Timestamp Conversion

If an existing PostgreSQL installation still contains legacy `timestamp without time zone` columns from before the `timestampTz()` switch, CorePanel ships a one-time conversion command:

```bash
php artisan core-panel:convert-timestamps-tz --dry-run
php artisan core-panel:convert-timestamps-tz --force
```

The command interprets legacy values in the configured source timezone and converts them directly to `timestamptz` instants without depending on the PostgreSQL session timezone.

Default source timezone:

- legacy timezone: `Europe/Berlin`

Override them in the host application if needed:

```php
// config/core-panel.php
'database' => [
    'timestamp_tz_conversion' => [
        'legacy_timezone' => env('CORE_PANEL_TIMESTAMP_LEGACY_TIMEZONE', 'Europe/Berlin'),
    ],
],
```

### Add Project-Specific Tables

CorePanel only knows its own package tables by default. Host applications can explicitly extend the conversion lists for project-specific tables in `config/core-panel.php`:

```php
// config/core-panel.php
'database' => [
    'timestamp_tz_conversion' => [
        'datasets' => [
            'central' => [
                'projects' => ['created_at', 'updated_at', 'deleted_at'],
                'appointments' => ['scheduled_for', 'cancelled_at', 'created_at', 'updated_at'],
            ],
        ],
    ],
],
```

Available datasets:

- `central` for the main application database
- `tenancy` for tenancy metadata tables when the addon is installed
- `tenant` for tenant application databases when the addon is installed

Use the same structure for each dataset: table name => list of timestamp columns to convert.

## PWA

CorePanel can now scaffold the host application for Progressive Web App support via `erag/laravel-pwa`.

### What CorePanel Sets Up

For new installs, `core-panel:install` scaffolds the PWA host files automatically.

For existing installs, update the host scaffolds once:

```bash
php artisan core-panel:update --force
```

This brings the following host files into place when they are missing or managed by the CorePanel scaffold manifest:

- `bootstrap/providers.php`
- `config/pwa.php`
- `public/manifest.json`
- `public/offline.html`
- `public/sw.js`
- `public/logo.png`

CorePanel also renders the package Inertia root view with:

- `@PwaHead` inside `<head>`
- `@RegisterServiceWorkerScript` before `</body>`

### What You Should Adjust In The Host App

After installation or update, review these host-specific values:

- set the correct public app name and URL in `.env`, especially `APP_NAME` and `APP_URL`
- review `config/pwa.php` and adjust `name`, `short_name`, `description`, `theme_color`, and `background_color`
- replace `public/logo.png` with the real app icon in at least `512x512`
- if the install prompt should not be shown globally, set `'install-button' => false` in `config/pwa.php`

### What You Should Verify

- PWA features require HTTPS in real environments; service workers will not work correctly without it
- if you use `config:cache`, rebuild the cache after changing `config/pwa.php`
- after changing `config/pwa.php`, regenerate the browser-facing manifest with `php artisan erag:update-manifest` so updated names, colors, icons, and prompts reach `public/manifest.json`
- make sure the deployed `public/` directory contains `manifest.json`, `sw.js`, `offline.html`, and the final `logo.png`
- if the host app had its own custom `bootstrap/providers.php`, merge the `EragLaravelPwa\EragLaravelPwaServiceProvider::class` entry intentionally instead of overwriting unrelated providers

Typical rollout after enabling PWA support in an existing app:

```bash
composer update mapo-89/core-panel
php artisan core-panel:update --force
php artisan erag:update-manifest
php artisan optimize:clear
npm run build
```

### Optional Host Customization

The scaffold gives you a working baseline, but most applications should still make a few deliberate host decisions:

- replace the default offline page in `public/offline.html` with project-specific branding and support text
- expand `public/manifest.json` icons or screenshots if the target platforms require more than the default single icon
- rerun `php artisan erag:update-manifest` whenever `config/pwa.php` or the referenced icon assets change
- if the host application already has its own PWA strategy or service worker, consolidate that logic instead of keeping two competing implementations

## Upgrade To The Unified Docker Application Image

This one-time upgrade is required when an existing installation still uses separate PHP-FPM and Nginx images. Afterwards, `app`, `horizon`, and `scheduler` run from the same application image. The former `nginx` service is removed, while PostgreSQL, Redis, and `system-updater` remain separate services.

### 1. Prepare A Maintenance Window And Backups

Plan for a short interruption while the containers are replaced. Back up at least the database, persistent storage directory, `.env`, and the Compose files currently in use. Example for PostgreSQL:

```bash
docker compose --env-file .env \
  -f docker-compose.prod.yml \
  -f docker-compose.registry.yml \
  exec -T postgres sh -lc 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
  > core-panel-before-upgrade.sql
```

Record the currently deployed image tags or digests as well so that a rollback remains possible.

### 2. Update The Package And Managed Scaffolds

Update CorePanel and then publish the new Docker and Compose structure:

```bash
composer update mapo-89/core-panel
php artisan core-panel:update --force --no-interaction
```

With the tenancy addon installed:

```bash
composer update mapo-89/core-panel mapo-89/core-panel-tenancy
php artisan core-panel:update --force --with-addon-updates --no-interaction
```

`core-panel:update` stores backups of replaced managed files in `.core-panel-backups/`. Review the diff afterwards and deliberately merge any project-specific customizations into the new files. Unmanaged host files are not overwritten without an existing scaffold baseline.

### 3. Migrate The Image Variables

Replace the previous `PHP_IMAGE` and `NGINX_IMAGE` variables in `.env`, Portainer, or the relevant deployment configuration with one unified application image:

```dotenv
APP_IMAGE=registry.example.com/core-panel/app:1.5.0
UPDATER_IMAGE=registry.example.com/core-panel/system-updater:1.5.0
SYSTEM_UPDATER_RUNTIME_SERVICES=app,horizon,scheduler
```

Remove obsolete values for `PHP_IMAGE`, `NGINX_IMAGE`, and `PHP_UPSTREAM`. `UPDATER_IMAGE` remains separate because the system updater is not part of the application image.

For a registry deployment, the updater must use both Compose files:

```dotenv
SYSTEM_UPDATER_COMPOSE_FILES=docker-compose.prod.yml,docker-compose.registry.yml
```

Portainer can continue to use automatic detection for `SYSTEM_UPDATER_COMPOSE_FILES`. Make sure that `APP_IMAGE` and `UPDATER_IMAGE` are configured as stack variables.

### 4. Render The New Compose Configuration

The configuration must render successfully and must no longer contain an `nginx` service:

```bash
docker compose --env-file .env \
  -f docker-compose.prod.yml \
  -f docker-compose.registry.yml \
  config > /dev/null

docker compose --env-file .env \
  -f docker-compose.prod.yml \
  -f docker-compose.registry.yml \
  config --services
```

The service list must contain `app`, `horizon`, `scheduler`, and `system-updater`, but no separate `nginx` service. `app`, `horizon`, and `scheduler` must all resolve to the same `APP_IMAGE` value.

### 5. Pull The Images And Replace The Stack

Pull the new images first without changing the running containers:

```bash
docker compose --env-file .env \
  -f docker-compose.prod.yml \
  -f docker-compose.registry.yml \
  pull app horizon scheduler system-updater
```

Start the updated stack afterwards. `--remove-orphans` removes the old Nginx container, which is no longer defined:

```bash
docker compose --env-file .env \
  -f docker-compose.prod.yml \
  -f docker-compose.registry.yml \
  up -d --remove-orphans
```

Only `app` receives `RUN_MIGRATIONS=true` by default. Horizon and Scheduler therefore do not run migrations in parallel.

For Portainer, redeploy the updated stack using `docker-compose.portainer.yml` instead. The reverse proxy must then target port `8080` on the `app` service directly; a target named `nginx` no longer exists.

### 6. Verify The Upgrade

Run the following checks immediately after the replacement:

```bash
docker compose --env-file .env \
  -f docker-compose.prod.yml \
  -f docker-compose.registry.yml \
  ps

curl --fail --show-error http://127.0.0.1:8000/healthcheck

docker compose --env-file .env \
  -f docker-compose.prod.yml \
  -f docker-compose.registry.yml \
  exec app php artisan migrate:status

docker compose --env-file .env \
  -f docker-compose.prod.yml \
  -f docker-compose.registry.yml \
  exec app php artisan horizon:status
```

Also verify a normal Laravel page, an asset under `/build/assets/`, uploads or the storage link, and the external reverse proxy. `app`, `horizon`, and `scheduler` must be healthy and use the same image or digest.

### 7. Future Image Upgrades

For later releases, update `APP_IMAGE` and `UPDATER_IMAGE` to the new tags or digests, then repeat the render, pull, start, and verification steps. Separate PHP and Nginx tags are no longer required.

For a rollback, restore the backed-up Compose and `.env` files together with the previously recorded image tags, then start the previous Compose configuration again. Restore the database only if the upgrade ran migrations that are not backward compatible.

## Update

CorePanel is designed vendor-first where Laravel supports it:

- package config is loaded by default and only needs publishing when the host app wants to override it
- translations and Blade views are loaded from the package first and can be overridden through the normal Laravel vendor paths when needed
- `core-panel:update` keeps frontend overlays vendor-first by default and only refreshes host scaffolds plus explicit opt-in overrides

### What To Watch For In Existing Installations

If the application previously published CorePanel frontend directories such as `resources/js/components`, `resources/js/layouts`, `resources/js/composables`, `resources/js/plugins`, `resources/js/support`, `resources/js/types`, `resources/js/assets`, or `resources/js/theme/core-panel`, you have two options:

- if you want to keep the local overrides, leave them in place and do not force the migration
- if you want to move back to vendor-first wherever possible, run `php artisan core-panel:update` once

The default frontend migration is intentionally conservative:

- unchanged published CorePanel frontend files are removed from the host and resolved directly from `vendor` again
- locally modified published files stay in place
- with `--force`, even locally modified published files are removed after a backup so the vendor files take over again
- rebuild the frontend afterwards, at minimum with `npm run build` or `npm run dev`

### What To Watch For In Future Updates

Once an application has been migrated to vendor-first, the normal update flow becomes:

- update the package through Composer
- run `php artisan core-panel:update --force`
- rebuild the frontend

If you later publish `components` or `theme` again, the next `core-panel:update` will try to migrate those overlays back to vendor-first automatically.

Refresh published CorePanel assets after upgrading the package:

```bash
composer update mapo-89/core-panel
php artisan core-panel:update --force
```

If you want to migrate previously published CorePanel frontend overlays back to vendor assets, run:

```bash
php artisan core-panel:update
```

Use `--force` only when you intentionally want to remove local overlay changes after creating a backup.

If you also have optional addons installed:

```bash
composer update mapo-89/core-panel mapo-89/core-panel-tenancy
php artisan core-panel:update --force --with-addon-updates
```

For normal in-place updates, the command also runs outstanding migrations automatically after refreshing the published assets.
If you use `--base-path` to target a different application directory, migrations are skipped and must be run manually in that target application.
The update flow also synchronizes `.env` from `.env.example` in a template-first way: the `.env.example` structure is reused, existing values for known keys are preserved, new keys are added, and keys no longer present in `.env.example` are removed. Before an existing `.env` is rewritten, its previous contents are copied to `.env.backup`.
If your application owns the frontend version metadata itself, set `"managed_by_application": true` in `config/app-version.json`. In that case, `core-panel:update` will leave that file untouched, including `--force` updates.

If you want to run the environment synchronization on its own:

```bash
php artisan core-panel:env:sync
```

Use this when you want to refresh `.env` against the current `.env.example` without running the full package update.
If a `.env` already exists, the command writes its previous contents to `.env.backup` before applying the synchronized result.
Pass `--replace-template-values` only if existing values for template-managed keys should also be replaced by the current template defaults.

Typical update runbook for an existing installation:

```bash
composer update mapo-89/core-panel
php artisan core-panel:update --force
npm install
npm run build
php artisan optimize:clear
```

If the tenancy addon is installed, prefer:

```bash
composer update mapo-89/core-panel mapo-89/core-panel-tenancy
php artisan core-panel:update --force --with-addon-updates
```

If generated assets such as `resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`, `public/build`, or `public/hot` were previously committed, remove them from the Git index once after adopting the new `.gitignore`:

```bash
git rm -r --cached -- resources/js/actions resources/js/routes resources/js/wayfinder public/build public/hot
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

Publish only the parts the host application really needs to own:

- `config`: optional local overrides for `config/core-panel.php` and `config/core-panel-access.php`
- `lang`: optional overrides in `lang/vendor/core-panel`
- `views`: optional Blade overrides in `resources/views/vendor/core-panel`
- `components` and `theme`: mutable frontend overlays when the host app needs to customize shipped UI building blocks
- `stubs`: internal generator stubs for advanced customization

Normal package usage does not require publishing `lang` or `views`, because both are resolved vendor-first by Laravel.
Published `components` and `theme` overrides can be migrated back to package assets later with `php artisan core-panel:update`.
Bei Neuinstallationen solltest du `components` und `theme` nach Möglichkeit gar nicht publishen. Solange der Host keine lokalen Änderungen an diesen Bausteinen braucht, ist vendor-first der vorgesehene Standard.

```bash
php artisan core-panel:publish --tag=config
php artisan core-panel:publish --tag=lang
php artisan core-panel:publish --tag=components
php artisan core-panel:publish --tag=theme
php artisan core-panel:publish --tag=stubs
php artisan core-panel:publish --tag=views
```

## License

CorePanel is released under the [MIT license](./LICENSE.md).
