<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class DatabaseBackupTable
{
    /**
     * @param  Collection<int, DatabaseBackupFile>  $backups
     * @return array{backups: array<int, array<string, mixed>>, backupsTable: array<string, mixed>, summary: array<string, mixed>}
     */
    public function build(Request $request, Collection $backups): array
    {
        $defaultColumns = ['name', 'storage_locations', 'encrypted', 'source', 'created_at', 'size'];
        $search = trim((string) $request->query('search', ''));
        $source = $this->sourceFilter($request);
        $sort = $this->sort($request);

        $filteredBackups = $backups
            ->when($search !== '', fn (Collection $items): Collection => $items->filter(
                fn (DatabaseBackupFile $backup): bool => str_contains(Str::lower($backup->name), Str::lower($search))
                    || str_contains(Str::lower($backup->source()), Str::lower($search)),
            ))
            ->when($source !== '', fn (Collection $items): Collection => $items->filter(
                fn (DatabaseBackupFile $backup): bool => $backup->source() === $source,
            ));

        $sortedBackups = $filteredBackups
            ->sortBy(
                fn (DatabaseBackupFile $backup): int|string => $this->sortValue($backup, $sort),
                SORT_REGULAR,
                str_starts_with($sort, '-'),
            )
            ->values();

        $perPage = max(1, min(100, $request->integer('per_page', 10)));
        $total = $sortedBackups->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($lastPage, $request->integer('page', 1)));
        $pageBackups = $sortedBackups->forPage($page, $perPage)->values();

        return [
            'backups' => $pageBackups
                ->map(fn (DatabaseBackupFile $backup): array => $backup->toArray())
                ->all(),
            'backupsTable' => [
                'pagination' => [
                    'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
                    'lastPage' => $lastPage,
                    'page' => $page,
                    'perPage' => $perPage,
                    'to' => $total === 0 ? null : min($page * $perPage, $total),
                    'total' => $total,
                ],
                'state' => [
                    'filters' => [
                        'source' => $source,
                    ],
                    'search' => $search,
                    'sort' => $sort,
                    'visibleColumns' => $this->visibleColumns($request, $defaultColumns),
                ],
            ],
            'summary' => [
                'count' => $backups->count(),
                'latest' => $backups->first()?->toArray(),
                'total_size' => $backups->sum(fn (DatabaseBackupFile $backup): int => $backup->size),
            ],
        ];
    }

    private function sourceFilter(Request $request): string
    {
        $source = (string) $request->input('filter.source', '');

        return in_array($source, ['automatic', 'custom', 'imported', 'manual'], true)
            ? $source
            : '';
    }

    private function sort(Request $request): string
    {
        $sort = (string) $request->query('sort', '-created_at');
        $field = ltrim($sort, '-');

        if (! in_array($field, ['created_at', 'encrypted', 'name', 'size', 'source'], true)) {
            return '-created_at';
        }

        return str_starts_with($sort, '-') ? "-{$field}" : $field;
    }

    private function sortValue(DatabaseBackupFile $backup, string $sort): int|string
    {
        return match (ltrim($sort, '-')) {
            'name' => Str::lower($backup->name),
            'encrypted' => (int) $backup->encrypted,
            'size' => $backup->size,
            'source' => $backup->source(),
            default => $backup->createdAt->getTimestamp(),
        };
    }

    /**
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private function visibleColumns(Request $request, array $fallback): array
    {
        $columns = (string) $request->query('columns', '');

        if ($columns === '') {
            return $fallback;
        }

        $visibleColumns = collect(explode(',', $columns))
            ->filter(fn (string $column): bool => in_array($column, $fallback, true))
            ->values()
            ->all();

        return $visibleColumns === [] ? $fallback : $visibleColumns;
    }
}
