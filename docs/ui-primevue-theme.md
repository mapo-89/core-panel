# UI: PrimeVue Theme

CorePanel uses PrimeVue with a package-provided theme preset and CSS variable layer.

## What is provided

- a PrimeVue preset
- light and dark token sets
- CSS variable application for runtime color mode
- host publish path for theme files

## Publish the theme

```bash
php artisan core-panel:publish --tag=theme
```

## Theme runtime

The UI bootstrap installs PrimeVue and applies the configured theme preset.

Theme state is driven by:

- stored color mode
- settings defaults
- CSS variables on `document.documentElement`

## Theme-related settings

Typical UI settings include:

- dark mode default
- primary color token
- radius token
- layout density

## Notes

- prefer theme tokens instead of hardcoded colors
- keep component-level styling minimal if the preset already covers the need
- publish the theme when you want host-level customization without editing vendor files
