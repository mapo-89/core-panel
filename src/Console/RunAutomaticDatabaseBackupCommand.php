<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Support\Administration\DatabaseBackups\RunAutomaticDatabaseBackupAction;
use Illuminate\Console\Command;
use Throwable;

final class RunAutomaticDatabaseBackupCommand extends Command
{
    protected $signature = 'database-backups:auto';

    protected $description = 'Create automatic database backups according to the configured schedule.';

    public function handle(RunAutomaticDatabaseBackupAction $action): int
    {
        try {
            $result = $action->execute();
        } catch (Throwable $throwable) {
            report($throwable);
            $this->components->error('Automatic database backup failed.');

            return self::FAILURE;
        }

        $this->components->info("Database backup automation {$result['status']}: {$result['message']}");

        return self::SUCCESS;
    }
}
