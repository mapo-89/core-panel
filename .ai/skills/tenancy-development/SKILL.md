---
name: tenancy-development
description: Use this skill for stancl/tenancy multi-tenancy work in this Laravel project. Activate when installing or configuring stancl/tenancy, setting up central and tenant routes, defining central domains, creating or updating the Tenant model, TenancyServiceProvider, config/tenancy.php, tenant provisioning flows, multi-database tenant connections, tenant migrations, tenant-aware tests, or central-vs-tenant application boundaries.
---

# Tenancy Development

Use this skill when the task touches `stancl/tenancy` or the app's central and tenant separation.

## First checks

1. Check the official `stancl/tenancy` docs first to confirm what the package supports and which approach is intended before designing a custom solution. Start with the v3 docs and follow into the relevant feature pages (for example: introduction, tenant identification, routes, multi-database tenancy, bootstrappers, integrations).
2. Use `search-docs` for Laravel/Inertia syntax and confirm the current `stancl/tenancy` approach from official docs when behavior is ambiguous.
3. Inspect these files before changing tenancy behavior:
   - `config/tenancy.php`
   - `app/Providers/TenancyServiceProvider.php`
   - `bootstrap/app.php`
   - `bootstrap/providers.php`
   - `routes/central.php`
   - `routes/central-api.php`
   - `routes/tenant.php`
   - `routes/tenant-api.php`
   - `app/Models/Tenant.php`
   - `config/database.php`
4. Check whether the requested change belongs to the central app, the tenant app, or both. Do not blur those boundaries by default.

## Working rules

- Before implementing tenancy behavior, verify in the official package docs whether the feature already exists, is intentionally unsupported, or has a recommended pattern. Prefer the documented package pattern over project-local improvisation unless the project has an intentional divergence.
- Keep central routes bound to `config('tenancy.central_domains')`.
- Keep tenant routes behind tenancy middleware such as `InitializeTenancyByDomain` and `PreventAccessFromCentralDomains`, unless tests intentionally need a simplified path.
- Treat `database/migrations` as central migrations and `database/migrations/tenant` as tenant migrations for the multi-database setup.
- Prefer separate central and tenant route entrypoints over reusing the same route file in both contexts, unless the duplication cost is trivial and intentional.
- Be careful with route names. The same named route should not exist in both central and tenant contexts unless that is explicitly intended and known to work.
- Keep the tenant model based on `Stancl\Tenancy\Database\Models\Tenant` and use `HasDatabase` / `HasDomains` when the app uses multi-db plus domain identification.
- When changing tenant provisioning, review the `TenantCreated` and `TenantDeleted` job pipeline in `app/Providers/TenancyServiceProvider.php`.
- When changing database behavior, verify `tenancy.database.central_connection`, tenant DB prefix/suffix, and the template tenant connection.
- When changing filesystem, queue, or cache behavior, review whether the tenancy bootstrappers should also change.

## Common tasks

### Central app changes

- Put central web behavior in `routes/central.php`.
- Put central API behavior in `routes/central-api.php`.
- Make sure the central domain list matches `.env` / `.env.example`.

### Tenant app changes

- Put tenant web behavior in `routes/tenant.php`.
- Put tenant API behavior in `routes/tenant-api.php`.
- If frontend tenant pages call backend controllers, also use `wayfinder-development`.

### Tenant provisioning

- Prefer an explicit command, action, or admin flow that:
  1. creates the tenant record,
  2. assigns one or more domains,
  3. lets the tenancy event pipeline create and migrate the tenant database.
- Keep central metadata in the central database only.

### Tests

- Use `pest-testing` whenever test files change.
- Confirm tests boot the schema they need. In this repo, tenant app tests may need tenant migrations in addition to the base test database setup.
- Prefer feature tests over ad-hoc scripts for tenant provisioning and route separation.

## Companion skills

- Use `laravel-best-practices` for Laravel PHP changes around tenancy actions, controllers, models, requests, and queries.
- Use `pest-testing` for tenancy-related test work.
- Use `wayfinder-development` for frontend-to-backend route wiring in tenant or central Inertia/Vue code.
- Use `inertia-vue-development` for tenant or central Inertia Vue pages.

## Done checklist

- Confirm whether the change affects central routes, tenant routes, or both.
- Confirm route registration still matches the intended domain boundary.
- Confirm migrations live in the correct central or tenant path.
- Run `vendor/bin/pint --dirty --format agent` after PHP edits.
- Run focused tests or `php artisan test --compact` when the tenancy change is covered by tests.
