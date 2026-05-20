<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

function makeGeneratorBasePath(string $suffix): string
{
    return sys_get_temp_dir().'/core-panel-generator-'.bin2hex(random_bytes(4)).'-'.$suffix;
}

function seedGeneratorProject(string $basePath): void
{
    $files = app(Filesystem::class);

    foreach ([
        'app',
        'database/factories',
        'database/migrations',
        'database/seeders',
        'resources/js/pages',
        'routes',
        'tests/Feature',
    ] as $directory) {
        $files->ensureDirectoryExists($basePath.'/'.$directory);
    }

    $files->put($basePath.'/routes/web.php', "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\\Support\\Facades\\Route;\n");
}

it('generates a domain structure', function (): void {
    $basePath = makeGeneratorBasePath('domain');
    seedGeneratorProject($basePath);

    $this->artisan('core-panel:make-domain', [
        'name' => 'Users',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(is_dir($basePath.'/app/Domains/Users/Actions'))->toBeTrue()
        ->and(is_file($basePath.'/app/Domains/Users/DTOs/.gitkeep'))->toBeTrue()
        ->and(is_file($basePath.'/app/Domains/Users/Policies/.gitkeep'))->toBeTrue();
});

it('generates standalone action dto form and table files', function (): void {
    $basePath = makeGeneratorBasePath('standalone');
    seedGeneratorProject($basePath);

    $this->artisan('core-panel:make-action', [
        'name' => 'CreateProduct',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $this->artisan('core-panel:make-dto', [
        'name' => 'ProductData',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $this->artisan('core-panel:make-form', [
        'name' => 'ProductForm',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    $this->artisan('core-panel:make-table', [
        'name' => 'ProductTable',
        '--base-path' => $basePath,
    ])->assertExitCode(0);

    expect(file_get_contents($basePath.'/app/Domains/Product/Actions/CreateProduct.php'))->toContain('final readonly class CreateProduct')
        ->and(file_get_contents($basePath.'/app/Domains/Product/DTOs/ProductData.php'))->toContain('final readonly class ProductData')
        ->and(file_get_contents($basePath.'/app/Support/FormBuilder/Forms/ProductForm.php'))->toContain('Form::make')
        ->and(file_get_contents($basePath.'/app/Support/TableBuilder/Tables/ProductTable.php'))->toContain('Table::make()');
});

it('generates the expected crud scaffold', function (): void {
    $basePath = makeGeneratorBasePath('crud');
    seedGeneratorProject($basePath);

    $this->artisan('core-panel:make-crud', [
        'name' => 'Product',
        '--no-interaction' => true,
        '--base-path' => $basePath,
        '--translatable-fields' => 'true',
        '--form-builder' => 'true',
        '--data-table' => 'true',
        '--api-resource' => 'true',
        '--policy' => 'true',
        '--factory' => 'true',
        '--seeder' => 'true',
        '--primevue-pages' => 'true',
    ])->assertExitCode(0);

    $migrationFiles = glob($basePath.'/database/migrations/*_create_products_table.php');

    expect($migrationFiles)->not->toBeFalse()
        ->and($migrationFiles)->toHaveCount(1)
        ->and(is_file($basePath.'/app/Models/Product.php'))->toBeTrue()
        ->and(is_file($basePath.'/app/Domains/Product/Http/Controllers/ProductController.php'))->toBeTrue()
        ->and(is_file($basePath.'/app/Domains/Product/Http/Requests/StoreProductRequest.php'))->toBeTrue()
        ->and(is_file($basePath.'/app/Domains/Product/Policies/ProductPolicy.php'))->toBeTrue()
        ->and(is_file($basePath.'/app/Domains/Product/Actions/CreateProduct.php'))->toBeTrue()
        ->and(is_file($basePath.'/app/Domains/Product/DTOs/ProductData.php'))->toBeTrue()
        ->and(is_file($basePath.'/app/Support/FormBuilder/Forms/ProductForm.php'))->toBeTrue()
        ->and(is_file($basePath.'/app/Support/TableBuilder/Tables/ProductTable.php'))->toBeTrue()
        ->and(is_file($basePath.'/resources/js/pages/Products/Index.vue'))->toBeTrue()
        ->and(is_file($basePath.'/tests/Feature/ProductCrudTest.php'))->toBeTrue()
        ->and(file_get_contents($migrationFiles[0]))->toContain("\$table->jsonb('name')")
        ->and(file_get_contents($migrationFiles[0]))->not->toContain('tenant_id')
        ->and(file_get_contents($basePath.'/routes/web.php'))->toContain('// [core-panel-generator:Product]')
        ->and(file_get_contents($basePath.'/tests/Feature/ProductCrudTest.php'))->toContain("Route::has('core-panel.products.index')");
});
