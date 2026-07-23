<?php

declare(strict_types=1);

namespace CorePanel;

use CorePanel\Console\AssignSuperAdminCommand;
use CorePanel\Console\CleanActivityLogsCommand;
use CorePanel\Console\ConvertMySqlDatetimesCommand;
use CorePanel\Console\ConvertTimestampsToTimestamptzCommand;
use CorePanel\Console\InstallCommand;
use CorePanel\Console\MakeActionCommand;
use CorePanel\Console\MakeCrudCommand;
use CorePanel\Console\MakeDomainCommand;
use CorePanel\Console\MakeDtoCommand;
use CorePanel\Console\MakeFormCommand;
use CorePanel\Console\MakeTableCommand;
use CorePanel\Console\PublishCommand;
use CorePanel\Console\RunAutomaticDatabaseBackupCommand;
use CorePanel\Console\RunAutomaticSystemUpdateCommand;
use CorePanel\Console\SyncAccessCommand;
use CorePanel\Console\SyncEnvironmentCommand;
use CorePanel\Console\UpdateCommand;
use CorePanel\Console\VendorFirstCleanupCommand;
use CorePanel\Contracts\CorePanelInstallerInterface;
use CorePanel\Contracts\LocaleResolver;
use CorePanel\Contracts\SettingsLogoUrlGenerator;
use CorePanel\Domain\File\Policies\FilePolicy;
use CorePanel\Domain\Form\Policies\FormPolicy;
use CorePanel\Domain\Permission\Policies\RolePolicy;
use CorePanel\Domain\User\Policies\UserPolicy;
use CorePanel\Domain\UserGroup\Policies\UserGroupPolicy;
use CorePanel\Http\Middleware\ApplyCorePanelRuntimeSettings;
use CorePanel\Http\Middleware\CheckPermission;
use CorePanel\Http\Middleware\EnsureApiDocsAccess;
use CorePanel\Http\Middleware\EnsureCorePanelEmailIsVerified;
use CorePanel\Http\Middleware\ResolveCorePanelLocale;
use CorePanel\Http\Middleware\SecurityHeaders;
use CorePanel\Http\Middleware\ShareLocaleDataWithInertia;
use CorePanel\Models\ApiToken;
use CorePanel\Models\Form;
use CorePanel\Models\ManagedFile;
use CorePanel\Models\Media;
use CorePanel\Models\OAuthClient;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupCloudBackupService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupEncryptor;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreStatus;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSettings;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSqlExportService;
use CorePanel\Support\Administration\DatabaseBackups\RunAutomaticDatabaseBackupAction;
use CorePanel\Support\Administration\SystemUpdates\RunAutomaticSystemUpdateAction;
use CorePanel\Support\Api\ApiResponseFactory;
use CorePanel\Support\Api\ApiTokenAbilityOptions;
use CorePanel\Support\Auth\AuthenticationLogRecorder;
use CorePanel\Support\Auth\ListBrowserSessions;
use CorePanel\Support\Auth\RevokeBrowserSession;
use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Database\TimestampTzConverter;
use CorePanel\Support\Files\FileModelManager;
use CorePanel\Support\FormBuilder\FormSubmissionValidator;
use CorePanel\Support\Forms\FormModelManager;
use CorePanel\Support\Generators\CorePanelGenerator;
use CorePanel\Support\Install\AppServiceProviderMerger;
use CorePanel\Support\Install\BackupManager;
use CorePanel\Support\Install\CorePanelInstaller;
use CorePanel\Support\LocaleResolver as RequestLocaleResolver;
use CorePanel\Support\Media\CorePanelMediaPathGenerator;
use CorePanel\Support\Media\MediaService;
use CorePanel\Support\Migrations\HostMigrationExecutor;
use CorePanel\Support\Octane\MediaStateResetter;
use CorePanel\Support\Octane\OctaneStateResetter;
use CorePanel\Support\Octane\PermissionCacheResetter;
use CorePanel\Support\Permissions\CorePanelAccess;
use CorePanel\Support\Permissions\CorePanelPermissions;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\Permissions\RoutePermissionResolver;
use CorePanel\Support\Publishing\CorePanelPublisher;
use CorePanel\Support\Publishing\PublishedAssetManifest;
use CorePanel\Support\Publishing\VendorFirstAssetMigrator;
use CorePanel\Support\PublishTag;
use CorePanel\Support\Query\QueryBuilderAdapter;
use CorePanel\Support\Security\SecurityHeaderConfig;
use CorePanel\Support\Settings\AssetSettingsLogoUrlGenerator;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Support\Socialite\OidcProvider;
use CorePanel\Support\Socialite\SocialAccountStore;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use CorePanel\Support\Socialite\SocialUserManager;
use CorePanel\Support\Translation\TranslationService;
use CorePanel\Support\UserGroups\UserGroupModelManager;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Passport\Passport;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

