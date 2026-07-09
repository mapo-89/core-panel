<?php

declare(strict_types=1);

it('ships social login buttons in the login page and linked account actions in security settings', function (): void {
    $login = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/Login.vue');
    $authConflictPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Auth/SocialAccountConflict.vue');
    $avatarSyncDialog = file_get_contents(__DIR__.'/../../stubs/resources/js/components/Auth/SocialAvatarSyncDialog.vue');
    $conflictDialog = file_get_contents(__DIR__.'/../../stubs/resources/js/components/Auth/SocialAccountConflictDialog.vue');
    $appLayout = file_get_contents(__DIR__.'/../../stubs/resources/js/layouts/AppLayout.vue');
    $connections = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/ProfileConnectionsTab.vue');
    $conflictPage = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/SocialAccountConflict.vue');
    $providerConnectionCard = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/SocialProviderConnectionCard.vue');
    $authSettingsTab = file_get_contents(__DIR__.'/../../stubs/resources/js/pages/Admin/Settings/components/AuthSettingsTab.vue');
    $fortifyProvider = file_get_contents(__DIR__.'/../../stubs/app/Providers/FortifyServiceProvider.php');
    $callbackController = file_get_contents(__DIR__.'/../../src/Http/Controllers/Auth/SocialiteCallbackController.php');

    expect($login)->toContain('socialite.redirect.url(provider.provider)')
        ->and($login)->toContain('socialProviders')
        ->and($login)->toContain("import githubIcon from '@/assets/icons/github.svg'")
        ->and($login)->toContain("import githubWhiteIcon from '@/assets/icons/github-white.svg'")
        ->and($login)->toContain("import googleIcon from '@/assets/icons/google.png'")
        ->and($login)->toContain('function providerIcon(provider: string): string | null')
        ->and($login)->toContain('google: googleIcon,')
        ->and($login)->toContain('microsoft: microsoftIcon,')
        ->and($login)->toContain("provider.provider === 'github'")
        ->and($login)->toContain('auth-social__button-lockup--github')
        ->and($login)->toContain('auth-social__button-lockup--light')
        ->and($login)->toContain('auth-social__button-lockup--dark')
        ->and($login)->toContain("provider.provider === 'microsoft'")
        ->and($login)->toContain("? 'Microsoft'")
        ->and($fortifyProvider)->toContain("'socialProviders' => \$socialite->enabledProviders()")
        ->and($callbackController)->toContain('linkMasterProviderAccount')
        ->and($callbackController)->toContain('resolveAvatarSync')
        ->and($callbackController)->toContain('syncMasterProviderAvatar')
        ->and($callbackController)->toContain("return \$provider === 'microsoft' || \$this->providers->isMasterProvider(\$provider, true);")
        ->and($callbackController)->toContain("route(\$this->tenantAwareRouteName('socialite.conflict')")
        ->and($callbackController)->toContain("redirect()->to(\$destination['destination'])")
        ->and($authSettingsTab)->toContain('social_master_provider')
        ->and($authSettingsTab)->toContain('provider.isMaster')
        ->and($connections)->toContain("['microsoft', 'github', 'google']")
        ->and($conflictPage)->toContain('<SocialAccountConflictDialog')
        ->and($authConflictPage)->toContain('<SocialAccountConflictDialog')
        ->and($conflictDialog)->toContain('<Dialog')
        ->and($conflictDialog)->toContain('avatar_decision')
        ->and($conflictDialog)->toContain('social_master_conflict_avatar_only_title')
        ->and($conflictDialog)->toContain('resolveConflict.url(props.provider)')
        ->and($avatarSyncDialog)->toContain('resolveAvatarSync.url(props.provider)')
        ->and($avatarSyncDialog)->toContain('social_avatar_sync_title')
        ->and($appLayout)->toContain("import SocialAvatarSyncDialog from '@/components/Auth/SocialAvatarSyncDialog.vue'")
        ->and($appLayout)->toContain('<SocialAvatarSyncDialog')
        ->and($conflictDialog)->toContain('social_master_conflict_title')
        ->and($conflictDialog)->toContain('social_master_conflict_confirm_link_action')
        ->and($connections)->toContain('<SocialProviderConnectionCard')
        ->and($connections)->not->toContain('connected_accounts')
        ->and($providerConnectionCard)->toContain('provider: string')
        ->and($providerConnectionCard)->toContain('submitLinkAction(socialite.link.url(props.provider))')
        ->and($providerConnectionCard)->toContain('socialite.testMail.url(props.provider)')
        ->and($providerConnectionCard)->toContain('class="cp-card grid gap-5 p-6"')
        ->and($providerConnectionCard)->toContain('space-y-4 rounded-2xl border border-surface-200/80 bg-surface-50/70 p-4 dark:border-surface-800 dark:bg-surface-950/80')
        ->and($providerConnectionCard)->toContain('dark:border dark:border-surface-800 dark:bg-surface-900/90')
        ->and($providerConnectionCard)->toContain('dark:border-surface-800 dark:bg-surface-950/95')
        ->and($providerConnectionCard)->toContain('page-settings.microsoft_send_test_mail')
        ->and($providerConnectionCard)->toContain('page-settings.social_provider_connection_status_label')
        ->and($providerConnectionCard)->toContain('page-settings.social_provider_title')
        ->and($providerConnectionCard)->toContain("import githubIcon from '@/assets/icons/github.svg'")
        ->and($providerConnectionCard)->toContain("import githubWhiteIcon from '@/assets/icons/github-white.svg'")
        ->and($providerConnectionCard)->toContain("import googleIcon from '@/assets/icons/google.png'")
        ->and($providerConnectionCard)->toContain('const providerLogo = computed(() => {')
        ->and($providerConnectionCard)->toContain("const isGithubProvider = computed(() => props.provider === 'github')")
        ->and($providerConnectionCard)->toContain('const showProviderLabel = computed(')
        ->and($providerConnectionCard)->toContain("props.provider === 'microsoft'")
        ->and($providerConnectionCard)->toContain('github: githubIcon,')
        ->and($providerConnectionCard)->toContain('google: googleIcon,')
        ->and($providerConnectionCard)->toContain('githubWhiteIcon')
        ->and($providerConnectionCard)->toContain('dark:text-surface-200')
        ->and($providerConnectionCard)->toContain('dark:bg-surface-800/70 dark:text-surface-200 dark:ring-surface-700')
        ->and($providerConnectionCard)->toContain("import AppIcon from '@/components/AppIcon.vue'")
        ->and($providerConnectionCard)->toContain('<AppIcon')
        ->and($providerConnectionCard)->toContain(":name=\"isConnected ? 'check' : 'ban'\"")
        ->and($providerConnectionCard)->toContain('providerHealth.available')
        ->and($providerConnectionCard)->toContain('providerRecord?.isMaster')
        ->and($providerConnectionCard)->toContain('providerIconName')
        ->and($providerConnectionCard)->toContain('socialite.unlink.url(props.provider)');
});

