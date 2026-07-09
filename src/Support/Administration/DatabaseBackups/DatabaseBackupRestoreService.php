<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

final readonly class DatabaseBackupRestoreService
{
    public function __construct(
        private DatabaseBackupEncryptor $encryptor,
        private DatabaseBackupService $backups,
        private DatabaseBackupSettings $settings,
    ) {}

    /**
     * @param  list<string>  $tables
     */
    public function restore(string $backup, string $mode, array $tables = []): void
    {
        $sourcePath = $this->backups->pathFor($backup);
        $restorePath = $sourcePath;
        $temporaryPath = null;

        if ($this->encryptor->isEncrypted($sourcePath)) {
            $temporaryPath = storage_path('framework/cache/database-restore-'.bin2hex(random_bytes(8)).'.dump');
            File::ensureDirectoryExists(dirname($temporaryPath));
            $this->encryptor->decryptFileWithCodes($sourcePath, $temporaryPath, $this->settings->encryptionCodes());
            $restorePath = $temporaryPath;
        }

        try {
            $this->runRestore($restorePath, $mode === 'tables' ? $tables : []);
        } finally {
            if ($temporaryPath !== null && File::isFile($temporaryPath)) {
                File::delete($temporaryPath);
            }
        }
    }

    /**
     * @return list<array{dependencies: list<string>, label: string, value: string}>
     */
    public function tableOptions(): array
    {
        if (! $this->supportsSelectiveRestore()) {
            return [];
        }

        return $this->pgsqlTableOptions();
    }

    public function supportsRestore(): bool
    {
        return in_array($this->driver(), ['pgsql', 'mysql', 'mariadb'], true);
    }

    public function supportsSelectiveRestore(): bool
    {
        return $this->driver() === 'pgsql';
    }

    /**
     * @return list<string>
     */
    public function supportedModes(): array
    {
        if (! $this->supportsRestore()) {
            return [];
        }

        if ($this->supportsSelectiveRestore()) {
            return ['all', 'tables'];
        }

        return ['all'];
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    public function expandTablesWithDependencies(array $tables): array
    {
        $dependencies = collect($this->tableOptions())
            ->mapWithKeys(fn (array $table): array => [(string) $table['value'] => $table['dependencies']])
            ->all();

        $expanded = [];
        $queue = array_values(array_unique($tables));

        while ($queue !== []) {
            $table = array_shift($queue);

            if (in_array($table, $expanded, true)) {
                continue;
            }

            $expanded[] = $table;

            foreach ($dependencies[$table] ?? [] as $dependency) {
                if (! in_array($dependency, $expanded, true)) {
                    $queue[] = $dependency;
                }
            }
        }

        return $expanded;
    }

    /**
     * @param  list<string>  $tables
     */
    private function runRestore(string $path, array $tables): void
    {
        $connectionName = (string) config('database.default');
        $connection = config('database.connections.'.$connectionName);

        if (! is_array($connection)) {
            throw new RuntimeException('Database connection is not configured.');
        }

        $driver = (string) ($connection['driver'] ?? '');

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if ($tables !== []) {
                throw new RuntimeException('Partial restore is currently only available for PostgreSQL backups.');
            }

            $this->restoreMysqlDatabase($connection, $path);

            return;
        }

        if ($driver !== 'pgsql') {
            throw new RuntimeException('Database restore currently supports pgsql and mysql connections only.');
        }

        if ($tables !== []) {
            $this->restoreSelectedPostgresTables($connection, $path, $this->expandTablesWithDependencies($tables));

            return;
        }

        $command = [
            'pg_restore',
            '--clean',
            '--if-exists',
            '--exit-on-error',
            '--no-owner',
            '--no-acl',
            '--single-transaction',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '5432'),
            '--username='.(string) ($connection['username'] ?? ''),
            '--dbname='.(string) ($connection['database'] ?? ''),
            $path,
        ];

        $this->runCommand($command, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
    }

    /**
     * @return list<array{dependencies: list<string>, label: string, value: string}>
     */
    private function pgsqlTableOptions(): array
    {
        try {
            $dependencies = $this->dependencyMap();

            return collect(DB::select(
                "select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE' order by table_name"
            ))
                ->map(fn (object $row): array => [
                    'dependencies' => $dependencies[(string) $row->table_name] ?? [],
                    'label' => (string) $row->table_name,
                    'value' => (string) $row->table_name,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function driver(): string
    {
        return (string) config('database.connections.'.config('database.default').'.driver');
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function restoreMysqlDatabase(array $connection, string $path): void
    {
        $command = [
            'mysql',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.(string) ($connection['username'] ?? ''),
            '--database='.(string) ($connection['database'] ?? ''),
            '--execute=source '.$path,
        ];

        $this->runCommand($command, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  list<string>  $tables
     */
    private function restoreSelectedPostgresTables(array $connection, string $path, array $tables): void
    {
        $token = bin2hex(random_bytes(8));
        $scriptPath = storage_path("framework/cache/database-restore-script-{$token}.sql");
        $orderedTables = $this->orderTablesForRestore($tables);
        $tableSequences = $this->tableSequences($orderedTables);
        $dataPaths = [];

        File::ensureDirectoryExists(dirname($scriptPath));

        try {
            foreach ($orderedTables as $index => $table) {
                $dataPath = storage_path("framework/cache/database-restore-data-{$token}-{$index}.sql");
                $this->dumpTableData($connection, $path, $table, $dataPath);
                $dataPaths[$table] = $dataPath;
            }

            $this->writeTransactionalTableRestoreScript($scriptPath, $dataPaths, $tables, $orderedTables, $tableSequences);
            DB::disconnect();
            $this->runPsqlFile($connection, $scriptPath);
        } finally {
            File::delete(array_values($dataPaths));
            File::delete($scriptPath);
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function dumpTableData(array $connection, string $path, string $table, string $dataPath): void
    {
        $command = [
            'pg_restore',
            '--data-only',
            '--exit-on-error',
            '--no-owner',
            '--no-acl',
            '--file='.$dataPath,
            '--table='.$table,
            $path,
        ];

        $this->runCommand($command, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
    }

    /**
     * @param  array<string, string>  $dataPaths
     * @param  list<string>  $truncatedTables
     * @param  list<string>  $orderedTables
     * @param  array<string, list<string>>  $tableSequences
     */
    private function writeTransactionalTableRestoreScript(string $scriptPath, array $dataPaths, array $truncatedTables, array $orderedTables, array $tableSequences): void
    {
        $qualifiedTables = array_map(
            fn (string $table): string => $this->quoteIdentifier('public').'.'.$this->quoteIdentifier($table),
            $truncatedTables,
        );

        $statements = [
            $this->terminateOtherConnectionsSql(),
            'begin;',
            'set constraints all deferred;',
            'truncate table '.implode(', ', $qualifiedTables).' continue identity cascade;',
        ];

        foreach ($orderedTables as $table) {
            $statements[] = '\\i '.$this->quotePsqlPath($dataPaths[$table]);
        }

        foreach ($this->sequenceResetStatements($orderedTables, $tableSequences) as $statement) {
            $statements[] = $statement;
        }

        File::put($scriptPath, implode("\n", [
            ...$statements,
            'commit;',
            '',
        ]));
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function runPsqlFile(array $connection, string $scriptPath): void
    {
        $command = [
            'psql',
            '--set=ON_ERROR_STOP=1',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '5432'),
            '--username='.(string) ($connection['username'] ?? ''),
            '--dbname='.(string) ($connection['database'] ?? ''),
            '--file='.$scriptPath,
        ];

        $this->runCommand($command, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function quotePsqlPath(string $path): string
    {
        return "'".str_replace("'", "''", $path)."'";
    }

    private function quoteSqlLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, list<string>>
     */
    private function tableSequences(array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        $bindings = $tables;
        $placeholders = implode(', ', array_fill(0, count($bindings), '?'));

        $rows = DB::select(<<<SQL
            select table_class.relname as table_name, attribute.attname as column_name
            from pg_class sequence_class
            join pg_depend dependency on dependency.objid = sequence_class.oid
            join pg_class table_class on table_class.oid = dependency.refobjid
            join pg_namespace table_namespace on table_namespace.oid = table_class.relnamespace
            join pg_attribute attribute on attribute.attrelid = table_class.oid and attribute.attnum = dependency.refobjsubid
            where sequence_class.relkind = 'S'
                and dependency.deptype in ('a', 'i')
                and table_namespace.nspname = 'public'
                and table_class.relname in ({$placeholders})
            order by table_class.relname, attribute.attnum
        SQL, $bindings);

        $sequences = [];

        foreach ($rows as $row) {
            $table = (string) $row->table_name;
            $column = (string) $row->column_name;

            $sequences[$table] ??= [];

            if (! in_array($column, $sequences[$table], true)) {
                $sequences[$table][] = $column;
            }
        }

        return $sequences;
    }

    /**
     * @param  list<string>  $orderedTables
     * @param  array<string, list<string>>  $tableSequences
     * @return list<string>
     */
    private function sequenceResetStatements(array $orderedTables, array $tableSequences): array
    {
        $statements = [];

        foreach ($orderedTables as $table) {
            foreach ($tableSequences[$table] ?? [] as $column) {
                $statements[] = sprintf(
                    'select setval(pg_get_serial_sequence(%s, %s), coalesce(max(%s), 1), max(%s) is not null) from %s;',
                    $this->quoteSqlLiteral('public.'.$table),
                    $this->quoteSqlLiteral($column),
                    $this->quoteIdentifier($column),
                    $this->quoteIdentifier($column),
                    $this->quoteIdentifier('public').'.'.$this->quoteIdentifier($table),
                );
            }
        }

        return $statements;
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function orderTablesForRestore(array $tables): array
    {
        $selectedTables = array_values(array_unique($tables));
        $selectedLookup = array_fill_keys($selectedTables, true);
        $references = $this->foreignKeyReferences();
        $ordered = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $table) use (&$ordered, &$references, &$selectedLookup, &$visit, &$visited, &$visiting): void {
            if (isset($visited[$table])) {
                return;
            }

            if (isset($visiting[$table])) {
                $visited[$table] = true;
                $ordered[] = $table;

                return;
            }

            $visiting[$table] = true;

            foreach ($references[$table] ?? [] as $referencedTable) {
                if (isset($selectedLookup[$referencedTable])) {
                    $visit($referencedTable);
                }
            }

            unset($visiting[$table]);
            $visited[$table] = true;
            $ordered[] = $table;
        };

        foreach ($selectedTables as $table) {
            $visit($table);
        }

        return array_values(array_unique($ordered));
    }

    private function terminateOtherConnectionsSql(): string
    {
        return <<<'SQL'
            select pg_terminate_backend(pid)
            from pg_stat_activity
            where datname = current_database()
                and pid <> pg_backend_pid();
            SQL;
    }

    /**
     * @return array<string, list<string>>
     */
    private function dependencyMap(): array
    {
        $dependencies = [];

        foreach ($this->foreignKeyReferences() as $dependentTable => $referencedTables) {
            $dependencies[$dependentTable] ??= [];

            foreach ($referencedTables as $referencedTable) {
                $dependencies[$referencedTable] ??= [];

                if ($referencedTable === $dependentTable) {
                    continue;
                }

                if (! in_array($dependentTable, $dependencies[$referencedTable], true)) {
                    $dependencies[$referencedTable][] = $dependentTable;
                }

                if (! in_array($referencedTable, $dependencies[$dependentTable], true)) {
                    $dependencies[$dependentTable][] = $referencedTable;
                }
            }
        }

        return $dependencies;
    }

    /**
     * @return array<string, list<string>>
     */
    private function foreignKeyReferences(): array
    {
        $references = [];

        $rows = DB::select(<<<'SQL'
            select source.relname as table_name, target.relname as referenced_table_name
            from pg_constraint constraint_record
            join pg_class source on source.oid = constraint_record.conrelid
            join pg_namespace source_namespace on source_namespace.oid = source.relnamespace
            join pg_class target on target.oid = constraint_record.confrelid
            join pg_namespace target_namespace on target_namespace.oid = target.relnamespace
            where constraint_record.contype = 'f'
                and source_namespace.nspname = 'public'
                and target_namespace.nspname = 'public'
            order by target.relname, source.relname
        SQL);

        foreach ($rows as $row) {
            $referencedTable = (string) $row->referenced_table_name;
            $dependentTable = (string) $row->table_name;

            if ($referencedTable === $dependentTable) {
                continue;
            }

            $references[$dependentTable] ??= [];

            if (! in_array($referencedTable, $references[$dependentTable], true)) {
                $references[$dependentTable][] = $referencedTable;
            }
        }

        return $references;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function runCommand(array $command, array $environment): void
    {
        $lockTimeout = (int) config('core-panel.administration.database_backups.restore_lock_timeout', 15);

        if ($lockTimeout > 0) {
            $environment['PGOPTIONS'] = '-c lock_timeout='.$lockTimeout.'s';
        }

        $result = Process::timeout((int) config('core-panel.administration.database_backups.restore_timeout', 900))
            ->env($environment)
            ->run($command);

        if (! $result->successful()) {
            $message = trim($result->errorOutput());

            throw new RuntimeException($message !== '' ? $message : 'Database restore failed.');
        }
    }
}
