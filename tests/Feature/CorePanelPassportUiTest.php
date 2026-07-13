<?php

declare(strict_types=1);

it('ships an oauth clients page that uses wayfinder and the shared form renderer', function (): void {
    $page = file_get_contents(__DIR__.'/../../resources/js/pages/Admin/OAuthClients/Index.vue');

    expect($page)->toContain("import oauthClients from '@/routes/core-panel/oauth-clients'")
        ->and($page)->toContain('<DataTable')
        ->and($page)->toContain("import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'")
        ->and($page)->toContain('<FormRenderer')
        ->and($page)->toContain('oauthClients.store.url()')
        ->and($page)->toContain('oauthClients.update.url(editingClient.value.id)')
        ->and($page)->toContain('oauthClients.destroy.url(pendingDeleteClient.value.id)');
});

it('ships oauth scaffolding that prepares passport environment and runtime hooks', function (): void {
    $environment = file_get_contents(__DIR__.'/../../stubs/.env.example');
    $installer = file_get_contents(__DIR__.'/../../src/Support/Install/CorePanelInstaller.php');
    $routes = file_get_contents(__DIR__.'/../../routes/web/admin/oauth-clients.php');

    expect($environment)->toContain('PASSPORT_TOKEN_TTL_MINUTES=15')
        ->and($environment)->toContain('PASSPORT_REFRESH_TOKEN_TTL_DAYS=30')
        ->and($installer)->not->toContain("'passport-config'")
        ->and($installer)->not->toContain("'passport-migrations'")
        ->and($installer)->toContain("'passport:keys'")
        ->and($routes)->toContain("Route::get('/oauth-clients'")
        ->and($routes)->toContain("Route::post('/oauth-clients'")
        ->and($routes)->toContain("Route::put('/oauth-clients/{client}'")
        ->and($routes)->toContain("Route::delete('/oauth-clients/{client}'");
});
