# Architecture

CorePanel uses a package-first structure with a scaffolded Laravel host application.

## Package split

- `mapo-89/core-panel`: core runtime, scaffold, UI, auth, settings, files, forms
- `mapo-89/core-panel-tenancy`: optional tenant-aware extension

Tenant-aware behavior should live in the addon, not in the core package, whenever that boundary can be kept clean.

## Main layers

- `src/`: package runtime code
- `stubs/`: host application scaffold and published frontend files
- `packages/core-panel-tenancy/`: optional tenancy package
- `tests/`: package tests

## Backend conventions

- actions for writes
- DTOs for transport
- form requests for validation
- policies and gates for authorization
- support services for reusable package behavior

## Frontend conventions

- Inertia v3
- Vue 3 with TypeScript
- PrimeVue components
- Wayfinder-generated route trees
- stable local route-helper wrappers so the core UI does not depend directly on tenancy-specific Wayfinder paths

## Builders

- FormBuilder
- TableBuilder
- TabBuilder

These builders produce backend schemas that are rendered in the host frontend.

## Tenancy boundary

The addon owns:

- central / universal / tenant route split
- tenant-aware route helper overrides
- tenant settings/controller overrides
- tenant asset handling
- tenant-specific UI overlays
