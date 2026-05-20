# Create Project

Use this when you want a fresh app and a clean `v1.0.0` validation path.

## Minimal flow

```bash
composer create-project laravel/laravel core-panel-app
cd core-panel-app
composer require mapo-89/core-panel
php artisan core-panel:install
```

## Local package development flow

```bash
composer create-project laravel/laravel core-panel-app
cd core-panel-app
composer config repositories.core-panel '{"type":"path","url":"/home/manue/projects/packages/core-panel","options":{"symlink":true}}'
composer require mapo-89/core-panel:dev-main
php artisan core-panel:install
```

If you install tenancy and the addon exists locally under the core package checkout, the installer can wire the local addon dependency automatically.

## Recommended first answers

- APP_URL: `https://core-panel-app.test`
- DB driver: `pgsql`
- DB host: `127.0.0.1`
- DB port: `5432`
- DB database: `core_panel`
- DB username: `core_panel`
- DB password: `core_panel`
- test DB: `core_panel_test`
- default locale: `de`
- fallback locale: `en`
- create admin: `yes`
- run migrations: `yes`
- run seeders: `yes`
- install frontend: `yes`
- install tenancy: only if you are validating the addon too
- central domain: `core-panel-app.test` when tenancy is enabled

## After installation

If you skipped the frontend step in the installer:

```bash
npm install
npm run build
```

Always finish with:

```bash
php artisan optimize:clear
```

## What to verify first

Central app:

- login page renders
- admin login works
- `/admin` renders without JS errors
- settings page renders
- profile page renders
- file upload works

If tenancy is enabled:

- create tenant
- open tenant domain
- tenant login works
- tenant branding loads
- tenant uploads work

## Full release-style check

- [release-v1-checklist.md](./release-v1-checklist.md)
