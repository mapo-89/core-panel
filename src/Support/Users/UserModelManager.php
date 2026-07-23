<?php

declare(strict_types=1);

namespace CorePanel\Support\Users;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UserModelManager
{
    /**
     * @return class-string<Model&Authenticatable>
     */
    public function modelClass(): string
    {
        /** @var class-string<Model&Authenticatable> $modelClass */
        $modelClass = (string) config('core-panel.user_model', config('auth.providers.users.model'));

        return $modelClass;
    }

    /**
     * @return Model&Authenticatable
     */
    public function newModel(): Model
    {
        $modelClass = $this->modelClass();

        return new $modelClass;
    }

    /**
     * @return Builder<Model>
     */
    public function query(bool $withTrashed = false): Builder
    {
        $modelClass = $this->modelClass();
        $query = $modelClass::query();

        if ($withTrashed && $this->supportsSoftDeletes() && method_exists($query, 'withTrashed')) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * @return Builder<Model>
     */
    public function visibleQuery(bool $withTrashed = false): Builder
    {
        return $this->query($withTrashed);
    }

    /**
     * @return Model&Authenticatable
     */
    public function findOrFail(int|string $userId, bool $withTrashed = false): Model
    {
        $user = $this->query($withTrashed)->with($this->relations())->findOrFail($userId);

        if (! $user instanceof Authenticatable) {
            throw new RuntimeException(sprintf(
                'Configured user model [%s] must implement [%s].',
                $user::class,
                Authenticatable::class,
            ));
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        $relations = [];

        if ($this->supportsRoles() && method_exists($this->newModel(), 'roles')) {
            $relations[] = 'roles';
        }

        if ($this->supportsUserGroups()) {
            $relations[] = 'userGroups';
        }

        return $relations;
    }

    public function supportsSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass()), true);
    }

    public function supportsRoles(): bool
    {
        return method_exists($this->newModel(), 'syncRoles');
    }

    public function supportsMedia(): bool
    {
        return interface_exists(HasMedia::class) && is_subclass_of($this->modelClass(), HasMedia::class);
    }

    public function supportsUserGroups(): bool
    {
        return method_exists($this->newModel(), 'userGroups');
    }

    public function supportsLocale(): bool
    {
        $model = $this->newModel();

        if (method_exists($model, 'supportsCorePanelLocale')) {
            return (bool) $model->supportsCorePanelLocale();
        }

        return $this->hasColumn('locale');
    }

    public function supportsStatus(): bool
    {
        $model = $this->newModel();

        if (method_exists($model, 'supportsCorePanelStatus')) {
            return (bool) $model->supportsCorePanelStatus();
        }

        return $this->hasColumn('status');
    }

    /**
     * @return array{first_name:string,last_name:string}
     */
    public function splitName(string $fullName): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($fullName)) ?? '';

        if ($normalized === '') {
            return [
                'first_name' => '',
                'last_name' => '',
            ];
        }

        $segments = explode(' ', $normalized);
        $firstName = (string) array_shift($segments);

        return [
            'first_name' => $firstName,
            'last_name' => implode(' ', $segments),
        ];
    }

    public function composeDisplayName(?string $firstName, ?string $lastName): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $firstName),
            trim((string) $lastName),
        ], static fn (?string $value): bool => $value !== null && $value !== '')));
    }

    public function supportsEmailVerification(): bool
    {
        return $this->hasColumn('email_verified_at');
    }

    public function supportsTwoFactor(Model $user): bool
    {
        return method_exists($user, 'hasEnabledTwoFactorAuthentication');
    }

    public function isSuperAdmin(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin')) {
            return (bool) $user->isSuperAdmin();
        }

        if (method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole('super-admin');
        }

        return false;
    }

    public function modelIsSuperAdmin(Model $user): bool
    {
        if (method_exists($user, 'isSuperAdmin')) {
            return (bool) $user->isSuperAdmin();
        }

        if (method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole('super-admin');
        }

        return in_array('super-admin', $this->roleNames($user), true);
    }

    public function activeSuperAdminCount(?Model $exclude = null): int
    {
        if (! $this->supportsRoles() || ! method_exists($this->newModel(), 'roles')) {
            return 0;
        }

        $userModel = $this->newModel();
        $tableNames = config('permission.table_names', []);
        $columnNames = config('permission.column_names', []);
        $roleModelClass = (string) config('permission.models.role');
        $modelHasRolesTable = (string) ($tableNames['model_has_roles'] ?? 'model_has_roles');
        $rolesTable = $roleModelClass !== '' && class_exists($roleModelClass)
            ? (new $roleModelClass)->getTable()
            : (string) ($tableNames['roles'] ?? 'roles');
        $rolePivotColumn = trim((string) ($columnNames['role_pivot_key'] ?? '')) ?: 'role_id';
        $modelMorphKey = trim((string) ($columnNames['model_morph_key'] ?? '')) ?: 'model_id';

        $superAdminIds = DB::connection($userModel->getConnectionName())
            ->table($modelHasRolesTable)
            ->join($rolesTable, $rolesTable.'.id', '=', $modelHasRolesTable.'.'.$rolePivotColumn)
            ->where($modelHasRolesTable.'.model_type', $userModel->getMorphClass())
            ->where($rolesTable.'.name', 'super-admin')
            ->distinct()
            ->pluck($modelHasRolesTable.'.'.$modelMorphKey)
            ->map(static fn (mixed $value): string => (string) $value)
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        if ($superAdminIds === []) {
            return 0;
        }

        $query = $this->query()->whereIn($userModel->getQualifiedKeyName(), $superAdminIds);

        if ($exclude !== null) {
            $query->whereKeyNot($exclude->getKey());
        }

        return (int) $query->count();
    }

    public function canDeleteManagedUser(Authenticatable $actor, Model $target): bool
    {
        if (! $this->modelIsSuperAdmin($target)) {
            return true;
        }

        if (! $this->isSuperAdmin($actor)) {
            return false;
        }

        if ((string) $actor->getAuthIdentifier() === (string) $target->getKey()) {
            return false;
        }

        return $this->activeSuperAdminCount($target) >= 1;
    }

    public function avatarUrl(Model $user): ?string
    {
        if (! method_exists($user, 'getFirstMediaUrl')) {
            return null;
        }

        $media = method_exists($user, 'getFirstMedia')
            ? $user->getFirstMedia('avatars')
            : null;
        $url = (string) $user->getFirstMediaUrl('avatars');

        if ($url === '') {
            return null;
        }

        return $this->normalizeLocalMediaUrl($url, $media);
    }

    public function presenceStatus(Model $user): string
    {
        if (method_exists($user, 'corePanelPresenceStatus')) {
            return $this->normalizePresenceStatus($user->corePanelPresenceStatus()) ?? 'offline';
        }

        return $this->normalizePresenceStatus($user->getAttribute('presence_status')) ?? 'offline';
    }

    public function presenceLastSeenAt(Model $user): ?int
    {
        if (method_exists($user, 'corePanelPresenceLastSeenAt')) {
            $lastSeenAt = $user->corePanelPresenceLastSeenAt();

            if (is_int($lastSeenAt) || ctype_digit((string) $lastSeenAt)) {
                return (int) $lastSeenAt;
            }
        }

        return null;
    }

    public function status(Model $user): string
    {
        if (method_exists($user, 'corePanelUserStatus')) {
            return $this->normalizeUserStatus($user->corePanelUserStatus()) ?? 'active';
        }

        if ($this->supportsStatus()) {
            return $this->normalizeUserStatus($user->getAttribute('status')) ?? 'active';
        }

        return $user->getAttribute('deleted_at') === null ? 'active' : 'inactive';
    }

    /**
     * @return list<string>
     */
    public function roleNames(Model $user): array
    {
        if ($user->relationLoaded('roles')) {
            /** @var list<string> $roles */
            $roles = $user->getRelation('roles')->pluck('name')->map(static fn (mixed $value): string => (string) $value)->values()->all();

            return $roles;
        }

        if (method_exists($user, 'getRoleNames')) {
            /** @var list<string> $roles */
            $roles = $user->getRoleNames()->map(static fn (mixed $value): string => (string) $value)->values()->all();

            return $roles;
        }

        return [];
    }

    public function primaryRole(?Authenticatable $user): ?string
    {
        if (! $user instanceof Model) {
            return null;
        }

        $roles = $this->roleNames($user);

        if (in_array('super-admin', $roles, true)) {
            return 'super-admin';
        }

        return $roles[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(?Authenticatable $user): array
    {
        if (! $user instanceof Model) {
            return [];
        }

        if (method_exists($user, 'getAllPermissions')) {
            /** @var list<string> $permissions */
            $permissions = $user->getAllPermissions()
                ->pluck('name')
                ->map(static fn (mixed $value): string => (string) $value)
                ->unique()
                ->values()
                ->all();

            return $permissions;
        }

        return [];
    }

    public function hasColumn(string $column): bool
    {
        $model = $this->newModel();

        try {
            return Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizePresenceStatus(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return match ($value) {
            'online', 'away', 'offline' => $value,
            default => null,
        };
    }

    private function normalizeUserStatus(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return match ($value) {
            'active', 'inactive', 'blocked' => $value,
            default => null,
        };
    }

    private function normalizeLocalMediaUrl(string $url, mixed $media): string
    {
        if (! $media instanceof Media) {
            return $url;
        }

        $driver = config(sprintf('filesystems.disks.%s.driver', $media->disk));

        if ($driver !== 'local') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return $url;
        }

        $request = request();
        $suffix = $this->buildUrlSuffix($url);

        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($host) || $host === '' || ! is_string($scheme) || $scheme === '') {
            return $path.$suffix;
        }

        $urlRoot = sprintf(
            '%s://%s%s',
            $scheme,
            $host,
            is_int($port) ? ':'.$port : '',
        );

        if ($urlRoot === $request->getSchemeAndHttpHost()) {
            return $url;
        }

        return $path.$suffix;
    }

    private function buildUrlSuffix(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return ($query !== null && $query !== '' ? '?'.$query : '')
            .($fragment !== null && $fragment !== '' ? '#'.$fragment : '');
    }

    /**
     * @return array{
     *     supportsLocale:bool,
     *     supportsMedia:bool,
     *     supportsRoles:bool,
     *     supportsStatus:bool,
     *     supportsSoftDeletes:bool
     * }
     */
    public function capabilities(): array
    {
        return [
            'supportsLocale' => $this->supportsLocale(),
            'supportsMedia' => $this->supportsMedia(),
            'supportsRoles' => $this->supportsRoles(),
            'supportsStatus' => $this->supportsStatus(),
            'supportsSoftDeletes' => $this->supportsSoftDeletes(),
        ];
    }

    public function defaultPassword(): string
    {
        return Str::password(24);
    }
}
