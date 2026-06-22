<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Support\Migrations\CorePanelHostMigrationRunner;
use CorePanel\Support\PublishesCorePanelAssets;
use CorePanel\Support\PublishTag;
use CorePanel\Support\ScaffoldsCorePanelStubs;
use CorePanel\Support\SynchronizesEnvironmentFile;
use Illuminate\Console\Command;
use Throwable;

final class UpdateCommand extends Command
{
    use PublishesCorePanelAssets;

    public function __construct(
        private readonly ScaffoldsCorePanelStubs $stubs,
        private readonly SynchronizesEnvironmentFile $environment,
        private readonly CorePanelHostMigrationRunner $migrations,
    ) {
        parent::__construct();
    }

    protected $signature = 'core-panel:update
        {--dry-run : Show planned changes without writing files}
        {--force : Overwrite published files after creating a backup}
        {--base-path= : Override the target base path}
        {--with-addon-updates : Also run update for installed optional addons}
        {--breaking-changes : Also refresh config files for breaking update paths}';

    protected $description = 'Republish mutable Laravel CorePanel assets after package updates.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core:update'];

    public function handle(): int
    {
        $basePath = is_string($this->option('base-path')) && $this->option('base-path') !== ''
            ? (string) $this->option('base-path')
            : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $withAddonUpdates = (bool) $this->option('with-addon-updates');
        $withBreakingChanges = (bool) $this->option('breaking-changes');
        $tags = PublishTag::updateTags();

        if ($withBreakingChanges) {
            $tags[] = PublishTag::Config->value;
        }

        $result = $this->updatePublishedTags($tags, $force, $dryRun, $basePath);

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

        if ($dryRun) {
            return collect($result['changes'])->contains(static fn (array $change): bool => $change['status'] === 'conflict')
                ? self::FAILURE
                : self::SUCCESS;
        }

        if ($result['themeMigrationHint']) {
            $this->components->warn('Theme files changed. Review token changes and rebuild frontend assets.');
        }

        if (! $dryRun) {
            $this->stubs->scaffold(
                force: false,
                basePath: $basePath,
                pruneHostScaffolds: false,
                mergeExisting: true,
                onlyManagedChanges: true,
            );
            $this->syncEnvironmentDefaults($basePath);

            if ($withAddonUpdates) {
                $this->updateInstalledOptionalAddons(
                    $basePath,
                    $withBreakingChanges,
                    $force,
                );
            }

            $this->runMigrations($basePath);
            $this->generateWayfinderRoutes();
            $this->generateSwaggerDocs();
        }

        return collect($result['changes'])->contains(static fn (array $change): bool => $change['status'] === 'conflict')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function updateInstalledOptionalAddons(
        ?string $basePath,
        bool $withBreakingChanges,
        bool $force,
    ): void {
        if (! $this->withTenancyUpdateCommand()) {
            $this->components->warn('No installed optional addons exposed an update command.');

            return;
        }

        $options = [
            '--force' => $force,
        ];

        if ($withBreakingChanges) {
            $options['--breaking-changes'] = true;
        }

        if ($basePath !== null && $basePath !== '') {
            $options['--base-path'] = $basePath;
        }

        $this->call('core-panel:tenancy:update', array_filter($options));
    }

    private function withTenancyUpdateCommand(): bool
    {
        return ($this->getApplication()?->has('core-panel:tenancy:update') ?? false)
            || app()->bound('command.core-panel.tenancy.update');
    }

    private function syncEnvironmentDefaults(?string $basePath): void
    {
        $root = $basePath ?? base_path();

        if (! file_exists($root.'/.env')) {
            return;
        }

        $this->environment->sync($basePath);
    }

    private function runMigrations(?string $basePath): void
    {
        if ($basePath !== null && realpath($basePath) !== realpath(base_path())) {
            $this->components->warn('Skipping automatic migrations for external base-path updates. Run php artisan migrate in the target application manually.');

            return;
        }

        if (! ($this->getApplication()?->has('migrate') ?? false)) {
            return;
        }

        $this->migrations->run($this, $basePath);
    }

    private function generateSwaggerDocs(): void
    {
        if (! (($this->getApplication()?->has('l5-swagger:generate') ?? false) || app()->bound('command.l5-swagger.generate'))) {
            return;
        }

        config()->set('l5-swagger.documentations.default.paths.annotations', [
            base_path('app/OpenApi'),
        ]);

        try {
            $this->call('l5-swagger:generate');
        } catch (Throwable $exception) {
            $this->components->warn(sprintf(
                'Swagger docs were not generated automatically: %s',
                $exception->getMessage(),
            ));
        }
    }
}
