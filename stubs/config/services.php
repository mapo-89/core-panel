<?php

declare(strict_types=1);

return [
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/auth/github/callback'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/auth/microsoft/callback'),
        'tenant' => env('MICROSOFT_TENANT', 'common'),
    ],

    'oidc' => [
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'issuer' => env('OIDC_ISSUER'),
        'redirect' => env('OIDC_REDIRECT_URI', '/auth/oidc/callback'),
        'claims' => [
            'avatar' => env('OIDC_CLAIM_AVATAR', 'picture'),
            'email' => env('OIDC_CLAIM_EMAIL', 'email'),
            'id' => env('OIDC_CLAIM_ID', 'sub'),
            'name' => env('OIDC_CLAIM_NAME', 'name'),
            'nickname' => env('OIDC_CLAIM_NICKNAME', 'preferred_username'),
        ],
    ],
];
