<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Console\Concerns\InteractsWithGeneratorPrompts;
use CorePanel\Support\Generators\CorePanelGenerator;
use Illuminate\Console\Command;
use RuntimeException;

final class MakeActionCommand extends Command
{
    use InteractsWithGeneratorPrompts;

    protected $signature = 'core-panel:make-action
        {name : Action class name}
        {--force : Overwrite existing files}
        {--base-path= : Override the target base path}';

    protected $description = 'Generate a CorePanel action class in the inferred domain.';

    protected $aliases = ['core:make-action'];

    public function __construct(private readonly CorePanelGenerator $generator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->generator->makeAction(
                (string) $this->argument('name'),
                $this->basePath(),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Action generated.');

        return self::SUCCESS;
    }
}
