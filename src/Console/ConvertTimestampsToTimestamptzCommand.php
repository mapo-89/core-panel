<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Support\Database\TimestampTzConverter;
use Illuminate\Console\Command;

final class ConvertTimestampsToTimestamptzCommand extends Command
{
    protected $signature = 'core-panel:convert-timestamps-tz
        {--database= : Database connection for the central dataset. Defaults to the current default connection}
        {--dry-run : Only report columns that would be converted}
        {--force : Run without confirmation}';

    protected $description = 'Convert legacy PostgreSQL timestamp columns in the central database from local timestamps to UTC-based timestamptz columns.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core-panel:convert-timestamps-to-timestamptz'];

    public function handle(TimestampTzConverter $converter): int
    {
        if (! $this->shouldProceed()) {
            $this->components->warn('Timestamp conversion aborted.');

            return self::INVALID;
        }

        $this->renderSection('central', $converter->convertDataset('central', $this->databaseConnection(), $this->dryRun()));

        return self::SUCCESS;
    }

    private function shouldProceed(): bool
    {
        if ($this->dryRun() || (bool) $this->option('force')) {
            return true;
        }

        return $this->components->confirm(
            sprintf(
                'Convert legacy timestamp columns for [central] using source timezone [%s] and store them as timestamptz instants? ',
                (string) config('core-panel.database.timestamp_tz_conversion.legacy_timezone', 'Europe/Berlin'),
            ),
            false,
        );
    }

    private function dryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }

    private function databaseConnection(): ?string
    {
        $database = $this->option('database');

        if (is_string($database) && trim($database) !== '') {
            return trim($database);
        }

        $defaultConnection = config('database.default', 'pgsql');

        return is_string($defaultConnection) && trim($defaultConnection) !== ''
            ? trim($defaultConnection)
            : null;
    }

    /**
     * @param  array{tables_scanned:int, columns_scanned:int, converted:list<array{table:string,column:string}>, pending:list<array{table:string,column:string}>, skipped:list<array{table:string,column:string,reason:string}>}  $result
     */
    private function renderSection(string $scope, array $result, ?string $label = null): void
    {
        $this->newLine();
        $this->components->info($label === null ? strtoupper($scope) : strtoupper($scope).' ['.$label.']');
        $this->components->twoColumnDetail('Tables scanned', (string) $result['tables_scanned']);
        $this->components->twoColumnDetail('Columns scanned', (string) $result['columns_scanned']);
        $this->components->twoColumnDetail(
            $this->dryRun() ? 'Would convert' : 'Converted',
            (string) count($this->dryRun() ? $result['pending'] : $result['converted']),
        );

        $rows = $this->dryRun() ? $result['pending'] : $result['converted'];

        if ($rows === []) {
            return;
        }

        $this->table(
            ['Table', 'Column'],
            array_map(
                static fn (array $row): array => [$row['table'], $row['column']],
                $rows,
            ),
        );
    }
}