final class CorePanelServiceProvider extends PackageServiceProvider
{
    public function registeringPackage(): void
    {
        /** @var array<string, mixed> $corePanelConfig */
        $corePanelConfig = require __DIR__.'/../config/core-panel.php';
        $this->app['config']->set(
            'core-panel',
            array_replace_recursive($corePanelConfig, (array) $this->app['config']->get('core-panel', [])),
        );

        /** @var array<string, mixed> $accessConfig */
        $accessConfig = require __DIR__.'/../config/core-panel-access.php';
        $this->app['config']->set(
            'core-panel-access',
            array_replace_recursive($accessConfig, (array) $this->app['config']->get('core-panel-access', [])),
        );

        $this->app->bind(LocaleResolver::class, RequestLocaleResolver::class);
        $this->app->bind(SettingsLogoUrlGenerator::class, AssetSettingsLogoUrlGenerator::class);
        $this->app->scoped(CorePanelConfig::class, static fn ($app): CorePanelConfig => CorePanelConfig::fromRepository($app['config']));
        $this->app->scoped(ActivityLogService::class);
        $this->app->scoped(AuthenticationLogRecorder::class);
        $this->app->scoped(ApiResponseFactory::class);
        $this->app->scoped(AppServiceProviderMerger::class);
        $this->app->scoped(BackupManager::class);
        $this->app->scoped(DatabaseBackupCloudBackupService::class);
        $this->app->scoped(DatabaseBackupEncryptor::class);
        $this->app->scoped(DatabaseBackupRestoreService::class);
        $this->app->scoped(DatabaseBackupRestoreStatus::class);
        $this->app->scoped(DatabaseBackupSettings::class);
        $this->app->scoped(DatabaseBackupSqlExportService::class);
        $this->app->scoped(DatabaseBackupService::class);
        $this->app->scoped(RunAutomaticDatabaseBackupAction::class);
        $this->app->scoped(TimestampTzConverter::class);
        $this->app->scoped(CorePanelInstallerInterface::class, CorePanelInstaller::class);
        $this->app->scoped(FileModelManager::class);
        $this->app->scoped(FormModelManager::class);
        $this->app->scoped(FormSubmissionValidator::class);
        $this->app->scoped(CorePanelGenerator::class);
        $this->app->scoped(HostMigrationExecutor::class);
        $this->app->scoped(ListBrowserSessions::class);
        $this->app->scoped(MediaService::class);
        $this->app->scoped(MediaStateResetter::class);
        $this->app->scoped(OctaneStateResetter::class);
        $this->app->scoped(PermissionCacheResetter::class);
        $this->app->scoped(RunAutomaticSystemUpdateAction::class);
        $this->app->scoped(CorePanelAccess::class);
        $this->app->scoped(PermissionService::class);
        $this->app->scoped(RoutePermissionResolver::class);
        $this->app->scoped(PublishedAssetManifest::class);
        $this->app->scoped(CorePanelPublisher::class);
        $this->app->scoped(VendorFirstAssetMigrator::class);
        $this->app->scoped(QueryBuilderAdapter::class);
        $this->app->scoped(RevokeBrowserSession::class);
        $this->app->scoped(SecurityHeaderConfig::class);
        $this->app->scoped(SocialAccountStore::class);
        $this->app->scoped(SocialiteProviderRegistry::class);
        $this->app->scoped(SocialUserManager::class);
        $this->app->scoped(SettingsRepository::class);
        $this->app->scoped(TranslationService::class);
        $this->app->scoped(UserGroupModelManager::class);
        $this->app->scoped(UserModelManager::class);
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('core-panel')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews('core-panel')
            ->hasRoutes('api')
            ->hasCommands([
                AssignSuperAdminCommand::class,
                CleanActivityLogsCommand::class,
                ConvertTimestampsToTimestamptzCommand::class,
                ConvertMySqlDatetimesCommand::class,
                InstallCommand::class,
                MakeActionCommand::class,
                MakeCrudCommand::class,
                RunAutomaticDatabaseBackupCommand::class,
                MakeDomainCommand::class,
                MakeDtoCommand::class,
                MakeFormCommand::class,
                MakeTableCommand::class,
                PublishCommand::class,
                RunAutomaticSystemUpdateCommand::class,
                SyncEnvironmentCommand::class,
                SyncAccessCommand::class,
                UpdateCommand::class,
                VendorFirstCleanupCommand::class,
            ]);
    }

