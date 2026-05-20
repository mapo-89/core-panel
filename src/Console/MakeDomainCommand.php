<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Console\Concerns\InteractsWithGeneratorPrompts;
use CorePanel\Support\Generators\CorePanelGenerator;
use Illuminate\Console\Command;
use RuntimeException;

final class MakeDomainCommand extends Command
{
    use InteractsWithGeneratorPrompts;

    protected $signature = 'core-panel:make-domain
        {name : Domain name}
        {--force : Overwrite existing files}
        {--base-path= : Override the target base path}';

    protected $description = 'Generate a domain folder structure for a CorePanel application.';

    protected $aliases = ['core:make-domain'];

    public function __construct(private readonly CorePanelGenerator $generator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $paths = $this->generator->makeDomain(
                (string) $this->argument('name'),
                $this->basePath(),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Domain generated.');
        $this->components->twoColumnDetail('Created', (string) count($paths).' paths');

        return self::SUCCESS;
    }
}
