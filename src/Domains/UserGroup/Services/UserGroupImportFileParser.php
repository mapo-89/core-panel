<?php

declare(strict_types=1);

namespace CorePanel\Domains\UserGroup\Services;

use InvalidArgumentException;

final class UserGroupImportFileParser
{
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
    public function parse(string $path, ?string $originalExtension = null): array
    {
        $extension = mb_strtolower($originalExtension ?: (string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => $this->parseCsv($path),
            'sql' => $this->parseSql($path),
            default => throw new InvalidArgumentException('Only CSV, TXT and SQL files are supported.'),
        };
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
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("The file [{$path}] could not be opened.");
        }

        $rows = [];
        $header = null;
        $delimiter = $this->detectCsvDelimiter($path);

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $data = array_map(
                static fn (?string $value): ?string => $value === null ? null : trim($value),
                $data,
            );

            if ($data === [null] || $data === ['']) {
                continue;
            }

            if ($header === null && $this->looksLikeHeader($data)) {
                $header = array_map(
                    static fn (?string $value): string => mb_strtolower((string) $value),
                    $data,
                );

                continue;
            }

            $mapped = $header !== null
                ? $this->mapCsvRowWithHeader($header, $data)
                : $this->mapCsvRowWithoutHeader($data);

            if ($mapped !== null) {
                $rows[] = $mapped;
            }
        }

        fclose($handle);

        return $rows;
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
    private function parseSql(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new InvalidArgumentException("The file [{$path}] could not be read.");
        }

        preg_match_all(
            '/INSERT\s+INTO\s+[`"]?(?:user_groups|groups)[`"]?\s*\((?<columns>[^)]+)\)\s*VALUES\s*(?<values>.*?);/is',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $rows = [];

        foreach ($matches as $match) {
            $columns = array_map(
                static fn (string $column): string => trim($column, " \t\n\r\0\x0B`\""),
                explode(',', (string) $match['columns']),
            );

            foreach ($this->splitSqlValueTuples((string) $match['values']) as $tuple) {
                $values = str_getcsv($tuple, ',', "'", '\\');
                $record = [];

                foreach ($columns as $index => $column) {
                    $record[$column] = $values[$index] ?? null;
                }

                $mapped = $this->mapSqlRecord($record);

                if ($mapped !== null) {
                    $rows[] = $mapped;
                }
            }
        }

        return $rows;
    }

    private function detectCsvDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');
        $firstLine = $handle === false ? '' : (string) fgets($handle);

        if ($handle !== false) {
            fclose($handle);
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * @param  list<?string>  $row
     */
    private function looksLikeHeader(array $row): bool
    {
        $normalized = array_map(
            static fn (?string $value): string => mb_strtolower((string) $value),
            $row,
        );

        return collect($normalized)->contains(
            static fn (string $value): bool => in_array($value, [
                'id',
                'name',
                'group',
                'group_name',
                'group_color',
                'user_group',
                'color',
                'created_at',
                'updated_at',
                'deleted_at',
            ], true),
        );
    }

    /**
     * @param  list<string>  $header
     * @param  list<?string>  $row
     * @return array{
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
     * }|null
     */
    private function mapCsvRowWithHeader(array $header, array $row): ?array
    {
        $record = [];

        foreach ($header as $index => $column) {
            $record[$column] = $row[$index] ?? null;
        }

        return $this->normalizeImportedRow(
            id: $record['id'] ?? null,
            hasId: array_key_exists('id', $record),
            name: $record['name'] ?? $record['group_name'] ?? $record['group'] ?? $record['user_group'] ?? null,
            color: $record['color'] ?? $record['group_color'] ?? null,
            createdAt: $record['created_at'] ?? null,
            hasCreatedAt: array_key_exists('created_at', $record),
            updatedAt: $record['updated_at'] ?? null,
            hasUpdatedAt: array_key_exists('updated_at', $record),
            deletedAt: $record['deleted_at'] ?? null,
            hasDeletedAt: array_key_exists('deleted_at', $record),
        );
    }

    /**
     * @param  list<?string>  $row
     * @return array{
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
     * }|null
     */
    private function mapCsvRowWithoutHeader(array $row): ?array
    {
        return $this->normalizeImportedRow(
            id: null,
            hasId: false,
            name: $row[0] ?? null,
            color: $row[1] ?? null,
            createdAt: null,
            hasCreatedAt: false,
            updatedAt: null,
            hasUpdatedAt: false,
            deletedAt: null,
            hasDeletedAt: false,
        );
    }

    /**
     * @param  array<string, string|null>  $record
     * @return array{
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
     * }|null
     */
    private function mapSqlRecord(array $record): ?array
    {
        return $this->normalizeImportedRow(
            id: $record['id'] ?? null,
            hasId: array_key_exists('id', $record),
            name: $record['name'] ?? $record['group_name'] ?? null,
            color: $record['color'] ?? $record['group_color'] ?? null,
            createdAt: $record['created_at'] ?? null,
            hasCreatedAt: array_key_exists('created_at', $record),
            updatedAt: $record['updated_at'] ?? null,
            hasUpdatedAt: array_key_exists('updated_at', $record),
            deletedAt: $record['deleted_at'] ?? null,
            hasDeletedAt: array_key_exists('deleted_at', $record),
        );
    }

    /**
     * @return list<string>
     */
    private function splitSqlValueTuples(string $values): array
    {
        preg_match_all('/\((.*?)\)/s', $values, $matches);

        /** @var list<string> $tuples */
        $tuples = $matches[1];

        return $tuples;
    }

    /**
     * @return array{
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
     * }|null
     */
    private function normalizeImportedRow(
        mixed $id,
        bool $hasId,
        mixed $name,
        mixed $color,
        mixed $createdAt,
        bool $hasCreatedAt,
        mixed $updatedAt,
        bool $hasUpdatedAt,
        mixed $deletedAt,
        bool $hasDeletedAt,
    ): ?array {
        $normalizedName = trim((string) $name);

        if ($normalizedName === '') {
            return null;
        }

        $normalizedColor = $this->normalizeColor($color);

        return [
            'id' => is_numeric($id) ? (int) $id : null,
            'has_id' => $hasId,
            'name' => $normalizedName,
            'color' => $normalizedColor,
            'created_at' => $this->normalizeNullableString($createdAt),
            'has_created_at' => $hasCreatedAt,
            'updated_at' => $this->normalizeNullableString($updatedAt),
            'has_updated_at' => $hasUpdatedAt,
            'deleted_at' => $this->normalizeNullableString($deletedAt),
            'has_deleted_at' => $hasDeletedAt,
        ];
    }

    private function normalizeColor(mixed $value): string
    {
        $color = strtoupper(trim((string) $value));

        if ($color === '') {
            return '#6366F1';
        }

        if (! str_starts_with($color, '#')) {
            $color = '#'.$color;
        }

        return preg_match('/^#[A-F0-9]{6}$/', $color) === 1 ? $color : '#6366F1';
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' || strtoupper($normalized) === 'NULL' ? null : $normalized;
    }
}
