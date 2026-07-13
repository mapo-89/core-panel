<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Support\Publishing\VendorFirstAssetMigrator;
use CorePanel\Support\PublishTag;
use Illuminate\Console\Command;

final class VendorFirstCleanupCommand extends Command
{
    protected $signature = 'core-panel:vendor-first
        {--dry-run : Show planned frontend cleanup without writing files}
        {--force : Remove locally modified frontend overrides after creating a backup}
        {--base-path= : Override the target base path}';

    protected $description = 'Remove local frontend overrides that can resolve directly from vendor assets.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core:vendor-first'];

    public function __construct(private readonly VendorFirstAssetMigrator $vendorFirstAssets)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $basePath = is_string($this->option('base-path')) && $this->option('base-path') !== ''
            ? (string) $this->option('base-path')
            : null;

        $result = $this->vendorFirstAssets->migrate(
            [PublishTag::Components->value, PublishTag::Lang->value, PublishTag::Theme->value, PublishTag::Views->value],
            (bool) $this->option('force'),
            (bool) $this->option('dry-run'),
            $basePath,
        );

        $this->table(
            ['Tag', 'Status', 'Reason', 'Destination'],
            array_map(
                static fn (array $change): array => [
                    'tag' => $change['tag'],
                    'status' => $change['status'],
                    'reason' => $change['reason'],
                    'destination' => $change['destination'],
                ],
                $result['changes'],
            ),
        );

        $this->components->info('Manifest: '.$result['manifestPath']);

        if ($result['themeMigrationHint']) {
            $this->components->warn('Theme files changed. Review token changes and rebuild frontend assets.');
        }

        return collect($result['changes'])->contains(static fn (array $change): bool => $change['status'] === 'conflict')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
