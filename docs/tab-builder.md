# Tab Builder

CorePanel includes a simple tab schema builder and a PrimeVue tabs renderer.

## Backend API

```php
use CorePanel\Support\TabBuilder\Tab;
use CorePanel\Support\TabBuilder\Tabs;

$tabs = Tabs::make()->tabs([
    Tab::make('General')->icon('settings'),
    Tab::make('Security')->icon('shield')->lazy(),
]);
```

## Features

- translated labels
- icons
- badges
- lazy tabs
- visibility rules
- permission-aware tabs
- optional schema or component payloads