    public function bootingPackage(): void
    {
        $router = $this->app['router'];

        $this->loadTranslationsFrom(lang_path('vendor/core-panel'), null);
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', null);

        if ($this->shouldLoadFallbackWebRoutes()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        Gate::policy(ManagedFile::class, FilePolicy::class);
        Gate::policy(Form::class, FormPolicy::class);
        $this->configureMediaLibrary();
        $this->configureHorizon();
        $this->configurePassport();
        $this->configureAuthNotificationMail();
        $this->configureSocialiteProviders();
        $roleModel = config('permission.models.role');
        $userGroupModel = config('core-panel.user_group_model');
        $userModel = config('core-panel.user_model');

        if (is_string($roleModel) && class_exists($roleModel)) {
            Gate::policy($roleModel, RolePolicy::class);
        }

        if (is_string($userModel) && class_exists($userModel)) {
            Gate::policy($userModel, UserPolicy::class);
        }

        if (is_string($userGroupModel) && class_exists($userGroupModel)) {
            Gate::policy($userGroupModel, UserGroupPolicy::class);
        }

        $router->aliasMiddleware('core-panel.runtime-settings', ApplyCorePanelRuntimeSettings::class);
        $router->aliasMiddleware('core-panel.api-docs', EnsureApiDocsAccess::class);
        $router->aliasMiddleware('check.permission', CheckPermission::class);
        $router->aliasMiddleware('core-panel.verified', EnsureCorePanelEmailIsVerified::class);
        $router->aliasMiddleware('core-panel.resolve-locale', ResolveCorePanelLocale::class);
        $router->aliasMiddleware('core-panel.security-headers', SecurityHeaders::class);
        $router->aliasMiddleware('core-panel.share-locale', ShareLocaleDataWithInertia::class);

        Event::listen(Login::class, function (Login $event): void {
            app(AuthenticationLogRecorder::class)->recordSuccessfulLogin($event);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            app(AuthenticationLogRecorder::class)->recordFailedLogin($event);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            app(AuthenticationLogRecorder::class)->recordLogout($event);
        });

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/core-panel'),
        ], PublishTag::Lang->value);

        $this->publishes([
            __DIR__.'/../config/core-panel.php' => config_path('core-panel.php'),
            __DIR__.'/../config/core-panel-access.php' => config_path('core-panel-access.php'),
        ], PublishTag::Config->value);

        $this->publishes([
            __DIR__.'/../resources/js/assets' => resource_path('js/assets'),
            __DIR__.'/../resources/js/components' => resource_path('js/components'),
            __DIR__.'/../resources/js/composables' => resource_path('js/composables'),
            __DIR__.'/../resources/js/layouts' => resource_path('js/layouts'),
            __DIR__.'/../resources/js/plugins' => resource_path('js/plugins'),
            __DIR__.'/../resources/js/support' => resource_path('js/support'),
            __DIR__.'/../resources/js/types' => resource_path('js/types'),
        ], PublishTag::Components->value);

