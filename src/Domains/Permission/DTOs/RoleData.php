<?php

declare(strict_types=1);

namespace CorePanel\Domains\Permission\DTOs;

use Illuminate\Database\Eloquent\Model;

final readonly class RoleData
{
    /**
     * @param  list<string>  $seededPermissions
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $group,
        public string $guardName,
        public array $permissions,
        public array $seededPermissions,
        public bool $isSuperAdmin,
        public bool $isProtected,
        public int $permissionsCount,
        public int $usersCount,
        public ?string $createdAt,
    ) {}

    /**
     * @return array{id:string,name:string,group:string,guardName:string,permissions:list<string>,seededPermissions:list<string>,isSuperAdmin:bool,isProtected:bool,permissionsCount:int,usersCount:int,createdAt:?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'group' => $this->group,
            'guardName' => $this->guardName,
            'permissions' => $this->permissions,
            'seededPermissions' => $this->seededPermissions,
            'isSuperAdmin' => $this->isSuperAdmin,
            'isProtected' => $this->isProtected,
            'permissionsCount' => $this->permissionsCount,
            'usersCount' => $this->usersCount,
            'createdAt' => $this->createdAt,
        ];
    }

    public static function fromModel(Model $role): self
    {
        $permissions = $role->relationLoaded('permissions')
            ? $role->getRelation('permissions')->pluck('name')->values()->all()
            : [];

        return new self(
            id: (string) $role->getKey(),
            name: (string) $role->getAttribute('name'),
            group: (string) ($role->getAttribute('core_panel_group') ?? 'system'),
            guardName: (string) $role->getAttribute('guard_name'),
            permissions: $permissions,
            seededPermissions: self::normalizeSeededPermissions($role->getAttribute('core_panel_seeded_permissions')),
            isSuperAdmin: (string) $role->getAttribute('name') === 'super-admin',
            isProtected: (bool) ($role->getAttribute('core_panel_is_protected') ?? false),
            permissionsCount: (int) ($role->getAttribute('permissions_count') ?? count($permissions)),
            usersCount: (int) ($role->getAttribute('users_count') ?? 0),
            createdAt: $role->getAttribute('created_at')?->toJSON(),
        );
    }

    /**
     * @return list<string>
     */
    private static function normalizeSeededPermissions(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $value),
            static fn (string $permission): bool => $permission !== '',
        ));
    }
}
