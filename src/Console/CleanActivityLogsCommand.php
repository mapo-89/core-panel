<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Support\ActivityLog\ActivityLogService;
use Illuminate\Console\Command;

final class CleanActivityLogsCommand extends Command
{
    protected $signature = 'core-panel:activity:clean {--days= : Delete entries older than the given number of days}';

    protected $description = 'Delete old CorePanel activity log records.';

    public function __construct(private readonly ActivityLogService $activityLog)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = $this->option('days');
        $resolvedDays = is_numeric($days)
            ? max(1, (int) $days)
            : (int) config('core-panel.activity_log.clean_after_days', 90);

        $deleted = $this->activityLog->cleanup($resolvedDays);

        $this->components->info("Deleted {$deleted} activity log entries.");

        return self::SUCCESS;
    }
}
