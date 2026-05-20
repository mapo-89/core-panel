# Installation

This is the entry point for installing CorePanel correctly on the current `v1.0.0` baseline.

## Requirements

- PHP 8.5
- Laravel 13
- Node.js 22+
- npm
- Redis
- PostgreSQL or MySQL

Optional:

- Docker
- tenancy addon

## Choose the right path

- Existing Laravel app: [package-installation.md](./package-installation.md)
- Fresh Laravel app: [create-project.md](./create-project.md)
- Full zero-to-release smoke test: [release-v1-checklist.md](./release-v1-checklist.md)

## Current installer behavior

Install CorePanel with:

```bash
composer require mapo-89/core-panel
php artisan core-panel:install
```

The installer handles:

- host scaffold sync
- `.env` sync
- `APP_URL` capture
- database preparation
- app key generation
- config publishing
- migration execution
- permission and settings seeding
- optional admin creation
- Wayfinder generation
- optional frontend install and build
- optional tenancy addon install

If tenancy is enabled, the installer also captures the central domain and passes it through to the tenancy addon environment setup.

## Database note

CorePanel now ships with a cleaned initial schema. If you are testing against an old prototype app or an older local database, do not assume additive migrations will repair it.

For a clean validation run, use a fresh application or:

```bash
php artisan migrate:fresh --seed
```

## PostgreSQL note

When using `pgsql`, the installer can prepare the target database before migrations run. The configured database user still needs permission to create databases on the PostgreSQL server.

## Next steps

- [auth.md](./auth.md)
- [testing.md](./testing.md)
- [updating.md](./updating.md)
