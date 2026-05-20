# Activity Logs

CorePanel integrates activity logging around high-value admin actions.

## Typical logged subjects

- users
- roles
- permissions
- settings
- files
- forms
- tenants
- API tokens
- OAuth clients

## Features

- filterable index
- detail view
- JSON diff rendering
- cleanup command
- tenant-aware filtering

## Cleanup

```bash
php artisan core-panel:clean-activity-logs --days=30
```

Use your actual registered command name if you changed it in the host application.
