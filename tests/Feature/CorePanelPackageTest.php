<?php

declare(strict_types=1);

use CorePanel\Console\CleanActivityLogsCommand;
use CorePanel\Console\InstallCommand;
use CorePanel\Console\PublishCommand;
use CorePanel\Console\UpdateCommand;
use CorePanel\CorePanelServiceProvider;
use CorePanel\Http\Responses\ResetPasswordResponse;
use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\PublishTag;
use CorePanel\Support\ScaffoldsCorePanelStubs;
use CorePanel\Support\SynchronizesEnvironmentFile;
use CorePanel\Support\Version\AppVersionRepository;
use CorePanel\Tests\FakeUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

final class ConfiguredUser extends Model
{
    protected $table = 'configured_users';
}

/**
 * @return array<string, string>
 */
function publishedJavascriptAssetDirectories(string $basePath): array
{
    return [
        'stubs/resources/js/actions' => $basePath.'/resources/js/actions',
        'stubs/resources/js/app.ts' => $basePath.'/resources/js/app.ts',
        'stubs/resources/js/assets' => $basePath.'/resources/js/assets',
        'stubs/resources/js/components/Locale' => $basePath.'/resources/js/components/Locale',
        'resources/js/components/TranslatedPassword.vue' => $basePath.'/resources/js/components/TranslatedPassword.vue',
        'resources/js/components/FormBuilder' => $basePath.'/resources/js/components/FormBuilder',
        'resources/js/components/TabBuilder' => $basePath.'/resources/js/components/TabBuilder',
        'resources/js/components/TableBuilder' => $basePath.'/resources/js/components/TableBuilder',
        'stubs/resources/js/components/Users' => $basePath.'/resources/js/pages/Admin/Users/components',
        'stubs/resources/js/composables' => $basePath.'/resources/js/composables',
        'stubs/resources/js/layouts' => $basePath.'/resources/js/layouts',
        'stubs/resources/js/pages' => $basePath.'/resources/js/pages',
        'stubs/resources/js/plugins' => $basePath.'/resources/js/plugins',
        'stubs/resources/js/routes' => $basePath.'/resources/js/routes',
        'stubs/resources/js/types' => $basePath.'/resources/js/types',
        'resources/js/theme/core-panel' => $basePath.'/resources/js/theme/core-panel',
    ];
}

function seedPublishedJavascriptAssets(string $basePath): void
{
    $files = app(Filesystem::class);

    foreach (publishedJavascriptAssetDirectories($basePath) as $source => $destination) {
        $absoluteSource = __DIR__.'/../../'.$source;

        if (is_file($absoluteSource)) {
            $files->ensureDirectoryExists(dirname($destination));
            $files->copy($absoluteSource, $destination);

            continue;
        }

        $files->copyDirectory($absoluteSource, $destination);
    }

    foreach ([
        'core-panel/users/index.ts',
        'login/index.ts',
        'password/index.ts',
        'register/index.ts',
        'socialite/index.ts',
        'two-factor/index.ts',
        'two-factor/login/index.ts',
        'user-password/index.ts',
        'user-profile-information/index.ts',
        'verification/index.ts',
    ] as $routeFile) {
        $path = $basePath.'/resources/js/routes/'.$routeFile;
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, "export default {};\n");
    }
}

/**
 * @return list<string>
 */
function resolveRelativeImportCandidates(string $importerPath, string $importPath): array
{
    $basePath = dirname($importerPath).'/'.$importPath;

    return [
        $basePath,
        $basePath.'.ts',
        $basePath.'.js',
        $basePath.'.vue',
        $basePath.'/index.ts',
        $basePath.'/index.js',
        $basePath.'/index.vue',
    ];
}

it('loads the package service provider', function (): void {
    expect(app()->getProvider(CorePanelServiceProvider::class))
        ->toBeInstanceOf(CorePanelServiceProvider::class);
});

it('ships tenancy route helpers in the core scaffold', function (): void {
    expect(file_exists(__DIR__.'/../../stubs/resources/js/routes/core-panel/tenants.ts'))->toBeTrue();
});

it('retranslates held login validation errors on locale changes in the auth scaffold', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/Login.vue');
    $helper = file_get_contents(__DIR__.'/../../stubs/resources/js/composables/useTranslatedAuthErrors.ts');

    expect($helper)->toContain('export function useTranslatedAuthErrors<TField extends string>(')
        ->and($helper)->toContain("key: 'validation.required',")
        ->and($helper)->toContain("key: 'validation.email',")
        ->and($helper)->toContain("key: 'validation.min.string',")
        ->and($helper)->toContain("key: 'validation.confirmed',")
        ->and($helper)->toContain("key: 'auth.failed',")
        ->and($helper)->toContain("key: 'auth.throttle',")
        ->and($contents)->toContain("import { useTranslatedAuthErrors } from '@/composables/useTranslatedAuthErrors'")
        ->and($contents)->toContain('const emailError = computed(() =>')
        ->and($contents)->toContain("translateLoginError('email', form.errors.email)")
        ->and($contents)->toContain('const passwordError = computed(() =>')
        ->and($contents)->toContain('return translatedAuthError(field, error)');
});

it('uses the translated auth error helper across publishable auth pages', function (): void {
    $pages = [
        'ForgotPassword.vue',
        'Login.vue',
        'Register.vue',
        'ResetPassword.vue',
        'TwoFactorChallenge.vue',
    ];

    foreach ($pages as $page) {
        $contents = file_get_contents(__DIR__."/../../stubs/resources/js/pages/Auth/{$page}");

        expect($contents)->toContain("import { useTranslatedAuthErrors } from '@/composables/useTranslatedAuthErrors'")
            ->and($contents)->toContain('translatedAuthError(');
    }
});

it('uses the package fake user model in the orchestra testbench auth provider', function (): void {
    expect(config('auth.providers.users.model'))->toBe(FakeUser::class);
});

it('loads the expected configuration defaults', function (): void {
    expect(config('core-panel.route_prefix'))->toBe('admin')
        ->and(config('core-panel.middleware'))->toBe(['web', 'auth'])
        ->and(config('core-panel.user_model'))->toBe('App\\Models\\User');

    /** @var CorePanelConfig $config */
    $config = app(CorePanelConfig::class);

    expect($config->auth->usesPassport())->toBeTrue()
        ->and($config->auth->registrationEnabled)->toBeFalse()
        ->and($config->i18n->defaultLocale)->toBe((string) config('core-panel.i18n.default_locale'))
        ->and($config->i18n->fallbackLocale)->toBe('en')
        ->and($config->files->disk)->toBe('public')
        ->and($config->files->maxUploadSize)->toBe(10240)
        ->and($config->security->headersEnabled)->toBeTrue()
        ->and($config->security->cspReportOnly)->toBeFalse()
        ->and($config->security->referrerPolicy)->toBe('strict-origin-when-cross-origin')
        ->and($config->horizon->enabled)->toBeTrue();
});

it('preloads the active locale before mounting the publishable inertia app', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/resources/js/app.ts');

    expect($contents)->toContain("import { I18n, i18nVue } from 'laravel-vue-i18n'")
        ->and($contents)->toContain('async setup({ el, App, props, plugin })')
        ->and($contents)->toContain('const i18nOptions = {')
        ->and($contents)->toContain('await I18n.getSharedInstance(i18nOptions).loadLanguageAsync(')
        ->and($contents)->toContain('document.documentElement.lang = activeLocale')
        ->and($contents)->toContain('app.use(i18nVue, i18nOptions)');
});

it('reapplies runtime ui settings when inertia shared props change', function (): void {
    $composable = file_get_contents(__DIR__.'/../../stubs/resources/js/composables/useRuntimeUiSettings.ts');
    $appLayout = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AppLayout.vue');
    $authLayout = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AuthLayout.vue');
    $runtimeTheme = file_get_contents(__DIR__.'/../../resources/js/theme/core-panel/index.ts');

    expect($composable)->toContain('export function useRuntimeUiSettings(): void')
        ->and($composable)->toContain('watch(')
        ->and($composable)->toContain('applyCorePanelLayoutDensity(')
        ->and($composable)->toContain('applyCorePanelRadiusToken(')
        ->and($composable)->toContain('applyCorePanelThemePalette(')
        ->and($composable)->toContain('applyCorePanelThemeAccent(')
        ->and($runtimeTheme)->toContain('const activeRadius = normalizeCorePanelRadiusToken(root.dataset.radiusToken)')
        ->and($runtimeTheme)->toContain('const activeAccent = normalizeCorePanelThemeAccent(root.dataset.themeAccent)')
        ->and($runtimeTheme)->toContain('applyCorePanelRadiusToken(activeRadius)')
        ->and($runtimeTheme)->toContain('applyCorePanelThemeAccent(activeAccent)')
        ->and($appLayout)->toContain("import { useRuntimeUiSettings } from '@/composables/useRuntimeUiSettings'")
        ->and($appLayout)->toContain('useRuntimeUiSettings()')
        ->and($authLayout)->toContain("import { useRuntimeUiSettings } from '@/composables/useRuntimeUiSettings'")
        ->and($authLayout)->toContain('useRuntimeUiSettings()');
});

it('applies environment overrides from the config file', function (): void {
    putenv('CORE_PANEL_USER_MODEL=Domain\\Auth\\AdminUser');
    putenv('CORE_PANEL_ROUTE_PREFIX=control');
    putenv('CORE_PANEL_REGISTRATION_ENABLED=1');
    putenv('FILESYSTEM_DISK=s3');

    /** @var array<string, mixed> $config */
    $rawConfig = require __DIR__.'/../../config/core-panel.php';
    config()->set('core-panel', $rawConfig);
    $config = CorePanelConfig::fromRepository(config());

    expect($config->userModel)->toBe('Domain\\Auth\\AdminUser')
        ->and($config->routePrefix)->toBe('control')
        ->and($config->auth->usesPassport())->toBeTrue()
        ->and($config->auth->registrationEnabled)->toBeTrue()
        ->and($config->files->disk)->toBe('s3');

    putenv('CORE_PANEL_USER_MODEL');
    putenv('CORE_PANEL_ROUTE_PREFIX');
    putenv('CORE_PANEL_REGISTRATION_ENABLED');
    putenv('FILESYSTEM_DISK');
});

it('allows configuring the user model', function (): void {
    config()->set('core-panel.user_model', 'Domain\\Auth\\AdminUser');

    /** @var CorePanelConfig $config */
    $config = app(CorePanelConfig::class);

    expect($config->userModel)->toBe('Domain\\Auth\\AdminUser');
});

it('registers the configured publish tags', function (): void {
    foreach (PublishTag::values() as $tag) {
        expect(ServiceProvider::pathsToPublish(null, $tag))
            ->not->toBeEmpty("Expected publish tag [{$tag}] to be registered.");
    }
});

it('merges the managed access configuration defaults', function (): void {
    expect(config('core-panel-access.resources.settings'))->toBeArray()
        ->and(config('core-panel-access.role_permissions.super-admin'))->toBe('*')
        ->and(config('core-panel-access.custom_permissions'))->toContain('core-panel.view-horizon');
});

it('registers the theme publish tag', function (): void {
    expect(ServiceProvider::pathsToPublish(null, PublishTag::Theme->value))
        ->not->toBeEmpty();
});

it('keeps internal stubs out of the default install and update publish groups', function (): void {
    expect(PublishTag::installTags())->toBe([])
        ->and(PublishTag::updateTags())->not->toContain(PublishTag::Stubs->value);
});

it('bundles spatie permission migrations and still publishes its config during installation', function (): void {
    $contents = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');

    expect($contents)->not->toContain("'permission-migrations'")
        ->and($contents)->not->toContain("'permission-config'");
});

it('bundles passport, activitylog, and medialibrary migrations and still publishes their configs during installation', function (): void {
    $contents = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');

    expect($contents)->not->toContain("'passport-migrations'")
        ->and($contents)->not->toContain("'passport-config'")
        ->and($contents)->not->toContain("'activitylog-migrations'")
        ->and($contents)->not->toContain("'activitylog-config'")
        ->and($contents)->not->toContain("'media-library-migrations'")
        ->and($contents)->not->toContain("'media-library-config'");
});

it('contains the primevue theme configuration', function (): void {
    /** @var CorePanelConfig $config */
    $config = app(CorePanelConfig::class);

    expect($config->ui->library)->toBe('primevue')
        ->and($config->ui->theme)->toBe('core-panel');
});

it('ships a dark surface scale with a true dark 950 token', function (): void {
    $contents = file_get_contents(__DIR__.'/../../resources/js/theme/core-panel/dark.ts');

    expect($contents)->toContain("0: '#ffffff'")
        ->and($contents)->toContain("800: '#0f172a'")
        ->and($contents)->toContain("900: '#0b1120'")
        ->and($contents)->toContain("950: '#030712'");
});

it('ships mysql and postgresql scaffold defaults in the database config stub', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/config/database.php');

    expect($contents)->toContain("'default' => env('DB_CONNECTION', 'pgsql')")
        ->and($contents)->toContain("'mysql' => [")
        ->and($contents)->toContain("'port' => env('DB_PORT', '3306')")
        ->and($contents)->toContain("'pgsql' => [")
        ->and($contents)->toContain("'port' => env('DB_PORT', '5432')");
});

