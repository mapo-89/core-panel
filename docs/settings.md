# Settings

CorePanel provides a database-backed settings system with group-based validation and tenant overrides.

## Groups

- general
- auth
- api
- oauth
- mail
- storage
- files
- tenancy
- appearance
- i18n
- security
- ui

## Features

- cached reads
- typed values
- localized values
- public settings for Inertia share
- tenant overrides

## Example

```php
$value = app(\CorePanel\Support\Settings\SettingsRepository::class)
    ->get('ui', 'dark_mode_default', true);
```

## UI-related settings

- dark mode default
- primary color token
- radius token
- layout density
