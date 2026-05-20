<?php

declare(strict_types=1);

return [

    'resources' => [
        'dashboard' => ['view'],
        'users' => null,
        'user-groups' => null,
        'roles' => ['create', 'view', 'update', 'delete'],
        'activity-logs' => ['view'],
        'authentication-logs' => ['view'],
        'settings' => ['view', 'update'],
        'api-routes' => ['view'],
        'files' => ['create', 'view', 'update', 'delete'],
    ],

    'sub_resources' => [
        // 'users' => [
        //     'student' => ['create', 'view', 'update', 'delete'],
        // ],
    ],

    'custom_permissions' => [
        'api-docs.view',
        'users.reinvite',
        'horizon.view',
    ],

    'permission_groups' => [
        'users' => ['users', 'user-groups', 'roles'],
        'system' => ['settings', 'files', 'activity-logs', 'authentication-logs'],
        'developer' => ['api-routes', 'api-docs'],
    ],

    'role_groups' => [
        'super-admin' => 'system',
        'admin' => 'system',
        'user' => 'system',
    ],

    'role_permissions' => [
        'super-admin' => '*',
        'admin' => [
            'users.create', 'users.view', 'users.update', 'users.delete',
            'user-groups.create', 'user-groups.view', 'user-groups.update', 'user-groups.delete',
            'users.reinvite',
            'roles.view',
            'dashboard.view',
            'activity-logs.view',
            'authentication-logs.view',
            'files.create', 'files.view', 'files.update', 'files.delete',
        ],
        'user' => [
            'dashboard.view',
        ],
    ],

    'ignored_route_names' => [
        'core-panel.dashboard',
    ],

    'route_permissions' => [
        'core-panel.activity.index' => null,
        'core-panel.activity.show' => 'activity-logs.view',
        'core-panel.authentication-logs.index' => null,
        'core-panel.authentication-logs.show' => 'authentication-logs.view',
        'core-panel.developer.index' => null,
        'core-panel.logs.index' => null,
        'core-panel.files.download' => 'files.view',
        'core-panel.files.store' => 'files.create',
        'core-panel.forms.submissions.export' => 'users.view',
        'core-panel.log-files.entries' => null,
        'core-panel.log-files.index' => null,
        'core-panel.roles.permissions.sync' => 'roles.update',
        'core-panel.roles.resync' => 'roles.update',
        'core-panel.user-groups.import' => 'user-groups.create',
        'core-panel.user-groups.preview' => 'user-groups.create',
        'core-panel.users.avatar.destroy' => 'users.update',
        'core-panel.users.avatar.store' => 'users.update',
        'core-panel.users.force-delete' => 'users.delete',
        'core-panel.users.roles.assign' => 'roles.update',
        'core-panel.users.sessions.destroy' => 'users.update',
    ],

    'roles' => [
        'super-admin' => [
            'group' => 'system',
            'permissions' => '*',
            'protected' => true,
        ],
        'admin' => [
            'group' => 'system',
            'permissions' => [
                'users.view', 'users.create', 'users.update', 'users.delete',
                'user-groups.view', 'user-groups.create', 'user-groups.update', 'user-groups.delete',
                'roles.view', 'roles.update',
                'settings.view', 'settings.update',
                'activity-logs.view', 'authentication-logs.view',
                'files.view', 'files.upload', 'files.delete',
                'forms.view', 'forms.create', 'forms.update', 'forms.delete',
                'api.tokens.view', 'api.tokens.create', 'api.tokens.delete',
                'oauth.clients.view', 'oauth.clients.create', 'oauth.clients.update', 'oauth.clients.delete',
            ],
            'protected' => true,
        ],
        'user' => [
            'group' => 'workspace',
            'permissions' => [
                'forms.view',
                'files.view',
            ],
            'protected' => true,
        ],
    ],
];