it('keeps manual public form helpers inside the routes tree without a separate route-helper layer', function (): void {
    expect(file_exists(__DIR__.'/../../stubs/resources/js/routes/_wayfinder.ts'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../stubs/resources/js/routes/locale.ts'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/resources/js/routes/core-panel/forms/public.ts'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../stubs/resources/js/route-helpers/_wayfinder.ts'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/resources/js/route-helpers/locale.ts'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/resources/js/route-helpers/core-panel/forms/public.ts'))->toBeFalse()
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/routes/core-panel/forms/public.ts'))
        ->toContain("import { callableAction } from '../../_wayfinder'");
});

it('renders the publishable logs tabs through the shared table builder surface', function (): void {
    foreach ([
        'ActivityLogsTab.vue',
        'AuthenticationLogsTab.vue',
        'LogFilesTab.vue',
    ] as $component) {
        $contents = file_get_contents(
            __DIR__."/../../stubs/resources/js/pages/Admin/Logs/components/{$component}",
        );

        expect($contents)->toContain(
            "import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'",
        )->toContain('<TableBuilderDataTable')
            ->toContain('class="grid gap-1 px-5 pt-5"')
            ->toContain("import LogBadge from '@/pages/Admin/Logs/components/LogBadge.vue'")
            ->toContain('<LogBadge');
    }

    $activityDetail = file_get_contents(
        __DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/ActivityLogDetail.vue',
    );

    expect($activityDetail)->toContain("import LogBadge from '@/pages/Admin/Logs/components/LogBadge.vue'")
        ->and($activityDetail)->toContain('{{ $t(\'activity.labels.changes\') }}')
        ->and($activityDetail)->toContain('{{ $t(\'activity.labels.properties\') }}')
        ->and($activityDetail)->toContain("const propertiesView = ref<'json' | 'table'>('table')")
        ->and($activityDetail)->toContain("return trans('activity.models.user')")
        ->and($activityDetail)->toContain(':label="$t(\'activity.labels.table\')"')
        ->and($activityDetail)->toContain(':label="$t(\'activity.labels.json\')"');

    $logsController = file_get_contents(__DIR__.'/../../src/Http/Controllers/Logs/LogController.php');
    $activityTab = file_get_contents(
        __DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/ActivityLogsTab.vue',
    );

    expect($logsController)->toContain("return __('activity.models.user');")
        ->and($activityTab)->toContain('const subjectTypeLabels = computed(() =>')
        ->and($activityTab)->toContain("meta: { labelKey: 'activity.columns.subject_id' }")
        ->and($activityTab)->toContain("meta: { labelKey: 'activity.columns.subject_type' }")
        ->and($activityTab)->toContain("meta: { labelKey: 'activity.columns.causer' }");
});

it('renders the publishable logs page with the vertical side-tab layout', function (): void {
    $contents = file_get_contents(
        __DIR__.'/../../stubs/resources/js/pages/Admin/Logs/Index.vue',
    );
    $tabsTheme = file_get_contents(
        __DIR__.'/../../stubs/resources/css/theme/_tabs.css',
    );

    expect($contents)->toContain(":title=\"trans('page-logs.title')\"")
        ->and($contents)->toContain(":subtitle=\"trans('page-logs.description')\"")
        ->and($contents)->not->toContain('<header class="cp-section__header">')
        ->and($contents)->not->toContain('<section class="cp-section">')
        ->and($contents)->toContain('<div class="cp-log-management">')
        ->and($contents)->toContain("panelSurfaceVariant: 'card'")
        ->and($contents)->toContain('class="cp-side-tabs"')
        ->and($contents)->toContain('layout="vertical"')
        ->and($tabsTheme)->toContain('.cp-log-management .cp-side-tabs__panel-surface {');
});

it('registers the primevue bootstrap and services in the publishable app entry', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/resources/js/plugins/core-panel.ts');

    expect($contents)->toContain('app.use(PrimeVue')
        ->and($contents)->toContain('app.use(ToastService)')
        ->and($contents)->toContain('app.use(ConfirmationService)')
        ->and($contents)->toContain('app.use(DialogService)')
        ->and($contents)->toContain("from '@core-panel/theme/core-panel'")
        ->and($contents)->toContain("app.component('DataTable'")
        ->and($contents)->toContain("app.component('FileUpload'");
});

it('ships the requested theme token files', function (): void {
    $files = [
        'index.ts',
        'preset.ts',
        'tokens.ts',
        'dark.ts',
        'light.ts',
    ];

    foreach ($files as $file) {
        expect(file_exists(__DIR__."/../../resources/js/theme/core-panel/{$file}"))->toBeTrue();
    }

    $tokens = file_get_contents(__DIR__.'/../../resources/js/theme/core-panel/tokens.ts');

    expect($tokens)->toContain('primary')
        ->and($tokens)->toContain('surface')
        ->and($tokens)->toContain('text')
        ->and($tokens)->toContain('muted')
        ->and($tokens)->toContain('success')
        ->and($tokens)->toContain('warning')
        ->and($tokens)->toContain('danger')
        ->and($tokens)->toContain('info')
        ->and($tokens)->toContain('radius')
        ->and($tokens)->toContain('shadow')
        ->and($tokens)->toContain('spacing');
});

it('persists and toggles dark mode in the publishable layout assets', function (): void {
    $composable = file_get_contents(__DIR__.'/../../stubs/resources/js/composables/useColorMode.ts');
    $layout = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AppLayout.vue');
    $header = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/components/AppHeader.vue');
    $authLayout = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AuthLayout.vue');
    $hostEntry = file_get_contents(__DIR__.'/../../stubs/resources/js/app.ts');
    $plugin = file_get_contents(__DIR__.'/../../stubs/resources/js/plugins/core-panel.ts');
    $themeIndex = file_get_contents(__DIR__.'/../../resources/js/theme/core-panel/index.ts');

    expect($themeIndex)->toContain('core-panel.color-mode')
        ->and($themeIndex)->toContain('localStorage')
        ->and($themeIndex)->toContain("export type CorePanelColorModePreference = CorePanelColorMode | 'system'")
        ->and($themeIndex)->toContain("if (mode === 'dark' || mode === 'light' || mode === 'system') {")
        ->and($composable)->toContain("import { useMediaQuery, useStorage } from '@vueuse/core'")
        ->and($composable)->toContain("initialMode: CorePanelColorModePreference = 'system'")
        ->and($composable)->toContain("const systemPrefersDark = useMediaQuery('(prefers-color-scheme: dark)')")
        ->and($composable)->toContain("colorMode.value === 'system'")
        ->and($layout)->toContain(':color-mode="colorMode"')
        ->and($layout)->toContain('@set-color-mode="setColorMode"')
        ->and($layout)->toContain("useColorMode('system')")
        ->and($authLayout)->toContain("useColorMode('system')")
        ->and($hostEntry)->not->toContain('dark_mode_default')
        ->and($plugin)->toContain("config.darkMode === false ? 'light' : 'system'")
        ->and($header)->toContain("import type { CorePanelColorModePreference } from '@core-panel/theme/core-panel'")
        ->and($header)->toContain("if (props.colorMode === 'system') {")
        ->and($header)->toContain('const nextColorMode = computed<CorePanelColorModePreference>(() => {')
        ->and($header)->toContain("return 'system'")
        ->and($header)->toContain('v-tooltip.top="colorModeTooltip"')
        ->and($header)->toContain("@click=\"emit('setColorMode', nextColorMode)\"")
        ->and($header)->not->toContain(':title="colorModeTooltip"')
        ->and($header)->toContain("if (nextColorMode.value === 'system') {")
        ->and($header)->toContain("return nextColorMode.value === 'dark' ? 'moon' : 'sun'")
        ->and($header)->toContain('nextColorModeLabel.value')
        ->and($layout)->toContain("import AppHeader from '@/layouts/components/AppHeader.vue'")
        ->and($layout)->toContain("import AppFooter from '@/layouts/components/AppFooter.vue'")
        ->and($layout)->toContain("import AppPageHeader from '@/layouts/components/AppPageHeader.vue'")
        ->and($layout)->toContain("import AppSidebar from '@/layouts/components/AppSidebar.vue'")
        ->and($themeIndex)->toContain('core-panel-dark');
});

it('keeps publishable vue assets free of hardcoded color values', function (): void {
    $files = [
        __DIR__.'/../../stubs/resources/js/layouts/AppLayout.vue',
        __DIR__.'/../../stubs/resources/js/pages/Admin/Dashboard/Index.vue',
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toMatch('/#[0-9a-fA-F]{3,8}/')
            ->and($contents)->not->toMatch('/(?:bg|text|border|ring|from|to|via)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|black|white)\b/');
    }
});

it('allows the host application to override the published theme', function (): void {
    $themePublishPaths = ServiceProvider::pathsToPublish(null, PublishTag::Theme->value);
    $entry = file_get_contents(__DIR__.'/../../stubs/resources/js/plugins/core-panel.ts');

    expect($themePublishPaths)->not->toBeEmpty()
        ->and($entry)->toContain("const themeName = config.theme ?? 'core-panel'");
});

it('registers the PrimeVue toggle switch used by the auth settings tab', function (): void {
    $entry = file_get_contents(__DIR__.'/../../stubs/resources/js/plugins/core-panel.ts');
    $authSettingsTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/AuthSettingsTab.vue');

    expect($authSettingsTab)->toContain('<ToggleSwitch')
        ->and($entry)->toContain("import ToggleSwitch from 'primevue/toggleswitch'")
        ->and($entry)->toContain("app.component('ToggleSwitch', ToggleSwitch)");
});

it('ships host application templates as regular files inside the package sources', function (): void {
    foreach (ScaffoldsCorePanelStubs::paths() as $path) {
        $stubSourcePath = __DIR__.'/../../stubs/'.$path;
        $packageSourcePath = __DIR__.'/../../'.$path;
        $packageResourceSourcePath = __DIR__.'/../../resources/'.$path;
        $sourcePath = file_exists($stubSourcePath)
            ? $stubSourcePath
            : (file_exists($packageSourcePath) ? $packageSourcePath : $packageResourceSourcePath);

        expect(file_exists($sourcePath))->toBeTrue();
    }
});

it('excludes generated scaffold artifacts from the installable stubs tree', function (): void {
    foreach (ScaffoldsCorePanelStubs::paths() as $path) {
        expect($path)->not->toBe('.env')
            ->and($path)->not->toStartWith('public/build/')
            ->and($path)->not->toStartWith('node_modules/')
            ->and($path)->not->toEndWith('.scss');
    }
});

it('maps installer templates onto the host application paths by relative path', function (): void {
    expect(ScaffoldsCorePanelStubs::paths())->toContain(
        '.prettierignore',
        '.env.example',
        '.env.testing',
        'app/Actions/Fortify/CreateNewUser.php',
        'app/Actions/Fortify/ResetUserPassword.php',
        'app/Actions/Fortify/UpdateUserPassword.php',
        'app/Actions/Fortify/UpdateUserProfileInformation.php',
        'app/Http/Middleware/HandleInertiaRequests.php',
        'app/Models/User.php',
        'app/Providers/FortifyServiceProvider.php',
        'app/Providers/HorizonServiceProvider.php',
        'bootstrap/app.php',
        'bootstrap/providers.php',
        'config/cache.php',
        'config/database.php',
        'config/fortify.php',
        'config/horizon.php',
        'config/queue.php',
        'config/session.php',
        'Dockerfile',
        '.docker/php/opcache.ini',
        '.docker/php/php.ini',
        '.docker/supervisor/horizon.conf',
        '.docker/supervisor/octane.conf',
        '.docker/supervisor/scheduler.conf',
        'docker-compose.yml',
        'docker-compose.dev.yml',
        'docker-compose.prod.yml',
        'lang/de/auth.php',
        'lang/de/common.php',
        'lang/en/auth.php',
        'lang/en/common.php',
        'lang/de/validation.php',
        'lang/en/validation.php',
        'lang/de/page-layout.php',
        'lang/en/page-layout.php',
        'package.json',
        'routes/console.php',
        'routes/web.php',
        'routes/web/admin.php',
        'routes/web/auth.php',
        'routes/web/forms.php',
        'routes/web/platform.php',
        'routes/web/profile.php',
        'resources/css/app.css',
        'resources/js/app.ts',
        'resources/js/components/AppIcon.vue',
        'resources/js/components/CorePanelLogo.vue',
        'resources/js/components/UserAvatar.vue',
        'tsconfig.json',
        'vite.config.ts',
        'tests/TestCase.php',
        'tests/Feature/CorePanelAuthApiTest.php',
        'tests/Feature/CorePanelInstallationTest.php',
        'tests/Feature/CorePanelResourcesTest.php',
        'app/Http/Middleware/TrackUserPresence.php',
    );
});

it('ships split user name scaffolding without legacy user name migration fallbacks', function (): void {
    $usersMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/0001_01_01_000000_create_users_table.php');
    $fortifyCreateUser = file_get_contents(__DIR__.'/../../stubs/app/Actions/Fortify/CreateNewUser.php');
    $inertiaMiddleware = file_get_contents(__DIR__.'/../../stubs/app/Http/Middleware/HandleInertiaRequests.php');
    $presenceMiddleware = file_get_contents(__DIR__.'/../../stubs/app/Http/Middleware/TrackUserPresence.php');
    $stubUser = file_get_contents(__DIR__.'/../../stubs/app/Models/User.php');
    $userFactory = file_get_contents(__DIR__.'/../../stubs/database/factories/UserFactory.php');
    $databaseSeeder = file_get_contents(__DIR__.'/../../stubs/database/seeders/DatabaseSeeder.php');
    $bootstrap = file_get_contents(__DIR__.'/../../stubs/bootstrap/app.php');

    expect($usersMigration)->toContain("\$table->string('first_name');")
        ->and($usersMigration)->toContain("\$table->string('last_name');")
        ->and($usersMigration)->toContain("\$table->uuid('id')->primary();")
        ->and($usersMigration)->toContain("\$table->string('locale', 12)->nullable();")
        ->and($usersMigration)->toContain("\$table->string('user_id')->nullable()->index();")
        ->and($usersMigration)->toContain("\$table->boolean('requires_password_setup')->default(false);")
        ->and($usersMigration)->toContain('$table->softDeletes();')
        ->and($usersMigration)->toContain("\$table->text('two_factor_secret')->nullable();")
        ->and($usersMigration)->toContain("\$table->text('two_factor_recovery_codes')->nullable();")
        ->and($usersMigration)->toContain("\$table->timestamp('two_factor_confirmed_at')->nullable();")
        ->and($usersMigration)->not->toContain("\$table->string('name');")
        ->and(file_exists(__DIR__.'/../../stubs/database/migrations/2026_01_01_000017_add_split_name_fields_to_users_table.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/database/migrations/2026_01_01_000004_add_core_panel_fields_to_users_table.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/database/migrations/2026_01_01_000017_add_two_factor_columns_to_users_table.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/database/migrations/2026_01_01_000018_add_status_to_users_table.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/database/migrations/2026_01_01_000020_add_requires_password_setup_to_users_table.php'))->toBeFalse()
        ->and($fortifyCreateUser)->not->toContain("'name' =>")
        ->and($inertiaMiddleware)->not->toContain("'firstName' => \$firstName,\n                    'lastName' => \$lastName,\n                    'name' =>")
        ->and($inertiaMiddleware)->toContain('use CorePanel\\Support\\Users\\UserModelManager;')
        ->and($inertiaMiddleware)->toContain('$users = app(UserModelManager::class);')
        ->and($inertiaMiddleware)->toContain("'avatarUrl' => \$users->avatarUrl(\$user),")
        ->and($inertiaMiddleware)->toContain('use CorePanel\\Support\\Presence\\PresenceManager;')
        ->and($inertiaMiddleware)->toContain('$presence = app(PresenceManager::class);')
        ->and($inertiaMiddleware)->toContain("'presenceLastSeenAt' => \$presence->lastSeenTimestamp(\$user),")
        ->and($inertiaMiddleware)->toContain("'presenceStatus' => \$presence->statusFor(\$user),")
        ->and($presenceMiddleware)->toContain('use CorePanel\\Support\\Presence\\PresenceManager;')
        ->and($presenceMiddleware)->toContain('private PresenceManager $presence,')
        ->and($presenceMiddleware)->toContain('$this->presence->touch($user);')
        ->and($stubUser)->toContain('implements HasMedia, MustVerifyEmail')
        ->and($stubUser)->toContain('use HasUuids;')
        ->and($stubUser)->toContain('use InteractsWithMedia;')
        ->and($stubUser)->not->toContain('protected function mediaKey(): Attribute')
        ->and($stubUser)->toContain("'two_factor_confirmed_at' => 'datetime',")
        ->and($stubUser)->toContain('public function presenceCacheKey(): string')
        ->and($stubUser)->toContain('public function corePanelPresenceStatus(): string')
        ->and($stubUser)->toContain('public function corePanelPresenceLastSeenAt(): ?int')
        ->and($stubUser)->toContain('protected function presenceStatus(): Attribute')
        ->and($userFactory)->toContain("'first_name' => fake()->firstName(),")
        ->and($userFactory)->toContain("'last_name' => fake()->lastName(),")
        ->and($userFactory)->not->toContain("'name' => fake()->name(),")
        ->and($databaseSeeder)->toContain("'first_name' => 'Test',")
        ->and($databaseSeeder)->toContain("'last_name' => 'User',")
        ->and($databaseSeeder)->not->toContain("'name' => 'Test User',")
        ->and($bootstrap)->toContain('TrackUserPresence::class');
});

it('reapplies installed addon overlays after the core install publishes its own scaffolds', function (): void {
    $installer = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');

    expect($installer)->toContain('Synchronizing optional addon overlays')
        ->and($installer)->toContain('core-panel:tenancy:install')
        ->and($installer)->toContain("'--force' => \$options->force");
});

it('requires the runtime packages needed by the installer scaffolds', function (): void {
    /** @var array{name:string,require:array<string,string>,autoload:array{psr-4:array<string,string>}} $composer */
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['require'])->toHaveKeys([
        'inertiajs/inertia-laravel',
        'laravel/fortify',
        'laravel/horizon',
        'laravel/passport',
        'laravel/wayfinder',
        'socialiteproviders/microsoft',
        'spatie/laravel-permission',
    ])->and($composer['autoload']['psr-4'])
        ->toHaveKey('CorePanel\\Database\\Seeders\\', 'database/seeders/');
});

it('adds laravel-vue-i18n to the scaffolded frontend dependencies', function (): void {
    /** @var array{dependencies:array<string,string>,devDependencies:array<string,string>,scripts:array<string,string>} $packageJson */
    $packageJson = json_decode((string) file_get_contents(__DIR__.'/../../stubs/package.json'), true, 512, JSON_THROW_ON_ERROR);
    $useColorMode = file_get_contents(__DIR__.'/../../stubs/resources/js/composables/useColorMode.ts');
    $useSidebar = file_get_contents(__DIR__.'/../../stubs/resources/js/composables/useSidebar.ts');

    expect($packageJson['dependencies'])->toHaveKey('laravel-vue-i18n')
        ->and($packageJson['dependencies'])->toHaveKey('@vueuse/core')
        ->and($packageJson['dependencies'])->toHaveKey('lucide-vue-next')
        ->and($packageJson['dependencies'])->not->toHaveKey('primeicons')
        ->and($useColorMode)->toContain("import { useMediaQuery, useStorage } from '@vueuse/core'")
        ->and($useSidebar)->toContain("import { useMediaQuery, useStorage } from '@vueuse/core'")
        ->and($packageJson['devDependencies'])->toHaveKeys([
            '@eslint/js',
            'eslint-plugin-vue',
            'globals',
            'prettier',
            'typescript-eslint',
            'vue-eslint-parser',
        ])
        ->and($packageJson['scripts'])->toHaveKeys([
            'build',
            'lint',
            'lint:eslint',
            'lint:prettier',
            'typecheck',
        ]);
});

it('ships scaffold linting, formatting and ci workflow configuration', function (): void {
    /** @var array{compilerOptions:array<string,mixed>} $tsconfig */
    $tsconfig = json_decode((string) file_get_contents(__DIR__.'/../../stubs/tsconfig.json'), true, 512, JSON_THROW_ON_ERROR);
    $eslint = file_get_contents(__DIR__.'/../../stubs/eslint.config.mjs');
    $prettier = file_get_contents(__DIR__.'/../../stubs/prettier.config.mjs');
    $workflow = file_get_contents(__DIR__.'/../../../../.github/workflows/ci.yml');
    $releaseWorkflow = file_get_contents(__DIR__.'/../../../../.github/workflows/release.yml');
    $addonPhpstanScript = file_get_contents(__DIR__.'/../../../../.github/scripts/addon-phpstan.sh');
    $frontendQualityScript = file_get_contents(__DIR__.'/../../../../.github/scripts/frontend-quality.sh');
    $installSmokeScript = file_get_contents(__DIR__.'/../../../../.github/scripts/install-smoke.sh');
    $provisionPlaygroundsScript = file_get_contents(__DIR__.'/../../../../.github/scripts/provision-playgrounds.sh');
    $setReleaseVersionScript = file_get_contents(__DIR__.'/../../../../.github/scripts/set-release-version.php');
    $updateTestProjectsScript = file_get_contents(__DIR__.'/../../../../.github/scripts/update-test-projects.sh');
    $appVersionJson = file_get_contents(__DIR__.'/../../config/app-version.json');
    $hostAppVersionJson = file_get_contents(__DIR__.'/../../stubs/config/app-version.json');
    $versionSupport = file_get_contents(__DIR__.'/../../stubs/resources/js/support/version.ts');
    $middleware = file_get_contents(__DIR__.'/../../stubs/app/Http/Middleware/HandleInertiaRequests.php');
    /** @var array{require-dev:array<string,string>,scripts:array<string,string>} $composer */
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);
    /** @var array{require-dev:array<string,string>,scripts:array<string,string>} $addonComposer */
    $addonComposer = json_decode((string) file_get_contents(__DIR__.'/../../../core-panel-tenancy/composer.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($tsconfig['compilerOptions']['strict'])->toBeTrue()
        ->and($tsconfig['compilerOptions']['noEmit'])->toBeTrue()
        ->and($eslint)->toContain('eslint-plugin-vue')
        ->and($eslint)->toContain('typescript-eslint')
        ->and($prettier)->toContain('singleQuote: true')
        ->and($workflow)->toContain('name: CI')
        ->and($releaseWorkflow)->toContain('name: Release')
        ->and($releaseWorkflow)->toContain("RELEASE_COMMIT_PATTERN: '^🚀 Release: v?[0-9]+\\.[0-9]+\\.[0-9]+$'")
        ->and($releaseWorkflow)->toContain('php .github/scripts/set-release-version.php "${RELEASE_VERSION}"')
        ->and($releaseWorkflow)->toContain('git tag "${RELEASE_VERSION}"')
        ->and($releaseWorkflow)->toContain('packages/core-panel/config/app-version.json')
        ->and($releaseWorkflow)->toContain('packages/core-panel/stubs/config/app-version.json')
        ->and($releaseWorkflow)->toContain('name: Create GitHub release')
        ->and($releaseWorkflow)->toContain('gh release create "${RELEASE_VERSION}"')
        ->and($setReleaseVersionScript)->toContain('APP_VERSION=\'.$version')
        ->and($setReleaseVersionScript)->toContain("'display_version' => \$displayVersion")
        ->and($setReleaseVersionScript)->toContain('CorePanelApiDocumentation.php')
        ->and($appVersionJson)->toContain('"release_version": "1.0.0"')
        ->and($hostAppVersionJson)->toContain('"display_version": "1.0.0 (')
        ->and($versionSupport)->toContain("import versionInfo from '../../../config/app-version.json'")
        ->and($versionSupport)->toContain('export const APP_RELEASE_VERSION')
        ->and($versionSupport)->toContain('export function formatCommitDate')
        ->and($middleware)->toContain('AppVersionRepository::class')
        ->and($workflow)->toContain('vendor/bin/phpstan analyse')
        ->and($workflow)->toContain('vendor/bin/pint --test')
        ->and($workflow)->toContain('composer test')
        ->and($workflow)->toContain('name: Addon Quality')
        ->and($workflow)->toContain('bash .github/scripts/addon-phpstan.sh')
        ->and($workflow)->toContain('cd packages/core-panel-tenancy')
        ->and($workflow)->toContain('composer test')
        ->and($addonPhpstanScript)->toContain('cd "${addon_path}"')
        ->and($addonPhpstanScript)->toContain('composer config repositories.core-panel')
        ->and($addonPhpstanScript)->toContain('composer update --no-interaction --no-progress --prefer-dist')
        ->and($addonPhpstanScript)->toContain('vendor/bin/phpstan analyse --no-progress --memory-limit=1G -c phpstan.neon.dist')
        ->and($workflow)->toContain('name: Frontend Quality (core-package)')
        ->and($workflow)->toContain('name: Frontend Quality (tenancy-addon)')
        ->and($workflow)->toContain('bash .github/scripts/frontend-quality.sh core-package')
        ->and($workflow)->toContain('bash .github/scripts/frontend-quality.sh tenancy-addon')
        ->and($workflow)->toContain('name: Install Smoke (core-package)')
        ->and($workflow)->toContain('name: Install Smoke (tenancy-addon)')
        ->and($frontendQualityScript)->toContain('case "${variant}" in')
        ->and($frontendQualityScript)->toContain('workspace="$(mktemp -d /tmp/core-panel-frontend-${variant}-XXXXXX)"')
        ->and($frontendQualityScript)->toContain('tar -C "${repo_root}/packages/core-panel-tenancy/stubs" -cf - . | tar -C "${workspace}" -xf -')
        ->and($frontendQualityScript)->toContain('npm install')
        ->and($frontendQualityScript)->toContain('npm run lint')
        ->and($frontendQualityScript)->toContain('npm run build')
        ->and($workflow)->toContain('bash .github/scripts/install-smoke.sh core-package')
        ->and($workflow)->toContain('bash .github/scripts/install-smoke.sh tenancy-addon')
        ->and($installSmokeScript)->toContain('php artisan core-panel:install')
        ->and($installSmokeScript)->toContain('mkdir -p "${repo_root}/apps"')
        ->and($installSmokeScript)->toContain('app_dir="${repo_root}/apps/ci-${variant}"')
        ->and($installSmokeScript)->toContain('install_tenancy="false"')
        ->and($installSmokeScript)->toContain('install_tenancy="true"')
        ->and($installSmokeScript)->toContain('php artisan serve --host=127.0.0.1')
        ->and($installSmokeScript)->toContain('wait_for_server "${path}"')
        ->and($installSmokeScript)->toContain('http://127.0.0.1:${serve_port}')
        ->and($installSmokeScript)->toContain('--header "Host: ${app_host}"')
        ->and($provisionPlaygroundsScript)->toContain('composer create-project laravel/laravel "${app_dir}" "^13.0" --no-scripts --no-interaction --prefer-dist')
        ->and($provisionPlaygroundsScript)->toContain('composer config repositories.core-panel')
        ->and($provisionPlaygroundsScript)->toContain('composer require mapo-89/core-panel:dev-main --no-interaction --prefer-dist')
        ->and($provisionPlaygroundsScript)->toContain('php artisan core-panel:install')
        ->and($provisionPlaygroundsScript)->toContain('--install-tenancy="${install_tenancy}"')
        ->and($provisionPlaygroundsScript)->toContain('npm install')
        ->and($provisionPlaygroundsScript)->toContain('npm run build')
        ->and($updateTestProjectsScript)->toContain('target="${1:-all}"')
        ->and($updateTestProjectsScript)->toContain('local app_dir="${apps_root}/${playground}"')
        ->and($updateTestProjectsScript)->toContain('php artisan core-panel:update --force --with-addon-updates')
        ->and($updateTestProjectsScript)->toContain('php artisan core-panel:update --force')
        ->and($updateTestProjectsScript)->toContain('npm run build')
        ->and($installSmokeScript)->toContain('grep -q \'^TENANCY_CENTRAL_CONNECTION=pgsql$\' .env')
        ->and($installSmokeScript)->toContain('php artisan route:list --name=tenant.core-panel.users.index --except-vendor')
        ->and($composer['require-dev'])->toHaveKey('larastan/larastan')
        ->and($addonComposer['require-dev'])->toHaveKey('larastan/larastan')
        ->and($addonComposer['scripts'])->toHaveKey('analyse')
        ->and($composer['scripts'])->toHaveKeys([
            'analyse',
            'apps:provision',
            'apps:update',
            'check-style',
            'format',
            'release:prepare',
            'test',
            'test:update-test-projects',
        ]);
});

it('synchronizes the environment file with the core panel defaults', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-env-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    file_put_contents($temporaryBasePath.'/.env', implode(PHP_EOL, [
        'APP_NAME=Laravel',
        'DB_CONNECTION=sqlite',
        'CACHE_STORE=database',
        '',
    ]));

    app(SynchronizesEnvironmentFile::class)->sync($temporaryBasePath);

    $contents = file_get_contents($temporaryBasePath.'/.env');

    expect($contents)->toContain('APP_NAME=Laravel')
        ->and($contents)->toContain('DB_CONNECTION=sqlite')
        ->and($contents)->toContain('CACHE_STORE=database')
        ->and($contents)->toContain('QUEUE_CONNECTION=redis')
        ->and($contents)->toContain('REDIS_HOST=127.0.0.1');
});

it('reads the packaged app version metadata from app-version json', function (): void {
    $version = app(AppVersionRepository::class)->current();

    expect($version['release_version'])->toBe('1.0.0')
        ->and($version['display_version'])->not->toBeNull()
        ->and($version)->toHaveKeys([
            'release_version',
            'display_version',
            'image_version',
            'commit',
            'commit_date',
        ]);
});

it('does not use legacy .stub suffixes inside the host template tree', function (): void {
    foreach (ScaffoldsCorePanelStubs::paths() as $path) {
        expect($path)->not->toEndWith('.stub');
    }
});

