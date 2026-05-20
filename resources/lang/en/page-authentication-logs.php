<?php

declare(strict_types=1);

return [
    'columns' => [
        'browser' => 'Browser',
        'device' => 'Device',
        'device_type' => 'Device type',
        'guard' => 'Guard',
        'ip_address' => 'IP address',
        'last_active_at' => 'Last active',
        'login_at' => 'Login',
        'logout_at' => 'Logout',
        'method' => 'Method',
        'platform' => 'Platform',
        'result' => 'Result',
        'user' => 'User',
    ],
    'description' => 'Review successful and failed sign-in activity across the panel.',
    'device_browser_on_platform' => ':browser on :platform',
    'detail_title' => 'Authentication details',
    'details_load_failed' => 'Authentication details could not be loaded.',
    'empty' => 'No authentication entries found.',
    'failed' => 'Failed',
    'filters' => [
        'date_from' => 'From',
        'date_to' => 'To',
        'guard' => 'Guard',
        'result' => 'Result',
        'search' => 'User, IP, browser',
        'user' => 'User',
    ],
    'methods' => [
        'form' => 'Login form',
        'socialite' => 'Social login',
        'socialite_provider' => 'Login via :provider',
    ],
    'properties' => 'Properties',
    'results' => [
        'expired' => 'Session expired',
        'failed' => 'Failed',
        'logout' => 'Signed out',
        'revoked' => 'Session revoked',
        'successful' => 'Active',
    ],
    'successful' => 'Successful',
    'title' => 'Authentication logs',
    'user_agent' => 'User agent',
];
