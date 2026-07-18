<?php

declare(strict_types=1);

namespace CorePanel\Support\Migrations;

use Illuminate\Console\Command;

final readonly class HostMigrationRunner
{
    public function __construct(private HostMigrationExecutor $executor) {}

    public function run(Command $command, ?string $basePath = null): void
    {
        $migrationFiles = $this->executor->migrationFiles($basePath);

        if ($migrationFiles === []) {
            return;
        }

        $command->call('migrate', [
            '--force' => true,
            '--path' => $migrationFiles,
            '--realpath' => true,
        ]);
    }
}