it('ships a visible domain scaffold structure for host applications', function (): void {
    expect(is_dir(__DIR__.'/../../stubs/app/Actions/Fortify'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/Actions/Fortify/CreateNewUser.php'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/Actions/Fortify/UpdateUserProfileInformation.php'))->toBeTrue()
        ->and(is_dir(__DIR__.'/../../stubs/app/Http/Middleware'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/Http/Middleware/HandleInertiaRequests.php'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/Http/Middleware/TrackUserPresence.php'))->toBeTrue()
        ->and(is_dir(__DIR__.'/../../stubs/app/Models'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/Models/User.php'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/Models/UserGroup.php'))->toBeTrue()
        ->and(is_dir(__DIR__.'/../../stubs/app/OpenApi/Paths'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/OpenApi/CorePanelApiDocumentation.php'))->toBeTrue()
        ->and(is_dir(__DIR__.'/../../stubs/app/Providers'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/Providers/FortifyServiceProvider.php'))->toBeTrue()
        ->and(is_file(__DIR__.'/../../stubs/app/Providers/HorizonServiceProvider.php'))->toBeTrue();
});

it('uses readable generator template filenames and still avoids legacy package stub suffixes in defaults', function (): void {
    $generatorTemplates = glob(__DIR__.'/../../stubs/core-panel/generators/*') ?: [];
    $crudTemplates = glob(__DIR__.'/../../stubs/core-panel/generators/crud/*') ?: [];

    foreach (array_merge($generatorTemplates, $crudTemplates) as $path) {
        if (is_dir($path)) {
            continue;
        }

        expect($path)->not->toEndWith('.stub');
    }
});

it('ships host route templates that can be composed by the tenancy addon', function (): void {
    $webRoutes = file_get_contents(__DIR__.'/../../stubs/routes/web.php');
    $routeManifest = file_get_contents(__DIR__.'/../../stubs/routes/web/routes.php');

    expect($webRoutes)->toContain("Route::redirect('/', config('core-panel.route_prefix', 'admin'));")
        ->and($webRoutes)->toContain("\$shouldLoadPublicRoutes = ! file_exists(__DIR__.'/universal.php');")
        ->and($webRoutes)->not->toContain("if (file_exists(__DIR__.'/web/tenants.php'))")
        ->and($routeManifest)->toContain("'dashboard.php'")
        ->and($routeManifest)->toContain("'platform.php'")
        ->and($routeManifest)->toContain("'forms.php'");
});

it('keeps canonical web route fragments in the package runtime tree instead of mirrored stub copies', function (): void {
    expect(file_exists(__DIR__.'/../../routes/web/admin.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../routes/web/dashboard.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../routes/web/admin/settings.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../routes/web/admin/users.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../routes/web/admin/logs.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../routes/web/auth.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../routes/web/forms.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../routes/web/platform.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../routes/web/profile.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../stubs/routes/web/admin.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/routes/web/auth.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/routes/web/forms.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/routes/web/platform.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/routes/web/profile.php'))->toBeFalse();
});

it('keeps tenancy-only frontend types out of the core scaffold', function (): void {
    $corePanelTypes = file_get_contents(__DIR__.'/../../stubs/resources/js/types/core-panel.ts');

    expect($corePanelTypes)->not->toContain('export type CorePanelTenancyContext = {');
});

it('ships an inertia root view template for the host application', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/resources/views/app.blade.php');

    expect($contents)->toContain("@vite(['resources/css/app.css', 'resources/js/app.ts'])")
        ->and($contents)->toContain('<x-inertia::head />')
        ->and($contents)->toContain('<x-inertia::app />');
});

it('ships a vite config that exposes localhost instead of the invalid 0.0.0.0 browser origin', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/vite.config.ts');

    expect($contents)->toContain("import path from 'node:path'")
        ->and($contents)->toContain("find: '@'")
        ->and($contents)->toContain("replacement: path.resolve(__dirname, 'resources/js')")
        ->and($contents)->toContain("find: '@core-panel'")
        ->and($contents)->toContain('manualChunks(id)')
        ->and($contents)->toContain("return 'vendor-primevue'")
        ->and($contents)->toContain("return 'vendor-vue'")
        ->and($contents)->toContain("ignored: ['**/storage/framework/views/**']")
        ->and($contents)->toContain("message.includes('Sourcemap is likely to be incorrect')")
        ->and($contents)->toContain("host: '0.0.0.0'")
        ->and($contents)->toContain("origin: 'http://localhost:5173'")
        ->and($contents)->toContain("origin: ['http://localhost:8000', 'http://127.0.0.1:8000']")
        ->and($contents)->toContain("import i18n from 'laravel-vue-i18n/vite'")
        ->and($contents)->toContain('const additionalLangPaths = [')
        ->and($contents)->toContain("path.resolve(__dirname, 'lang/vendor/core-panel')")
        ->and($contents)->toContain("path.resolve(__dirname, '../resources/lang')")
        ->and($contents)->toContain('additionalLangPaths,')
        ->and($contents)->toContain("input: ['resources/css/app.css', 'resources/js/app.ts']")
        ->and($contents)->toContain("host: 'localhost'");
});

it('ships a bootstrap template that stays focused on web middleware and passport route aliases', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/bootstrap/app.php');

    expect($contents)->toContain('redirectGuestsTo(static fn (Request')
        ->and($contents)->toContain('redirectUsersTo(static fn (Request $request): string =>')
        ->and($contents)->toContain("'/login'")
        ->and($contents)->toContain("config('core-panel.route_prefix', 'admin')")
        ->and($contents)->not->toContain('use CorePanelTenancy\Http\Middleware\SetTenantAwareSessionCookie;')
        ->and($contents)->toContain("\$tenantSessionCookieMiddlewareClass = 'CorePanelTenancy\\\\Http\\\\Middleware\\\\SetTenantAwareSessionCookie';")
        ->and($contents)->toContain('$tenantSessionCookieMiddleware = class_exists($tenantSessionCookieMiddlewareClass)')
        ->and($contents)->toContain('$middleware->web(prepend: $tenantSessionCookieMiddleware);')
        ->and($contents)->toContain('$corePanelRoutingPaths = static function (): array {')
        ->and($contents)->not->toContain('function corePanelRoutingPaths(): array')
        ->and($contents)->not->toContain('statefulApi()')
        ->and($contents)->not->toContain('CheckAbilities::class')
        ->and($contents)->not->toContain('CheckForAnyAbility::class');
});

it('ships passport-oriented defaults in the scaffold environment template', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/.env.example');

    expect($contents)->toContain('SESSION_DOMAIN=')
        ->and($contents)->toContain('LOG_CHANNEL=daily')
        ->and($contents)->toContain('DB_HOST=127.0.0.1')
        ->and($contents)->toContain('FILESYSTEM_DISK=public')
        ->and($contents)->toContain('REDIS_HOST=127.0.0.1')
        ->and($contents)->toContain('DB_DATABASE_TEST=core_panel_test')
        ->and($contents)->toContain('CORE_PANEL_PASSPORT_TOKEN_TTL_MINUTES=15')
        ->and($contents)->not->toContain('SANCTUM_STATEFUL_DOMAINS=')
        ->and($contents)->not->toContain('CORE_PANEL_API_DRIVER=')
        ->and($contents)->not->toContain('CORE_PANEL_DARK_MODE=')
        ->and($contents)->not->toContain('CORE_PANEL_PUBLISH_THEME=')
        ->and($contents)->not->toContain('CORE_PANEL_FILES_DISK=')
        ->and($contents)->not->toContain('CORE_PANEL_PACKAGE_PATH=')
        ->and($contents)->not->toContain('CORE_PANEL_PACKAGE_CONTAINER_PATH=/opt/core-panel-package')
        ->and($contents)->not->toContain('CORE_PANEL_TENANCY_ENABLED=')
        ->and($contents)->not->toContain('CORE_PANEL_TENANCY_MODE=')
        ->and($contents)->not->toContain('CORE_PANEL_TENANCY_ADAPTER=')
        ->and($contents)->not->toContain('CORE_PANEL_TENANCY_RESOLVER=')
        ->and($contents)->not->toContain('CORE_PANEL_ALLOW_TENANT_SWITCHING=')
        ->and($contents)->not->toContain('CENTRAL_DOMAINS=')
        ->and($contents)->not->toContain('TENANCY_CENTRAL_CONNECTION=');
});

it('ships a Fortify config that supports both central-only and tenancy-enabled hosts', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/config/fortify.php');

    expect($contents)->not->toContain('InitializeTenancyByDomain')
        ->and($contents)->toContain("'middleware' => ['web']")
        ->and($contents)->toContain("'auth_middleware' => 'auth'")
        ->and($contents)->toContain("'lowercase_usernames' => false")
        ->and($contents)->toContain("'paths' => [")
        ->and($contents)->toContain("'redirects' => [")
        ->and($contents)->toContain("'views' => true");
});

it('keeps the core scaffold free of tenancy-specific app service provider overrides', function (): void {
    expect(ScaffoldsCorePanelStubs::paths())->not->toContain('app/Providers/AppServiceProvider.php');
});

it('ships docker scaffolding for package development and skeleton app runtime', function (): void {
    $dockerfile = file_get_contents(__DIR__.'/../../stubs/Dockerfile');
    $baseCompose = file_get_contents(__DIR__.'/../../stubs/docker-compose.yml');
    $developmentCompose = file_get_contents(__DIR__.'/../../stubs/docker-compose.dev.yml');
    $productionCompose = file_get_contents(__DIR__.'/../../stubs/docker-compose.prod.yml');
    $phpIni = file_get_contents(__DIR__.'/../../stubs/.docker/php/php.ini');
    $opcacheIni = file_get_contents(__DIR__.'/../../stubs/.docker/php/opcache.ini');
    $octaneSupervisor = file_get_contents(__DIR__.'/../../stubs/.docker/supervisor/octane.conf');
    $horizonSupervisor = file_get_contents(__DIR__.'/../../stubs/.docker/supervisor/horizon.conf');
    $schedulerSupervisor = file_get_contents(__DIR__.'/../../stubs/.docker/supervisor/scheduler.conf');

    expect($dockerfile)->toContain('FROM dunglas/frankenphp:1-php8.5-bookworm AS php-base')
        ->and($dockerfile)->toContain('FROM php-base AS vendor')
        ->and($dockerfile)->toContain('FROM node:22-bookworm-slim AS node-base')
        ->and($dockerfile)->toContain('FROM php-base AS assets')
        ->and($dockerfile)->toContain('FROM php-base AS runtime')
        ->and($dockerfile)->not->toContain('FROM composer:2 AS vendor')
        ->and($dockerfile)->not->toContain('CORE_PANEL_PACKAGE_CONTAINER_PATH=/opt/core-panel-package')
        ->and($dockerfile)->not->toContain('core-panel-prepare-composer')
        ->and($dockerfile)->not->toContain('core-panel-restore-composer')
        ->and($dockerfile)->not->toContain('COPY --from=core_panel_package . /opt/core-panel-package')
        ->and($dockerfile)->toContain('--no-scripts')
        ->and($dockerfile)->toContain('COPY --from=node-base /usr/local/ /usr/local/')
        ->and($dockerfile)->toContain('COPY --from=vendor /app/vendor /app/vendor')
        ->and($dockerfile)->toContain('RUN php artisan package:discover --ansi')
        ->and($dockerfile)->toContain('&& npm run build')
        ->and($dockerfile)->toContain('COPY --from=assets /app/public/build /var/www/html/public/build')
        ->and($dockerfile)->toContain('&& mkdir -p /var/log/supervisor /var/www/html/storage/logs')
        ->and($dockerfile)->toContain('COPY package*.json ./')
        ->and($dockerfile)->toContain('RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi')
        ->and($dockerfile)->toContain('install-php-extensions')
        ->and($dockerfile)->toContain('exif')
        ->and($dockerfile)->toContain('pcntl')
        ->and($dockerfile)->toContain('pdo_pgsql')
        ->and($dockerfile)->toContain('redis')
        ->and($dockerfile)->toContain('gd')
        ->and($dockerfile)->toContain('HEALTHCHECK --interval=15s --timeout=5s --retries=5 CMD curl -fsS http://127.0.0.1:8000/up || exit 1')
        ->and($baseCompose)->toContain('app-test:')
        ->and($baseCompose)->toContain('horizon:')
        ->and($baseCompose)->toContain('scheduler:')
        ->and($baseCompose)->toContain('postgres:')
        ->and($baseCompose)->toContain('redis:')
        ->and($baseCompose)->toContain('mailpit:')
        ->and($baseCompose)->not->toContain('additional_contexts:')
        ->and($baseCompose)->not->toContain('CORE_PANEL_PACKAGE_CONTAINER_PATH')
        ->and($baseCompose)->toContain('DB_HOST: postgres')
        ->and($baseCompose)->toContain('REDIS_HOST: redis')
        ->and($baseCompose)->toContain('DB_DATABASE: ${DB_DATABASE_TEST:-core_panel_test}')
        ->and($baseCompose)->toContain('pg_isready -U ${POSTGRES_USER:-core_panel} -d ${POSTGRES_DB:-core_panel}')
        ->and($baseCompose)->toContain('redis-cli')
        ->and($developmentCompose)->not->toContain('composer install --no-interaction')
        ->and($developmentCompose)->not->toContain('core-panel-prepare-composer')
        ->and($developmentCompose)->not->toContain('core-panel-restore-composer')
        ->and($developmentCompose)->not->toContain('CORE_PANEL_PACKAGE_CONTAINER_PATH')
        ->and($developmentCompose)->toContain('target: php-base')
        ->and($developmentCompose)->toContain('- ./:/var/www/html')
        ->and($developmentCompose)->toContain('php artisan optimize:clear')
        ->and($developmentCompose)->toContain('php artisan serve --host=0.0.0.0 --port=8000')
        ->and($developmentCompose)->not->toContain('php artisan migrate --force')
        ->and($developmentCompose)->toContain('CREATE DATABASE \\\\\\"$${DB_DATABASE_TEST:-core_panel_test}\\\\\\"')
        ->and($developmentCompose)->toContain('volumes:')
        ->and($developmentCompose)->toContain('postgres-data:')
        ->and($developmentCompose)->toContain('redis-data:')
        ->and($productionCompose)->toContain('nginx:')
        ->and($productionCompose)->toContain('/usr/bin/supervisord')
        ->and($productionCompose)->toContain('postgres-data:')
        ->and($productionCompose)->toContain('redis-data:')
        ->and($phpIni)->toContain('upload_max_filesize=64M')
        ->and($opcacheIni)->toContain('opcache.enable=1')
        ->and($octaneSupervisor)->toContain('artisan octane:start')
        ->and($horizonSupervisor)->toContain('artisan horizon')
        ->and($schedulerSupervisor)->toContain('artisan schedule:work');

    expect(file_exists(__DIR__.'/../../stubs/package-lock.json'))->toBeTrue();
});

it('ships a user stub that uses passport tokens without sanctum compatibility shims', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/app/Models/User.php');
    $fortifyProvider = file_get_contents(__DIR__.'/../../stubs/app/Providers/FortifyServiceProvider.php');

    expect($contents)->toContain('use PassportHasApiTokens {')
        ->and($contents)->not->toContain('Laravel\\Passport\\Contracts\\OAuthenticatable')
        ->and($contents)->not->toContain('implements MustVerifyEmail, OAuthenticatable')
        ->and($contents)->not->toContain('Sanctum')
        ->and($contents)->not->toContain('createPassportToken')
        ->and($fortifyProvider)->toContain('use App\\Actions\\Fortify\\CreateNewUser;')
        ->and($fortifyProvider)->toContain('use App\\Actions\\Fortify\\ResetUserPassword;')
        ->and($fortifyProvider)->toContain('use App\\Actions\\Fortify\\UpdateUserPassword;')
        ->and($fortifyProvider)->toContain('use App\\Actions\\Fortify\\UpdateUserProfileInformation;')
        ->and($fortifyProvider)->toContain('use CorePanel\\Http\\Responses\\ResetPasswordResponse;')
        ->and($fortifyProvider)->toContain('use Laravel\\Fortify\\Contracts\\PasswordResetResponse as PasswordResetResponseContract;')
        ->and($fortifyProvider)->toContain('$this->app->singleton(PasswordResetResponseContract::class, ResetPasswordResponse::class);');
});

it('redirects successful password resets back to the login path without requiring a login route name', function (): void {
    $response = new ResetPasswordResponse('passwords.reset');
    $request = Request::create('/reset-password', 'POST');
    $result = $response->toResponse($request);

    expect($result)->toBeInstanceOf(RedirectResponse::class)
        ->and($result->getTargetUrl())->toEndWith('/login')
        ->and($result->getSession()->get('status'))->toBe(__('page-auth.password_reset_success'));
});

it('removes a conflicting vite.config.js when scaffolding a host application', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-vite-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);
    file_put_contents($temporaryBasePath.'/vite.config.js', 'legacy');

    app(ScaffoldsCorePanelStubs::class)->scaffold(true, $temporaryBasePath);

    expect(file_exists($temporaryBasePath.'/vite.config.js'))->toBeFalse()
        ->and(file_exists($temporaryBasePath.'/vite.config.ts'))->toBeTrue();
});

it('replaces the default laravel baseline migrations when scaffolding a host application', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-migrations-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/database/migrations', 0777, true);
    mkdir($temporaryBasePath.'/app/Models', 0777, true);
    mkdir($temporaryBasePath.'/database/factories', 0777, true);
    mkdir($temporaryBasePath.'/database/seeders', 0777, true);

    file_put_contents($temporaryBasePath.'/database/migrations/0001_01_01_000000_create_users_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }
};
PHP);
    file_put_contents($temporaryBasePath.'/app/Models/User.php', 'legacy user model');
    file_put_contents($temporaryBasePath.'/database/factories/UserFactory.php', 'legacy user factory');
    file_put_contents($temporaryBasePath.'/database/seeders/DatabaseSeeder.php', 'legacy database seeder');

    app(ScaffoldsCorePanelStubs::class)->scaffold(false, $temporaryBasePath);

    $contents = file_get_contents($temporaryBasePath.'/database/migrations/0001_01_01_000000_create_users_table.php');
    $userModel = file_get_contents($temporaryBasePath.'/app/Models/User.php');
    $userFactory = file_get_contents($temporaryBasePath.'/database/factories/UserFactory.php');
    $databaseSeeder = file_get_contents($temporaryBasePath.'/database/seeders/DatabaseSeeder.php');

    expect($contents)->toContain("\$table->uuid('id')->primary();")
        ->and($contents)->not->toContain('$table->id();')
        ->and($contents)->not->toContain("\$table->string('name');")
        ->and($userModel)->toContain('use HasUuids;')
        ->and($userFactory)->toContain("'first_name' => fake()->firstName(),")
        ->and($databaseSeeder)->toContain("'first_name' => 'Test',");
});

it('replaces the default laravel bootstrap and web entrypoints when scaffolding a host application', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-host-entrypoints-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/bootstrap', 0777, true);
    mkdir($temporaryBasePath.'/routes', 0777, true);
    mkdir($temporaryBasePath.'/resources/views', 0777, true);

    file_put_contents($temporaryBasePath.'/bootstrap/app.php', <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
PHP);
    file_put_contents($temporaryBasePath.'/routes/web.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
PHP);
    file_put_contents($temporaryBasePath.'/routes/console.php', <<<'PHP'
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
PHP);
    file_put_contents($temporaryBasePath.'/resources/views/welcome.blade.php', <<<'BLADE'
<!DOCTYPE html>
<html>
    <head>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
</html>
BLADE);

    app(ScaffoldsCorePanelStubs::class)->scaffold(false, $temporaryBasePath);

    $bootstrap = file_get_contents($temporaryBasePath.'/bootstrap/app.php');
    $webRoutes = file_get_contents($temporaryBasePath.'/routes/web.php');
    $consoleRoutes = file_get_contents($temporaryBasePath.'/routes/console.php');

    expect($bootstrap)->toContain('->withRouting(')
        ->and($bootstrap)->toContain('api: $apiRoutes,')
        ->and($bootstrap)->toContain('redirectGuestsTo(static fn (Request')
        ->and($webRoutes)->toContain("Route::redirect('/', config('core-panel.route_prefix', 'admin'));")
        ->and($webRoutes)->not->toContain("return view('welcome');")
        ->and($consoleRoutes)->toContain("if ((bool) config('core-panel.horizon.enabled', true) && app()->bound('command.horizon.snapshot')) {")
        ->and(file_exists($temporaryBasePath.'/resources/views/welcome.blade.php'))->toBeFalse()
        ->and(file_exists($temporaryBasePath.'/resources/views/app.blade.php'))->toBeTrue();
});

it('removes legacy sass theme files when scaffolding a host application', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-theme-cleanup-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath.'/resources/css/theme', 0777, true);

    file_put_contents($temporaryBasePath.'/resources/css/app.css', '/* legacy app css */');
    file_put_contents($temporaryBasePath.'/resources/css/theme/_auth.scss', '.legacy-auth {}');
    file_put_contents($temporaryBasePath.'/resources/css/theme/theme.scss', '@import "./_auth.scss";');

    app(ScaffoldsCorePanelStubs::class)->scaffold(false, $temporaryBasePath);

    expect(file_get_contents($temporaryBasePath.'/resources/css/app.css'))->toContain("@import '@core-panel/theme/core-panel/index.css';")
        ->and(file_exists($temporaryBasePath.'/resources/css/theme/_auth.scss'))->toBeFalse()
        ->and(file_exists($temporaryBasePath.'/resources/css/theme/theme.scss'))->toBeFalse()
        ->and(file_exists($temporaryBasePath.'/resources/css/theme/_auth.css'))->toBeTrue()
        ->and(file_exists($temporaryBasePath.'/resources/css/theme/theme.css'))->toBeTrue();
});

