<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

final readonly class DatabaseBackupSqlExportService
{
    public function __construct(
        private DatabaseBackupEncryptor $encryptor,
        private DatabaseBackupService $backups,
        private DatabaseBackupSettings $settings,
    ) {}

    public function export(string $backup): DatabaseBackupSqlExport
    {
        $sourcePath = $this->backups->pathFor($backup);
        $dumpPath = $sourcePath;
        $temporaryDumpPath = null;
        $sqlPath = storage_path('framework/cache/database-backup-sql-'.bin2hex(random_bytes(8)).'.sql');

        File::ensureDirectoryExists(dirname($sqlPath));

        if ($this->encryptor->isEncrypted($sourcePath)) {
            $temporaryDumpPath = storage_path('framework/cache/database-backup-sql-'.bin2hex(random_bytes(8)).'.dump');
            $this->encryptor->decryptFileWithCodes($sourcePath, $temporaryDumpPath, $this->settings->encryptionCodes());
            $dumpPath = $temporaryDumpPath;
        }

        try {
            $this->createSqlExport($dumpPath, $sqlPath);

            if (! File::isFile($sqlPath)) {
                throw new RuntimeException('Database backup SQL export did not create a file.');
            }

            return new DatabaseBackupSqlExport(
                path: $sqlPath,
                name: $this->sqlName($backup),
            );
        } catch (Throwable $throwable) {
            File::delete($sqlPath);

            throw $throwable;
        } finally {
            if ($temporaryDumpPath !== null) {
                File::delete($temporaryDumpPath);
            }
        }
    }

    private function createSqlExport(string $dumpPath, string $sqlPath): void
    {
        $driver = (string) config('database.connections.'.config('database.default').'.driver');

        if ($driver === 'sqlite') {
            $result = Process::timeout((int) config('core-panel.administration.database_backups.timeout', 900))
                ->run([
                    'sqlite3',
                    $dumpPath,
                    '.dump',
                ]);

            if (! $result->successful()) {
                $message = trim($result->errorOutput());

                throw new RuntimeException($message !== '' ? $message : 'Database backup SQL export failed.');
            }

            File::put($sqlPath, $result->output());

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            File::copy($dumpPath, $sqlPath);

            return;
        }

        $result = Process::timeout((int) config('core-panel.administration.database_backups.timeout', 900))
            ->run([
                'pg_restore',
                '--no-owner',
                '--no-acl',
                '--file='.$sqlPath,
                $dumpPath,
            ]);

        if (! $result->successful()) {
            $message = trim($result->errorOutput());

            throw new RuntimeException($message !== '' ? $message : 'Database backup SQL export failed.');
        }
    }

    private function sqlName(string $backup): string
    {
        $name = basename($backup);

        if (str_ends_with($name, '.dump.enc')) {
            return substr($name, 0, -9).'.sql';
        }

        if (str_ends_with($name, '.dump')) {
            return substr($name, 0, -5).'.sql';
        }

        return $name.'.sql';
    }
}
