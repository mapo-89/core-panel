<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Console\Concerns\InteractsWithGeneratorPrompts;
use CorePanel\Support\Generators\CorePanelGenerator;
use Illuminate\Console\Command;
use RuntimeException;

final class MakeCrudCommand extends Command
{
    use InteractsWithGeneratorPrompts;

    protected $signature = 'core-panel:make-crud
        {name : Resource name}
        {--translatable-fields= : true|false}
        {--form-builder= : true|false}
        {--data-table= : true|false}
        {--api-resource= : true|false}
        {--policy= : true|false}
        {--factory= : true|false}
        {--seeder= : true|false}
        {--primevue-pages= : true|false}
        {--force : Overwrite existing files}
        {--base-path= : Override the target base path}';

    protected $description = 'Generate a CorePanel CRUD scaffold.';

    protected $aliases = ['core:make-crud'];

    public function __construct(private readonly CorePanelGenerator $generator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $paths = $this->generator->makeCrud(
                (string) $this->argument('name'),
                [
                    'translatableFields' => $this->booleanGeneratorOption('translatable-fields', 'Translatable fields?', false),
                    'formBuilder' => $this->booleanGeneratorOption('form-builder', 'Generate a FormBuilder form?', true),
                    'dataTable' => $this->booleanGeneratorOption('data-table', 'Generate a DataTable?', true),
                    'apiResource' => $this->booleanGeneratorOption('api-resource', 'Generate an API resource?', true),
                    'policy' => $this->booleanGeneratorOption('policy', 'Generate a policy?', true),
                    'factory' => $this->booleanGeneratorOption('factory', 'Generate a factory?', true),
                    'seeder' => $this->booleanGeneratorOption('seeder', 'Generate a seeder?', false),
                    'primeVuePages' => $this->booleanGeneratorOption('primevue-pages', 'Generate PrimeVue pages?', true),
                ],
                $this->basePath(),
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('CRUD scaffold generated.');
        $this->components->twoColumnDetail('Created', (string) count($paths).' files');

        return self::SUCCESS;
    }
}
