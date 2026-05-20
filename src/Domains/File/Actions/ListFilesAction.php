<?php

declare(strict_types=1);

namespace CorePanel\Domains\File\Actions;

use CorePanel\Models\ManagedFile;
use CorePanel\Support\Files\FileModelManager;
use CorePanel\Support\Query\AllowedQuery;
use CorePanel\Support\Query\QueryBuilderAdapter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListFilesAction
{
    public function __construct(
        private FileModelManager $files,
        private QueryBuilderAdapter $queryBuilder,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ManagedFile>
     */
    public function execute(Request $request): LengthAwarePaginator
    {
        return $this->configuredQuery($request)->paginate(
            max(1, (int) $request->integer('per_page', 18))
        )->appends($request->query());
    }

    public function totalSize(Request $request): int
    {
        return (int) $this->configuredQuery($request)->toBase()->sum('size');
    }

    /**
     * @return QueryBuilder<ManagedFile>
     */
    private function configuredQuery(Request $request): QueryBuilder
    {
        return $this->queryBuilder
            ->allowed(
                AllowedQuery::make()
                    ->filters(['collection', 'folder_id', 'mime_type'])
                    ->sorts(['created_at', 'name', 'size'])
                    ->defaultSort('-created_at')
                    ->globalSearch(['name', 'mime_type'])
                    ->perPage(18, 100)
            )
            ->for($this->files->filesQuery(), $request);
    }
}