it('merges the scaffold package.json into an existing host package.json', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-package-json-'.bin2hex(random_bytes(5));

    mkdir($temporaryBasePath, 0777, true);

    file_put_contents($temporaryBasePath.'/package.json', json_encode([
        'name' => 'host-app',
        'private' => true,
        'scripts' => [
            'custom' => 'echo host',
        ],
        'dependencies' => [
            'axios' => '^1.7.0',
        ],
        'devDependencies' => [
            'sass' => '^1.93.2',
            'vitest' => '^3.0.0',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    app(ScaffoldsCorePanelStubs::class)->scaffold(false, $temporaryBasePath);

    $packageJson = json_decode((string) file_get_contents($temporaryBasePath.'/package.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($packageJson)->toBeArray()
        ->and($packageJson['name'])->toBe('host-app')
        ->and($packageJson['scripts'])->toHaveKey('custom')
        ->and($packageJson['scripts'])->toHaveKey('build')
        ->and($packageJson['scripts'])->toHaveKey('lint')
        ->and($packageJson['dependencies'])->toHaveKey('axios')
        ->and($packageJson['dependencies'])->toHaveKey('vue')
        ->and($packageJson['devDependencies'])->toHaveKey('vitest')
        ->and($packageJson['devDependencies'])->toHaveKey('@vitejs/plugin-vue')
        ->and($packageJson['devDependencies'])->not->toHaveKey('sass')
        ->and(glob($temporaryBasePath.'/.core-panel-backups/*/package.json'))->not->toBe([]);
});

// it('imports the published theme css from the javascript theme target path', function (): void {
//     $contents = file_get_contents(__DIR__.'/../../stubs/resources/css/app.css');

//     expect($contents)->toContain('@import "../js/theme/core-panel/index.css";');
// });

it('uses host-aware theme import paths in published javascript assets', function (): void {
    $appEntry = file_get_contents(__DIR__.'/../../stubs/resources/js/plugins/core-panel.ts');
    $baseStyles = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_base.css');
    $colorModeComposable = file_get_contents(__DIR__.'/../../stubs/resources/js/composables/useColorMode.ts');
    $hostEntry = file_get_contents(__DIR__.'/../../stubs/resources/js/app.ts');
    $forgotPasswordPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/ForgotPassword.vue');
    $dashboardPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Dashboard/Index.vue');
    $loginPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/Login.vue');
    $registerPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/Register.vue');
    $profileConnectionsTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileConnectionsTab.vue');
    $profileSecurityTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileSecurityTab.vue');

    expect($appEntry)->toContain("from '@core-panel/theme/core-panel'")
        ->and($colorModeComposable)->toContain("from '@core-panel/theme/core-panel'")
        ->and($hostEntry)->toContain("import { I18n, i18nVue } from 'laravel-vue-i18n'")
        ->and($hostEntry)->toContain('const lazyLanguageModules = import.meta.glob<{')
        ->and($hostEntry)->toContain('default: Record<string, string>')
        ->and($hostEntry)->toContain('const loader =')
        ->and($hostEntry)->toContain('lazyLanguageModules[`../../lang/php_${lang}.json`]')
        ->and($hostEntry)->toContain('await I18n.getSharedInstance(i18nOptions).loadLanguageAsync(')
        ->and($hostEntry)->toContain('app.use(i18nVue, i18nOptions)')
        ->and($baseStyles)->toContain('.cp-icon {')
        ->and($dashboardPage)->toContain('<AppLayout')
        ->and($dashboardPage)->toContain(':subtitle="labels.centralContext"')
        ->and($dashboardPage)->toContain(':title="labels.title"')
        ->and($dashboardPage)->toContain("key: 'pendingJobs'")
        ->and($dashboardPage)->toContain("key: 'failedJobs'")
        ->and($dashboardPage)->toContain("trans('dashboard.guidance_title')")
        ->and($dashboardPage)->toContain('const guidanceCards = computed(() => [')
        ->and($dashboardPage)->not->toContain("import users from '@/routes/core-panel/users'")
        ->and($dashboardPage)->not->toContain("import settings from '@/routes/core-panel/settings'")
        ->and($dashboardPage)->not->toContain("from '../../../../actions")
        ->and($dashboardPage)->not->toContain("from '../../../../routes")
        ->and($dashboardPage)->not->toContain("from '../../../routes/core-panel/users'")
        ->and($forgotPasswordPage)->not->toContain("from '@/routes/auth'")
        ->and($forgotPasswordPage)->toContain('href="/login"')
        ->and($loginPage)->not->toContain("from '@/routes/auth'")
        ->and($loginPage)->toContain("form.setError('email', fallbackError)")
        ->and($loginPage)->toContain('page.props.errors?.socialite')
        ->and($loginPage)->toContain('page.props.flash?.status ?? null')
        ->and($loginPage)->toContain('<Message v-if="statusMessage" severity="success">')
        ->and($loginPage)->toContain('href="/register"')
        ->and($loginPage)->toContain("from '@/routes/socialite'")
        ->and($loginPage)->not->toContain("from '@/routes/password'")
        ->and($loginPage)->toContain('href="/forgot-password"')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/TwoFactorChallenge.vue'))->toContain("import twoFactorLoginRoutes from '@/routes/two-factor/login'")
        ->and($registerPage)->not->toContain("from '@/routes/auth'")
        ->and($registerPage)->toContain('href="/login"')
        ->and($loginPage)->not->toContain('SocialiteRedirectController')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/SocialProviderConnectionCard.vue'))->toContain("from '@/routes/socialite'")
        ->and($profileSecurityTab)->toContain("from '@/routes/password/confirm'")
        ->and($profileConnectionsTab)->not->toContain('LinkSocialAccountController')
        ->and($profileConnectionsTab)->not->toContain('UnlinkSocialAccountController');
});

it('renders the forgot-password action after the submit button while shifting with a status message', function (): void {
    $loginPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/Login.vue');
    $authStyles = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_auth.css');

    expect($loginPage)->not->toBeFalse()
        ->and($authStyles)->not->toBeFalse();

    $passwordFieldPosition = strpos($loginPage, 'class="auth-form__field auth-form__field--password"');
    $submitPosition = strpos($loginPage, 'class="auth-form__submit"');
    $forgotPosition = strpos($loginPage, 'class="auth-form__forgot"');

    expect($passwordFieldPosition)->not->toBeFalse()
        ->and($submitPosition)->not->toBeFalse()
        ->and($forgotPosition)->not->toBeFalse()
        ->and($submitPosition)->toBeGreaterThan($passwordFieldPosition)
        ->and($forgotPosition)->toBeGreaterThan($submitPosition)
        ->and($loginPage)->not->toContain('class="auth-form__messages"')
        ->and($loginPage)->toContain(':invalid="Boolean(form.errors.email)"')
        ->and($loginPage)->toContain(':invalid="Boolean(form.errors.password)"')
        ->and($loginPage)->toContain('class="auth-form__field-error"')
        ->and($loginPage)->toContain('const socialiteError = computed(() => page.props.errors?.socialite ?? null)')
        ->and($loginPage)->toContain('const statusMessage = computed(() => page.props.flash?.status ?? null)')
        ->and($loginPage)->toContain("form.setError('email', fallbackError)")
        ->and($authStyles)->toContain('grid-template-columns: minmax(0, 1fr) auto;')
        ->and($authStyles)->not->toContain('.auth-form__field-header {')
        ->and($authStyles)->toContain('.auth-form .p-inputtext.p-invalid,')
        ->and($authStyles)->toContain('.auth-form .p-password-input.p-invalid {')
        ->and($authStyles)->toContain('grid-row: 2;')
        ->and($authStyles)->toContain('.auth-form:has(> .p-message) {')
        ->and($authStyles)->toContain('.auth-form__field.auth-form__field--password,')
        ->and($authStyles)->toContain('grid-row: 3;');
});

it('styles the two-factor otp inputs with readable sizing in the auth theme', function (): void {
    $authStyles = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_auth.css');

    expect($authStyles)->toContain('.auth-form .p-inputotp {')
        ->and($authStyles)->toContain('.auth-form .p-inputotp-input.p-inputtext {')
        ->and($authStyles)->toContain('height: 3.25rem;')
        ->and($authStyles)->toContain('font-size: 1.125rem;')
        ->and($authStyles)->toContain('padding-inline: 0 !important;')
        ->and($authStyles)->toContain('text-align: center;');
});

it('ships shared live password requirement feedback for auth and admin password setup flows', function (): void {
    $passwordRequirementsList = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/PasswordRequirementsList.vue');
    $passwordField = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/fields/PasswordField.vue');
    $passwordRequirementsHelper = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/passwordRequirements.ts');
    $formRenderer = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');
    $translatedPassword = file_get_contents(__DIR__.'/../../resources/js/components/TranslatedPassword.vue');
    $registerPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/Register.vue');
    $resetPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/ResetPassword.vue');
    $profilePasswordTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfilePasswordTab.vue');
    $userFormFields = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserFormFields.vue');
    $utilitiesStyles = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/utilities.css');
    $commonEn = file_get_contents(__DIR__.'/../../resources/lang/en/common.php');
    $commonDe = file_get_contents(__DIR__.'/../../resources/lang/de/common.php');

    expect($passwordRequirementsList)->toContain("trans('common.auth.password_rule_min_length'")
        ->and($passwordRequirementsList)->toContain("trans('common.auth.password_rule_confirmation')")
        ->and($passwordRequirementsList)->not->toContain('password_strength_label')
        ->and($translatedPassword)->toContain("import PasswordRequirementsList from '@core-panel/components/FormBuilder/fields/PasswordRequirementsList.vue'")
        ->and($translatedPassword)->toContain("import { computed, useAttrs } from 'vue'")
        ->and($translatedPassword)->toContain('const showsStrengthFeedback = computed(() => props.minLength !== null)')
        ->and($translatedPassword)->toContain('const showsConfirmationFeedback = computed(')
        ->and($translatedPassword)->toContain('const showsOverlay = computed(')
        ->and($translatedPassword)->toContain('const passThrough = computed<Record<string, unknown> | undefined>(() => {')
        ->and($translatedPassword)->toContain('style: `${existingStyle}display: none;`,')
        ->and($translatedPassword)->toContain(':feedback="showsOverlay"')
        ->and($translatedPassword)->toContain(':pt="passThrough"')
        ->and($translatedPassword)->toContain(':prompt-label="labels.prompt"')
        ->and($translatedPassword)->toContain("trans('common.auth.password_strength_prompt')")
        ->and($translatedPassword)->toContain('showsConfirmationFeedback ? matchPassword : undefined')
        ->and($translatedPassword)->toContain(':min-length="minLength"')
        ->and($passwordRequirementsList)->toContain('props.confirmation !== undefined && props.confirmation !== null')
        ->and($passwordField)->not->toContain('import PasswordRequirementsList from')
        ->and($passwordField)->toContain("import TranslatedPassword from '@/components/TranslatedPassword.vue'")
        ->and($passwordField)->toContain('<TranslatedPassword')
        ->and($passwordField)->not->toContain(':feedback="Boolean(passwordRequirements)"')
        ->and($passwordField)->toContain(':match-password="matchedFieldValue"')
        ->and($passwordField)->toContain(':min-length="minLengthValue"')
        ->and($formRenderer)->toContain(":form-model=\"field.type === 'password' ? modelValue : undefined\"")
        ->and($registerPage)->toContain("import TranslatedPassword from '@/components/TranslatedPassword.vue'")
        ->and($registerPage)->toContain('<TranslatedPassword')
        ->and($registerPage)->toContain(':min-length="12"')
        ->and($registerPage)->toContain(':match-password="form.password"')
        ->and($resetPage)->toContain('<TranslatedPassword')
        ->and($resetPage)->toContain(':min-length="12"')
        ->and($resetPage)->toContain(':match-password="form.password"')
        ->and($passwordRequirementsHelper)->toContain('export function passwordMinLengthMeta(')
        ->and($passwordRequirementsHelper)->toContain('export function passwordMatchMeta(')
        ->and($profilePasswordTab)->toContain('passwordMinLengthMeta(12)')
        ->and($profilePasswordTab)->toContain('passwordMatchMeta()')
        ->and($userFormFields)->toContain('passwordMinLengthMeta(8)')
        ->and($userFormFields)->toContain('passwordMatchMeta()')
        ->and($utilitiesStyles)->toContain('.cp-password-rules {')
        ->and($utilitiesStyles)->toContain('border-t')
        ->and($utilitiesStyles)->toContain('.cp-password-rules__item--valid {')
        ->and($commonEn)->toContain("'password_rule_confirmation' => 'Passwords match'")
        ->and($commonEn)->toContain("'password_rule_min_length' => 'At least :count characters'")
        ->and($commonEn)->toContain("'password_strength_prompt' => 'Choose a password'")
        ->and($commonEn)->toContain("'password_strength_strong' => 'Strong'")
        ->and($commonDe)->toContain("'password_rule_confirmation' => 'Passwörter stimmen überein'")
        ->and($commonDe)->toContain("'password_rule_min_length' => 'Mindestens :count Zeichen'")
        ->and($commonDe)->toContain("'password_strength_prompt' => 'Wähle ein Passwort'")
        ->and($commonDe)->toContain("'password_strength_strong' => 'Stark'");
});

it('shares auth, locale, and upload state with the scaffold inertia middleware', function (): void {
    $middleware = file_get_contents(__DIR__.'/../../stubs/app/Http/Middleware/HandleInertiaRequests.php');

    expect($middleware)->not->toBeFalse()
        ->and($middleware)->toContain('public function version(Request $request): ?string')
        ->and($middleware)->toContain('return null;')
        ->and($middleware)->toContain("'appName' => config('app.name'),")
        ->and($middleware)->toContain("'appSubtitle' => is_string(\$appSubtitle)")
        ->and($middleware)->toContain(": (string) __('page-layout.brand_subtitle_default'),")
        ->and($middleware)->toContain('use CorePanel\\Support\\Users\\UserModelManager;')
        ->and($middleware)->toContain('$users = app(UserModelManager::class);')
        ->and($middleware)->toContain('$roleNames = $user === null ? [] : $users->roleNames($user);')
        ->and($middleware)->toContain('$permissionNames = $users->permissionNames($user);')
        ->and($middleware)->toContain("'avatarUrl' => \$users->avatarUrl(\$user),")
        ->and($middleware)->toContain("'debug' => (bool) config('app.debug', false),")
        ->and($middleware)->toContain("'environment' => app()->environment(),")
        ->and($middleware)->toContain("'isLocal' => app()->environment('local'),")
        ->and($middleware)->toContain("'permissions' => \$permissionNames,")
        ->and($middleware)->toContain("'role' => \$users->primaryRole(\$user),")
        ->and($middleware)->toContain("'roles' => \$roleNames,")
        ->and($middleware)->toContain('use CorePanel\\Support\\Presence\\PresenceManager;')
        ->and($middleware)->toContain('$presence = app(PresenceManager::class);')
        ->and($middleware)->toContain("'presenceLastSeenAt' => \$presence->lastSeenTimestamp(\$user),")
        ->and($middleware)->toContain("'presenceStatus' => \$presence->statusFor(\$user),");
});

it('keeps the core user management index page free of tenant datasets', function (): void {
    $controller = file_get_contents(__DIR__.'/../../src/Http/Controllers/Users/UserController.php');

    expect($controller)->not->toBeFalse()
        ->and($controller)->toContain("'locales' => SupportedLocales::codes(),")
        ->and($controller)->not->toContain("'tenants' => \$this->users->assignableTenantsFor(\$request->user())")
        ->and($controller)->not->toContain("'tenantRecords' => \$this->tenantRows()")
        ->and($controller)->not->toContain("'tenancyEnabled' =>")
        ->and($controller)->toContain("'assignableUsers' => \$this->permissions->usersForAssignment()")
        ->and($controller)->toContain("'usersTable' => [");
});

it('resolves all relative imports after publishing javascript assets into a host application layout', function (): void {
    $temporaryBasePath = sys_get_temp_dir().'/core-panel-publish-layout-'.bin2hex(random_bytes(5));
    mkdir($temporaryBasePath, 0777, true);

    seedPublishedJavascriptAssets($temporaryBasePath);

    $publishedFiles = [
        $temporaryBasePath.'/resources/js/app.ts',
        ...array_map(
            static fn (string $path): string => $temporaryBasePath.'/resources/js/'.$path,
            [
                'components/Locale/LocaleFlag.vue',
                'composables/useSidebar.ts',
                'layouts/AppLayout.vue',
                'layouts/AuthLayout.vue',
                'layouts/components/AppFooter.vue',
                'layouts/components/AppHeader.vue',
                'layouts/components/AppPageHeader.vue',
                'layouts/components/AppSidebar.vue',
                'pages/Admin/ApiTokens/Index.vue',
                'pages/Admin/Activity/Index.vue',
                'pages/Admin/Dashboard/Index.vue',
                'pages/Auth/ForgotPassword.vue',
                'pages/Auth/Login.vue',
                'pages/Auth/Register.vue',
                'pages/Auth/ResetPassword.vue',
                'pages/Auth/TwoFactorChallenge.vue',
                'pages/Auth/VerifyEmail.vue',
                'pages/Admin/Settings/Profile.vue',
                'pages/Admin/Settings/Index.vue',
                'pages/Admin/Settings/Security.vue',
                'composables/useColorMode.ts',
            ],
        ),
        ...array_map(
            static fn (string $path): string => $temporaryBasePath.'/resources/js/theme/core-panel/'.$path,
            [
                'dark.ts',
                'index.ts',
                'light.ts',
                'preset.ts',
            ],
        ),
    ];

    foreach ($publishedFiles as $file) {
        $contents = file_get_contents($file);
        preg_match_all('/from\s+[\'"](\.{1,2}\/[^\'"]+)[\'"]/', $contents === false ? '' : $contents, $matches);

        foreach ($matches[1] as $importPath) {
            $resolved = collect(resolveRelativeImportCandidates($file, $importPath))
                ->first(static fn (string $candidate): bool => file_exists($candidate));

            expect($resolved)->not->toBeFalse("Failed to resolve [{$importPath}] from [{$file}] after publish layout.");
        }
    }
});

it('registers the package commands', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKeys([
        'core-panel:activity:clean',
        'core-panel:install',
        'core-panel:publish',
        'core-panel:update',
    ])
        ->and($commands['core-panel:activity:clean'])->toBeInstanceOf(CleanActivityLogsCommand::class)
        ->and($commands['core-panel:install'])->toBeInstanceOf(InstallCommand::class)
        ->and($commands['core-panel:publish'])->toBeInstanceOf(PublishCommand::class)
        ->and($commands['core-panel:update'])->toBeInstanceOf(UpdateCommand::class)
        ->and($commands['core-panel:install']->getAliases())->toContain('core:install')
        ->and($commands['core-panel:publish']->getAliases())->toContain('core:publish')
        ->and($commands['core-panel:update']->getAliases())->toContain('core:update');
});

it('ships the optional tenancy addon package scaffold', function (): void {
    $addonInstallCommand = file_get_contents(__DIR__.'/../../../core-panel-tenancy/src/Console/InstallTenancyCommand.php');
    $addonProvider = file_get_contents(__DIR__.'/../../../core-panel-tenancy/src/CorePanelTenancyServiceProvider.php');
    $centralRouteStub = file_get_contents(__DIR__.'/../../../core-panel-tenancy/stubs/routes/central.php');
    $universalRouteStub = file_get_contents(__DIR__.'/../../../core-panel-tenancy/stubs/routes/universal.php');

    expect(file_exists(__DIR__.'/../../../core-panel-tenancy/composer.json'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/src/CorePanelTenancyServiceProvider.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/src/Console/InstallTenancyCommand.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/app/Models/Tenant.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/app/Providers/TenancyServiceProvider.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/config/tenancy.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/routes/central.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/routes/tenant.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/routes/universal.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/database/migrations/2026_01_01_000001_create_tenants_table.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/database/migrations/2026_01_01_000020_create_domains_table.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/lang/en/page-tenants.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/lang/de/page-tenants.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/lang/en/tenancy.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/stubs/lang/de/tenancy.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/resources/lang/en/tenancy.php'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../../core-panel-tenancy/resources/lang/de/tenancy.php'))->toBeTrue()
        ->and($addonInstallCommand)->toContain('use CorePanel\\Support\\SynchronizesEnvironmentFile;')
        ->and($addonInstallCommand)->toContain("'CENTRAL_DOMAINS' =>")
        ->and($addonInstallCommand)->not->toContain('CORE_PANEL_TENANCY_ADAPTER')
        ->and($addonInstallCommand)->not->toContain('CORE_PANEL_TENANCY_MODE')
        ->and($addonProvider)->toContain("__DIR__.'/../stubs/routes/central.php' => base_path('routes/central.php')")
        ->and($addonProvider)->toContain("__DIR__.'/../stubs/routes/tenant.php' => base_path('routes/tenant.php')")
        ->and($addonProvider)->toContain("__DIR__.'/../stubs/routes/universal.php' => base_path('routes/universal.php')")
        ->and($addonProvider)->toContain("__DIR__.'/../routes/web/tenants.php' => base_path('routes/web/tenants.php')")
        ->and($centralRouteStub)->toContain("require base_path('routes/universal.php');")
        ->and($universalRouteStub)->not->toContain("\$loadUniversalWebRouteFile('auth.php');");
});

it('loads web and api routes', function (): void {
    $platformRoutes = file_get_contents(__DIR__.'/../../routes/web/platform.php');

    expect(Route::has('core-panel.dashboard'))->toBeTrue()
        ->and(route('core-panel.dashboard'))->toEndWith('/dashboard')
        ->and(Route::has('core-panel.activity.index'))->toBeTrue()
        ->and(Route::has('core-panel.activity.show'))->toBeTrue()
        ->and(Route::has('core-panel.files.index'))->toBeTrue()
        ->and(Route::has('core-panel.files.store'))->toBeTrue()
        ->and(Route::has('core-panel.files.destroy'))->toBeTrue()
        ->and(Route::has('core-panel.files.download'))->toBeTrue()
        ->and(Route::has('core-panel.files.preview'))->toBeTrue()
        ->and(Route::has('core-panel.api-tokens.index'))->toBeTrue()
        ->and(Route::has('core-panel.api-tokens.store'))->toBeTrue()
        ->and(Route::has('core-panel.api-tokens.destroy'))->toBeTrue()
        ->and(Route::has('auth.login'))->toBeTrue()
        ->and(Route::has('two-factor.login'))->toBeTrue()
        ->and($platformRoutes)->not->toContain('InitializeTenancyByDomain')
        ->and(Route::has('locale.set'))->toBeTrue()
        ->and(Route::has('core-panel.settings.index'))->toBeTrue()
        ->and(Route::has('core-panel.settings.logo.destroy'))->toBeTrue()
        ->and(Route::has('core-panel.settings.logo.store'))->toBeTrue()
        ->and(Route::has('core-panel.settings.update'))->toBeTrue()
        ->and(Route::has('core-panel.settings.styles'))->toBeTrue()
        ->and(Route::has('profile.show'))->toBeTrue()
        ->and(Route::has('profile.security'))->toBeTrue()
        ->and(Route::has('profile.sessions.destroy-others'))->toBeTrue()
        ->and(Route::has('tenant-selection.store'))->toBeFalse()
        ->and(Route::has('core-panel.roles.index'))->toBeTrue()
        ->and(Route::has('core-panel.roles.store'))->toBeTrue()
        ->and(Route::has('core-panel.roles.resync'))->toBeTrue()
        ->and(Route::has('core-panel.roles.matrix'))->toBeTrue()
        ->and(Route::has('core-panel.roles.update'))->toBeTrue()
        ->and(Route::has('core-panel.roles.destroy'))->toBeTrue()
        ->and(Route::has('core-panel.roles.permissions.sync'))->toBeTrue()
        ->and(Route::has('core-panel.user-groups.index'))->toBeTrue()
        ->and(Route::has('core-panel.user-groups.store'))->toBeTrue()
        ->and(Route::has('core-panel.user-groups.preview'))->toBeTrue()
        ->and(Route::has('core-panel.user-groups.import'))->toBeTrue()
        ->and(Route::has('core-panel.user-groups.update'))->toBeTrue()
        ->and(Route::has('core-panel.user-groups.destroy'))->toBeTrue()
        ->and(Route::has('core-panel.permissions.store'))->toBeTrue()
        ->and(Route::has('core-panel.permissions.update'))->toBeTrue()
        ->and(Route::has('core-panel.permissions.destroy'))->toBeTrue()
        ->and(Route::has('core-panel.users.roles.assign'))->toBeTrue()
        ->and(Route::has('core-panel.users.index'))->toBeTrue()
        ->and(Route::has('core-panel.users.create'))->toBeTrue()
        ->and(Route::has('core-panel.users.store'))->toBeTrue()
        ->and(Route::has('core-panel.users.show'))->toBeTrue()
        ->and(Route::has('core-panel.users.edit'))->toBeTrue()
        ->and(Route::has('core-panel.users.update'))->toBeTrue()
        ->and(Route::has('core-panel.users.destroy'))->toBeTrue()
        ->and(Route::has('core-panel.users.restore'))->toBeTrue()
        ->and(Route::has('core-panel.users.force-delete'))->toBeTrue()
        ->and(Route::has('core-panel.users.avatar.store'))->toBeTrue()
        ->and(Route::has('core-panel.users.avatar.destroy'))->toBeTrue()
        ->and(Route::has('core-panel.users.sessions.index'))->toBeTrue()
        ->and(Route::has('core-panel.users.sessions.destroy'))->toBeTrue()
        ->and(Route::has('core-panel.tenants.current'))->toBeFalse()
        ->and(Route::has('core-panel.tenants.available'))->toBeFalse()
        ->and(Route::has('core-panel.tenants.switch'))->toBeFalse()
        ->and(Route::has('core-panel.api.ping'))->toBeTrue()
        ->and(Route::has('core-panel.api.me'))->toBeFalse()
        ->and(Route::has('core-panel.api.v1.me'))->toBeTrue();
});

it('loads the scaffold migrations into the isolated package database', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('tenants'))->toBeFalse()
        ->and(Schema::hasTable('user_groups'))->toBeTrue()
        ->and(Schema::hasTable('user_group_user'))->toBeTrue()
        ->and(Schema::hasTable('settings'))->toBeTrue()
        ->and(Schema::hasTable('oauth_access_tokens'))->toBeTrue()
        ->and(Schema::hasTable('oauth_clients'))->toBeTrue()
        ->and(Schema::hasTable('activity_log'))->toBeTrue()
        ->and(Schema::hasTable('media'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'core_panel_group'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'core_panel_is_protected'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'core_panel_seeded_permissions'))->toBeTrue();
});

it('loads package translations without host application assumptions', function (): void {
    expect(trans('core-panel::settings.groups.general'))->not->toBe('core-panel::settings.groups.general')
        ->and(trans('core-panel::table-builder.actions.edit'))->not->toBe('core-panel::table-builder.actions.edit');
});

it('applies the expected auth middleware to auth and settings inertia routes', function (): void {
    $loginRoute = Route::getRoutes()->getByName('auth.login');
    $securityRoute = Route::getRoutes()->getByName('profile.security');
    $twoFactorRoute = Route::getRoutes()->getByName('two-factor.login');
    $verifyEmailRoute = Route::getRoutes()->getByName('auth.verification.notice');

    expect($loginRoute)->not->toBeNull()
        ->and($securityRoute)->not->toBeNull()
        ->and($twoFactorRoute)->not->toBeNull()
        ->and($verifyEmailRoute)->not->toBeNull()
        ->and($loginRoute?->gatherMiddleware())->toContain('web')
        ->and($loginRoute?->gatherMiddleware())->toContain('guest')
        ->and($twoFactorRoute?->gatherMiddleware())->toContain('web')
        ->and($twoFactorRoute?->gatherMiddleware())->toContain('guest')
        ->and($verifyEmailRoute?->gatherMiddleware())->toContain('web')
        ->and($securityRoute?->gatherMiddleware())->toContain('auth');
});

it('ships auth and settings inertia pages in the publishable package assets', function (): void {
    $pages = [
        'stubs/resources/js/assets/icons/microsoft.svg',
        'stubs/resources/js/components/Locale/LocaleSwitcher.vue',
        'stubs/resources/js/layouts/AuthLayout.vue',
        'stubs/resources/js/pages/Admin/ApiTokens/Index.vue',
        'stubs/resources/js/pages/Admin/Activity/Index.vue',
        'stubs/resources/js/pages/Admin/Files/Index.vue',
        'stubs/resources/js/pages/Auth/Login.vue',
        'stubs/resources/js/pages/Auth/Register.vue',
        'stubs/resources/js/pages/Auth/ForgotPassword.vue',
        'stubs/resources/js/pages/Auth/ResetPassword.vue',
        'stubs/resources/js/pages/Auth/VerifyEmail.vue',
        'stubs/resources/js/pages/Auth/TwoFactorChallenge.vue',
        'stubs/resources/js/pages/Admin/Roles/Index.vue',
        'stubs/resources/js/pages/Admin/Users/Index.vue',
        'stubs/resources/js/pages/Admin/Users/Create.vue',
        'stubs/resources/js/pages/Admin/Users/Edit.vue',
        'stubs/resources/js/pages/Admin/Users/Show.vue',
        'stubs/resources/js/pages/Admin/Settings/Index.vue',
        'stubs/resources/js/pages/Admin/Settings/Profile.vue',
        'stubs/resources/js/pages/Admin/Settings/Security.vue',
        'stubs/resources/js/pages/Admin/Settings/components/ProfileAvatarUpload.vue',
        'stubs/resources/js/pages/Admin/Settings/components/ProfileConnectionsTab.vue',
        'stubs/resources/js/pages/Admin/Settings/components/ProfileInfoTab.vue',
        'stubs/resources/js/pages/Admin/Settings/components/SocialProviderConnectionCard.vue',
        'stubs/resources/js/pages/Admin/Settings/components/ProfilePasswordTab.vue',
        'stubs/resources/js/pages/Admin/Settings/components/ProfileSecurityTab.vue',
        'stubs/resources/js/pages/Admin/Settings/components/ProfileSessionsTab.vue',
        'stubs/resources/js/pages/Admin/Settings/components/ProfileWorkspace.vue',
    ];

    foreach ($pages as $page) {
        expect(file_exists(__DIR__.'/../../'.$page))->toBeTrue();
    }

    $loginContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/Login.vue');

    expect($loginContents)->toContain("import microsoftIcon from '@/assets/icons/microsoft.svg'")
        ->and($loginContents)->toContain("import githubIcon from '@/assets/icons/github.svg'")
        ->and($loginContents)->toContain("import githubWhiteIcon from '@/assets/icons/github-white.svg'")
        ->and($loginContents)->toContain("import googleIcon from '@/assets/icons/google.png'")
        ->and($loginContents)->toContain('function providerIcon(provider: string): string | null')
        ->and($loginContents)->toContain("provider.provider === 'github'")
        ->and($loginContents)->toContain('auth-social__button-lockup--github')
        ->and($loginContents)->toContain("provider.provider === 'microsoft'")
        ->and($loginContents)->toContain("? 'Microsoft'")
        ->and($loginContents)->toContain('providerIcon(provider.provider) ?? undefined');
});

it('renders profile workspace tabs without forcing a shared panel surface', function (): void {
    $workspaceContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileWorkspace.vue');
    $rendererContents = file_get_contents(__DIR__.'/../../resources/js/components/TabBuilder/TabsRenderer.vue');
    $avatarContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileAvatarUpload.vue');
    $profileConnectionsContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileConnectionsTab.vue');
    $profileInfoContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileInfoTab.vue');
    $providerConnectionContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/SocialProviderConnectionCard.vue');
    $profilePasswordContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfilePasswordTab.vue');
    $profileSecurityContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileSecurityTab.vue');
    $profileSessionsContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileSessionsTab.vue');
    $challengeContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/TwoFactorChallenge.vue');
    $appLayoutContents = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AppLayout.vue');
    $authLayoutContents = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AuthLayout.vue');
    $appToastContents = file_get_contents(__DIR__.'/../../stubs/resources/js/components/AppToast.vue');
    $adminThemeContents = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_admin.css');
    $germanCommonTranslations = file_get_contents(__DIR__.'/../../resources/lang/de/common.php');
    $germanPageSettingsTranslations = file_get_contents(__DIR__.'/../../resources/lang/de/page-settings.php');
    $germanPageUsersTranslations = file_get_contents(__DIR__.'/../../resources/lang/de/page-users.php');

    expect($workspaceContents)->not->toContain('panelSurface:')
        ->and($rendererContents)->toContain('schema.panelSurface')
        ->and($rendererContents)->toContain("emit('update:modelValue', activeTab.value)")
        ->and($avatarContents)->toContain('page-settings.avatar_title')
        ->and($avatarContents)->toContain('<div class="cp-avatar-upload__copy">')
        ->and($avatarContents)->toContain('v-model:visible="removeDialogVisible"')
        ->and($avatarContents)->toContain("import AvatarUploadDropzone from '@/components/AvatarUploadDropzone.vue'")
        ->and($avatarContents)->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and($avatarContents)->toContain("detail: trans('page-settings.avatar_invalid_type')")
        ->and($avatarContents)->toContain("detail: trans('page-settings.avatar_upload_failed')")
        ->and($avatarContents)->toContain("detail: trans('page-settings.avatar_remove_failed')")
        ->and($avatarContents)->toContain("detail: trans('page-settings.avatar_uploaded_status')")
        ->and($avatarContents)->toContain("detail: trans('page-settings.avatar_removed_status')")
        ->and($avatarContents)->toContain('layout="inline"')
        ->and($avatarContents)->toContain(':show-badges="false"')
        ->and($avatarContents)->toContain(':show-hint="false"')
        ->and($avatarContents)->toContain('variant="regular"')
        ->and($avatarContents)->toContain('@invalid-file="notifyInvalidFileType"')
        ->and($avatarContents)->toContain('@update:model-value="handleAvatarSelection"')
        ->and($workspaceContents)->toContain('ProfileConnectionsTab')
        ->and($workspaceContents)->toContain("label: 'page-settings.tab_connections'")
        ->and($profileInfoContents)->toContain('page-settings.profile_info_title')
        ->and($profileInfoContents)->toContain('form.defaults()')
        ->and($profileInfoContents)->toContain(':disabled="form.processing || !form.isDirty"')
        ->and($profileInfoContents)->not->toContain('<SocialProviderConnectionCard')
        ->and($profileInfoContents)->not->toContain("detail: trans('page-users.users.updated')")
        ->and($profileInfoContents)->toContain('<FormRenderer')
        ->and($profilePasswordContents)->toContain('form.defaults()')
        ->and($profilePasswordContents)->toContain(':disabled="form.processing || !form.isDirty"')
        ->and($profileConnectionsContents)->toContain('<SocialProviderConnectionCard')
        ->and($profileConnectionsContents)->toContain("['microsoft', 'github', 'google']")
        ->and(file_exists(__DIR__.'/../../stubs/resources/js/assets/icons/github-mark.svg'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../stubs/resources/js/assets/icons/github-white.svg'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../stubs/resources/js/assets/icons/google.png'))->toBeTrue()
        ->and($providerConnectionContents)->toContain("import microsoftIcon from '@/assets/icons/microsoft.svg'")
        ->and($providerConnectionContents)->toContain("import githubIcon from '@/assets/icons/github.svg'")
        ->and($providerConnectionContents)->toContain("import githubWhiteIcon from '@/assets/icons/github-white.svg'")
        ->and($providerConnectionContents)->toContain("import googleIcon from '@/assets/icons/google.png'")
        ->and($providerConnectionContents)->toContain('provider: string')
        ->and($providerConnectionContents)->toContain('providerIconName')
        ->and($providerConnectionContents)->toContain('const providerLogo = computed(() => {')
        ->and($providerConnectionContents)->toContain('const showProviderLabel = computed(')
        ->and($providerConnectionContents)->toContain("props.provider === 'microsoft'")
        ->and($providerConnectionContents)->toContain('dark:border-surface-800 dark:bg-surface-950/80')
        ->and($providerConnectionContents)->toContain('dark:border dark:border-surface-800 dark:bg-surface-900/90')
        ->and($providerConnectionContents)->toContain('dark:border-surface-800 dark:bg-surface-950/95')
        ->and($providerConnectionContents)->toContain('submitLinkAction(socialite.link.url(props.provider))')
        ->and($providerConnectionContents)->toContain('page-settings.social_provider_title')
        ->and($providerConnectionContents)->toContain('github: githubIcon,')
        ->and($providerConnectionContents)->toContain('google: googleIcon,')
        ->and($providerConnectionContents)->toContain('dark:text-surface-200')
        ->and($providerConnectionContents)->toContain('dark:bg-surface-800/70 dark:text-surface-200 dark:ring-surface-700')
        ->and($providerConnectionContents)->toContain("trans('page-settings.microsoft_connect')")
        ->and($providerConnectionContents)->toContain("trans('page-settings.social_provider_title'")
        ->and($profilePasswordContents)->toContain('requiresPasswordSetup: boolean')
        ->and($profilePasswordContents)->toContain('page-settings.password_setup_required_notice')
        ->and($profilePasswordContents)->toContain('page-settings.password_setup_submit')
        ->and($profilePasswordContents)->toContain("'page-settings.password_setup_title'")
        ->and($profileSecurityContents)->toContain('page-settings.two_factor_finish_description')
        ->and($profileSecurityContents)->not->toContain('connected_accounts')
        ->and($profileSecurityContents)->toContain('await loadQrAndSetupKey()')
        ->and($profileSecurityContents)->toContain('passwordConfirm.store.url()')
        ->and($profileSecurityContents)->toContain('twoFactorRoutes.regenerateRecoveryCodes.url()')
        ->and($profileSecurityContents)->toContain("trans('page-settings.two_factor_hide_codes')")
        ->and($profileSecurityContents)->toContain('<AppIcon name="check" />')
        ->and($profileSecurityContents)->toContain('v-if="props.twoFactor.enabled && !props.twoFactor.confirmed"')
        ->and($profileSecurityContents)->toContain('size="small"')
        ->and($profileSessionsContents)->toContain('profile.sessions.destroyOthers.url()')
        ->and($profileSessionsContents)->toContain('browser_sessions_logout_others')
        ->and($adminThemeContents)->toContain('background-color: var(--cp-surface-panel);')
        ->and($germanCommonTranslations)->toContain("'confirm' => 'Bestätigen'")
        ->and($germanPageSettingsTranslations)->toContain("'social_avatar_sync_title' => ':provider-Profilbild übernehmen?'")
        ->and($germanPageSettingsTranslations)->toContain("'tab_connections' => 'Verbundene Konten'")
        ->and($germanPageUsersTranslations)->toContain("'updated' => 'Benutzer aktualisiert.'")
        ->and($challengeContents)->toContain('useRecoveryCode')
        ->and($challengeContents)->toContain('<InputOtp')
        ->and($challengeContents)->toContain('page-auth.two_factor_use_recovery_code')
        ->and($appLayoutContents)->toContain("import AppToast from '@/components/AppToast.vue'")
        ->and($appLayoutContents)->toContain('SocialAvatarSyncDialog')
        ->and($appLayoutContents)->toContain('<AppToast />')
        ->and($appLayoutContents)->toContain('function resolveFlashStatus(status: string): string {')
        ->and($appLayoutContents)->toContain("'profile-information-updated': trans('page-users.users.updated')")
        ->and($appLayoutContents)->toContain("'password-updated': trans('page-settings.password_updated_status')")
        ->and($appLayoutContents)->toContain("'verification-link-sent': trans(")
        ->and($appLayoutContents)->toContain('detail: resolveFlashStatus(flash.status)')
        ->and($authLayoutContents)->toContain('<AppToast />')
        ->and($appToastContents)->toContain("icon: 'triangle-alert'")
        ->and($germanPageSettingsTranslations)->toContain("'avatar_remove_failed' => 'Das Profilfoto konnte nicht entfernt werden.'")
        ->and($germanPageSettingsTranslations)->toContain("'avatar_removed_status' => 'Profilfoto entfernt.'")
        ->and($germanPageSettingsTranslations)->toContain("'avatar_upload_failed' => 'Das Profilfoto konnte nicht hochgeladen werden.'")
        ->and($germanPageSettingsTranslations)->toContain("'avatar_uploaded_status' => 'Profilfoto hochgeladen.'");
});

it('defines the shared card surface globally in the admin theme', function (): void {
    $themeContents = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_admin.css');
    $rolesOverviewContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Access/components/RolesOverviewPanel.vue');

    expect($themeContents)->toContain('.cp-card {')
        ->and($themeContents)->toContain('.cp-avatar-upload__state--success {')
        ->and($themeContents)->toContain('.cp-avatar-upload__state--warning {')
        ->and($themeContents)->toContain('.cp-confirm-dialog__icon {')
        ->and($themeContents)->toContain('border-top: 1px solid')
        ->and($themeContents)->toContain('.core-panel-dark .cp-confirm-dialog__actions {')
        ->and($themeContents)->toContain('.core-panel-dark .cp-avatar-dropzone__overlay-badge {')
        ->and($rolesOverviewContents)->not->toContain(".cp-card {\n");
});

it('renders user management with a reference-style datatable shell and a table-only roles tab variant', function (): void {
    $usersIndexContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/Index.vue');
    $usersTableContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UsersTableTab.vue');
    $userGroupsTabContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserGroupsTab.vue');
    $userGroupFormContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/UserGroups/components/UserGroupForm.vue');
    $userGroupImportContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/UserGroups/components/UserGroupImportForm.vue');
    $rolesOverviewContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Access/components/RolesOverviewPanel.vue');
    $dataTableContents = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/DataTable.vue');
    $bulkActionBarContents = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/BulkActionBar.vue');
    $tableActionsContents = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/TableActions.vue');
    $columnVisibilityContents = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/ColumnVisibilityDropdown.vue');
    $paginationContents = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/TablePagination.vue');
    $datatableThemeContents = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_datatable.css');
    $corePanelPluginContents = file_get_contents(__DIR__.'/../../stubs/resources/js/plugins/core-panel.ts');
    $tabsThemeContents = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_tabs.css');
    $tableBuilderGerman = file_get_contents(__DIR__.'/../../resources/lang/de/table-builder.php');

    expect($usersTableContents)->not->toContain("key: 'avatar'")
        ->and($usersTableContents)->toContain("key: 'first_name'")
        ->and($usersTableContents)->toContain("key: 'user_groups'")
        ->and($usersTableContents)->toContain("key: 'status'")
        ->and($usersTableContents)->toContain("key: 'created_at'")
        ->and($usersTableContents)->not->toContain("key: 'security'")
        ->and($usersIndexContents)->toContain('panelSurface: true,')
        ->and($usersIndexContents)->toContain("component: 'UserGroupsTab'")
        ->and($usersIndexContents)->toContain("key: 'user_groups'")
        ->and($usersIndexContents)->toContain("label: 'navigation.user_groups'")
        ->and($usersIndexContents)->toContain("panelSurfaceVariant: 'card'")
        ->and($usersTableContents)->toContain("import UserAvatar from '@/components/UserAvatar.vue'")
        ->and($usersTableContents)->toContain("labelKey: 'navigation.users'")
        ->and($usersTableContents)->toContain("labelKey: 'navigation.user_groups'")
        ->and($usersIndexContents)->toContain('roleLabels: props.roleLabels,')
        ->and($usersIndexContents)->toContain('userGroupOptions: props.userGroupOptions,')
        ->and($usersIndexContents)->toContain('userGroups: props.userGroups,')
        ->and($usersTableContents)->not->toContain('#cell-avatar')
        ->and($usersTableContents)->toContain(':presence-status=')
        ->and($usersTableContents)->toContain('#cell-first_name')
        ->and($usersTableContents)->toContain('#cell-user_groups')
        ->and($usersTableContents)->toContain('#cell-status')
        ->and($usersTableContents)->toContain('#cell-created_at')
        ->and($usersTableContents)->not->toContain('#cell-security')
        ->and($usersTableContents)->toContain('statusOptions = computed(() =>')
        ->and($usersTableContents)->toContain('roleOptions = computed(() =>')
        ->and($usersTableContents)->toContain('v-tooltip.top')
        ->and($usersTableContents)->toContain('class="cp-users-tab__status-security"')
        ->and($usersTableContents)->toContain('AppIcon name="lock"')
        ->and($usersTableContents)->toContain('class="cp-users-tab__filter-content"')
        ->and($usersTableContents)->toContain('#toolbar-footer')
        ->and($usersTableContents)->toContain('table-builder.actions.reset_filters')
        ->and($usersTableContents)->toContain("'role' in overrides")
        ->and($usersTableContents)->toContain("'status' in overrides")
        ->and($usersTableContents)->toContain("'userGroupId' in overrides")
        ->and($usersTableContents)->toContain('class="flex flex-wrap items-center gap-2"')
        ->and($usersTableContents)->toContain('class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"')
        ->and($usersTableContents)->toContain('cp-datatable__action-button')
        ->and($usersTableContents)->toContain('cp-datatable__action-button--danger')
        ->and($usersTableContents)->toContain('severity="danger"')
        ->and($usersTableContents)->not->toContain("class=\"cp-datatable__action-button cp-datatable__action-button--danger\"\n                            outlined")
        ->and($usersTableContents)->toContain('AppIcon name="more-vertical"')
        ->and($usersTableContents)->toContain('class="flex items-center gap-3"')
        ->and($dataTableContents)->toContain('const hasBulkActions = computed(() => table.bulkActions.value.length > 0)')
        ->and($dataTableContents)->toContain('function resolveColumnLabel(column: DataTableColumn): string {')
        ->and($dataTableContents)->toContain('if (column.label === null) {')
        ->and($dataTableContents)->toContain('class="cp-datatable__body-overlay"')
        ->and($dataTableContents)->toContain('table-builder.states.loading')
        ->and($dataTableContents)->toContain('class="grid gap-3 px-[1.125rem] pt-[1.125rem] pb-1"')
        ->and($dataTableContents)->toContain('header-class="cp-datatable__actions-header"')
        ->and($bulkActionBarContents)->toContain('class="flex flex-wrap items-center justify-between gap-3 rounded border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-4 py-3"')
        ->and($dataTableContents)->toContain('cp-datatable__surface')
        ->and($dataTableContents)->toContain(':header="$t(\'common.ui.actions\')"')
        ->and($dataTableContents)->toContain(":selection-mode=\"hasBulkActions ? 'multiple' : undefined\"")
        ->and($dataTableContents)->toContain('v-if="hasBulkActions"')
        ->and($tableActionsContents)->toContain('class="cp-datatable-actions__trigger"')
        ->and($tableActionsContents)->toContain('cp-datatable-actions__item--danger')
        ->and($columnVisibilityContents)->toContain('class="cp-datatable-columns__trigger"')
        ->and($columnVisibilityContents)->toContain('function resolveColumnLabel(column: DataTableColumn): string {')
        ->and($paginationContents)->toContain('class="cp-datatable-pagination"')
        ->and($paginationContents)->toContain(':rows-per-page-options="[10, 20, 50, 100]"')
        ->and($corePanelPluginContents)->toContain("import Tooltip from 'primevue/tooltip'")
        ->and($corePanelPluginContents)->toContain("app.directive('tooltip', Tooltip)")
        ->and($datatableThemeContents)->toContain('.cp-datatable__surface {')
        ->and($datatableThemeContents)->toContain('border: 0;')
        ->and($datatableThemeContents)->toContain('border-radius: 0 0 var(--cp-radius-lg) var(--cp-radius-lg);')
        ->and($datatableThemeContents)->toContain('.cp-datatable__body-overlay {')
        ->and($datatableThemeContents)->toContain('.cp-datatable__table .p-datatable-thead > tr > th {')
        ->and($datatableThemeContents)->toContain('.cp-datatable__table .cp-datatable__actions-header .p-column-header-content {')
        ->and($datatableThemeContents)->toContain('height: 3.85rem;')
        ->and($datatableThemeContents)->toContain('font-size: 0.81rem;')
        ->and($datatableThemeContents)->toContain('.cp-datatable-pagination {')
        ->and($datatableThemeContents)->toContain('padding: 0.95rem 1.125rem 1.05rem;')
        ->and($datatableThemeContents)->toContain('border-top: 1px solid')
        ->and($datatableThemeContents)->toContain('color-mix(in srgb, var(--cp-surface-border) 74%, transparent);')
        ->and($datatableThemeContents)->toContain('.cp-datatable-pagination .p-paginator-rpp-dropdown .p-select-label {')
        ->and($datatableThemeContents)->toContain('justify-content: center;')
        ->and($datatableThemeContents)->toContain('.cp-datatable-actions__item--danger {')
        ->and($tabsThemeContents)->toContain('.cp-side-tabs__panel-surface--flush {')
        ->and($tabsThemeContents)->toContain('border: 0;')
        ->and($tabsThemeContents)->toContain('border-radius: 0;')
        ->and($tabsThemeContents)->toContain('background: transparent;')
        ->and($tabsThemeContents)->toContain('.cp-side-tabs__panel-surface:has(> .cp-card.cp-datatable__surface),')
        ->and($tabsThemeContents)->toContain('.cp-user-management .cp-side-tabs__panel-surface {')
        ->and($tableBuilderGerman)->toContain("'reset_filters' => 'Alles zurücksetzen'")
        ->and($tableBuilderGerman)->toContain("'loading' => 'Wird geladen'")
        ->and($usersIndexContents)->toContain('openImportUserGroupsDialog')
        ->and($usersIndexContents)->toContain('openCreateRoleDialog')
        ->and($usersIndexContents)->toContain('resyncManagedRoles')
        ->and($usersIndexContents)->toContain("const canImportUserGroups = computed(() => can('user-groups.import'))")
        ->and($usersIndexContents)->toContain('const canResyncManagedRoles = computed(')
        ->and($usersIndexContents)->toContain('page-user-groups.import_action')
        ->and($userGroupsTabContents)->not->toContain('openImportDialog')
        ->and($userGroupsTabContents)->toContain("header: trans('page-user-groups.edit')")
        ->and($userGroupsTabContents)->toContain("labelKey: 'page-user-groups.members'")
        ->and($userGroupsTabContents)->toContain('class="cp-user-groups-tab grid gap-4"')
        ->and($userGroupsTabContents)->toContain('class="cp-datatable__action-button"')
        ->and($userGroupsTabContents)->toContain('cp-datatable__action-button--danger')
        ->and($userGroupsTabContents)->toContain('cp-user-groups-tab__actions-header')
        ->and($userGroupsTabContents)->toContain('<ColumnVisibilityDropdown')
        ->and($userGroupsTabContents)->toContain('<TablePagination')
        ->and($userGroupsTabContents)->toContain('class="-mt-[0.15rem]"')
        ->and($userGroupsTabContents)->toContain('const pagination = computed<DataTablePagination>(() =>')
        ->and($userGroupsTabContents)->toContain('userGroupRoutes.destroy.url(pendingDeleteUserGroup.value.id)')
        ->and($userGroupsTabContents)->toContain('cp-user-groups-tab__surface')
        ->and($userGroupFormContents)->toContain('userGroupRoutes.update.url(userGroup.id)')
        ->and($userGroupFormContents)->toContain("{{ \$t('page-user-groups.color') }}")
        ->and($userGroupFormContents)->toContain('border-t border-[var(--cp-surface-border)] pt-5')
        ->and($userGroupImportContents)->toContain('userGroupRoutes.preview.url()')
        ->and($userGroupImportContents)->toContain('toAppRelativeUrl(userGroupRoutes.preview.url())')
        ->and($userGroupImportContents)->toContain('page-user-groups.preview_total')
        ->and($userGroupImportContents)->toContain('page-user-groups.preview_empty')
        ->and($userGroupImportContents)->toContain('accept=".csv,.txt,.sql"')
        ->and($userGroupImportContents)->toContain("'X-XSRF-TOKEN'")
        ->and($userGroupImportContents)->toContain('void loadPreview()')
        ->and($userGroupImportContents)->toContain('rounded-[var(--cp-radius-lg)] border border-slate-200 bg-slate-50 px-4 py-3')
        ->and($userGroupImportContents)->toContain('border-t border-[var(--cp-surface-border)] pt-5')
        ->and($userGroupImportContents)->toContain(':disabled="preview === null || previewLoading"')
        ->and($userGroupImportContents)->not->toContain('@click="loadPreview"')
        ->and($rolesOverviewContents)->toContain('class="cp-datatable cp-roles-panel"')
        ->and($rolesOverviewContents)->toContain('class="grid gap-3 px-[1.125rem] pt-[1.125rem] pb-1"')
        ->and($rolesOverviewContents)->toContain('class="flex items-center justify-between gap-4"')
        ->and($rolesOverviewContents)->toContain('class="cp-datatable__action-button"')
        ->and($rolesOverviewContents)->toContain('cp-datatable__action-button--danger')
        ->and($rolesOverviewContents)->toContain('<ColumnVisibilityDropdown')
        ->and($rolesOverviewContents)->toContain('<TablePagination')
        ->and($rolesOverviewContents)->not->toContain('{{ $t(\'page-roles.new_role\') }}')
        ->and($rolesOverviewContents)->not->toContain('{{ $t(\'page-roles.resync\') }}')
        ->and($rolesOverviewContents)->toContain('openEditRoleDialog(data)')
        ->and($rolesOverviewContents)->toContain('confirmDeleteRole(data)');
});

it('uses the shared danger confirmation dialog for destructive admin table actions', function (): void {
    $usersTableContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UsersTableTab.vue');
    $userGroupsTabContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserGroupsTab.vue');
    $rolesOverviewContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Access/components/RolesOverviewPanel.vue');
    $formsIndexContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Forms/Index.vue');
    $apiTokensContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/ApiTokens/components/ApiTokenManager.vue');
    $oauthClientsContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/OAuthClients/Index.vue');
    $filesIndexContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Files/Index.vue');

    expect($usersTableContents)->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and($usersTableContents)->toContain('<ConfirmActionDialog')
        ->and($usersTableContents)->toContain('confirm-severity="danger"')
        ->and($usersTableContents)->toContain('icon="trash"')
        ->and($usersTableContents)->not->toContain('useConfirm(')
        ->and($userGroupsTabContents)->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and($userGroupsTabContents)->toContain('<ConfirmActionDialog')
        ->and($userGroupsTabContents)->not->toContain('useConfirm(')
        ->and($rolesOverviewContents)->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and($rolesOverviewContents)->toContain('<ConfirmActionDialog')
        ->and($rolesOverviewContents)->not->toContain('useConfirm(')
        ->and($formsIndexContents)->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and($formsIndexContents)->toContain('<ConfirmActionDialog')
        ->and($formsIndexContents)->not->toContain('useConfirm(')
        ->and($apiTokensContents)->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and($apiTokensContents)->toContain('<ConfirmActionDialog')
        ->and($apiTokensContents)->not->toContain('useConfirm(')
        ->and($oauthClientsContents)->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and($oauthClientsContents)->toContain('<ConfirmActionDialog')
        ->and($oauthClientsContents)->not->toContain('useConfirm(')
        ->and($filesIndexContents)->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and($filesIndexContents)->toContain('<ConfirmActionDialog')
        ->and($filesIndexContents)->not->toContain('useConfirm(');
});

it('renders the datatable empty state without a subtitle line', function (): void {
    $dataTableContents = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/DataTable.vue');

    expect($dataTableContents)->toContain("{{ \$t('table-builder.states.empty_title') }}")
        ->and($dataTableContents)->not->toContain('table-builder.states.empty_description');
});

it('styles confirm dialog button severities and layout affordances globally', function (): void {
    $adminTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_admin.css');
    $themeIndex = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/theme.css');
    $formsTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_forms.css');
    $navigationTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_navigation.css');
    $menusTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_menus.css');
    $dialogsTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_dialogs.css');
    $overlaysTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_overlays.css');
    $primeVueTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_primevue.css');
    $toastTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_toast.css');
    $headerContents = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/components/AppHeader.vue');
    $pluginContents = file_get_contents(__DIR__.'/../../stubs/resources/js/plugins/core-panel.ts');
    $baseTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_base.css');
    $runtimeTheme = file_get_contents(__DIR__.'/../../resources/js/theme/core-panel/index.ts');

    expect($adminTheme)->toContain('margin-inline: auto;')
        ->and($adminTheme)->toContain('background: rgba(255, 255, 255, 0.08);')
        ->and($themeIndex)->toContain("@import './_forms.css';")
        ->and($themeIndex)->toContain("@import './_menus.css';")
        ->and($themeIndex)->toContain("@import './_dialogs.css';")
        ->and($themeIndex)->toContain("@import './_overlays.css';")
        ->and($primeVueTheme)->toContain('PrimeVue bridge layer')
        ->and($headerContents)->toContain('class="flex min-w-40 items-center gap-[0.65rem] px-3 py-2"')
        ->and($baseTheme)->toContain('--p-button-padding-y: 0.56rem;')
        ->and($formsTheme)->toContain('--p-button-padding-y: var(--cp-control-padding-y, 0.48rem);')
        ->and($runtimeTheme)->toContain("'--cp-control-padding-y': '0.52rem'")
        ->and($runtimeTheme)->toContain("'--p-button-padding-y': '0.46rem'")
        ->and($navigationTheme)->toContain('.app-nav-badge {')
        ->and($navigationTheme)->toContain('.app-sidebar__footer-badge--info {')
        ->and($navigationTheme)->toContain('.app-sidebar__footer-badge--danger {')
        ->and($navigationTheme)->toContain('min-height: 1.4rem;')
        ->and($navigationTheme)->toContain('white-space: nowrap;')
        ->and($adminTheme)->toContain('.app-sidebar__version-badge.p-badge {')
        ->and($adminTheme)->toContain('display: inline-flex;')
        ->and($menusTheme)->toContain('.cp-header-menu .p-menu-item-content {')
        ->and($menusTheme)->toContain('.cp-header-menu__locale-item:hover,')
        ->and($dialogsTheme)->toContain('.p-dialog-header,')
        ->and($dialogsTheme)->toContain('.p-dialog-mask {')
        ->and($overlaysTheme)->toContain('.p-megamenu-submenu,')
        ->and($headerContents)->toContain("class: 'cp-header-menu cp-header-menu--locale'")
        ->and($headerContents)->not->toContain("class: 'cp-header-menu cp-header-menu--user'")
        ->and($pluginContents)->toContain("name: 'primevue'")
        ->and($pluginContents)->toContain("order: 'tailwind-base, primevue, tailwind-utilities'")
        ->and($toastTheme)->toContain('.app-toast {')
        ->and($toastTheme)->toContain('.core-panel-dark .app-toast {')
        ->and($toastTheme)->toContain('.app-toast__progress-bar--success {');
});

it('uses wayfinder-driven permission management endpoints in the roles page', function (): void {
    $indexContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Roles/Index.vue');
    $overviewContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Access/components/RolesOverviewPanel.vue');
    $managerContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Access/components/RolesManagerPanel.vue');
    $matrixContents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Roles/Matrix.vue');

    expect($indexContents)->toContain("import RolesOverviewPanel from '@/pages/Admin/Access/components/RolesOverviewPanel.vue'")
        ->and($indexContents)->toContain("import RoleCreateDialog from '@/pages/Admin/Roles/components/RoleCreateDialog.vue'")
        ->and($indexContents)->toContain("import roleRoutes from '@/routes/core-panel/roles'")
        ->and($indexContents)->toContain('const canResyncManagedRoles = computed(')
        ->and($indexContents)->toContain('dialog.open(RoleCreateDialog')
        ->and($overviewContents)->toContain("import roleRoutes from '@/routes/core-panel/roles'")
        ->and($managerContents)->toContain('const canResyncManagedAccess = computed(')
        ->and($matrixContents)->toContain("import roleRoutes from '@/routes/core-panel/roles'")
        ->and($overviewContents)->toContain('roleRoutes.destroy.url(pendingDeleteRole.value.id)')
        ->and($matrixContents)->toContain('form.put(roleRoutes.update.url(selectedRole.value.id)')
        ->and($matrixContents)->toContain('class="cp-role-matrix-page__actions"')
        ->and($matrixContents)->not->toContain('cp-role-matrix-page__default-permissions')
        ->and($overviewContents)->not->toContain('permissionRoutes')
        ->and($overviewContents)->not->toContain('AssignUserRoleController')
        ->and($matrixContents)->not->toContain('permissionRoutes')
        ->and($matrixContents)->not->toContain('AssignUserRoleController');
});

it('uses wayfinder-driven file management endpoints in the files page', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Files/Index.vue');
    $translations = file_get_contents(__DIR__.'/../../resources/lang/de/files.php');
    $layoutContents = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AppLayout.vue');

    expect($contents)->toContain("import fileRoutes from '@/routes/core-panel/files'")
        ->and($contents)->toContain(":title=\"trans('files.title')\"")
        ->and($contents)->toContain(":subtitle=\"trans('files.description')\"")
        ->and($contents)->toContain('<template #page-actions>')
        ->and($contents)->toContain("import AppIcon from '@/components/AppIcon.vue'")
        ->and($contents)->toContain('class="cp-card cp-section"')
        ->and($contents)->toContain('summary: {')
        ->and($contents)->toContain("trans('files.summary.total_size')")
        ->and($contents)->toContain("trans('files.states.count_one', { count: String(props.files.total) })")
        ->and($contents)->toContain("trans('files.states.count_many', {")
        ->and($contents)->toContain("{{ trans('files.filters.title') }}")
        ->and($contents)->toContain('<FileUpload')
        ->and($contents)->toContain('<AppIcon name="grid" />')
        ->and($contents)->toContain('<AppIcon name="list" />')
        ->and($contents)->toContain('function filePreviewUrl(file: FileRecord): string {')
        ->and($contents)->toContain('class="h-full w-full scale-[0.92]"')
        ->and($contents)->toContain('class="h-full w-full object-contain p-2"')
        ->and($contents)->toContain('class="flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden')
        ->and($contents)->toContain('fileRoutes.index.url()')
        ->and($contents)->toContain('fileRoutes.store.url()')
        ->and($contents)->toContain('router.delete(fileRoutes.destroy.url(pendingDeleteFile.value.id), {')
        ->and($contents)->toContain('fileRoutes.download.url(file.id)')
        ->and($contents)->toContain('fileRoutes.preview.url(file.id)')
        ->and($contents)->not->toContain('Files/FileController')
        ->and($contents)->not->toContain('Files/FileDeleteController')
        ->and($contents)->not->toContain('Files/FileDownloadController')
        ->and($contents)->not->toContain('Files/FilePreviewController')
        ->and($contents)->not->toContain('Files/FileUploadController')
        ->and($translations)->toContain("'states' => [")
        ->and($translations)->toContain("'count_many' => ':count Dateien'")
        ->and($translations)->toContain("'count_one' => ':count Datei'")
        ->and($translations)->toContain("'title' => 'Filter'")
        ->and($translations)->toContain("'summary' => [")
        ->and($translations)->toContain("'total_size' => 'Gesamtgröße'")
        ->and($layoutContents)->toContain("if (method === 'get') {");
});

it('keeps the primary admin page wrappers fluid', function (): void {
    $pages = [
        'stubs/resources/js/pages/Admin/Activity/Index.vue',
        'stubs/resources/js/pages/Admin/ApiTokens/Index.vue',
        'stubs/resources/js/pages/Admin/Dashboard/Index.vue',
        'stubs/resources/js/pages/Admin/Files/Index.vue',
        'stubs/resources/js/pages/Admin/Forms/Create.vue',
        'stubs/resources/js/pages/Admin/Forms/Edit.vue',
        'stubs/resources/js/pages/Admin/Forms/Index.vue',
        'stubs/resources/js/pages/Admin/Forms/Preview.vue',
        'stubs/resources/js/pages/Admin/Forms/Submissions.vue',
        'stubs/resources/js/pages/Admin/Forms/Versions.vue',
        'stubs/resources/js/pages/Admin/OAuthClients/Index.vue',
        'stubs/resources/js/pages/Admin/Settings/Profile.vue',
        'stubs/resources/js/pages/Admin/Settings/Security.vue',
    ];

    foreach ($pages as $page) {
        $contents = file_get_contents(__DIR__.'/../../'.$page);

        expect($contents)->not->toBeFalse()
            ->and($contents)->not->toContain('mx-auto grid max-w-')
            ->and($contents)->not->toContain('max-w-7xl gap-6 px-4 py-8')
            ->and($contents)->not->toContain('max-w-6xl gap-6 px-4 py-8')
            ->and($contents)->not->toContain('max-w-5xl gap-6 px-4 py-8')
            ->and($contents)->not->toContain('max-w-4xl gap-6')
            ->and($contents)->not->toContain('max-w-3xl gap-6 px-4 py-8');
    }
});

it('uses a scrollable main admin content container', function (): void {
    $adminStyles = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_admin.css');

    expect($adminStyles)->not->toBeFalse()
        ->and($adminStyles)->toContain('.app-main {')
        ->and($adminStyles)->toContain('overscroll-behavior: contain;')
        ->and($adminStyles)->not->toContain('@apply flex min-h-0 w-full flex-1 flex-col overflow-y-auto;')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AppLayout.vue'))
        ->toContain('class="app-main flex min-h-0 w-full flex-1 flex-col overflow-y-auto px-4 pt-[calc(4.5rem+1.5rem)] pb-8 md:px-6 lg:px-8"');
});

it('uses wayfinder-driven user management endpoints in the user pages', function (): void {
    $index = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/Index.vue');
    $create = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/Create.vue');
    $edit = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/Edit.vue');
    $show = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/Show.vue');
    $usersTableTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UsersTableTab.vue');
    $userFormDialog = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserFormDialog.vue');
    $dashboard = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Dashboard/Index.vue');
    $header = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/components/AppHeader.vue');
    $rolesManager = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Access/components/RolesManagerPanel.vue');
    $authSettings = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/AuthSettingsTab.vue');
    $generalSettings = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/GeneralSettingsTab.vue');
    $settingsGroupPanel = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/SettingsGroupPanel.vue');
    $uiAppearanceSettings = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/UiAppearanceSettingsTab.vue');
    $avatarUpload = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileAvatarUpload.vue');
    $avatarController = file_get_contents(__DIR__.'/../../src/Http/Controllers/Users/UserAvatarController.php');
    $inertiaMiddleware = file_get_contents(__DIR__.'/../../stubs/app/Http/Middleware/HandleInertiaRequests.php');
    $adminTheme = file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_admin.css');

    expect($index)->toContain("import TabsRenderer from '@core-panel/components/TabBuilder/TabsRenderer.vue'")
        ->and($index)->toContain("import UserFormDialog from '@/pages/Admin/Users/components/UserFormDialog.vue'")
        ->and($index)->toContain('const dialog = useDialog()')
        ->and($index)->toContain('onSaved: reloadUsers')
        ->and($index)->not->toContain("from '../../../routes/core-panel/users'")
        ->and($usersTableTab)->toContain("import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'")
        ->and($usersTableTab)->toContain("import userRoutes from '@/routes/core-panel/users'")
        ->and($usersTableTab)->toContain('userRoutes.destroy.url(user.id)')
        ->and($usersTableTab)->toContain('userRoutes.restore.url(user.id)')
        ->and($usersTableTab)->toContain('userRoutes.forceDelete.url(user.id)')
        ->and($usersTableTab)->toContain('<TableBuilderDataTable')
        ->and($userFormDialog)->toContain("import users from '@/routes/core-panel/users'")
        ->and($userFormDialog)->toContain('form.put(users.update.url(user.id), options)')
        ->and($userFormDialog)->toContain('form.post(users.store.url(), options)')
        ->and($create)->toContain("import users from '@/routes/core-panel/users'")
        ->and($create)->toContain('form.post(users.store.url())')
        ->and($edit)->toContain("import users from '@/routes/core-panel/users'")
        ->and($edit)->toContain('form.put(users.update.url(props.user.id))')
        ->and($show)->toContain("import users from '@/routes/core-panel/users'")
        ->and($show)->toContain("import TabsRenderer from '@core-panel/components/TabBuilder/TabsRenderer.vue'")
        ->and($show)->toContain("import UserFormDialog from '@/pages/Admin/Users/components/UserFormDialog.vue'")
        ->and($show)->toContain('UserOverviewTab')
        ->and($show)->toContain('UserConnectionsTab')
        ->and($show)->toContain('UserSecurityTab')
        ->and($show)->toContain('UserSessionsTab')
        ->and($show)->toContain('canHardResetPassword')
        ->and($show)->toContain('dialog.open(UserFormDialog, {')
        ->and($show)->toContain("header: trans('page-users.edit_title')")
        ->and($show)->toContain('label: \'page-settings.tab_connections\'')
        ->and($show)->toContain('label: \'page-settings.tab_sessions\'')
        ->and($show)->toContain('class="cp-side-tabs cp-user-profile"')
        ->and($show)->not->toContain('panelSurface: true')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/components/AvatarUploadDropzone.vue'))->toContain('@drop.prevent="handleDrop"')
        ->and($adminTheme)->toContain('.cp-avatar-dropzone__media {')
        ->and($adminTheme)->toContain('border: 1px dashed')
        ->and($generalSettings)->toContain('const logoDragActive = ref(false)')
        ->and($generalSettings)->toContain('() => generalFieldsByKey.value.app_subtitle ?? null')
        ->and($generalSettings)->toContain('const generalAppSubtitleValue = computed<string>({')
        ->and($generalSettings)->toContain('generalForm.defaults()')
        ->and($generalSettings)->toContain('languageForm.defaults()')
        ->and($generalSettings)->toContain('generalForm.processing || !generalForm.isDirty')
        ->and($generalSettings)->toContain('languageForm.processing || !languageForm.isDirty')
        ->and($generalSettings)->toContain("fieldError(generalForm, 'app_subtitle')")
        ->and($generalSettings)->toContain('settings-general-app-subtitle')
        ->and($authSettings)->toContain('form.defaults()')
        ->and($authSettings)->toContain(':disabled="form.processing || !form.isDirty"')
        ->and($settingsGroupPanel)->toContain('form.defaults()')
        ->and($settingsGroupPanel)->toContain(':disabled="form.processing || !form.isDirty"')
        ->and($uiAppearanceSettings)->toContain('styleForm.defaults()')
        ->and($uiAppearanceSettings)->toContain(':disabled="styleForm.processing || !styleForm.isDirty"')
        ->and($generalSettings)->toContain('async function handleLogoDrop(event: DragEvent): Promise<void>')
        ->and($generalSettings)->toContain('@drop.prevent="handleLogoDrop"')
        ->and($generalSettings)->toContain(":class=\"{ 'is-drag-active': logoDragActive }\"")
        ->and($avatarUpload)->toContain('router.reload({')
        ->and($avatarUpload)->toContain('<AvatarUploadDropzone')
        ->and($avatarUpload)->toContain('@invalid-file="notifyInvalidFileType"')
        ->and($avatarUpload)->toContain('size="xl"')
        ->and($avatarUpload)->toContain("import AvatarUploadDropzone from '@/components/AvatarUploadDropzone.vue'")
        ->and($avatarUpload)->toContain("import userAvatarRoutes from '@/routes/core-panel/users/avatar'")
        ->and($avatarUpload)->toContain('layout="inline"')
        ->and($avatarUpload)->toContain("reloadKeys: () => ['auth', 'flash']")
        ->and($avatarUpload)->toContain('only: props.reloadKeys')
        ->and($avatarUpload)->not->toContain('page-settings.avatar_subtitle')
        ->and($avatarUpload)->not->toContain('useConfirm(')
        ->and($avatarUpload)->not->toContain('<ConfirmDialog')
        ->and($avatarUpload)->toContain('<ConfirmActionDialog')
        ->and($avatarUpload)->not->toContain('<Toast />')
        ->and($avatarUpload)->toContain(':title="$t(\'page-settings.avatar_remove_title\')"')
        ->and($avatarController)->toContain("'mimetypes:'.implode(',', \$allowedMimeTypes)")
        ->and($avatarController)->toContain("'max:'.\$maxUploadSize")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->toContain("import userPasswordRoutes from '@/routes/core-panel/users/password'")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->toContain('userPasswordRoutes.resetLink.url(props.user.id)')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->toContain("import UserPasswordResetDialog from '@/pages/Admin/Users/components/UserPasswordResetDialog.vue'")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->toContain("import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->toContain('dialog.open(UserPasswordResetDialog, {')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->toContain('<ConfirmActionDialog')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->toContain('confirm-severity="danger"')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->toContain('icon="trash"')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->not->toContain('useConfirm(')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSecurityTab.vue'))->not->toContain('$t(\'common.ui.roles\')')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserPasswordResetDialog.vue'))->toContain("import TranslatedPassword from '@/components/TranslatedPassword.vue'")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserPasswordResetDialog.vue'))->toContain('userPasswordRoutes.update.url(user.id)')
        ->and($inertiaMiddleware)->toContain("'uploads' => [")
        ->and($inertiaMiddleware)->toContain('$publicSettings = app(SettingsRepository::class)->public();')
        ->and($inertiaMiddleware)->toContain("\$appSubtitle = data_get(\$publicSettings, 'general.app_subtitle');")
        ->and($inertiaMiddleware)->toContain("'appLogo' => fn (): ?string => \$settingsLogo->currentUrl()")
        ->and($inertiaMiddleware)->toContain("'error' => fn (): ?string => \$request->session()->get('error')")
        ->and($inertiaMiddleware)->toContain("'success' => fn (): ?string => \$request->session()->get('success')")
        ->and($inertiaMiddleware)->toContain("'accept' => implode(',', \$avatarMimeTypes)")
        ->and($inertiaMiddleware)->toContain("'formatBadges' => \$avatarFormatBadges")
        ->and($inertiaMiddleware)->toContain("'accept' => implode(',', \$logoMimeTypes)")
        ->and($inertiaMiddleware)->toContain("'formatBadges' => \$logoFormatBadges")
        ->and($inertiaMiddleware)->toContain("'maxSizeMb' => (int) floor(")
        ->and($inertiaMiddleware)->toContain("'mimeTypes' => \$avatarMimeTypes")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AuthLayout.vue'))->toContain('const appSubtitle = computed(() => {')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AuthLayout.vue'))->toContain('<h2 v-if="appSubtitle">{{ appSubtitle }}</h2>')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/components/AppSidebar.vue'))->toContain('const appSubtitle = computed(() => {')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/components/AppSidebar.vue'))->toContain('v-if="appSubtitle"')
        ->and(file_get_contents(__DIR__.'/../../src/Http/Requests/UpdateUserRequest.php'))->toContain("'remove_avatar' => ['sometimes', 'boolean']")
        ->and(file_get_contents(__DIR__.'/../../src/Domains/User/Actions/UpdateUserAction.php'))->toContain("(\$attributes['remove_avatar'] ?? false) === true")
        ->and($avatarController)->toContain('if ($request->expectsJson()) {')
        ->and($avatarController)->toContain("'avatar_url' => \$this->users->avatarUrl(\$target->refresh())")
        ->and($header)->toContain("import users from '@/routes/core-panel/users'")
        ->and($header)->toContain('router.visit(users.index.url())')
        ->and($rolesManager)->toContain("import userRoleRoutes from '@/routes/core-panel/users/roles'")
        ->and($rolesManager)->toContain('assignmentForm.post(userRoleRoutes.assign.url(assignmentForm.user_id), {})')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSessionsTab.vue'))->toContain('userSessionRoutes.index.url(props.userId)')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSessionsTab.vue'))->toContain('userSessionRoutes.destroy.url({')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSessionsTab.vue'))->toContain('user: props.userId')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserSessionsTab.vue'))->toContain('session: session.id')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain("import UserAvatar from '@/components/UserAvatar.vue'")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain('<UserAvatar')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain(":presence-status=\"user.presenceStatus ?? 'offline'\"")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain('size="lg"')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain("props.user.status === 'blocked'")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain("props.user.status === 'inactive'")
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain(':severity="statusSeverity"')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain(':value="$t(statusLabel)"')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain('visibleRoleLabels')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain('props.roleLabels[role] ?? role')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->toContain('Intl.DateTimeFormat(')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Users/components/UserOverviewTab.vue'))->not->toContain("\$t('common.ui.assigned')")
        ->and($show)->toContain('roleLabels: props.roleLabels,')
        ->and($show)->not->toContain('UserProfileController.edit.url(user.id)')
        ->and($show)->not->toContain('UserController.index.url()');
});

