# Testing

CorePanel should be validated on two levels:

- package tests in this repository
- a fresh host-app smoke test

## Package tests

Run from the package repository:

```bash
vendor/bin/pest --compact
vendor/bin/pint --dirty --format agent
```

For targeted work, run the smallest relevant subset.

## Host-app smoke test

For `v1.0.0`, do not rely only on package tests. Also validate a fresh Laravel app from zero.

Recommended host flow:

```bash
composer create-project laravel/laravel core-panel-app
cd core-panel-app
composer require mapo-89/core-panel
php artisan core-panel:install
```

Then verify:

- central login
- `/admin`
- settings tabs
- profile tabs
- file upload
- social login

If tenancy is enabled, also verify:

- tenant creation
- tenant login
- tenant settings
- tenant branding
- tenant uploads
- tenant social login

## Docker-based host tests

When using the scaffolded Docker environment, run tests in the dedicated test container:

```bash
docker compose -f docker-compose.dev.yml exec app-test php artisan test --compact
```

## Release-style checklist

Use this for the full manual validation run:

- [release-v1-checklist.md](./release-v1-checklist.md)
