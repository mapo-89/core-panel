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

New installations do not require any additional vendor-first migration:

- `core-panel:install` already configures the host so CorePanel frontend building blocks are loaded from `vendor` by default
- `resources/css/app.css` already contains the required Tailwind `@source` entries for `vendor/mapo-89/core-panel/resources/js`
- only publish `components` or `theme` if the host application really needs to own and customize those files locally

CorePanel also registers the short alias:

```bash
php artisan core:install
```

## Update

CorePanel is designed vendor-first where Laravel supports it:

- package config is loaded by default and only needs publishing when the host app wants to override it
- translations and Blade views are loaded from the package first and can be overridden through the normal Laravel vendor paths when needed
- only mutable frontend overlays and host scaffolds are refreshed through `core-panel:update`

### What To Watch For In Existing Installations

If the application previously published CorePanel frontend directories such as `resources/js/components`, `resources/js/layouts`, `resources/js/composables`, `resources/js/plugins`, `resources/js/support`, `resources/js/types`, `resources/js/assets`, or `resources/js/theme/core-panel`, you have two options:

- if you want to keep the local overrides, continue updating normally with `php artisan core-panel:update --force`
- if you want to move back to vendor-first wherever possible, run `php artisan core-panel:update --vendor-first` once

The `--vendor-first` migration is intentionally conservative:

- unchanged published CorePanel frontend files are removed from the host and resolved directly from `vendor` again
- locally modified published files stay in place
- with `--vendor-first --force`, even locally modified published files are removed after a backup so the vendor files take over again
- rebuild the frontend afterwards, at minimum with `npm run build` or `npm run dev`

### What To Watch For In Future Updates

Once an application has been migrated to vendor-first, you usually do not need to run `--vendor-first` again on every update.
The normal update flow becomes:

- update the package through Composer
- run `php artisan core-panel:update --force`
- rebuild the frontend

After that, `--vendor-first` is only needed again if you later publish `components` or `theme` and want to migrate those local overlays back to the vendor-managed state.

Refresh published CorePanel assets after upgrading the package:

```bash
composer update mapo-89/core-panel
php artisan core-panel:update --force
```

If you want to migrate previously published CorePanel frontend overlays back to vendor assets, run:

```bash
php artisan core-panel:update --vendor-first
```

Use `--force` only when you intentionally want to remove local overlay changes after creating a backup.

If you also have optional addons installed:

```bash
composer update mapo-89/core-panel mapo-89/core-panel-tenancy
php artisan core-panel:update --force --with-addon-updates
```

For normal in-place updates, the command also runs outstanding migrations automatically after refreshing the published assets.
If you use `--base-path` to target a different application directory, migrations are skipped and must be run manually in that target application.
If your application owns the frontend version metadata itself, set `"managed_by_application": true` in `config/app-version.json`. In that case, `core-panel:update` will leave that file untouched, including `--force` updates.

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
Published `components` and `theme` overrides can be migrated back to package assets later with `php artisan core-panel:update --vendor-first`.
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