it('uses wayfinder-driven form management endpoints in the form pages', function (): void {
    $index = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Forms/Index.vue');
    $create = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Forms/Create.vue');
    $edit = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Forms/Edit.vue');
    $preview = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Forms/Preview.vue');
    $submissions = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Forms/Submissions.vue');

    expect($index)->toContain("import formRoutes from '@/routes/core-panel/forms'")
        ->and($index)->toContain('formRoutes.create.url()')
        ->and($index)->toContain('formRoutes.destroy.url(pendingDeleteForm.value.id)')
        ->and($index)->not->toContain('Forms/FormController')
        ->and($create)->toContain('forms.store.url()')
        ->and($create)->toContain('forms.index.url()')
        ->and($create)->not->toContain('Forms/FormController')
        ->and($edit)->toContain('forms.update.url(props.form.id)')
        ->and($edit)->toContain('forms.publish.url(props.form.id)')
        ->and($edit)->toContain('forms.preview.url(props.form.id)')
        ->and($edit)->not->toContain('Forms/FormController')
        ->and($edit)->not->toContain('Forms/FormSubmissionController')
        ->and($preview)->toContain("import publicForms from '@/routes/core-panel/forms/public'")
        ->and($preview)->toContain('publicForms.store(props.form.slug)')
        ->and($preview)->not->toContain('Forms/PublicFormController')
        ->and($submissions)->toContain('forms.submissions.export.url(form.id)')
        ->and($submissions)->toContain('forms.edit.url(form.id)')
        ->and($submissions)->not->toContain('Forms/FormController')
        ->and($submissions)->not->toContain('Forms/FormSubmissionController');
});

