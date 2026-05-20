<?php

declare(strict_types=1);

namespace CorePanel\Support\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CorePanelGenerator
{
    public function __construct(
        private Filesystem $files,
    ) {}

    /**
     * @return list<string>
     */
    public function makeDomain(string $name, string $basePath, bool $force = false): array
    {
        $domain = Str::studly($name);
        $paths = [];

        foreach (['Actions', 'DTOs', 'Queries', 'Events', 'Listeners', 'Contracts', 'Policies'] as $layer) {
            $directory = $basePath.'/app/Domains/'.$domain.'/'.$layer;
            $this->files->ensureDirectoryExists($directory);

            $gitkeep = $directory.'/.gitkeep';
            $this->writeFile($gitkeep, '', $force);
            $paths[] = $gitkeep;
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    public function makeAction(string $name, string $basePath, bool $force = false): array
    {
        $action = Str::studly($name);
        $domain = $this->domainFromAction($action);
        $path = $basePath.'/app/Domains/'.$domain.'/Actions/'.$action.'.php';

        $this->writeFile($path, $this->render('action', [
            'DummyActionClass' => $action,
            'DummyDomain' => $domain,
            'DummyModelClass' => $domain,
            'DummyModelVariable' => Str::camel($domain),
        ], $basePath), $force);

        return [$path];
    }

    /**
     * @return list<string>
     */
    public function makeDto(string $name, string $basePath, bool $force = false): array
    {
        $dto = Str::studly($name);
        $domain = $this->domainFromDto($dto);
        $path = $basePath.'/app/Domains/'.$domain.'/DTOs/'.$dto.'.php';

        $this->writeFile($path, $this->render('dto', [
            'DummyDomain' => $domain,
            'DummyDtoClass' => $dto,
            'DummyModelClass' => $domain,
        ], $basePath), $force);

        return [$path];
    }

    /**
     * @return list<string>
     */
    public function makeForm(string $name, string $basePath, bool $force = false): array
    {
        $form = Str::studly($name);
        $model = $this->domainFromSuffix($form, 'Form');
        $path = $basePath.'/app/Support/FormBuilder/Forms/'.$form.'.php';

        $this->writeFile($path, $this->render('form', [
            'DummyFormClass' => $form,
            'DummyModelClass' => $model,
            'DummyModelVariable' => Str::camel($model),
            'DummyModelLabel' => $this->headline($model),
        ], $basePath), $force);

        return [$path];
    }

    /**
     * @return list<string>
     */
    public function makeTable(string $name, string $basePath, bool $force = false): array
    {
        $table = Str::studly($name);
        $model = $this->domainFromSuffix($table, 'Table');
        $path = $basePath.'/app/Support/TableBuilder/Tables/'.$table.'.php';

        $this->writeFile($path, $this->render('table', [
            'DummyTableClass' => $table,
            'DummyModelClass' => $model,
            'DummyModelVariable' => Str::camel($model),
            'DummyPluralLabel' => $this->headline(Str::pluralStudly($model)),
        ], $basePath), $force);

        return [$path];
    }

    /**
     * @param  array{
     *     translatableFields:bool,
     *     formBuilder:bool,
     *     dataTable:bool,
     *     apiResource:bool,
     *     policy:bool,
     *     factory:bool,
     *     seeder:bool,
     *     primeVuePages:bool
     * }  $options
     * @return list<string>
     */
    public function makeCrud(string $name, array $options, string $basePath, bool $force = false): array
    {
        $model = Str::studly(Str::singular($name));
        $pluralModel = Str::pluralStudly($model);
        $modelVariable = Str::camel($model);
        $modelsVariable = Str::camel($pluralModel);
        $table = Str::snake($pluralModel);
        $routeSegment = Str::kebab($pluralModel);
        $migrationDirectory = $this->migrationDirectory($basePath);
        $migrationPath = $migrationDirectory.'/'.$this->migrationFilename($table);

        $paths = [];
        $paths = [...$paths, ...$this->makeDomain($model, $basePath, $force)];
        $paths = [...$paths, ...$this->makeAction('Create'.$model, $basePath, $force)];
        $paths = [...$paths, ...$this->makeDto($model.'Data', $basePath, $force)];

        if ($options['formBuilder']) {
            $paths = [...$paths, ...$this->makeForm($model.'Form', $basePath, $force)];
        }

        if ($options['dataTable']) {
            $paths = [...$paths, ...$this->makeTable($model.'Table', $basePath, $force)];
        }

        $context = $this->crudContext($model, $pluralModel, $modelVariable, $modelsVariable, $table, $routeSegment, $options);

        $files = [
            $basePath.'/app/Models/'.$model.'.php' => 'crud/model',
            $migrationPath => 'crud/migration',
            $basePath.'/app/Domains/'.$model.'/Http/Controllers/'.$model.'Controller.php' => 'crud/controller',
            $basePath.'/app/Domains/'.$model.'/Http/Requests/Store'.$model.'Request.php' => 'crud/store-request',
            $basePath.'/app/Domains/'.$model.'/Http/Requests/Update'.$model.'Request.php' => 'crud/update-request',
            $basePath.'/tests/Feature/'.$model.'CrudTest.php' => 'crud/feature-test',
        ];

        if ($options['factory']) {
            $files[$basePath.'/database/factories/'.$model.'Factory.php'] = 'crud/factory';
        }

        if ($options['seeder']) {
            $files[$basePath.'/database/seeders/'.$model.'Seeder.php'] = 'crud/seeder';
        }

        if ($options['policy']) {
            $files[$basePath.'/app/Domains/'.$model.'/Policies/'.$model.'Policy.php'] = 'crud/policy';
        }

        if ($options['apiResource']) {
            $files[$basePath.'/app/Domains/'.$model.'/Http/Resources/'.$model.'Resource.php'] = 'crud/resource';
        }

        if ($options['primeVuePages']) {
            $files[$basePath.'/resources/js/pages/'.$pluralModel.'/Index.vue'] = 'crud/page-index';
            $files[$basePath.'/resources/js/pages/'.$pluralModel.'/Create.vue'] = 'crud/page-create';
            $files[$basePath.'/resources/js/pages/'.$pluralModel.'/Edit.vue'] = 'crud/page-edit';
            $files[$basePath.'/resources/js/pages/'.$pluralModel.'/Show.vue'] = 'crud/page-show';
        }

        foreach ($files as $path => $stub) {
            $this->writeFile($path, $this->render($stub, $context, $basePath), $force);
            $paths[] = $path;
        }

        $routePath = $basePath.'/routes/web.php';
        $this->files->ensureDirectoryExists(dirname($routePath));

        if (! $this->files->exists($routePath)) {
            $this->files->put($routePath, "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        }

        $this->appendGeneratedRouteBlock($routePath, $model, $this->render('crud/routes', $context, $basePath));

        return array_values(array_unique($paths));
    }

    /**
     * @param  array{
     *     translatableFields:bool,
     *     formBuilder:bool,
     *     dataTable:bool,
     *     apiResource:bool,
     *     policy:bool,
     *     factory:bool,
     *     seeder:bool,
     *     primeVuePages:bool
     * }  $options
     * @return array<string, string>
     */
    private function crudContext(
        string $model,
        string $pluralModel,
        string $modelVariable,
        string $modelsVariable,
        string $table,
        string $routeSegment,
        array $options,
    ): array {
        return [
            'DummyCreateActionClass' => 'Create'.$model.'Action',
            'DummyDomain' => $model,
            'DummyDtoClass' => $model.'Data',
            'DummyFactoryClass' => $model.'Factory',
            'DummyFormClass' => $model.'Form',
            'DummyFormRequestClass' => 'Store'.$model.'Request',
            'DummyMigrationClass' => 'Create'.$pluralModel.'Table',
            'DummyModelClass' => $model,
            'DummyModelLabel' => $this->headline($model),
            'DummyModelPluralLabel' => $this->headline($pluralModel),
            'DummyModelVariable' => $modelVariable,
            'DummyModelsVariable' => $modelsVariable,
            'DummyControllerNamespace' => 'App\\Domains\\'.$model.'\\Http\\Controllers',
            'DummyRequestNamespace' => 'App\\Domains\\'.$model.'\\Http\\Requests',
            'DummyPageDirectory' => $pluralModel,
            'DummyPolicyClass' => $model.'Policy',
            'DummyPolicyNamespace' => 'App\\Domains\\'.$model.'\\Policies',
            'DummyRequestUpdateClass' => 'Update'.$model.'Request',
            'DummyResourceClass' => $model.'Resource',
            'DummyResourceNamespace' => 'App\\Domains\\'.$model.'\\Http\\Resources',
            'DummyRouteSegment' => $routeSegment,
            'DummySeederClass' => $model.'Seeder',
            'DummyTableClass' => $model.'Table',
            'DummyTableName' => $table,
            'DummyTranslatableCast' => $options['translatableFields'] ? "            'name' => 'array',\n" : '',
            'DummyTranslatableFactoryName' => $options['translatableFields']
                ? "            'name' => ['en' => fake()->words(2, true), 'de' => fake()->words(2, true)],\n"
                : "            'name' => fake()->words(2, true),\n",
            'DummyTranslatableMigrationColumn' => $options['translatableFields'] ? "            \$table->jsonb('name');\n" : "            \$table->string('name');\n",
            'DummyTranslatableValidation' => $options['translatableFields'] ? "            'name' => ['required', 'array'],\n" : "            'name' => ['required', 'string', 'max:255'],\n",
            'DummyPolicyAuthorizeIndex' => $options['policy'] ? "        Gate::authorize('viewAny', {$model}::class);\n\n" : '',
            'DummyPolicyAuthorizeCreate' => $options['policy'] ? "        Gate::authorize('create', {$model}::class);\n\n" : '',
            'DummyPolicyAuthorizeShow' => $options['policy'] ? "        Gate::authorize('view', \${$modelVariable});\n\n" : '',
            'DummyPolicyAuthorizeUpdate' => $options['policy'] ? "        Gate::authorize('update', \${$modelVariable});\n\n" : '',
            'DummyPolicyAuthorizeDelete' => $options['policy'] ? "        Gate::authorize('delete', \${$modelVariable});\n\n" : '',
            'DummyPolicyImport' => $options['policy'] ? "use Illuminate\\Support\\Facades\\Gate;\n" : '',
            'DummyResourceImport' => $options['apiResource'] ? "use App\\Domains\\{$model}\\Http\\Resources\\{$model}Resource;\n" : '',
            'DummyResourceCollection' => $options['apiResource']
                ? "{$model}Resource::collection(\${$modelsVariable})->resolve()"
                : "\${$modelsVariable}->map(static fn ({$model} \${$modelVariable}): array => [\n                'id' => (string) \${$modelVariable}->getKey(),\n                'name' => \${$modelVariable}->getAttribute('name'),\n                'isActive' => (bool) \${$modelVariable}->getAttribute('is_active'),\n            ])->values()->all()",
        ];
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function render(string $stub, array $replacements, string $basePath): string
    {
        $stubPath = $this->resolveTemplatePath($stub, $basePath);

        if (! $this->files->exists($stubPath)) {
            throw new RuntimeException("Generator stub [{$stub}] could not be found.");
        }

        return strtr($this->files->get($stubPath), $replacements);
    }

    private function resolveTemplatePath(string $stub, string $basePath): string
    {
        foreach ($this->templateCandidates($stub, $basePath.'/stubs/core-panel/generators') as $candidate) {
            if ($this->files->exists($candidate)) {
                return $candidate;
            }
        }

        foreach ($this->templateCandidates($stub, __DIR__.'/../../../stubs/core-panel/generators') as $candidate) {
            if ($this->files->exists($candidate)) {
                return $candidate;
            }
        }

        return __DIR__.'/../../../stubs/core-panel/generators/'.$stub.'.stub';
    }

    /**
     * @return list<string>
     */
    private function templateCandidates(string $stub, string $root): array
    {
        return [
            $root.'/'.$stub.'.php.template',
            $root.'/'.$stub.'.vue.template',
            $root.'/'.$stub.'.ts.template',
            $root.'/'.$stub.'.php',
            $root.'/'.$stub.'.vue',
            $root.'/'.$stub.'.ts',
            $root.'/'.$stub.'.stub',
        ];
    }

    private function writeFile(string $path, string $contents, bool $force): void
    {
        if ($this->files->exists($path) && ! $force) {
            throw new RuntimeException("The file [{$path}] already exists. Use --force to overwrite it.");
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
    }

    private function appendGeneratedRouteBlock(string $path, string $model, string $block): void
    {
        $marker = "// [core-panel-generator:{$model}]";
        $contents = $this->files->exists($path) ? $this->files->get($path) : '';

        if (str_contains($contents, $marker)) {
            return;
        }

        $this->files->append($path, "\n\n{$marker}\n{$block}\n");
    }

    private function migrationDirectory(string $basePath): string
    {
        return $basePath.'/database/migrations';
    }

    private function migrationFilename(string $table): string
    {
        return now()->format('Y_m_d_His').'_create_'.$table.'_table.php';
    }

    private function domainFromAction(string $action): string
    {
        $domain = preg_replace('/^(Create|Update|Delete|Store|Publish|Assign|List|Get|Sync|Export|Import|Restore|ForceDelete)/', '', $action);

        return is_string($domain) && $domain !== '' ? $domain : $action;
    }

    private function domainFromDto(string $dto): string
    {
        return $this->domainFromSuffix($dto, 'Data');
    }

    private function domainFromSuffix(string $value, string $suffix): string
    {
        $resolved = (string) Str::of($value)->beforeLast($suffix);

        return $resolved !== '' ? $resolved : $value;
    }

    private function headline(string $value): string
    {
        return Str::headline($value);
    }
}
