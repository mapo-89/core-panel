<?php

declare(strict_types=1);

namespace CorePanel\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConvertMySqlDatetimesCommand extends Command
{
    protected $signature = 'core-panel:convert-mysql-datetimes
        {--database= : Database connection to convert; defaults to the active connection}
        {--dataset=central : Configured legacy datetime dataset to convert}
        {--from=UTC : Source timezone of the existing values}
        {--to= : Target timezone; defaults to APP_TIMEZONE}
        {--dry-run : Report affected values without changing data}
        {--force : Confirm the irreversible data conversion}';

    protected $description = 'Convert legacy UTC wall-clock values in CorePanel host database in MySQL datetime columns to the configured local timezone.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core-panel:convert-mysql-datetimes-central'];

    /**
     * Convert datetime values that were persisted as UTC wall-clock values before the host adopted APP_TIMEZONE.
     */
    public function handle(): int
    {
        $database = $this->option('database');
        $connection = DB::connection(is_string($database) && trim($database) !== '' ? trim($database) : null);
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mariadb', 'mysql'], true)) {
            $this->components->error(sprintf(
                'This command only supports MySQL-compatible databases; current driver: %s.',
                $driver,
            ));

            return self::FAILURE;
        }

        $sourceTimezone = (string) $this->option('from');
        $targetTimezone = (string) ($this->option('to') ?: config('app.timezone', 'UTC'));

        if (! $this->isValidTimezone($sourceTimezone) || ! $this->isValidTimezone($targetTimezone)) {
            $this->components->error('Both --from and --to must be valid IANA timezone identifiers.');

            return self::FAILURE;
        }

        if ($sourceTimezone === $targetTimezone) {
            $this->components->warn('Source and target timezone are identical; no conversion is necessary.');

            return self::SUCCESS;
        }

        $columns = $this->conversionColumns($connection, $this->timestampColumns($connection));

        try {
            $rows = $this->countAffectedRows($connection, $columns);

            $this->table(
                ['Table', 'Column', 'Rows'],
                array_map(
                    static fn (array $column): array => [$column['table'], $column['column'], $column['rows']],
                    $rows,
                ),
            );

            $affectedRows = array_sum(array_column($rows, 'rows'));

            if ($affectedRows === 0) {
                $this->components->info('No timestamp values require conversion.');

                return self::SUCCESS;
            }

            $sourceIssues = $this->sourceWallClockIssues($connection, $columns, $sourceTimezone);

            if ($sourceIssues !== []) {
                $this->components->error('Refusing to convert ambiguous or nonexistent source datetime values.');
                $this->table(
                    ['Table', 'Column', 'Source datetime', 'Issue'],
                    array_map(
                        static fn (array $issue): array => [
                            $issue['table'],
                            $issue['column'],
                            $issue['value'],
                            $issue['reason'],
                        ],
                        $sourceIssues,
                    ),
                );
                $this->components->warn('Resolve these wall-clock values to an unambiguous instant before retrying.');

                return self::FAILURE;
            }

            $collisions = $this->dstFallbackCollisions($connection, $columns, $sourceTimezone, $targetTimezone);

            if ($collisions !== []) {
                $this->components->error('Refusing to convert datetime values that collide during a daylight-saving fallback.');
                $this->table(
                    ['Table', 'Column', 'Local datetime', 'UTC values'],
                    array_map(
                        static fn (array $collision): array => [
                            $collision['table'],
                            $collision['column'],
                            $collision['value'],
                            implode(', ', $collision['sources']),
                        ],
                        $collisions,
                    ),
                );
                $this->components->warn('Use a timezone-aware column type to preserve both instants, or resolve these values before retrying.');

                return self::FAILURE;
            }

            if ((bool) $this->option('dry-run')) {
                $this->components->info(sprintf(
                    'Dry run: %d values would be converted from %s to %s.',
                    $affectedRows,
                    $sourceTimezone,
                    $targetTimezone,
                ));

                return self::SUCCESS;
            }

            if (! (bool) $this->option('force')) {
                $this->components->error('Refusing to modify timestamp data without --force. Run with --dry-run first and create a database backup.');

                return self::FAILURE;
            }

            $conflicts = $connection->transaction(function () use ($connection, $columns, $sourceTimezone, $targetTimezone): int {
                $conflicts = 0;

                foreach ($columns as $column) {
                    $conflicts += $this->convertColumn(
                        $connection,
                        $column['table'],
                        $column['column'],
                        $column['primaryKey'],
                        $column['snapshotTable'],
                        $sourceTimezone,
                        $targetTimezone,
                    );
                }

                if ($conflicts > 0) {
                    throw new \RuntimeException(sprintf(
                        'Conversion aborted because %d datetime value(s) changed after the snapshot was created. No values were converted.',
                        $conflicts,
                    ));
                }

                return $conflicts;
            });

            $this->components->info(sprintf(
                'Converted %d timestamp values from %s to %s.',
                $affectedRows,
                $sourceTimezone,
                $targetTimezone,
            ));

            return self::SUCCESS;
        } finally {
            $this->dropConversionSnapshots($connection, $columns);
        }
    }

    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }

    /**
     * @return list<array{table:string,column:string}>
     */
    private function timestampColumns(ConnectionInterface $connection): array
    {
        $conditions = [];
        $bindings = [];

        $dataset = $this->option('dataset');
        $dataset = is_string($dataset) && trim($dataset) !== '' ? trim($dataset) : 'central';

        foreach ((array) config("core-panel.database.mysql_datetime_conversion.datasets.{$dataset}", []) as $table => $tableColumns) {
            if (! is_string($table) || ! is_array($tableColumns)) {
                continue;
            }

            foreach ($tableColumns as $column) {
                if (! is_string($column)) {
                    continue;
                }

                $conditions[] = '(columns.table_name = ? and columns.column_name = ?)';
                $bindings[] = $table;
                $bindings[] = $column;
            }
        }

        if ($conditions === []) {
            return [];
        }

        /** @var list<object> $columns */
        $columns = $connection->select(
            "select columns.table_name, columns.column_name
             from information_schema.columns as columns
             inner join information_schema.tables as tables
                on tables.table_schema = columns.table_schema
               and tables.table_name = columns.table_name
             where columns.table_schema = database()
               and columns.data_type = 'datetime'
               and tables.table_type = 'BASE TABLE'
               and (".implode(' or ', $conditions).')
             order by columns.table_name, columns.ordinal_position',
            $bindings,
        );

        return array_map(fn (object $column): array => [
            'table' => $this->informationSchemaValue($column, 'table_name'),
            'column' => $this->informationSchemaValue($column, 'column_name'),
        ], $columns);
    }

    /**
     * @param  list<array{table:string,column:string,primaryKey:string,snapshotTable:string}>  $columns
     * @return list<array{table:string,column:string,primaryKey:string,snapshotTable:string,rows:int}>
     */
    private function countAffectedRows(ConnectionInterface $connection, array $columns): array
    {
        return array_map(function (array $column) use ($connection): array {
            $rows = $connection->table($column['snapshotTable'])->count();

            return [...$column, 'rows' => $rows];
        }, $columns);
    }

    private function convertColumn(
        ConnectionInterface $connection,
        string $table,
        string $column,
        string $primaryKey,
        string $snapshotTable,
        string $sourceTimezone,
        string $targetTimezone,
    ): int {
        $conflicts = 0;

        $connection->table($snapshotTable)
            ->select([$primaryKey, $column])
            ->orderBy($primaryKey)
            ->chunkById(500, function ($records) use ($connection, $table, $column, $primaryKey, $sourceTimezone, $targetTimezone, &$conflicts): void {
                foreach ($records as $record) {
                    $value = $record->{$column};

                    if (! is_string($value) || str_starts_with($value, '0000-00-00')) {
                        continue;
                    }

                    $convertedValue = $this->convertValue($value, $sourceTimezone, $targetTimezone);

                    if ($convertedValue === $value) {
                        continue;
                    }

                    $updated = $connection->table($table)
                        ->where($primaryKey, $record->{$primaryKey})
                        ->where($column, $value)
                        ->update([
                            $column => $convertedValue,
                        ]);

                    if ($updated === 0) {
                        $conflicts++;
                    }
                }
            }, $primaryKey);

        return $conflicts;
    }

    /**
     * Detect values that become indistinguishable as MySQL DATETIME during a DST fallback.
     *
     * @param  list<array{table:string,column:string,primaryKey:string,snapshotTable:string}>  $columns
     * @return list<array{table:string,column:string,value:string,sources:list<string>}>
     */
    private function dstFallbackCollisions(
        ConnectionInterface $connection,
        array $columns,
        string $sourceTimezone,
        string $targetTimezone,
    ): array {
        $collisions = [];

        foreach ($columns as $column) {
            /** @var array<string, array<string, true>> $sourcesByLocalValue */
            $sourcesByLocalValue = [];

            foreach ($connection->table($column['snapshotTable'])
                ->select($column['column'])
                ->cursor() as $record) {
                $source = $record->{$column['column']};

                if (! is_string($source) || str_starts_with($source, '0000-00-00')) {
                    continue;
                }

                $local = $this->convertValue($source, $sourceTimezone, $targetTimezone);

                if (! $this->isDstFallbackLocalValue($local, $targetTimezone)) {
                    continue;
                }

                $sourcesByLocalValue[$local][$source] = true;
            }

            foreach ($sourcesByLocalValue as $local => $sources) {
                if (count($sources) < 2) {
                    continue;
                }

                $collisions[] = [
                    'table' => $column['table'],
                    'column' => $column['column'],
                    'value' => $local,
                    'sources' => array_keys($sources),
                ];
            }
        }

        return $collisions;
    }

    /**
     * @param  list<array{table:string,column:string,primaryKey:string,snapshotTable:string}>  $columns
     * @return list<array{table:string,column:string,value:string,reason:string}>
     */
    private function sourceWallClockIssues(ConnectionInterface $connection, array $columns, string $sourceTimezone): array
    {
        $issues = [];

        foreach ($columns as $column) {
            /** @var array<string, string> $reasonsByValue */
            $reasonsByValue = [];

            foreach ($connection->table($column['snapshotTable'])->select($column['column'])->distinct()->cursor() as $record) {
                $value = $record->{$column['column']};

                if (! is_string($value) || str_starts_with($value, '0000-00-00')) {
                    continue;
                }

                $reason = $this->sourceWallClockIssue($value, $sourceTimezone);

                if ($reason !== null) {
                    $reasonsByValue[$value] = $reason;
                }
            }

            foreach ($reasonsByValue as $value => $reason) {
                $issues[] = [
                    'table' => $column['table'],
                    'column' => $column['column'],
                    'value' => $value,
                    'reason' => $reason,
                ];
            }
        }

        return $issues;
    }

    private function sourceWallClockIssue(string $value, string $timezone): ?string
    {
        $format = str_contains($value, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
        $parsed = CarbonImmutable::createFromFormat($format, $value, $timezone);

        if ($parsed->format($format) !== $value) {
            return 'Nonexistent during daylight-saving spring forward';
        }

        return $this->isDstFallbackLocalValue($value, $timezone)
            ? 'Ambiguous during daylight-saving fall back'
            : null;
    }

    /**
     * Materialize the primary keys and original values before updates begin, so concurrent
     * writes cannot be mistaken for legacy UTC values or overwritten by this conversion.
     *
     * @param  list<array{table:string,column:string}>  $columns
     * @return list<array{table:string,column:string,primaryKey:string,snapshotTable:string}>
     */
    private function conversionColumns(ConnectionInterface $connection, array $columns): array
    {
        $conversionColumns = [];

        foreach ($columns as $column) {
            $primaryKey = $this->primaryKey($connection, $column['table']);

            if ($primaryKey === null) {
                $this->components->warn(sprintf('Skipped %s.%s because the table has no single-column primary key.', $column['table'], $column['column']));

                continue;
            }

            $snapshotTable = 'core_panel_datetime_snapshot_'.Str::lower(Str::random(20));
            $grammar = $connection->table($column['table'])->getGrammar();
            $connection->statement(sprintf(
                'create temporary table %s as select %s, %s from %s where %s is not null',
                $grammar->wrapTable($snapshotTable),
                $grammar->wrap($primaryKey),
                $grammar->wrap($column['column']),
                $grammar->wrapTable($column['table']),
                $grammar->wrap($column['column']),
            ));
            $connection->statement(sprintf(
                'alter table %s add primary key (%s)',
                $grammar->wrapTable($snapshotTable),
                $grammar->wrap($primaryKey),
            ));

            $conversionColumns[] = [...$column, 'primaryKey' => $primaryKey, 'snapshotTable' => $snapshotTable];
        }

        return $conversionColumns;
    }

    /**
     * @param  list<array{snapshotTable:string}>  $columns
     */
    private function dropConversionSnapshots(ConnectionInterface $connection, array $columns): void
    {
        foreach ($columns as $column) {
            $grammar = $connection->table($column['snapshotTable'])->getGrammar();
            $connection->statement(sprintf('drop temporary table if exists %s', $grammar->wrapTable($column['snapshotTable'])));
        }
    }

    private function isDstFallbackLocalValue(string $value, string $timezone): bool
    {
        $format = str_contains($value, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
        $timestamp = CarbonImmutable::createFromFormat($format, $value, $timezone)->getTimestamp();
        $transitions = (new \DateTimeZone($timezone))->getTransitions($timestamp - 172_800, $timestamp + 172_800);
        $previousOffset = $transitions[0]['offset'] ?? null;

        foreach (array_slice($transitions, 1) as $transition) {
            $offset = $transition['offset'];

            if (! is_int($previousOffset) || $offset >= $previousOffset) {
                $previousOffset = $offset;

                continue;
            }

            $start = gmdate('Y-m-d H:i:s', $transition['ts'] + $offset);
            $end = gmdate('Y-m-d H:i:s', $transition['ts'] + $previousOffset);
            $wallClockValue = substr($value, 0, 19);

            if ($wallClockValue >= $start && $wallClockValue < $end) {
                return true;
            }

            $previousOffset = $offset;
        }

        return false;
    }

    private function primaryKey(ConnectionInterface $connection, string $table): ?string
    {
        /** @var list<object> $columns */
        $columns = $connection->select(
            "select column_name
             from information_schema.statistics
             where table_schema = database()
               and table_name = ?
               and index_name = 'PRIMARY'
             order by seq_in_index",
            [$table],
        );

        return count($columns) === 1
            ? $this->informationSchemaValue($columns[0], 'column_name')
            : null;
    }

    private function informationSchemaValue(object $row, string $key): string
    {
        $values = array_change_key_case(get_object_vars($row), CASE_LOWER);
        $value = $values[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new \UnexpectedValueException(sprintf(
                'The information_schema result did not contain the expected [%s] column.',
                $key,
            ));
        }

        return $value;
    }

    private function convertValue(string $value, string $sourceTimezone, string $targetTimezone): string
    {
        $format = str_contains($value, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';

        return CarbonImmutable::createFromFormat($format, $value, $sourceTimezone)
            ->setTimezone($targetTimezone)
            ->format($format);
    }
}
