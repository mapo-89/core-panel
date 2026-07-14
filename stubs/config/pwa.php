<?php

declare(strict_types=1);

return [
    'install-button' => true,

    'manifest' => [
        'name' => (string) config('app.name', 'CorePanel'),
        'short_name' => (string) config('app.name', 'CorePanel'),
        'background_color' => '#0f172a',
        'display' => 'standalone',
        'description' => 'CorePanel Progressive Web App.',
        'theme_color' => '#1ab88f',
        'icons' => [
            [
                'src' => 'logo.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
    ],

    'debug' => env('APP_DEBUG', false),

    'livewire-app' => false,
];
