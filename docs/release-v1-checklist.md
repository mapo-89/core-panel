# v1.0.0 Release Checklist

Use this checklist for the first real package release. The goal is not just green package tests. The goal is a clean install from zero and a working host app.

## 1. Start from zero

Create a fresh Laravel app:

```bash
composer create-project laravel/laravel core-panel-app
cd core-panel-app
```

For local package development:

```bash
composer config repositories.core-panel '{"type":"path","url":"/home/manue/projects/packages/core-panel","options":{"symlink":true}}'
composer require mapo-89/core-panel:dev-main
```

For a real release validation, replace `dev-main` with the release candidate tag or branch you actually plan to publish.

## 2. Run the installer

```bash
php artisan core-panel:install
```

Recommended answers:

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

If tenancy should be part of the release, answer `yes` to tenancy installation and set:

- central domain: `core-panel-app.test`

## 3. Verify installation output

The install run should complete without:

- Composer dependency errors
- database creation errors
- migration failures
- seeder failures
- Wayfinder generation failures
- Vite build failures

## 4. Central app smoke test

Verify the central domain:

- `/login` renders
- admin login works
- `/admin` renders
- profile works
- settings tabs render
- translations load
- no CSP errors or cross-domain frontend calls
- file upload works
- branding image loads

## 5. Social login smoke test

If Microsoft login is part of the release:

- button appears when enabled in settings
- redirect and callback work
- first login with unknown email creates a user
- created user is redirected to password setup when required
- existing email auto-links
- master-provider email mismatch shows the modal
- confirm / cancel flow behaves correctly

## 6. Tenancy smoke test

If tenancy is included:

- create tenant from the central app
- tenant migrations and seeders run
- tenant login works
- tenant admin is not logged into the central session by mistake
- tenant settings render
- tenant branding loads
- tenant file upload works
- tenant social login works
- central-only tenant CRUD is not exposed on tenant routes

## 7. Package test run

From the package repo:

```bash
vendor/bin/pest --compact
vendor/bin/pint --dirty --format agent
```

## 8. Final release decision

Only tag `v1.0.0` when all of these are true:

- fresh install succeeds
- central smoke test succeeds
- tenancy smoke test succeeds if tenancy is part of the release
- social login succeeds for the configured provider flow
- package tests are green
- docs match the actual installer and package split

## 9. If the run is dirty

If the host app being tested has an older experimental schema, do not try to infer release readiness from it. Reset it:

```bash
php artisan migrate:fresh --seed
```

This matters especially now that the baseline schema uses UUID users and consolidated initial migrations.
