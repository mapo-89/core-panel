# Generators

CorePanel includes generator commands for common package and host-app workflows.

## Commands

```bash
php artisan core-panel:make-domain Users
php artisan core-panel:make-crud Product
php artisan core-panel:make-form ProductForm
php artisan core-panel:make-table ProductTable
php artisan core-panel:make-action CreateProduct
php artisan core-panel:make-dto ProductData
```

Short aliases:

```bash
php artisan core:make-domain Users
php artisan core:make-crud Product
```

## Typical output

- model
- migration
- controller
- requests
- actions
- DTOs
- FormBuilder schema
- TableBuilder schema
- PrimeVue/Inertia pages
- tests