it('ships socialite scaffolding for services configuration and environment variables', function (): void {
    $services = file_get_contents(__DIR__.'/../../stubs/config/services.php');
    $environment = file_get_contents(__DIR__.'/../../stubs/.env.example');
    $platformRoutes = file_get_contents(__DIR__.'/../../routes/web/platform.php');
    $profileRoutes = file_get_contents(__DIR__.'/../../routes/web/profile.php');
    $addonUniversalRoutes = file_get_contents(__DIR__.'/../../../core-panel-tenancy/stubs/routes/universal.php');

    expect($services)->toContain("'github' => [")
        ->and($services)->toContain("'google' => [")
        ->and($services)->toContain("'microsoft' => [")
        ->and($environment)->toContain('SOCIAL_GITHUB_ENABLED=')
        ->and($environment)->toContain('GOOGLE_CLIENT_ID=')
        ->and($platformRoutes)->toContain("Route::get('/auth/{provider}/redirect'")
        ->and($platformRoutes)->toContain("Route::get('/auth/{provider}/callback'")
        ->and($addonUniversalRoutes)->toContain('InitializeTenancyByDomain::class')
        ->and($addonUniversalRoutes)->toContain("\$loadUniversalWebRouteFile('platform.php');")
        ->and($profileRoutes)->toContain("Route::post('/profile/security/social/{provider}/link'")
        ->and($profileRoutes)->toContain("Route::post('/profile/security/social/{provider}/avatar-sync'")
        ->and($profileRoutes)->toContain("Route::post('/profile/security/social/{provider}/test-mail'")
        ->and($profileRoutes)->toContain("Route::delete('/profile/security/social/{provider}'")
        ->and($platformRoutes)->toContain("Route::get('/auth/{provider}/conflict'")
        ->and($platformRoutes)->toContain("Route::post('/auth/{provider}/resolve-conflict'");
});
