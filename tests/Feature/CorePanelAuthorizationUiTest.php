<?php

declare(strict_types=1);

it('ships the permission middleware, menu filtering, and frontend authorization helpers', function (): void {
    $provider = file_get_contents(__DIR__.'/../../src/CorePanelServiceProvider.php');
    $bootstrap = file_get_contents(__DIR__.'/../../stubs/bootstrap/app.php');
    $appSidebar = file_get_contents(__DIR__.'/../../resources/js/layouts/components/AppSidebar.vue');
    $developerPage = file_get_contents(__DIR__.'/../../resources/js/pages/Admin/Logs/Index.vue');
    $header = file_get_contents(__DIR__.'/../../resources/js/layouts/components/AppHeader.vue');
    $adminMenu = file_get_contents(__DIR__.'/../../resources/js/composables/useAdminMenu.ts');
    $menuBuilder = file_get_contents(__DIR__.'/../../resources/js/composables/useMenuBuilder.ts');
    $fortifyProvider = file_get_contents(__DIR__.'/../../stubs/app/Providers/FortifyServiceProvider.php');

    expect($provider)->toContain("aliasMiddleware('check.permission', CheckPermission::class)")
        ->and($bootstrap)->toContain("'check.permission' => CheckPermission::class")
        ->and($appSidebar)->toContain("import { useAdminMenu } from '@core-panel/composables/useAdminMenu'")
        ->and($appSidebar)->toContain('const { isGroupOpen, isItemActive, items: menuItems } = useAdminMenu()')
        ->and($appSidebar)->toContain('class="app-sidebar__version-badge"')
        ->and($appSidebar)->toContain('app-sidebar__footer-badge app-sidebar__footer-badge--info')
        ->and($appSidebar)->toContain('app-sidebar__footer-badge app-sidebar__footer-badge--danger')
        ->and($appSidebar)->toContain("{{ \$t('common.ui.dev_mode') }}")
        ->and($appSidebar)->toContain("{{ \$t('common.ui.debug_mode') }}")
        ->and($developerPage)->toContain("permission: 'activity-logs.view'")
        ->and($developerPage)->toContain("permission: 'authentication-logs.view'")
        ->and($developerPage)->toContain("if (props.logsTab && hasRole('super-admin'))")
        ->and($header)->toContain("import { useCan } from '@core-panel/composables/useCan'")
        ->and($header)->toContain("canAny(['roles.view', 'user-groups.view', 'users.view'])")
        ->and($adminMenu)->toContain("permission: 'files.view'")
        ->and($adminMenu)->not->toContain("permission: 'settings.view'")
        ->and($adminMenu)->toContain("href: '/dashboard'")
        ->and($adminMenu)->toContain("label: 'navigation.logs'")
        ->and($adminMenu)->toContain('match: [logs.index.url()]')
        ->and($menuBuilder)->toContain('query?: Record<string, string>')
        ->and($menuBuilder)->toContain('currentUrl.searchParams.get(key) === value')
        ->and($fortifyProvider)->toContain('Fortify::authenticateUsing(function (Request $request): ?Authenticatable {')
        ->and($fortifyProvider)->toContain("corePanelUserStatus() !== 'active'")
        ->and($fortifyProvider)->toContain("with('microsoftAccount')")
        ->and($fortifyProvider)->toContain("__('page-auth.socialite.microsoft_password_required')")
        ->and($fortifyProvider)->toContain('requiresPasswordSetup()');
});

it('supports form-level permission gating in the shared form renderer', function (): void {
    $formClass = file_get_contents(__DIR__.'/../../src/Support/FormBuilder/Form.php');
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/FormRenderer.vue');
    $types = file_get_contents(__DIR__.'/../../resources/js/components/FormBuilder/types.ts');

    expect($formClass)->toContain('private ?string $permission = null')
        ->and($formClass)->toContain('public function permission(?string $permission): self')
        ->and($formClass)->toContain("'permission' => \$this->permission")
        ->and($renderer)->toContain('permission?: string | null')
        ->and($renderer)->toContain('const isReadOnly = computed(() =>')
        ->and($renderer)->toContain('v-if="(action || submitLabel) && !isReadOnly"')
        ->and($types)->toContain('export type SerializedForm = {');
});
