import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import type { FormDataConvertible } from '@inertiajs/core'

import type {
    DataTableAction,
    DataTableActionTarget,
    DataTableColumn,
    DataTableRow,
    DataTableSchema,
    DataTableState,
} from './types'

type DataTableReloadOptions = {
    only?: string[]
}

type PageLike = {
    url: string
}

function normalizeSort(field: string, order: 1 | -1 | undefined): string {
    if (field === '') {
        return ''
    }

    return order === -1 ? `-${field}` : field
}

function normalizeUrl(url: string): string {
    if (url === '') {
        return window.location.pathname
    }

    return url.startsWith('/') ? url : `/${url}`
}

function currentTab(url: string): string | undefined {
    const [, query = ''] = url.split('?')
    const tab = new URLSearchParams(query).get('tab')

    return tab ?? undefined
}

export function useDataTable(
    schema: Readonly<DataTableSchema>,
    options: DataTableReloadOptions = {},
) {
    const page = usePage<PageLike>()
    const selectedRows = ref<DataTableRow[]>([])
    const search = ref(schema.state.search ?? '')
    const filters = ref<Record<string, unknown>>({
        ...(schema.state.filters ?? {}),
    })
    const visibleColumns = ref<string[]>(
        schema.state.visibleColumns.length > 0
            ? [...schema.state.visibleColumns]
            : schema.columns
                  .filter((column) => column.visible)
                  .map((column) => column.key),
    )
    const sort = ref(schema.state.sort ?? '')
    const isLoading = ref(false)

    let searchDebounce: ReturnType<typeof setTimeout> | null = null

    const columnMap = computed<Record<string, DataTableColumn>>(() =>
        Object.fromEntries(
            schema.columns.map((column) => [column.key, column]),
        ),
    )

    const activeColumns = computed(() =>
        schema.columns.filter(
            (column) =>
                visibleColumns.value.length === 0 ||
                visibleColumns.value.includes(column.key),
        ),
    )

    const rowActions = computed(() =>
        schema.actions.filter((action) => !action.bulk),
    )
    const bulkActions = computed(() => schema.bulkActions)

    function syncQuery(overrides: Partial<DataTableState> = {}): void {
        const nextState: DataTableState = {
            filters: { ...filters.value, ...(overrides.filters ?? {}) },
            search: overrides.search ?? search.value,
            sort: overrides.sort ?? sort.value,
            visibleColumns: overrides.visibleColumns ?? visibleColumns.value,
        }

        isLoading.value = true

        router.get(
            normalizeUrl(page.url),
            {
                columns: nextState.visibleColumns.join(','),
                tab: currentTab(page.url),
                filter: nextState.filters as Record<
                    string,
                    FormDataConvertible
                >,
                page: 1,
                search: nextState.search || undefined,
                sort: nextState.sort || undefined,
            },
            {
                only: options.only,
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onFinish: () => {
                    isLoading.value = false
                },
            },
        )
    }

    function setFilter(key: string, value: unknown): void {
        filters.value = {
            ...filters.value,
            [key]: value,
        }

        syncQuery({
            filters: filters.value,
        })
    }

    function setPage(event: { page: number; rows: number }): void {
        isLoading.value = true

        router.get(
            normalizeUrl(page.url),
            {
                columns: visibleColumns.value.join(','),
                tab: currentTab(page.url),
                filter: filters.value as Record<string, FormDataConvertible>,
                page: event.page + 1,
                per_page: event.rows,
                search: search.value || undefined,
                sort: sort.value || undefined,
            },
            {
                only: options.only,
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onFinish: () => {
                    isLoading.value = false
                },
            },
        )
    }

    function setSearch(value: string): void {
        search.value = value

        if (searchDebounce !== null) {
            clearTimeout(searchDebounce)
        }

        searchDebounce = setTimeout(() => {
            syncQuery({
                search: value,
            })
        }, 250)
    }

    function setSort(field: string, order: 1 | -1 | undefined): void {
        sort.value = normalizeSort(field, order)

        syncQuery({
            sort: sort.value,
        })
    }

    function setVisibleColumns(columns: string[]): void {
        visibleColumns.value = columns

        syncQuery({
            visibleColumns: columns,
        })
    }

    function runAction(action: DataTableAction, row?: DataTableRow): void {
        const target = resolveActionTarget(action, row)

        if (target === null) {
            return
        }

        isLoading.value = true

        router.visit(target.url, {
            method: target.method ?? 'get',
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isLoading.value = false
            },
        })
    }

    function runBulkAction(action: DataTableAction): void {
        const target = action.target ?? null

        if (target === null) {
            return
        }

        isLoading.value = true

        router.visit(target.url, {
            data: {
                ids: selectedRows.value.map((row) => row.id).filter(Boolean),
            },
            method: target.method ?? 'post',
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isLoading.value = false
            },
        })
    }

    function resolveActionTarget(
        action: DataTableAction,
        row?: DataTableRow,
    ): DataTableActionTarget | null {
        const explicitTarget = action.target ?? null

        if (explicitTarget !== null) {
            return explicitTarget
        }

        const metaTarget = action.meta?.target

        if (
            metaTarget !== null &&
            typeof metaTarget === 'object' &&
            'url' in metaTarget
        ) {
            return metaTarget as DataTableActionTarget
        }

        if (row?.id === undefined || row.id === null) {
            return null
        }

        const template =
            typeof action.meta?.urlTemplate === 'string'
                ? action.meta.urlTemplate
                : null

        if (template === null) {
            return null
        }

        return {
            method:
                (action.meta?.method as
                    | DataTableActionTarget['method']
                    | undefined) ?? 'get',
            url: template.replace('{id}', String(row.id)),
        }
    }

    watch(
        () => schema.state,
        (nextState) => {
            filters.value = { ...(nextState.filters ?? {}) }
            search.value = nextState.search ?? ''
            sort.value = nextState.sort ?? ''
            visibleColumns.value =
                nextState.visibleColumns.length > 0
                    ? [...nextState.visibleColumns]
                    : schema.columns
                          .filter((column) => column.visible)
                          .map((column) => column.key)
        },
    )

    return {
        activeColumns,
        bulkActions,
        columnMap,
        filters,
        isLoading,
        rowActions,
        search,
        selectedRows,
        setFilter,
        setPage,
        setSearch,
        setSort,
        setVisibleColumns,
        sort,
        visibleColumns,
        runAction,
        runBulkAction,
    }
}

export type UseDataTableReturn = ReturnType<typeof useDataTable>
