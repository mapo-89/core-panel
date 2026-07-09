<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Support\SynchronizesEnvironmentFile;
use Illuminate\Console\Command;

final class SyncEnvironmentCommand extends Command
{
    protected $signature = 'core-panel:env:sync
        {--base-path= : Override the target base path}
        {--replace-template-values : Replace existing values that are also defined in the template}';

    protected $description = 'Synchronize the application .env file with the CorePanel .env.example template.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core:env:sync'];

    public function __construct(private readonly SynchronizesEnvironmentFile $environment)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $basePath = is_string($this->option('base-path')) && $this->option('base-path') !== ''
            ? (string) $this->option('base-path')
            : null;
        $replaceTemplateValues = (bool) $this->option('replace-template-values');
        $root = $basePath ?? base_path();

        $synchronized = $this->environment->sync(
            $basePath,
            replaceTemplateValues: $replaceTemplateValues,
        );

        if ($synchronized === []) {
            $this->components->error('CorePanel .env.example template could not be read.');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Environment synchronized for %s using %d keys.',
            $root,
            count($synchronized),
        ));

        return self::SUCCESS;
    }
}
