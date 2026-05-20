<?php

declare(strict_types=1);

namespace CorePanel\Domains\Permission\DTOs;

use CorePanel\Support\Permissions\CorePanelAccess;
use Illuminate\Database\Eloquent\Model;

final readonly class PermissionData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $group,
        public string $label,
        public string $guardName,
    ) {}

    /**
     * @return array{id:string,name:string,group:string,label:string,guardName:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'group' => $this->group,
            'label' => $this->label,
            'guardName' => $this->guardName,
        ];
    }

    public static function fromModel(Model $permission, ?CorePanelAccess $access = null): self
    {
        $access ??= app(CorePanelAccess::class);
        $name = (string) $permission->getAttribute('name');
        $group = $access->permissionGroup($name);
        $label = $access->permissionLabel($name);

        return new self(
            id: (string) $permission->getKey(),
            name: $name,
            group: $group,
            label: $label,
            guardName: (string) $permission->getAttribute('guard_name'),
        );
    }
}
