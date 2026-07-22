<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Domain\Permission\Actions\ResyncAccessMatrixAction;
use Illuminate\Console\Command;

final class SyncAccessCommand extends Command
{
    protected $signature = 'core-panel:sync-access
        {--fresh : Reset managed role permissions to match config exactly}';

    protected $description = 'Sync managed CorePanel roles and permissions from the access configuration.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core:sync-access'];

    public function handle(ResyncAccessMatrixAction $syncAccess): int
    {
        if ((bool) $this->option('fresh')) {
            $this->components->warn('Fresh mode will reset managed role permissions to match the configuration.');
        }

        $this->components->task('Syncing managed access matrix', function () use ($syncAccess): void {
            $syncAccess->execute((bool) $this->option('fresh'));
        });

        $this->newLine();
        $this->components->info('Managed access matrix synchronized.');

        return self::SUCCESS;
    }
}
