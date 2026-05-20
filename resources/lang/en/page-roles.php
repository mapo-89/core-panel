<?php

declare(strict_types=1);

return [
    'assign' => 'Assign roles',
    'assign_description' => 'Sync roles for a user inside the current access context.',
    'default_permissions' => 'Default Permissions',
    'default_permissions_description' => 'These managed permissions are generated from the CorePanel access configuration.',
    'delete_permission_header' => 'Delete permission',
    'delete_permission_message' => 'Delete permission ":name"?',
    'delete_role_header' => 'Delete role',
    'delete_role_message' => 'Delete role ":name"?',
    'description' => 'Manage role assignments, grouped permissions, and access boundaries.',
    'edit_permission' => 'Edit permission',
    'edit_role' => 'Edit role',
    'managed_access' => 'Managed Access',
    'managed_access_description' => 'Default roles and permissions are synchronized from the package access matrix.',
    'managed_role' => 'Managed',
    'matrix' => 'Permission Matrix',
    'matrix_description' => 'Permissions are grouped by resource to keep scanning costs low.',
    'new_permission' => 'New permission',
    'new_role' => 'New role',
    'permissions_create' => 'Create permission',
    'permissions_description' => 'Grouped labels are delivered from the backend translation layer.',
    'permissions_save' => 'Save permission',
    'permissions' => [
        'api' => [
            'tokens' => [
                'create' => 'Create API tokens',
                'delete' => 'Delete API tokens',
                'view' => 'View API tokens',
            ],
        ],
        'core-panel' => [
            'view-horizon' => 'View Horizon dashboard',
        ],
        'created' => 'Permission created.',
        'deleted' => 'Permission deleted.',
        'files' => [
            'delete' => 'Delete files',
            'upload' => 'Upload files',
            'view' => 'View files',
        ],
        'forms' => [
            'create' => 'Create forms',
            'delete' => 'Delete forms',
            'update' => 'Update forms',
            'view' => 'View forms',
        ],
        'oauth' => [
            'clients' => [
                'create' => 'Create OAuth clients',
                'delete' => 'Delete OAuth clients',
                'update' => 'Update OAuth clients',
                'view' => 'View OAuth clients',
            ],
        ],
        'roles' => [
            'create' => 'Create roles',
            'delete' => 'Delete roles',
            'update' => 'Update roles',
            'view' => 'View roles',
        ],
        'settings' => [
            'update' => 'Update settings',
            'view' => 'View settings',
        ],
        'updated' => 'Permission updated.',
        'users' => [
            'create' => 'Create users',
            'delete' => 'Delete users',
            'update' => 'Update users',
            'view' => 'View users',
        ],
    ],
    'resync' => 'Resync access',
    'resync_success' => 'Managed roles and permissions have been resynchronized.',
    'role_permission_count' => ':count managed permissions',
    'roles_create' => 'Create role',
    'roles_save' => 'Save role',
    'roles' => [
        'created' => 'Role created.',
        'deleted' => 'Role deleted.',
        'permissions_updated' => 'Role permissions updated.',
        'resynced' => 'Managed roles and permissions synchronized.',
        'updated' => 'Role updated.',
    ],
];