it('uses wayfinder-driven api token endpoints in the api token page', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/ApiTokens/components/ApiTokenManager.vue');

    expect($contents)->toContain("import apiTokens from '@/routes/core-panel/api-tokens'")
        ->and($contents)->toContain('apiTokens.store.url()')
        ->and($contents)->toContain('apiTokens.replace.url(pendingReplaceToken.value.id)')
        ->and($contents)->toContain('apiTokens.destroy.url(pendingDeleteToken.value.id)')
        ->and($contents)->toContain('navigator.clipboard.writeText(secret)')
        ->and($contents)->toContain('<AppIcon name="copy" />')
        ->and($contents)->toContain('<AppIcon name="refresh" />')
        ->and($contents)->toContain('<textarea')
        ->and($contents)->toContain('readonly')
        ->and($contents)->toContain('overflow-auto')
        ->and($contents)->toContain('formatDateTime(row.lastUsedAt)')
        ->and($contents)->toContain('class="flex justify-end"')
        ->and($contents)->toContain('v-for="ability in row.abilities"')
        ->and($contents)->toContain('class="cp-datatable__action-button"')
        ->and($contents)->toContain('outlined')
        ->and($contents)->toContain('severity="danger"')
        ->and($contents)->not->toContain('row.abilities.slice(0, 3)')
        ->and($contents)->not->toContain('row.abilities.length > 3')
        ->and($contents)->not->toContain("severity=\"danger\"\n                        text")
        ->and($contents)->not->toContain('ApiTokenController');
});

