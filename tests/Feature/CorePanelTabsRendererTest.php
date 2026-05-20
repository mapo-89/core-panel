<?php

declare(strict_types=1);

it('ships a tabs renderer that integrates primevue tabs and panels', function (): void {
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/TabBuilder/TabsRenderer.vue');

    expect($renderer)->toContain('<Tabs')
        ->and($renderer)->toContain('<TabList')
        ->and($renderer)->toContain('<TabPanel')
        ->and($renderer)->toContain('<Badge');
});

it('ships lazy tab loading with inertia partial reload hooks', function (): void {
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/TabBuilder/TabsRenderer.vue');

    expect($renderer)->toContain('router.reload({')
        ->and($renderer)->toContain('reloadOnly')
        ->and($renderer)->toContain('loadedTabs')
        ->and($renderer)->toContain('tab.lazy');
});

it('ships permission-aware visibility and url sync support', function (): void {
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/TabBuilder/TabsRenderer.vue');
    $types = file_get_contents(__DIR__.'/../../resources/js/components/TabBuilder/types.ts');

    expect($renderer)->toContain('availablePermissions')
        ->and($renderer)->toContain('syncWithUrl')
        ->and($renderer)->toContain('url.searchParams.set')
        ->and($types)->toContain('permission?: string');
});

it('ships locale-aware label fallback and first-load partial reload guards', function (): void {
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/TabBuilder/TabsRenderer.vue');

    expect($renderer)->toContain('tab.labelTranslations?.[locale]')
        ->and($renderer)->toContain('?? tab.label')
        ->and($renderer)->toContain('const wasLoaded = loadedTabs.value.includes(key)')
        ->and($renderer)->toContain('if (!wasLoaded && partials !== undefined && partials.length > 0)')
        ->and($renderer)->toContain('router.reload({');
});
