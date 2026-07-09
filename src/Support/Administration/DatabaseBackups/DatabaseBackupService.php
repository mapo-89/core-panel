<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class DatabaseBackupService
{
    public function __construct(
        private readonly DatabaseBackupEncryptor $encryptor,
        private readonly DatabaseBackupSettings $settings,
    ) {}

    /**
     * @return Collection<int, DatabaseBackupFile>
     */
    public function list(): Collection
    {
        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            return collect();
        }

        return collect(File::files($directory))
            ->filter(
                fn (\SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.dump')
                    || str_ends_with($file->getFilename(), '.dump.enc'),
            )
            ->map(fn (\SplFileInfo $file): DatabaseBackupFile => new DatabaseBackupFile(
                name: $file->getFilename(),
                path: $file->getPathname(),
                size: $file->getSize(),
                createdAt: Carbon::createFromTimestamp($file->getMTime()),
                encrypted: str_ends_with($file->getFilename(), '.enc'),
                storageLocations: ['local'],
            ))
            ->sortByDesc(fn (DatabaseBackupFile $file): int => $file->createdAt->getTimestamp())
            ->values();
    }

    public function enabled(): bool
    {
        return (bool) config('core-panel.administration.database_backups.enabled', true);
    }

    public function exists(string $name): bool
    {
        return File::isFile($this->pathFor($name));
    }

    public function pathFor(string $name): string
    {
        $name = basename($name);

        if (! $this->isValidName($name)) {
            throw new RuntimeException('Invalid backup name.');
        }

        return $this->directory().DIRECTORY_SEPARATOR.$name;
    }

    public function delete(string $name): void
    {
        $path = $this->pathFor($name);

        if (File::isFile($path)) {
            File::delete($path);
        }
    }

    public function create(string $suffix = 'manual'): DatabaseBackupFile
    {
        $this->ensureDirectoryExists();

        $rawName = $this->makeName($suffix);
        $name = $this->settings->encryptionEnabled() ? $rawName.'.enc' : $rawName;
        $path = $this->pathFor($name);
        $connectionName = (string) config('database.default');
        $connection = config('database.connections.'.$connectionName);

        if (! is_array($connection)) {
            throw new RuntimeException('Database connection is not configured.');
        }

        $driver = (string) ($connection['driver'] ?? '');
        $database = (string) ($connection['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Database name is not configured.');
        }

        $dumpPath = $this->settings->encryptionEnabled()
            ? $this->pathFor($rawName)
            : $path;

        if ($driver === 'sqlite') {
            $this->createSqliteBackup($dumpPath);

            if ($this->settings->encryptionEnabled()) {
                $this->encryptor->encryptFile($dumpPath, $path, $this->settings->encryptionCode());
                File::delete($dumpPath);
            }

            clearstatcache(true, $path);
            $backup = new DatabaseBackupFile(
                name: $name,
                path: $path,
                size: File::isFile($path) ? File::size($path) : 0,
                createdAt: now(),
                encrypted: str_ends_with($name, '.enc'),
            );

            $this->enforceRetention();

            return $backup;
        }

        $result = match ($driver) {
            'pgsql' => Process::timeout((int) config('core-panel.administration.database_backups.timeout', 900))
                ->env(['PGPASSWORD' => (string) ($connection['password'] ?? '')])
                ->run([
                    'pg_dump',
                    '--format=custom',
                    '--no-owner',
                    '--no-acl',
                    '--file='.$dumpPath,
                    '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
                    '--port='.(string) ($connection['port'] ?? '5432'),
                    '--username='.(string) ($connection['username'] ?? ''),
                    $database,
                ]),
            'mariadb', 'mysql' => Process::timeout((int) config('core-panel.administration.database_backups.timeout', 900))
                ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
                ->run([
                    'mysqldump',
                    '--result-file='.$dumpPath,
                    '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
                    '--port='.(string) ($connection['port'] ?? '3306'),
                    '--user='.(string) ($connection['username'] ?? ''),
                    $database,
                ]),
            default => throw new RuntimeException('Database backups currently support sqlite, pgsql and mysql connections only.'),
        };

        if (! $result->successful()) {
            File::delete([$path, $dumpPath]);

            $errorOutput = trim($result->errorOutput());

            throw new RuntimeException($errorOutput !== '' ? $errorOutput : 'Database backup failed.');
        }

        if ($this->settings->encryptionEnabled()) {
            $this->encryptor->encryptFile($dumpPath, $path, $this->settings->encryptionCode());
            File::delete($dumpPath);
        }

        clearstatcache(true, $path);
        $backup = new DatabaseBackupFile(
            name: $name,
            path: $path,
            size: File::isFile($path) ? File::size($path) : 0,
            createdAt: now(),
            encrypted: str_ends_with($name, '.enc'),
        );

        $this->enforceRetention();

        return $backup;
    }

    public function importUploaded(UploadedFile $file): DatabaseBackupFile
    {
        $this->ensureDirectoryExists();

        $importedName = $this->makeImportedName($file->getClientOriginalExtension() === 'enc');
        $targetPath = $this->pathFor($importedName);
        $file->move($this->directory(), $importedName);
        clearstatcache(true, $targetPath);
        $backup = new DatabaseBackupFile(
            name: $importedName,
            path: $targetPath,
            size: File::isFile($targetPath) ? File::size($targetPath) : 0,
            createdAt: now(),
            encrypted: str_ends_with($importedName, '.enc'),
        );

        $this->enforceRetention();

        return $backup;
    }

    public function ensureDirectoryExists(): string
    {
        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0750, true);
        }

        return $directory;
    }

    public function makeName(string $suffix = 'manual'): string
    {
        $database = preg_replace(
            '/[^A-Za-z0-9_.-]+/',
            '-',
            (string) config('database.connections.'.config('database.default').'.database', 'database'),
        );
        $timestamp = now()->format('Y-m-d_H-i-s');
        $normalizedSuffix = trim((string) preg_replace('/[^A-Za-z0-9_.-]+/', '-', $suffix), '-');

        return "{$database}-{$timestamp}-{$normalizedSuffix}.dump";
    }

    private function createSqliteBackup(string $dumpPath): void
    {
        File::delete($dumpPath);

        $quotedDumpPath = str_replace("'", "''", $dumpPath);

        DB::purge((string) config('database.default'));

        try {
            DB::unprepared("VACUUM INTO '{$quotedDumpPath}'");
        } catch (\Throwable $throwable) {
            File::delete($dumpPath);

            throw new RuntimeException('SQLite backup failed: '.$throwable->getMessage(), previous: $throwable);
        }

        if (! File::isFile($dumpPath)) {
            throw new RuntimeException('SQLite backup failed: no backup file was created.');
        }
    }

    private function makeImportedName(bool $encrypted): string
    {
        $name = $this->makeName('imported');

        return $encrypted ? $name.'.enc' : $name;
    }

    private function enforceRetention(): void
    {
        $settings = $this->settings->toArray();
        $retentionMode = $settings['retention_mode'];

        if ($retentionMode === 'forever') {
            return;
        }

        if ($retentionMode === 'count') {
            $this->pruneByCount($settings['retention_count']);

            return;
        }

        if ($retentionMode === 'days') {
            $this->pruneByDays($settings['retention_days']);
        }
    }

    private function pruneByCount(int $retentionCount): void
    {
        $backupsToDelete = $this->list()
            ->slice($retentionCount)
            ->pluck('name');

        foreach ($backupsToDelete as $backupName) {
            $this->delete($backupName);
        }
    }

    private function pruneByDays(int $retentionDays): void
    {
        $cutoff = now()->subDays($retentionDays);
        $backupsToDelete = $this->list()
            ->filter(fn (DatabaseBackupFile $backup): bool => $backup->createdAt->lt($cutoff))
            ->pluck('name');

        foreach ($backupsToDelete as $backupName) {
            $this->delete($backupName);
        }
    }

    private function directory(): string
    {
        $configuredPath = trim((string) config('core-panel.administration.database_backups.path', ''));

        if ($configuredPath !== '') {
            return $configuredPath;
        }

        return storage_path('app/backups/database');
    }

    private function isValidName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_.-]+\.dump(?:\.enc)?$/', $name) === 1;
    }
}
