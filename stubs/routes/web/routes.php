<?php

declare(strict_types=1);

return [
    'public' => [
        'platform.php',
        'forms.php',
    ],
    'authenticated_without_permission' => [
        'profile.php',
    ],
    'permission_protected' => [
        'dashboard.php',
        'admin.php',
    ],
];
