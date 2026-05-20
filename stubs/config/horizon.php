<?php

declare(strict_types=1);

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'core_panel_horizon:'),
    'middleware' => ['web'],
    'waits' => [
        'redis:default' => 60,
        'redis:exports' => 120,
        'redis:imports' => 120,
        'redis:mail' => 60,
        'redis:media' => 120,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'silenced' => [],
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'mail', 'imports', 'exports', 'media'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'balanceCooldown' => 3,
            'balanceMaxShift' => 1,
            'maxProcesses' => 5,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'minProcesses' => 1,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 2,
            ],
        ],
    ],
];
