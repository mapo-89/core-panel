# Updating

Use this when you already have CorePanel installed and need to pull a newer version into an existing app.

## Standard update flow

```bash
composer update mapo-89/core-panel
php artisan core-panel:update --dry-run
php artisan core-panel:update --dry-run --breaking-changes
php artisan core-panel:update --force
php artisan core-panel:update --force --with-addon-updates
php artisan optimize:clear
php artisan wayfinder:generate
npm run build
```

If you maintain a tenancy installation too, use:

```bash
php artisan core-panel:tenancy:update --force
```

If you maintain both a core- and a tenancy test project locally, run:

```bash
composer test:update-test-projects
```

Set these variables to override defaults:

- `CORE_PANEL_CORE_PROJECT=/path/to/core-app`
- `CORE_PANEL_TENANCY_PROJECT=/path/to/tenancy-app`

For potentially breaking updates, use:

```bash
php artisan core-panel:update --force --breaking-changes --with-addon-updates
```

## Important note for pre-v1 local prototypes

Do not treat old experimental databases as upgrade-safe.

The initial schema has been consolidated. In particular:

- user IDs moved to UUID
- activity and media morph IDs were folded into the baseline
- old add/change migrations were removed in favor of clean initial migrations

If your local app predates that baseline, the safest update path is:

```bash
php artisan migrate:fresh --seed
```

Use a backup first if the environment contains data you need.

## Publish-only resync

If you need to reapply only published assets:

```bash
php artisan core-panel:publish --tag=config
php artisan core-panel:publish --tag=lang
php artisan core-panel:publish --tag=components
php artisan core-panel:publish --tag=theme
php artisan core-panel:publish --tag=stubs
```
