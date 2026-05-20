<?php

declare(strict_types=1);

namespace CorePanel\Support;

use CorePanel\Support\Install\BackupManager;
use CorePanel\Support\Publishing\CorePanelPublisher;
use Illuminate\Support\ServiceProvider;

trait PublishesCorePanelAssets
{
    /**
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    private function publishTag(string $tag, bool $force, bool $dryRun = false, ?string $basePath = null): array
    {
        return app(CorePanelPublisher::class)->publish([$tag], $force, $dryRun, $basePath);
    }

    /**
     * @param  list<string>  $tags
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    private function publishTags(array $tags, bool $force, bool $dryRun = false, ?string $basePath = null): array
    {
        return app(CorePanelPublisher::class)->publish($tags, $force, $dryRun, $basePath);
    }

    private function publishProviderTag(string $provider, string $tag, bool $force): void
    {
        if (! class_exists($provider)) {
            return;
        }

        $this->backupPublishedAssets(provider: $provider, tag: $tag, force: $force);

        $this->call('vendor:publish', [
            '--provider' => $provider,
            '--tag' => $tag,
            '--force' => $force,
        ]);
    }

    private function generateWayfinderRoutes(): void
    {
        if ($this->getApplication()?->has('wayfinder:generate')) {
            $this->call('wayfinder:generate');
        }
    }

    /**
     * @param  list<string>  $tags
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    private function updatePublishedTags(array $tags, bool $force, bool $dryRun = false, ?string $basePath = null): array
    {
        return app(CorePanelPublisher::class)->update($tags, $force, $dryRun, $basePath);
    }

    /**
     * @param  list<string>  $tags
     * @return array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }
     */
    private function updatePublishedProviderTags(
        string $provider,
        array $tags,
        bool $force,
        bool $dryRun = false,
        ?string $basePath = null,
    ): array {
        return app(CorePanelPublisher::class)->updateForProvider($provider, $tags, $force, $dryRun, $basePath);
    }

    private function backupPublishedAssets(?string $provider = null, ?string $tag = null, bool $force = false): void
    {
        if (! $force) {
            return;
        }

        /** @var array<string, string> $paths */
        $paths = ServiceProvider::pathsToPublish($provider, $tag);

        if ($paths === []) {
            return;
        }

        app(BackupManager::class)->backupPaths($paths, base_path());
    }
}