        $this->publishes([
            __DIR__.'/../resources/js/theme/core-panel' => resource_path('js/theme/core-panel'),
        ], PublishTag::Theme->value);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/core-panel'),
        ], PublishTag::Views->value);
    }

    private function configureMediaLibrary(): void
    {
        if (! class_exists(Media::class)) {
            return;
        }

        config()->set('media-library.disk_name', (string) config('core-panel.files.disk', 'public'));
        config()->set('media-library.media_model', Media::class);
        config()->set('media-library.path_generator', CorePanelMediaPathGenerator::class);

        if (class_exists(PathGeneratorFactory::class)) {
            PathGeneratorFactory::setCustomPathGenerators(ManagedFile::class, CorePanelMediaPathGenerator::class);
        }
    }

    private function shouldLoadFallbackWebRoutes(): bool
    {
        return ! file_exists(base_path('routes/central.php'));
    }

    private function configureHorizon(): void
    {
        if (! (bool) config('core-panel.horizon.enabled', true) || ! class_exists(Horizon::class)) {
            return;
        }

        $gate = Gate::getFacadeRoot();

        if (method_exists($gate, 'has') && ! $gate->has('viewHorizon')) {
            Gate::define('viewHorizon', static function ($user): bool {
                if (! is_object($user)) {
                    return false;
                }

                if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                    return true;
                }

                if (! method_exists($user, 'can')) {
                    return false;
                }

                return $user->can('horizon.view') || $user->can('core-panel.view-horizon');
            });
        }
    }

    private function configurePassport(): void
    {
        if (! class_exists(Passport::class)) {
            return;
        }

        config()->set('auth.guards.api', [
            'driver' => 'passport',
            'provider' => 'users',
        ]);

        Passport::useClientModel(OAuthClient::class);
        Passport::useTokenModel(ApiToken::class);
        Passport::tokensCan($this->passportScopes());
        Passport::tokensExpireIn(now()->addMinutes((int) config('core-panel.auth.passport.token_ttl_minutes', 15)));
        Passport::refreshTokensExpireIn(now()->addDays((int) config('core-panel.auth.passport.refresh_token_ttl_days', 30)));
        Passport::personalAccessTokensExpireIn(now()->addDays((int) config('core-panel.auth.passport.personal_access_token_ttl_days', 180)));
    }

    private function configureAuthNotificationMail(): void
    {
        ResetPassword::toMailUsing(function (CanResetPassword $notifiable, string $token): MailMessage {
            $callback = ResetPassword::$createUrlCallback;

            $url = $callback !== null
                ? $callback($notifiable, $token)
                : url(route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false));

            return $this->buildAuthNotificationMailMessage(
                subject: __('account-mail.reset_password.subject'),
                actionText: __('account-mail.reset_password.action'),
                actionUrl: $url,
                introLines: [
                    __('account-mail.reset_password.intro'),
                ],
                outroLines: [
                    __('account-mail.reset_password.expiry', [
                        'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                    ]),
                    __('account-mail.reset_password.outro'),
                ],
            );
        });

        VerifyEmail::toMailUsing(function (MustVerifyEmail $notifiable, string $url): MailMessage {
            return $this->buildAuthNotificationMailMessage(
                subject: __('account-mail.verify_email.subject'),
                actionText: __('account-mail.verify_email.action'),
                actionUrl: $url,
                introLines: [
                    __('account-mail.verify_email.intro'),
                ],
                outroLines: [
                    __('account-mail.verify_email.outro'),
                ],
            );
        });
    }

    /**
     * @param  list<string>  $introLines
     * @param  list<string>  $outroLines
     */
    private function buildAuthNotificationMailMessage(
        string $subject,
        string $actionText,
        string $actionUrl,
        array $introLines,
        array $outroLines,
    ): MailMessage {
        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('account-mail.greeting'))
            ->lines($introLines)
            ->action($actionText, $actionUrl)
            ->lines($outroLines)
            ->salutation(__('account-mail.salutation'))
            ->view('core-panel::emails.notifications.default-html', [
                'appName' => (string) config('app.name'),
                'footer' => __('account-mail.footer'),
                'subcopy' => __('account-mail.subcopy', ['actionText' => $actionText]),
            ])
            ->text('core-panel::emails.notifications.default-text', [
                'appName' => (string) config('app.name'),
                'footer' => __('account-mail.footer'),
                'subcopy' => __('account-mail.subcopy', ['actionText' => $actionText]),
            ]);
    }

    private function configureSocialiteProviders(): void
    {
        $this->app->afterResolving(SocialiteFactory::class, function (mixed $socialite): void {
            $socialite->extend('oidc', function ($app) use ($socialite): OidcProvider {
                /** @var array<string, mixed> $config */
                $config = (array) $app['config']->get('services.oidc', []);

                return $socialite->buildProvider(OidcProvider::class, $config)
                    ->configure(
                        (string) ($config['issuer'] ?? ''),
                        (array) ($config['claims'] ?? []),
                    );
            });
        });

        if (! class_exists(SocialiteWasCalled::class) || ! class_exists(MicrosoftProvider::class)) {
            return;
        }

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });
    }

    /**
     * @return array<string, string>
     */
    private function passportScopes(): array
    {
        return collect(ApiTokenAbilityOptions::options())
            ->mapWithKeys(static fn (array $ability): array => [
                (string) $ability['value'] => (string) $ability['label'],
            ])
            ->merge(
                collect(CorePanelPermissions::defaults())
                    ->mapWithKeys(static fn (string $scope): array => [
                        $scope => __('page-roles.permissions.'.$scope),
                    ]),
            )
            ->all();
    }
}