it('organizes versioned api routes under a dedicated v1 route tree', function (): void {
    $apiRoutes = file_get_contents(__DIR__.'/../../routes/api.php');
    $apiV1Routes = file_get_contents(__DIR__.'/../../routes/api/v1.php');
    $apiV1UsersRoutes = file_get_contents(__DIR__.'/../../routes/api/v1/users.php');
    $apiV1UsersController = file_get_contents(__DIR__.'/../../src/Http/Controllers/Api/V1/UserController.php');

    expect($apiRoutes)->toContain("->group(__DIR__.'/api/v1.php');")
        ->and($apiRoutes)->not->toContain("Route::get('/me', [ApiTokenController::class, 'me'])")
        ->and($apiV1Routes)->toContain("Route::prefix('v1')")
        ->and($apiV1Routes)->toContain("require __DIR__.'/v1/users.php';")
        ->and($apiV1Routes)->toContain("Route::get('/me', [ApiTokenController::class, 'me'])->name('me');")
        ->and($apiV1UsersRoutes)->toContain("Route::get('/users', [UserController::class, 'index'])->name('users.index');")
        ->and($apiV1UsersRoutes)->toContain("Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');")
        ->and($apiV1UsersController)->toContain('return $this->paginated($paginator, UserResource::class);')
        ->and($apiV1UsersController)->toContain('return $this->success(UserResource::make($target));');
});

it('surfaces api token management inside the settings workspace', function (): void {
    $dashboard = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Dashboard/Index.vue');
    $settingsIndex = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/Index.vue');
    $apiSettingsTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ApiSettingsTab.vue');
    $apiTokensIndex = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/ApiTokens/Index.vue');
    $apiTokenManager = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/ApiTokens/components/ApiTokenManager.vue');
    $apiTokenAbilityOptions = file_get_contents(__DIR__.'/../../src/Support/Api/ApiTokenAbilityOptions.php');
    $settingsController = file_get_contents(__DIR__.'/../../src/Http/Controllers/SettingsController.php');
    $settingsSchema = file_get_contents(__DIR__.'/../../src/Support/Settings/SettingsSchema.php');

    expect($settingsIndex)->toContain("import ApiSettingsTab from '@/pages/Admin/Settings/components/ApiSettingsTab.vue'")
        ->and($settingsIndex)->toContain("const visibleGroupKeys = ['general', 'appearance', 'auth', 'api']")
        ->and($settingsIndex)->toContain("'ApiSettingsTab'")
        ->and($settingsIndex)->toContain('apiTokenManager: props.apiTokenManager')
        ->and($settingsIndex)->toContain('createRequestKey: apiTokenCreateRequest.value')
        ->and($settingsIndex)->toContain('onRequestCreateToken: requestCreateToken')
        ->and($apiSettingsTab)->toContain("import ApiTokenManager from '@/pages/Admin/ApiTokens/components/ApiTokenManager.vue'")
        ->and($apiSettingsTab)->toContain("import AppIcon from '@/components/AppIcon.vue'")
        ->and($apiSettingsTab)->toContain('cp-section__header cp-section__header--split')
        ->and($apiSettingsTab)->not->toContain('cp-section__body')
        ->and($apiSettingsTab)->toContain("{{ \$t('page-api-tokens.title') }}")
        ->and($apiSettingsTab)->toContain(':can-create="props.apiTokenManager.canCreate"')
        ->and($apiSettingsTab)->toContain(':create-request-key="props.createRequestKey"')
        ->and($apiSettingsTab)->toContain('@click="props.onRequestCreateToken?.()"')
        ->and($apiSettingsTab)->toContain('<AppIcon name="plus" />')
        ->and($apiTokensIndex)->toContain('<ApiTokenManager')
        ->and($apiTokensIndex)->toContain('can-create')
        ->and($apiTokensIndex)->toContain('can-delete')
        ->and($apiTokenManager)->toContain("import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'")
        ->and($apiTokenManager)->toContain("import apiTokens from '@/routes/core-panel/api-tokens'")
        ->and($apiTokenManager)->toContain('<TableBuilderDataTable')
        ->and($apiTokenManager)->toContain('<ColumnVisibilityDropdown')
        ->and($apiTokenManager)->toContain('createRequestKey?: number')
        ->and($apiTokenManager)->toContain('v-if="props.canCreate && !props.embedded"')
        ->and($apiTokenAbilityOptions)->toContain("__('page-api-tokens.abilities.create')")
        ->and($apiTokenAbilityOptions)->toContain("'value' => 'create'")
        ->and($apiTokenAbilityOptions)->toContain("__('page-api-tokens.abilities.read')")
        ->and($apiTokenAbilityOptions)->toContain("'value' => 'read'")
        ->and($apiTokenAbilityOptions)->toContain("__('page-api-tokens.abilities.update')")
        ->and($apiTokenAbilityOptions)->toContain("'value' => 'update'")
        ->and($apiTokenAbilityOptions)->toContain("__('page-api-tokens.abilities.delete')")
        ->and($apiTokenAbilityOptions)->toContain("'value' => 'delete'")
        ->and($settingsController)->toContain("'apiTokenManager' => \$this->apiTokenManagerPayload(\$request)")
        ->and($settingsController)->toContain("'abilities' => ApiTokenAbilityOptions::options()")
        ->and($settingsController)->toContain("'canCreate' => true")
        ->and($settingsController)->toContain("'canDelete' => true")
        ->and($settingsController)->toContain("'key' => 'api'")
        ->and($settingsController)->toContain("__('page-settings.tab_api')")
        ->and($settingsSchema)->not->toContain("'api' => [");
});

it('uses wayfinder-driven activity endpoints in the activity page', function (): void {
    $contents = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Activity/Index.vue');

    expect($contents)->toContain("import activity from '@/routes/core-panel/activity'")
        ->and($contents)->toContain('activity.index.url()')
        ->and($contents)->toContain('activity.show.url(log.id)')
        ->and($contents)->not->toContain('ActivityLogController')
        ->and($contents)->not->toContain('ActivityLogDetailController');
});

