<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Console\Concerns\InteractsWithGeneratorPrompts;
use CorePanel\Support\Generators\CorePanelGenerator;
use Illuminate\Console\Command;
use RuntimeException;

final class MakeTableCommand extends Command
{
    use InteractsWithGeneratorPrompts;

    protected $signature = 'core-panel:make-table
        {name : Table class name}
        {--force : Overwrite existing files}
        {--base-path= : Override the target base path}';

    protected $description = 'Generate a TableBuilder table class.';

    protected $aliases = ['core:make-table'];

    public function __construct(private readonly CorePanelGenerator $generator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->generator->makeTable(
                (string) $this->argument('name'),
                $this->basePath(),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Table generated.');

        return self::SUCCESS;
    }
}
