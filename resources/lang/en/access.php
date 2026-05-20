<?php

declare(strict_types=1);

return [
    'groups' => [
        'developer' => 'Developer',
        'system' => 'System',
        'users' => 'Users',
    ],
    'roles' => [
        'admin' => 'Admin',
        'super-admin' => 'System Admin',
        'user' => 'User',
    ],
    'resources' => [
        'activity-logs' => 'Activity logs',
        'api-docs' => 'API docs',
        'api-routes' => 'API routes',
        'authentication-logs' => 'Authentication logs',
        'dashboard' => 'Dashboard',
        'files' => 'Files',
        'forms' => 'Forms',
        'roles' => 'Roles',
        'settings' => 'Settings',
        'tenants' => 'Tenants',
        'user-groups' => 'User groups',
        'users' => 'Users',
        'api' => [
            'tokens' => 'API tokens',
        ],
        'oauth' => [
            'clients' => 'OAuth clients',
        ],
    ],
    'abilities' => [
        'create' => 'Create',
        'delete' => 'Delete',
        'export' => 'Export',
        'import' => 'Import',
        'reinvite' => 'Re-invite',
        'update' => 'Update',
        'upload' => 'Upload',
        'view' => 'View',
        'view-horizon' => 'View Horizon',
    ],
];
