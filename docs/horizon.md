# Horizon

CorePanel supports optional Horizon integration.

## Queue names

- `default`
- `mail`
- `imports`
- `exports`
- `tenants`
- `media`

## Included behavior

- Horizon dashboard authorization
- queue supervisor config scaffold
- snapshot scheduling
- optional long-wait notifications
- tenant-aware job context restoration

## Enable Horizon

Set the package config accordingly, then publish or scaffold the Horizon-related files through the installer.
