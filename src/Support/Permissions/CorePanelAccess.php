<?php

declare(strict_types=1);

namespace CorePanel\Support\Permissions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class CorePanelAccess
{
    /**
     * @return list<string>
     */
    public function managedPermissions(): array
    {
        $permissions = [];

        foreach ($this->resources() as $resource => $abilities) {
            foreach ($abilities ?? $this->defaultAbilities() as $ability) {
                $permissions[] = $this->permissionName($resource, $ability);
            }
        }

        foreach ($this->customPermissions() as $permission) {
            $permissions[] = $permission;
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return array<string, array{group:string,label:array<string, string>,permissions:list<string>|string,protected:bool}>
     */
    public function defaultRoles(): array
    {
        /** @var array<string, array{group?:string,label?:array<string, string>,permissions?:list<string>|string,protected?:bool}> $legacyRoles */
        $legacyRoles = (array) config('core-panel-access.roles', []);
        /** @var array<string, list<string>|string|bool> $rolePermissions */
        $rolePermissions = (array) config('core-panel-access.role_permissions', []);
        /** @var array<string, string> $roleGroups */
        $roleGroups = (array) config('core-panel-access.role_groups', []);
        /** @var array<string, array<string, string>> $displayRoleNames */
        $displayRoleNames = (array) config('core-panel-access.display_names.roles', []);
        $displayRoleNames = $displayRoleNames !== [] ? $displayRoleNames : (array) config('core-panel-access.labels.roles', []);
        $normalized = [];

        foreach ($rolePermissions as $name => $permissions) {
            $roleLabelTranslations = array_filter(
                (array) ($displayRoleNames[$name] ?? []),
                static fn (mixed $value): bool => trim((string) $value) !== '',
            );

            $normalized[$name] = [
                'group' => (string) ($roleGroups[$name] ?? 'system'),
                'label' => $roleLabelTranslations,
                'permissions' => $permissions,
                'protected' => false,
            ];
        }

        foreach ($legacyRoles as $name => $role) {
            if (! isset($normalized[$name])) {
                $normalized[$name] = [
                    'group' => (string) ($role['group'] ?? 'system'),
                    'label' => array_filter(
                        (array) ($role['label'] ?? []),
                        static fn (mixed $value): bool => trim((string) $value) !== '',
                    ),
                    'permissions' => $role['permissions'] ?? [],
                    'protected' => (bool) ($role['protected'] ?? false),
                ];

                continue;
            }

            $normalized[$name]['group'] = (string) ($role['group'] ?? $normalized[$name]['group']);
            $normalized[$name]['protected'] = (bool) ($role['protected'] ?? $normalized[$name]['protected']);

            $legacyLabel = array_filter(
                (array) ($role['label'] ?? []),
                static fn (mixed $value): bool => trim((string) $value) !== '',
            );

            if ($legacyLabel !== []) {
                $normalized[$name]['label'] = $legacyLabel;
            }

            if (! isset($rolePermissions[$name])) {
                $normalized[$name]['permissions'] = $role['permissions'] ?? [];
            }
        }

        foreach ($normalized as $name => $role) {
            $normalized[$name]['permissions'] = $this->normalizeRolePermissions($role['permissions']);
        }

        if (isset($normalized['super-admin'])) {
            $normalized['super-admin']['protected'] = true;
            $normalized['super-admin']['group'] = (string) ($normalized['super-admin']['group'] ?: 'system');
        }

        if (isset($normalized['admin'])) {
            $normalized['admin']['protected'] = (bool) $normalized['admin']['protected'];
            $normalized['admin']['group'] = (string) ($normalized['admin']['group'] ?: 'system');
        }

        if (isset($normalized['user'])) {
            $normalized['user']['group'] = (string) ($normalized['user']['group'] ?: 'other');
        }

        return $normalized;
    }

    /**
     * @param  list<string>|string|bool|null  $permissions
     * @return list<string>|string
     */
    private function normalizeRolePermissions(mixed $permissions): array|string
    {
        if ($permissions === '*' || $permissions === false) {
            return '*';
        }

        if (! is_array($permissions)) {
            return [];
        }

        /** @var list<string> $allowed */
        $allowed = array_values(array_filter(
            array_map('strval', $permissions),
            static fn (string $permission): bool => trim($permission) !== '',
        ));

        return $allowed;
    }

    /**
     * @return list<string>
     */
    public function protectedRoles(): array
    {
        return array_keys(array_filter(
            $this->defaultRoles(),
            static fn (array $role): bool => $role['protected'] === true,
        ));
    }

    /**
     * @return list<string>
     */
    public function rolePermissions(string $roleName): array
    {
        $role = $this->defaultRoles()[$roleName] ?? null;

        if ($role === null) {
            return [];
        }

        if ($role['permissions'] === '*') {
            return $this->managedPermissions();
        }

        /** @var list<string> $permissions */
        $permissions = (array) $role['permissions'];

        return $permissions;
    }

    /**
     * @return array<string, string>
     */
    public function groupLabels(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        /** @var array<string, array<string, string>> $labels */
        $labels = $this->displayNamesFor('groups');
        $groupKeys = array_values(array_unique([
            ...array_keys($this->groups()),
            ...array_map(
                static fn (array $role): string => $role['group'],
                $this->defaultRoles(),
            ),
            'other',
        ]));

        $resolved = [];

        foreach ($groupKeys as $group) {
            if ($group === '') {
                continue;
            }

            $translations = (array) ($labels[$group] ?? []);
            $resolved[$group] = $this->resolveLocalizedLabel($translations, 'groups', $group, $locale);
        }

        return $resolved;
    }

    public function permissionGroup(string $permissionName): string
    {
        $resource = $this->permissionResource($permissionName);

        foreach ($this->groups() as $group => $resources) {
            if (in_array($resource, $resources, true)) {
                return $group;
            }
        }

        return 'other';
    }

    public function permissionLabel(string $permissionName, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $resource = $this->permissionResource($permissionName);
        $ability = $this->permissionAbility($permissionName);
        $resourceLabel = $this->resourceLabel($resource, $locale);
        $abilityLabel = $this->abilityLabel($ability, $locale);

        return "{$resourceLabel} - {$abilityLabel}";
    }

    public function roleLabel(string $roleName, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $role = $this->defaultRoles()[$roleName] ?? null;

        if ($role === null) {
            return Str::of($roleName)->replace(['_', '-'], ' ')->headline()->toString();
        }

        $labels = (array) config('core-panel-access.display_names.roles', []);
        if ($labels === []) {
            $labels = (array) config('core-panel-access.labels.roles', []);
        }

        return $this->resolveLocalizedLabel($labels[$roleName] ?? [], 'roles', $roleName, $locale);
    }

    public function resourceLabel(string $resource, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        /** @var array<string, array<string, string>> $labels */
        $labels = $this->displayNamesFor('resources');
        $translations = (array) Arr::get($labels, $resource, []);

        return $this->resolveLocalizedLabel($translations, 'resources', $resource, $locale);
    }

    public function abilityLabel(string $ability, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        /** @var array<string, array<string, string>> $labels */
        $labels = $this->displayNamesFor('abilities');
        $translations = (array) Arr::get($labels, $ability, []);

        return $this->resolveLocalizedLabel($translations, 'abilities', $ability, $locale);
    }

    public function permissionResource(string $permissionName): string
    {
        $abilities = $this->allKnownAbilities();

        foreach ($abilities as $ability) {
            $suffix = '.'.$ability;

            if (str_ends_with($permissionName, $suffix)) {
                return Str::beforeLast($permissionName, $suffix);
            }
        }

        return Str::beforeLast($permissionName, '.');
    }

    public function permissionAbility(string $permissionName): string
    {
        $abilities = $this->allKnownAbilities();

        foreach ($abilities as $ability) {
            if (str_ends_with($permissionName, '.'.$ability)) {
                return $ability;
            }
        }

        return Str::afterLast($permissionName, '.');
    }

    private function permissionName(string $resource, string $ability): string
    {
        return "{$resource}.{$ability}";
    }

    /**
     * @return array<string, list<string>|null>
     */
    private function resources(): array
    {
        /** @var array<string, mixed> $resources */
        $resources = (array) config('core-panel-access.resources', []);
        /** @var array<string, array<string, mixed>> $matrix */
        $matrix = (array) config('core-panel-access.permission_matrix', []);
        /** @var array<string, mixed> $subResources */
        $subResources = (array) config('core-panel-access.sub_resources', []);

        foreach ($matrix as $entry) {
            /** @var array<string, mixed> $entry */
            $entryResources = $entry['resources'] ?? [];
            /** @var list<string> $entryAbilities */
            $entryAbilities = (array) ($entry['abilities'] ?? []);
            $normalizedAbilities = $this->normalizeAbilitySet($entryAbilities);

            foreach ($entryResources as $resource) {
                $resource = trim((string) $resource);
                if ($resource === '') {
                    continue;
                }

                $resources[$resource] = $normalizedAbilities;
            }
        }

        foreach ($subResources as $parent => $subResourceMap) {
            $parent = trim((string) $parent);
            if ($parent === '') {
                continue;
            }

            if (! is_array($subResourceMap)) {
                continue;
            }

            foreach ($subResourceMap as $type => $abilities) {
                $type = trim((string) $type);
                if ($type === '') {
                    continue;
                }

                $resource = $parent.':'.$type;
                $resources[$resource] = $this->normalizeAbilitySet($abilities);
            }
        }

        $filteredResources = [];
        foreach ($resources as $resource => $abilities) {
            if (! is_array($abilities) && $abilities !== null) {
                continue;
            }

            $filteredResources[$resource] = $abilities;
        }

        return $filteredResources;
    }

    /**
     * @return list<string>
     */
    private function customPermissions(): array
    {
        /** @var list<string> $permissions */
        $permissions = array_values(array_filter(
            (array) config('core-panel-access.custom_permissions', []),
            static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
        ));

        return $permissions;
    }

    /**
     * @return array<string, list<string>>
     */
    private function groups(): array
    {
        /** @var array<string, list<string>> $groups */
        $groups = (array) config('core-panel-access.permission_groups', []);

        if ($groups === []) {
            $groups = (array) config('core-panel-access.groups', []);
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function defaultAbilities(): array
    {
        $abilities = config('core-panel-access.default_abilities', null);

        if (is_array($abilities) && $abilities !== []) {
            return $this->normalizeAbilitySet(array_filter(
                $abilities,
                static fn (mixed $ability): bool => trim((string) $ability) !== '',
            ));
        }

        return $this->normalizeAbilitySet(['view', 'create', 'update', 'delete', 'import', 'export']);
    }

    /**
     * @return list<string>
     */
    private function allKnownAbilities(): array
    {
        /** @var array<string, array<string, string>> $displayLabels */
        $displayLabels = (array) $this->displayNamesFor('abilities');
        $resourceAbilities = [];

        foreach ($this->resources() as $abilities) {
            if ($abilities === null) {
                continue;
            }

            $resourceAbilities = array_merge($resourceAbilities, $abilities);
        }

        foreach ($this->defaultRoles() as $role) {
            if ($role['permissions'] === '*') {
                continue;
            }

            $resourceAbilities = array_merge(
                $resourceAbilities,
                array_map(
                    static fn (string $permission): string => (string) Str::of($permission)->afterLast('.'),
                    (array) $role['permissions'],
                ),
            );
        }

        return array_values(array_unique([
            ...array_keys($displayLabels),
            ...$this->normalizeAbilitySet($resourceAbilities),
            ...$this->normalizeAbilitySet($this->defaultAbilities()),
            ...$this->normalizeAbilitySet(['switch', 'upload', 'view-horizon']),
        ]));
    }

    /**
     * @param  array<int, mixed>|string  $abilities
     * @return list<string>
     */
    private function normalizeAbilitySet(mixed $abilities): array
    {
        if (! is_array($abilities)) {
            $abilities = [$abilities];
        }

        $normalized = [];

        foreach ($abilities as $ability) {
            if (! is_string($ability)) {
                continue;
            }

            $ability = trim($ability);
            if ($ability === '') {
                continue;
            }

            if ($ability === 'read') {
                $normalized[] = 'view';

                continue;
            }

            if ($ability === 'read-horizon') {
                $normalized[] = 'view-horizon';

                continue;
            }

            $normalized[] = $ability;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function displayNamesFor(string $scope): array
    {
        /** @var array<string, array<string, string>> $displayNames */
        $displayNames = (array) config("core-panel-access.display_names.$scope", []);
        if ($displayNames !== []) {
            return $displayNames;
        }

        return (array) config("core-panel-access.labels.$scope", []);
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function resolveLocalizedLabel(array $translations, string $scope, string $fallback, string $locale): string
    {
        $fallbackLocale = (string) config('app.fallback_locale', 'en');

        $localized = Arr::get($translations, $locale);
        if (is_string($localized) && trim($localized) !== '') {
            return $localized;
        }

        $localized = Arr::get($translations, $fallbackLocale);
        if (is_string($localized) && trim($localized) !== '') {
            return $localized;
        }

        $fromTranslations = trans("access.$scope.$fallback", locale: $locale);
        if (is_string($fromTranslations) && trim($fromTranslations) !== '' && $fromTranslations !== "access.$scope.$fallback") {
            return $fromTranslations;
        }

        $fromFallbackTranslations = trans("access.$scope.$fallback", locale: $fallbackLocale);
        if (is_string($fromFallbackTranslations) && trim($fromFallbackTranslations) !== '' && $fromFallbackTranslations !== "access.$scope.$fallback") {
            return $fromFallbackTranslations;
        }

        return collect($translations)->first(fn (mixed $value): bool => trim((string) $value) !== '')
            ?? Str::of($fallback)->replace(['_', '-', '.'], ' ')->headline()->toString();
    }
}
