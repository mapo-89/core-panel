<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use CorePanel\Database\Seeders\CorePanelPermissionSeeder;
use CorePanel\Database\Seeders\CorePanelSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $corePanelSeeded = false;

    protected function setUp(): void
    {
        $this->setTestingEnvironmentValue('DB_CONNECTION', 'pgsql');
        $this->setTestingEnvironmentValue('DB_HOST', (string) env('DB_HOST', '127.0.0.1'));
        $this->setTestingEnvironmentValue('DB_PORT', (string) env('DB_PORT', '5432'));
        $this->setTestingEnvironmentValue('DB_DATABASE', (string) env('DB_DATABASE_TEST', 'core_panel_test'));
        $this->setTestingEnvironmentValue('DB_USERNAME', (string) env('DB_USERNAME', 'core_panel'));
        $this->setTestingEnvironmentValue('DB_PASSWORD', (string) env('DB_PASSWORD', 'core_panel'));

        parent::setUp();

        config()->set('app.key', (string) env('APP_KEY', 'base64:'.base64_encode(str_repeat('a', 32))));
    }

    protected function seedCorePanel(): void
    {
        if ($this->corePanelSeeded) {
            return;
        }

        $this->seed(CorePanelPermissionSeeder::class);
        $this->seed(CorePanelSettingsSeeder::class);

        $this->corePanelSeeded = true;
    }

    protected function createSuperAdmin(array $attributes = []): User
    {
        $this->seedCorePanel();

        /** @var User $user */
        $user = User::factory()->create($attributes);
        $user->assignRole('super-admin');

        return $user->refresh();
    }

    private function setTestingEnvironmentValue(string $key, string $value): void
    {
        putenv(sprintf('%s=%s', $key, $value));
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
