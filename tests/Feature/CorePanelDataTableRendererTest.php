<?php

declare(strict_types=1);

it('ships a datatable renderer that binds schema rows into primevue datatable columns', function (): void {
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/DataTable.vue');

    expect($renderer)->toContain('<PrimeDataTable')
        ->and($renderer)->toContain(':value="table.rows.value"')
        ->and($renderer)->toContain('<PrimeColumn')
        ->and($renderer)->toContain('v-for="column in displayColumns"');
});

it('ships a datatable composable that triggers inertia requests for sorting', function (): void {
    $composable = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/useDataTable.ts');
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/DataTable.vue');

    expect($composable)->toContain('router.get(')
        ->and($composable)->toContain('function setSort')
        ->and($composable)->toContain('sort: nextState.sort || undefined')
        ->and($composable)->toContain('tab: currentTab(page.url)')
        ->and($renderer)->toContain('@sort="handleSort"');
});

it('ships filter components that sync filter values into query state', function (): void {
    $filters = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/TableFilters.vue');
    $composable = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/useDataTable.ts');

    expect($filters)->toContain('<InputText')
        ->and($filters)->toContain('<Select')
        ->and($filters)->toContain('<DatePicker')
        ->and($composable)->toContain('function setFilter')
        ->and($composable)->toContain('filter: nextState.filters');
});

it('ships bulk selection controls and bulk action handling', function (): void {
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/DataTable.vue');
    $bulkActionBar = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/BulkActionBar.vue');
    $composable = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/useDataTable.ts');

    expect($renderer)->toContain('v-model:selection="table.selectedRows.value"')
        ->and($renderer)->toContain('<BulkActionBar')
        ->and($bulkActionBar)->toContain('selectedCount > 0')
        ->and($composable)->toContain('function runBulkAction')
        ->and($composable)->toContain('ids: selectedRows.value.map((row) => row.id).filter(Boolean)');
});

it('registers the primevue components required by the datatable renderer', function (): void {
    $entry = file_get_contents(__DIR__.'/../../stubs/resources/js/plugins/core-panel.ts');
    $renderer = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/DataTable.vue');
    $pagination = file_get_contents(__DIR__.'/../../resources/js/components/TableBuilder/TablePagination.vue');
    $tabsRenderer = file_get_contents(__DIR__.'/../../resources/js/components/TabBuilder/TabsRenderer.vue');

    expect($entry)->toContain("import Paginator from 'primevue/paginator'")
        ->and($entry)->toContain("app.component('Paginator', Paginator)")
        ->and($renderer)->toContain('<Tag')
        ->and($tabsRenderer)->toContain('<Skeleton')
        ->and($pagination)->toContain('<Paginator');
});
