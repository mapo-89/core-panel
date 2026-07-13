<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class CorePanelInstallationTest extends TestCase
{
    public function test_it_ships_published_package_assets_into_the_skeleton_app(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertFileExists(base_path('config/core-panel.php'));
        $this->assertFileExists(base_path('config/core-panel-access.php'));
        $this->assertFileExists(base_path('lang/de/page-layout.php'));
        $this->assertFileExists(base_path('lang/en/page-layout.php'));
        $this->assertFileExists(base_path('lang/vendor/core-panel/de/navigation.php'));
        $this->assertFileExists(base_path('lang/vendor/core-panel/en/navigation.php'));
        $this->assertFileExists(base_path('resources/css/app.css'));
        $this->assertFileExists(base_path('resources/css/theme/theme.css'));
        $this->assertFileExists(base_path('resources/js/app.ts'));
        $this->assertFileDoesNotExist(base_path('resources/js/plugins/core-panel.ts'));
        $this->assertFileDoesNotExist(base_path('resources/js/components/AppIcon.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/components/Locale/LocaleFlag.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/components/UserAvatar.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/layouts/AppLayout.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/layouts/components/AppHeader.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/layouts/components/AppFooter.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/layouts/components/AppPageHeader.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/layouts/components/AppSidebar.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/composables/useSidebar.ts'));
        $this->assertFileDoesNotExist(base_path('resources/js/pages/Admin/Administration/Index.vue'));
        $this->assertFileDoesNotExist(base_path('resources/js/pages/Admin/Dashboard/Index.vue'));
        $this->assertFileExists(base_path('resources/js/theme/core-panel/index.ts'));
        $this->assertFileExists(base_path('app/Http/Middleware/TrackUserPresence.php'));
        $this->assertFileExists(base_path('database/migrations/users/0001_01_01_000000_create_users_table.php'));
        $this->assertFileExists(base_path('database/migrations/auth/2016_06_01_000001_create_oauth_auth_codes_table.php'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2026_01_01_000001_create_tenants_table.php'));
    }

    public function test_it_ships_primevue_bootstrap_and_theme_setup(): void
    {
        $appEntry = file_get_contents(base_path('resources/js/app.ts'));
        $themeEntry = file_get_contents(base_path('resources/js/theme/core-panel/index.ts'));

        $this->assertStringContainsString("import { installCorePanelUi } from '@core-panel/plugins/core-panel'", $appEntry);
        $this->assertStringContainsString('const lazyLanguageModules = import.meta.glob<{', $appEntry);
        $this->assertStringContainsString('default: Record<string, string>', $appEntry);
        $this->assertStringContainsString('`../../lang/php_${lang}.json`', $appEntry);
        $this->assertStringContainsString('const hostPageModules = import.meta.glob<{ default: DefineComponent }>(', $appEntry);
        $this->assertStringContainsString('const vendorPageModules = import.meta.glob<{ default: DefineComponent }>(', $appEntry);
        $this->assertStringContainsString('../../vendor/mapo-89/core-panel/stubs/resources/js/pages/**/*.vue', $appEntry);
        $this->assertStringContainsString('throw new Error(`Unable to resolve Inertia page [${name}].`)', $appEntry);
        $this->assertStringContainsString('resolve: (name) => resolvePage(name)', $appEntry);
        $this->assertStringContainsString('core-panel-dark', $themeEntry);
    }

    public function test_it_registers_the_package_commands_in_the_host_application(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('core-panel:install', $commands);
        $this->assertArrayHasKey('core-panel:publish', $commands);
        $this->assertArrayHasKey('core-panel:sync-access', $commands);
        $this->assertArrayHasKey('core-panel:update', $commands);
    }

    public function test_it_runs_the_installer_idempotently_in_the_skeleton_app(): void
    {
        $this->artisan('core-panel:install', [
            '--no-interaction' => true,
            '--force' => true,
            '--publish-components' => 'true',
            '--default-locale' => 'de',
            '--fallback-locale' => 'en',
            '--create-admin' => 'false',
            '--run-migrations' => 'false',
        ])->assertSuccessful();

        $this->assertFileExists(base_path('resources/js/app.ts'));
        $this->assertFileDoesNotExist(base_path('resources/js/plugins/core-panel.ts'));
        $this->assertFileDoesNotExist(base_path('resources/js/layouts/AppLayout.vue'));
        $this->assertFileExists(base_path('resources/js/theme/core-panel/index.ts'));
    }
}
