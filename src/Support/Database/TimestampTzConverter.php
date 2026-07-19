<?php

declare(strict_types=1);

namespace CorePanel\Support\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TimestampTzConverter
{
    /**
     * @return array{tables_scanned:int, columns_scanned:int, converted:list<array{table:string,column:string}>, pending:list<array{table:string,column:string}>, skipped:list<array{table:string,column:string,reason:string}>}
     */
    public function convertDataset(string $dataset, ?string $connectionName = null, bool $dryRun = false): array
    {
        /** @var array<string, list<string>> $tableColumns */
        $tableColumns = (array) config("core-panel.database.timestamp_tz_conversion.datasets.{$dataset}", []);

        $connection = $connectionName !== null ? DB::connection($connectionName) : DB::connection();
        $schema = $connectionName !== null ? Schema::connection($connectionName) : Schema::connection($connection->getName());

        $result = [
            'tables_scanned' => 0,
            'columns_scanned' => 0,
            'converted' => [],
            'pending' => [],
            'skipped' => [],
        ];

        if ($tableColumns === [] || $connection->getDriverName() !== 'pgsql') {
            return $result;
        }

        $legacyTimezone = (string) config('core-panel.database.timestamp_tz_conversion.legacy_timezone', 'Europe/Berlin');
        foreach ($tableColumns as $table => $columns) {
            $result['tables_scanned']++;

            if (! $schema->hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $result['columns_scanned']++;

                if (! $schema->hasColumn($table, $column)) {
                    continue;
                }

                $columnType = $this->columnType($connection, $table, $column);

                if ($columnType === null) {
                    $result['skipped'][] = [
                        'table' => $table,
                        'column' => $column,
                        'reason' => 'unknown_type',
                    ];

                    continue;
                }

                if ($columnType !== 'timestamp without time zone') {
                    $result['skipped'][] = [
                        'table' => $table,
                        'column' => $column,
                        'reason' => $columnType,
                    ];

                    continue;
                }

                $result[$dryRun ? 'pending' : 'converted'][] = [
                    'table' => $table,
                    'column' => $column,
                ];

                if ($dryRun) {
                    continue;
                }

                $connection->statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE timestamptz USING %s AT TIME ZONE %s',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($column),
                    $this->quoteIdentifier($column),
                    $connection->getPdo()->quote($legacyTimezone),
                ));
            }
        }

        return $result;
    }

    private function columnType(ConnectionInterface $connection, string $table, string $column): ?string
    {
        $result = $connection->selectOne(
            'SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ? LIMIT 1',
            [$table, $column],
        );

        return is_object($result) && isset($result->data_type) && is_string($result->data_type)
            ? $result->data_type
            : null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
