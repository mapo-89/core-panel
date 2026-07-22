<?php

declare(strict_types=1);

namespace CorePanel\Domain\UserGroup\Actions;

use CorePanel\Domain\UserGroup\Services\UserGroupImportFileParser;
use CorePanel\Support\UserGroups\UserGroupModelManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

final readonly class ImportUserGroupsAction
{
    public function __construct(
        private UserGroupImportFileParser $parser,
        private UserGroupModelManager $userGroups,
    ) {}

    /**
     * @return array{created:int,updated:int}
     */
    public function execute(UploadedFile $file): array
    {
        $rows = $this->parseRows($file);
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $userGroup = $this->findExistingUserGroup($row);
            $payload = [
                'color' => $row['color'],
                'name' => $row['name'],
            ];

            if ($row['has_id'] && $row['id'] !== null) {
                $payload['id'] = $row['id'];
            }

            if ($row['has_created_at']) {
                $payload['created_at'] = $row['created_at'];
            }

            if ($row['has_updated_at']) {
                $payload['updated_at'] = $row['updated_at'];
            }

            if ($row['has_deleted_at']) {
                $payload['deleted_at'] = $row['deleted_at'];
            }

            if ($userGroup instanceof Model) {
                $this->persistUserGroup($userGroup, $payload);
                $updated++;

                continue;
            }

            $this->persistUserGroup($this->userGroups->newModel(), $payload);
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * @return array{
     *     create_count:int,
     *     has_more:bool,
     *     rows:list<array{action:'create'|'update',color:string,name:string}>,
     *     total_count:int,
     *     update_count:int
     * }
     */
    public function preview(UploadedFile $file): array
    {
        $rows = $this->parseRows($file);
        $modelClass = $this->userGroups->modelClass();

        $existingKeys = $modelClass::query()
            ->withTrashed()
            ->get(['id', 'name'])
            ->mapWithKeys(function (Model $userGroup): array {
                $keys = [];
                $keys[$this->buildIdentityKey((string) $userGroup->getKey())] = true;
                $keys[$this->buildIdentityKey((string) $userGroup->getAttribute('name'))] = true;

                return $keys;
            });

        $previewRows = collect($rows)->map(function (array $row) use ($existingKeys): array {
            $action = $this->hasExistingMatch($existingKeys, $row) ? 'update' : 'create';

            return [
                'action' => $action,
                'color' => $row['color'],
                'name' => $row['name'],
            ];
        })->values();

        return [
            'create_count' => $previewRows->where('action', 'create')->count(),
            'has_more' => $previewRows->count() > 8,
            'rows' => $previewRows->take(8)->all(),
            'total_count' => $previewRows->count(),
            'update_count' => $previewRows->where('action', 'update')->count(),
        ];
    }

    /**
     * @return list<array{
     *     id: ?int,
     *     has_id: bool,
     *     name: string,
     *     color: string,
     *     created_at: ?string,
     *     has_created_at: bool,
     *     updated_at: ?string,
     *     has_updated_at: bool,
     *     deleted_at: ?string,
     *     has_deleted_at: bool
     * }>
     */
    private function parseRows(UploadedFile $file): array
    {
        return $this->parser->parse(
            $file->getRealPath() ?: $file->path(),
            $file->getClientOriginalExtension(),
        );
    }

    /**
     * @param  array{id:?int,name:string}  $row
     */
    private function findExistingUserGroup(array $row): ?Model
    {
        $modelClass = $this->userGroups->modelClass();

        if ($row['id'] !== null) {
            $userGroup = $modelClass::query()->withTrashed()->find($row['id']);

            if ($userGroup instanceof Model) {
                return $userGroup;
            }
        }

        return $modelClass::query()
            ->withTrashed()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])
            ->first();
    }

    /**
     * @param  Collection<string, bool>  $existingKeys
     * @param  array{id:?int,name:string}  $row
     */
    private function hasExistingMatch(Collection $existingKeys, array $row): bool
    {
        return ($row['id'] !== null && $existingKeys->has($this->buildIdentityKey((string) $row['id'])))
            || $existingKeys->has($this->buildIdentityKey($row['name']));
    }

    /**
     * @param  array{
     *     name:string,
     *     color:string,
     *     id?:?int,
     *     created_at?:?string,
     *     updated_at?:?string,
     *     deleted_at?:?string
     * }  $payload
     */
    private function persistUserGroup(Model $userGroup, array $payload): void
    {
        $userGroup->timestamps = false;
        $userGroup->forceFill($payload);
        $userGroup->saveQuietly();
        $userGroup->timestamps = true;
    }

    private function buildIdentityKey(string $value): string
    {
        return mb_strtolower($value);
    }
}
