# Form Builder

CorePanel includes a backend FormBuilder and a PrimeVue-based renderer.

## Backend API

```php
use CorePanel\Support\FormBuilder\Form;
use CorePanel\Support\FormBuilder\Fields\EmailInput;
use CorePanel\Support\FormBuilder\Fields\TextInput;

$form = Form::make('user-form')->schema([
    TextInput::make('name')->label('Name')->required(),
    EmailInput::make('email')->label('Email')->required(),
]);
```

## Features

- JSON-serializable schema
- validation rule derivation
- translation-aware labels and messages
- conditional visibility
- disabled state
- repeaters and groups

## Frontend renderer

The Vue renderer maps the schema to PrimeVue inputs and keeps route handling out of the PHP builder.
