<?php

declare(strict_types=1);

return [
    'assign' => 'Rollen zuweisen',
    'assign_description' => 'Rollen für einen Benutzer im aktuellen Zugriffskontext synchronisieren.',
    'default_permissions' => 'Standardberechtigungen',
    'default_permissions_description' => 'Diese verwalteten Berechtigungen werden aus der CorePanel-Zugriffskonfiguration erzeugt.',
    'delete_permission_header' => 'Berechtigung löschen',
    'delete_permission_message' => 'Berechtigung ":name" löschen?',
    'delete_role_header' => 'Rolle löschen',
    'delete_role_message' => 'Rolle ":name" löschen?',
    'description' => 'Rollenzuweisungen, gruppierte Berechtigungen und Zugriffsgrenzen verwalten.',
    'edit_permission' => 'Berechtigung bearbeiten',
    'edit_role' => 'Rolle bearbeiten',
    'managed_access' => 'Verwalteter Zugriff',
    'managed_access_description' => 'Vordefinierte Rollen und Berechtigungen werden aus der Zugriffsmatrix des Packages synchronisiert.',
    'managed_role' => 'Verwaltet',
    'matrix' => 'Berechtigungsmatrix',
    'matrix_description' => 'Berechtigungen sind nach Ressourcen gruppiert, damit sie schnell erfassbar bleiben.',
    'new_permission' => 'Neue Berechtigung',
    'new_role' => 'Neue Rolle',
    'permissions_create' => 'Berechtigung erstellen',
    'permissions_description' => 'Gruppierte Labels kommen aus der Backend-Übersetzungsschicht.',
    'permissions_save' => 'Berechtigung speichern',
    'permissions' => [
        'api' => [
            'tokens' => [
                'create' => 'API-Tokens erstellen',
                'delete' => 'API-Tokens löschen',
                'view' => 'API-Tokens anzeigen',
            ],
        ],
        'core-panel' => [
            'view-horizon' => 'Horizon-Dashboard anzeigen',
        ],
        'created' => 'Berechtigung erstellt.',
        'deleted' => 'Berechtigung gelöscht.',
        'files' => [
            'delete' => 'Dateien löschen',
            'upload' => 'Dateien hochladen',
            'view' => 'Dateien anzeigen',
        ],
        'forms' => [
            'create' => 'Formulare erstellen',
            'delete' => 'Formulare löschen',
            'update' => 'Formulare aktualisieren',
            'view' => 'Formulare anzeigen',
        ],
        'oauth' => [
            'clients' => [
                'create' => 'OAuth-Clients erstellen',
                'delete' => 'OAuth-Clients löschen',
                'update' => 'OAuth-Clients aktualisieren',
                'view' => 'OAuth-Clients anzeigen',
            ],
        ],
        'roles' => [
            'create' => 'Rollen erstellen',
            'delete' => 'Rollen löschen',
            'update' => 'Rollen aktualisieren',
            'view' => 'Rollen anzeigen',
        ],
        'settings' => [
            'update' => 'Einstellungen aktualisieren',
            'view' => 'Einstellungen anzeigen',
        ],
        'updated' => 'Berechtigung aktualisiert.',
        'users' => [
            'create' => 'Benutzer erstellen',
            'delete' => 'Benutzer löschen',
            'update' => 'Benutzer aktualisieren',
            'view' => 'Benutzer anzeigen',
        ],
    ],
    'resync' => 'Zugriff neu synchronisieren',
    'resync_success' => 'Verwaltete Rollen und Berechtigungen wurden neu synchronisiert.',
    'role_permission_count' => ':count verwaltete Berechtigungen',
    'roles_create' => 'Rolle erstellen',
    'roles_save' => 'Rolle speichern',
    'roles' => [
        'created' => 'Rolle erstellt.',
        'deleted' => 'Rolle gelöscht.',
        'permissions_updated' => 'Rollenberechtigungen aktualisiert.',
        'resynced' => 'Verwaltete Rollen und Berechtigungen synchronisiert.',
        'updated' => 'Rolle aktualisiert.',
    ],
];
