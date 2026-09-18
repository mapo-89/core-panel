<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Support\Administration\SystemUpdates\RunAutomaticSystemUpdateAction;
use Illuminate\Console\Command;
use Throwable;

final class RunAutomaticSystemUpdateCommand extends Command
{
    protected $signature = 'system-updates:auto';

    protected $description = 'Run a due automatic system update check or installation.';

    public function handle(RunAutomaticSystemUpdateAction $action): int
    {
        try {
            $result = $action->execute();
        } catch (Throwable $throwable) {
            report($throwable);
            $this->components->error('Automatic system update failed.');

            return self::FAILURE;
        }

        $this->components->info("System update automation {$result['status']}: {$result['message']}");

        return self::SUCCESS;
    }
}