it('ships the consolidated developer area with tabbed activity, authentication, and log views', function (): void {
    $routes = file_get_contents(__DIR__.'/../../routes/web/admin.php');
    $logRoutes = file_get_contents(__DIR__.'/../../routes/web/admin/logs.php');
    $developer = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/Index.vue');
    $logFilePage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/File.vue');
    $adminMenu = file_get_contents(__DIR__.'/../../stubs/resources/js/composables/useAdminMenu.ts');
    $logsController = file_get_contents(__DIR__.'/../../src/Http/Controllers/Logs/LogController.php');
    $activityTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/ActivityLogsTab.vue');
    $activityDetail = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/ActivityLogDetail.vue');
    $authenticationTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/AuthenticationLogsTab.vue');
    $authenticationDetail = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/AuthenticationLogDetail.vue');
    $authenticationPresentation = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/authenticationLogPresentation.ts');
    $logUserAvatar = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/LogUserAvatar.vue');
    $logsTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Logs/components/LogFilesTab.vue');

    expect($routes)->toContain("'logs.php'")
        ->and($logRoutes)->toContain('use CorePanel\Http\Controllers\Logs\ActivityLogDetailController;')
        ->and($logRoutes)->toContain('use CorePanel\Http\Controllers\Logs\AuthenticationLogDetailController;')
        ->and($logRoutes)->toContain('use CorePanel\Http\Controllers\Logs\LogController;')
        ->and($logRoutes)->toContain('use CorePanel\Http\Controllers\Logs\LogFileController;')
        ->and($logRoutes)->toContain('use CorePanel\Http\Controllers\Logs\LogFileEntriesController;')
        ->and($logRoutes)->toContain("Route::get('/logs', LogController::class)->name('logs.index');")
        ->and($logRoutes)->toContain("Route::get('/authentication-logs/{authenticationLog}', [AuthenticationLogDetailController::class, 'show'])->name('authentication-logs.show');")
        ->and($logRoutes)->toContain("Route::get('/log-files/{filename}', LogFileController::class)->name('log-files.show');")
        ->and($logRoutes)->toContain("Route::get('/log-files/{filename}/entries', LogFileEntriesController::class)->name('log-files.entries');")
        ->and($developer)->toContain("label: 'page-logs.tabs.activity'")
        ->and($developer)->toContain("label: 'page-logs.tabs.authentication'")
        ->and($developer)->toContain("label: 'page-logs.tabs.logs'")
        ->and($developer)->toContain("if (props.logsTab && hasRole('super-admin'))")
        ->and($adminMenu)->toContain("label: 'navigation.logs'")
        ->and($activityTab)->toContain("import logsPage from '@/routes/core-panel/logs'")
        ->and($activityTab)->toContain("import LogUserAvatar from '@/pages/Admin/Logs/components/LogUserAvatar.vue'")
        ->and($activityTab)->toContain('row.causerAvatarUrl ?? null')
        ->and($activityDetail)->toContain("import LogUserAvatar from '@/pages/Admin/Logs/components/LogUserAvatar.vue'")
        ->and($logsController)->toContain('$request->has(\'per_page\')')
        ->and($logsController)->toContain('$request->has(\'sort\')')
        ->and($authenticationTab)->toContain("import authenticationLogs from '@/routes/core-panel/authentication-logs'")
        ->and($authenticationTab)->toContain("import LogUserAvatar from '@/pages/Admin/Logs/components/LogUserAvatar.vue'")
        ->and($authenticationTab)->toContain('formatAuthenticationDeviceLabel')
        ->and($authenticationTab)->toContain('formatAuthenticationMethodLabel')
        ->and($authenticationTab)->toContain('row.userAvatarUrl ?? null')
        ->and($authenticationTab)->toContain('authMethodLabel: formatAuthenticationMethodLabel(log)')
        ->and($authenticationTab)->toContain("import AuthenticationLogDetail from '@/pages/Admin/Logs/components/AuthenticationLogDetail.vue'")
        ->and($authenticationDetail)->toContain("import LogUserAvatar from '@/pages/Admin/Logs/components/LogUserAvatar.vue'")
        ->and($authenticationDetail)->toContain("trans('page-authentication-logs.user_agent')")
        ->and($authenticationDetail)->toContain("trans('page-authentication-logs.columns.device_type')")
        ->and($authenticationPresentation)->toContain("trans('page-authentication-logs.device_browser_on_platform'")
        ->and($authenticationPresentation)->toContain('looksLikeUserAgent')
        ->and($authenticationPresentation)->toContain("normalized.includes('mozilla/')")
        ->and($authenticationPresentation)->toContain("'page-authentication-logs.methods.socialite_provider'")
        ->and($logUserAvatar)->toContain("import UserAvatar from '@/components/UserAvatar.vue'")
        ->and($logUserAvatar)->toContain('v-tooltip.top="label"')
        ->and($logsTab)->toContain("import logFiles from '@/routes/core-panel/log-files'")
        ->and($logFilePage)->toContain("import logFiles from '@/routes/core-panel/log-files'")
        ->and($logFilePage)->toContain("import logsPage from '@/routes/core-panel/logs'")
        ->and($logFilePage)->toContain('logFiles.entries.url(props.file.name)')
        ->and($logFilePage)->toContain('router.visit(`${logsPage.index.url()}?tab=logs`)');
});

it('ships a dedicated developer workspace with route inspection and swagger-backed docs', function (): void {
    $routes = file_get_contents(__DIR__.'/../../routes/web/admin.php');
    $developerRoutes = file_get_contents(__DIR__.'/../../routes/web/admin/developer.php');
    $controller = file_get_contents(__DIR__.'/../../src/Http/Controllers/Developer/DeveloperController.php');
    $catalog = file_get_contents(__DIR__.'/../../src/Support/Developer/RouteCatalog.php');
    $menu = file_get_contents(__DIR__.'/../../stubs/resources/js/composables/useAdminMenu.ts');
    $page = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Developer/Index.vue');
    $routeTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Developer/components/RouteListTab.vue');
    $docsTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Developer/components/SwaggerDocsTab.vue');
    $swaggerConfig = file_get_contents(__DIR__.'/../../stubs/config/l5-swagger.php');
    $openApiInfo = file_get_contents(__DIR__.'/../../stubs/app/OpenApi/CorePanelApiDocumentation.php');
    $openApiSchemas = file_get_contents(__DIR__.'/../../stubs/app/OpenApi/Components/CorePanelSchemas.php');
    $openApiAuth = file_get_contents(__DIR__.'/../../stubs/app/OpenApi/Paths/AuthenticationApi.php');
    $openApiSystem = file_get_contents(__DIR__.'/../../stubs/app/OpenApi/Paths/SystemApi.php');
    $openApiUsers = file_get_contents(__DIR__.'/../../stubs/app/OpenApi/Paths/UsersApi.php');
    $composer = file_get_contents(__DIR__.'/../../composer.json');

    expect($routes)->toContain("'developer.php'")
        ->and($developerRoutes)->toContain("Route::get('/developer', DeveloperController::class)->name('developer.index');")
        ->and($developerRoutes)->toContain("Route::post('/developer/regenerate-api-docs', RegenerateApiDocsController::class)->name('developer.regenerate-api-docs');")
        ->and($controller)->toContain("return Inertia::render('Developer/Index'")
        ->and($controller)->toContain("'docsUrl' => '/'.ltrim((string) config('l5-swagger.documentations.default.routes.api', 'api/documentation'), '/')")
        ->and($catalog)->toContain('final class RouteCatalog')
        ->and($menu)->toContain("import developer from '@/routes/core-panel/developer'")
        ->and($menu)->toContain("label: 'navigation.developer'")
        ->and($menu)->toContain("label: 'navigation.routes'")
        ->and($menu)->toContain("anyPermissions: ['api-routes.view', 'api-docs.view']")
        ->and($page)->toContain("import developer from '@/routes/core-panel/developer'")
        ->and($page)->toContain("import RouteListTab from '@/pages/Admin/Developer/components/RouteListTab.vue'")
        ->and($page)->toContain(":title=\"trans('navigation.routes')\"")
        ->and($page)->toContain('page-developer.actions.generate_docs')
        ->and($page)->toContain('developer.regenerateApiDocs.url()')
        ->and($page)->toContain("icon: 'sitemap'")
        ->and($page)->toContain("icon: 'globe'")
        ->and($page)->toContain("icon: 'bolt'")
        ->and($page)->toContain("panelSurfaceVariant: 'card'")
        ->and($page)->toContain('<div class="cp-route-management">')
        ->and($page)->not->toContain("label: 'page-developer.tabs.docs'")
        ->and($routeTab)->toContain("meta: { labelKey: 'page-developer.columns.method' }")
        ->and($routeTab)->toContain('<div class="cp-section__header">')
        ->and($routeTab)->toContain('class="grid min-w-0 flex-1 gap-1"')
        ->and($routeTab)->toContain('class="text-lg font-semibold text-[var(--cp-text-primary)]"')
        ->and($routeTab)->toContain('class="text-sm text-[var(--cp-text-muted)]"')
        ->and($routeTab)->not->toContain('class="cp-section cp-section--compact"')
        ->and($routeTab)->not->toContain('<div class="cp-section__body">')
        ->and($routeTab)->toContain('v-for="method in row.methods"')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/components/AppIcon.vue'))->toContain('Globe')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/js/components/AppIcon.vue'))->toContain('globe: Globe')
        ->and($docsTab)->toContain('cp-developer-docs__frame')
        ->and($swaggerConfig)->toContain("'api' => ['web', 'auth', 'core-panel.verified', 'core-panel.api-docs']")
        ->and($swaggerConfig)->toContain("base_path('app/OpenApi')")
        ->and($openApiInfo)->toContain('#[OA\\Info(')
        ->and($openApiInfo)->toContain('#[OA\\SecurityScheme(')
        ->and($openApiSchemas)->toContain("schema: 'CorePanelApiUser'")
        ->and($openApiSchemas)->toContain("schema: 'CorePanelUserSummary'")
        ->and($openApiSchemas)->toContain("schema: 'CorePanelAuthProvider'")
        ->and($openApiAuth)->toContain("path: '/api/v1/me'")
        ->and($openApiSystem)->toContain("path: '/api/v1/ping'")
        ->and($openApiUsers)->toContain("path: '/api/v1/users'")
        ->and($openApiUsers)->toContain("path: '/api/v1/users/{user}'")
        ->and($openApiInfo)->not->toContain('#[OA\\PathItem(path: \'/\')]')
        ->and(file_get_contents(__DIR__.'/../../stubs/resources/css/theme/_tabs.css'))->toContain('.cp-route-management .cp-side-tabs__panel-surface {')
        ->and($composer)->toContain('"darkaonline/l5-swagger": "^11.0"');
});

it('ships locale switching assets and shared locale scaffolding', function (): void {
    $switcher = file_get_contents(__DIR__.'/../../stubs/resources/js/components/Locale/LocaleSwitcher.vue');
    $hostEntry = file_get_contents(__DIR__.'/../../stubs/resources/js/app.ts');
    $handleInertia = file_get_contents(__DIR__.'/../../stubs/app/Http/Middleware/HandleInertiaRequests.php');
    $bootstrap = file_get_contents(__DIR__.'/../../stubs/bootstrap/app.php');
    $runtimeSettingsMiddleware = file_get_contents(__DIR__.'/../../src/Http/Middleware/ApplyCorePanelRuntimeSettings.php');
    $localeController = file_get_contents(__DIR__.'/../../src/Http/Controllers/SetLocaleController.php');
    $localeResolver = file_get_contents(__DIR__.'/../../src/Support/LocaleResolver.php');
    $authLayout = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AuthLayout.vue');
    $appHeader = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/components/AppHeader.vue');

    expect($switcher)->toContain("import locale from '@/routes/locale'")
        ->and($switcher)->toContain("import { router, usePage } from '@inertiajs/vue3'")
        ->and($switcher)->not->toContain("import { useForm, usePage } from '@inertiajs/vue3'")
        ->and($switcher)->toContain('locale.set.url()')
        ->and($switcher)->toContain('preserveState: true')
        ->and($switcher)->toContain('onSuccess: async () => {')
        ->and($switcher)->toContain('router.reload()')
        ->and($switcher)->toContain('page.props.locale?.labels?.[locale] ?? locale.toUpperCase()')
        ->and($switcher)->not->toContain('SetLocaleController')
        ->and($hostEntry)->toContain('const lazyLanguageModules = import.meta.glob<{')
        ->and($hostEntry)->toContain('default: Record<string, string>')
        ->and($hostEntry)->toContain('const loader =')
        ->and($hostEntry)->toContain('lazyLanguageModules[`../../lang/php_${lang}.json`]')
        ->and(ScaffoldsCorePanelStubs::paths())->toContain('lang/de/page-layout.php', 'lang/en/page-layout.php')
        ->and($handleInertia)->toContain('SettingsRepository::class')
        ->and($runtimeSettingsMiddleware)->toContain("config()->set('app.name'")
        ->and($runtimeSettingsMiddleware)->toContain("config()->set('app.languages', SupportedLocales::labelsFor(\$supportedLocaleCodes));")
        ->and($runtimeSettingsMiddleware)->toContain("config()->set('core-panel.i18n.supported_locales'")
        ->and($localeController)->toContain("Cookie::queue(Cookie::forever(self::COOKIE_NAME, \$validated['locale']));")
        ->and($localeResolver)->toContain('$request->cookie(self::COOKIE_NAME)')
        ->and($handleInertia)->toContain("'locale' => [")
        ->and($handleInertia)->toContain("'settings' => app(SettingsRepository::class)->public()")
        ->and($authLayout)->toContain('preserveState: true')
        ->and($authLayout)->toContain('auth-locale-switch__item-label')
        ->and($authLayout)->toContain('@click.prevent="switchLocale(item.localeCode)"')
        ->and($appHeader)->toContain('currentLocaleLabel')
        ->and($appHeader)->toContain('preserveState: true')
        ->and($appHeader)->toContain('@click.prevent="switchLocale(item.localeCode)"')
        ->and($bootstrap)->toContain('ApplyCorePanelRuntimeSettings::class')
        ->and($bootstrap)->toContain('SecurityHeaders::class')
        ->and($bootstrap)->toContain('ResolveCorePanelLocale::class')
        ->and($bootstrap)->toContain('ShareLocaleDataWithInertia::class');
});

it('sets the locale cookie on the locale switch response', function (): void {
    config()->set('core-panel.i18n.supported_locales', ['de', 'en']);

    $response = $this->from('/login')->post(route('locale.set'), [
        'locale' => 'en',
        'redirect_to' => '/login',
    ]);

    $response->assertRedirect('/login')
        ->assertCookie('locale', 'en');

    expect(session('locale'))->toBe('en');
});

it('sets the locale cookie on inertia locale switch responses', function (): void {
    config()->set('core-panel.i18n.supported_locales', ['de', 'en']);

    $response = $this
        ->from('/login')
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('locale.set'), [
            'locale' => 'en',
        ]);

    $response->assertRedirect('/login')
        ->assertCookie('locale', 'en');

    expect(session('locale'))->toBe('en');
});

it('resets repeated flash toast deduplication when a new inertia visit starts', function (): void {
    $layout = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AppLayout.vue');

    expect($layout)->toContain("import { router, usePage } from '@inertiajs/vue3'")
        ->and($layout)->toContain("removeVisitStartListener = router.on('start', (event) => {")
        ->and($layout)->toContain('(event as { detail?: { visit?: { method?: string } } }).detail')
        ->and($layout)->toContain("?.visit?.method ?? 'get'")
        ->and($layout)->toContain(').toLowerCase()')
        ->and($layout)->toContain("if (method === 'get') {")
        ->and($layout)->toContain('lastFlashFingerprint.value = null')
        ->and($layout)->toContain('removeVisitStartListener?.()');
});

it('does not ship unresolved core-panel users route imports in publishable core-panel vue assets', function (): void {
    $directory = new RecursiveDirectoryIterator(
        __DIR__.'/../../resources/js',
        FilesystemIterator::SKIP_DOTS,
    );
    $iterator = new RecursiveIteratorIterator($directory);
    $matches = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'vue' && $file->getExtension() !== 'ts') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if ($contents === false || ! str_contains($contents, 'routes/core-panel/users')) {
            continue;
        }

        $matches[] = $file->getPathname();
    }

    expect($matches)->toBe([]);
});

it('orders assignable users without relying on a legacy name column', function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('core-panel.user_model', FakeUser::class);

    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    FakeUser::query()->create([
        'email' => 'zoe@example.test',
        'first_name' => 'Zoe',
        'last_name' => 'Zimmer',
        'password' => 'secret',
    ]);
    FakeUser::query()->create([
        'email' => 'anna@example.test',
        'first_name' => 'Anna',
        'last_name' => 'Abele',
        'password' => 'secret',
    ]);

    $users = app(PermissionService::class)->usersForAssignment();

    expect($users->pluck('email')->all())->toBe([
        'anna@example.test',
        'zoe@example.test',
    ]);
});

it('ships core panel migrations with fixed timestamped php filenames', function (): void {
    $migrations = glob(__DIR__.'/../../stubs/database/migrations/*.php');

    expect($migrations)->not->toBeFalse()
        ->and($migrations)->each->toMatch('/\/\d{4}_\d{2}_\d{2}_\d{6}_.+\.php$/')
        ->and(glob(__DIR__.'/../../stubs/database/migrations/*.stub'))->toBe([]);
});

it('keeps package-level database migrations empty because scaffold migrations are the source of truth', function (): void {
    expect(glob(__DIR__.'/../../database/migrations/*.php'))->toBe([])
        ->and(file_get_contents(__DIR__.'/../../src/CorePanelServiceProvider.php'))->not->toContain("loadMigrationsFrom(__DIR__.'/../database/migrations')");
});

it('ships laravel default migration names in the scaffold', function (): void {
    $usersMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/0001_01_01_000000_create_users_table.php');
    $cacheMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/0001_01_01_000001_create_cache_table.php');
    $jobsMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/0001_01_01_000002_create_jobs_table.php');

    expect($usersMigration)->toContain("Schema::create('users'")
        ->and($usersMigration)->toContain("\$table->uuid('id')->primary();")
        ->and($usersMigration)->toContain("Schema::create('password_reset_tokens'")
        ->and($usersMigration)->toContain("Schema::create('sessions'")
        ->and($usersMigration)->toContain("\$table->string('user_id')->nullable()->index();")
        ->and($usersMigration)->toContain("\$table->text('two_factor_secret')->nullable();")
        ->and($usersMigration)->toContain("\$table->text('two_factor_recovery_codes')->nullable();")
        ->and($usersMigration)->toContain("\$table->timestamp('two_factor_confirmed_at')->nullable();")
        ->and($cacheMigration)->toContain("Schema::create('cache'")
        ->and($jobsMigration)->toContain("Schema::create('jobs'");
});

it('bundles fixed permission migrations from installed packages', function (): void {
    $permissionMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2026_01_01_000014_create_permission_tables.php');

    expect($permissionMigration)->toContain("Schema::create(\$tableNames['permissions']")
        ->and($permissionMigration)->toContain("Schema::create(\$tableNames['roles']")
        ->and($permissionMigration)->toContain("\$table->string('core_panel_group')->nullable();")
        ->and($permissionMigration)->toContain("\$table->string(\$columnNames['model_morph_key']);");
});

it('bundles fixed passport, activitylog, and medialibrary migrations from installed packages', function (): void {
    $passportAuthCodesMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2016_06_01_000001_create_oauth_auth_codes_table.php');
    $passportAccessTokensMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2016_06_01_000002_create_oauth_access_tokens_table.php');
    $passportAccessTokensLastUsedMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2026_01_01_000022_add_last_used_at_to_oauth_access_tokens_table.php');
    $passportClientsMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2016_06_01_000004_create_oauth_clients_table.php');
    $passportDeviceCodesMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2024_06_01_000001_create_oauth_device_codes_table.php');
    $socialAccountsMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2026_01_01_000008_create_social_accounts_table.php');
    $activityLogMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2018_01_01_000001_create_activity_log_table.php');
    $mediaMigration = file_get_contents(__DIR__.'/../../stubs/database/migrations/2019_01_01_000001_create_media_table.php');

    expect($passportAuthCodesMigration)->toContain("\$table->uuid('user_id')->index();")
        ->and($passportAccessTokensMigration)->toContain("\$table->uuid('user_id')->nullable()->index();")
        ->and($passportAccessTokensMigration)->toContain("\$table->timestamp('last_used_at')->nullable();")
        ->and($passportAccessTokensLastUsedMigration)->toContain("Schema::table('oauth_access_tokens'")
        ->and($passportAccessTokensLastUsedMigration)->toContain("\$table->timestamp('last_used_at')->nullable()->after('updated_at');")
        ->and($passportClientsMigration)->toContain("Schema::create('oauth_clients'")
        ->and($passportClientsMigration)->toContain("\$table->nullableUuidMorphs('owner');")
        ->and($passportDeviceCodesMigration)->toContain("Schema::create('oauth_device_codes'")
        ->and($passportDeviceCodesMigration)->toContain("\$table->uuid('user_id')->nullable()->index();")
        ->and($socialAccountsMigration)->toContain("\$table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();")
        ->and($activityLogMigration)->toContain("Schema::create('activity_log'")
        ->and($activityLogMigration)->toContain("\$table->string('subject_id')->nullable();")
        ->and($activityLogMigration)->toContain("\$table->string('causer_id')->nullable();")
        ->and($mediaMigration)->toContain("\$table->string('model_id');")
        ->and(file_exists(__DIR__.'/../../stubs/database/migrations/2026_01_01_000021_change_activity_log_morph_ids_to_strings.php'))->toBeFalse()
        ->and(file_exists(__DIR__.'/../../stubs/database/migrations/2026_01_01_000022_change_media_model_morph_ids_to_strings.php'))->toBeFalse()
        ->and($mediaMigration)->toContain("Schema::create('media'");
});
