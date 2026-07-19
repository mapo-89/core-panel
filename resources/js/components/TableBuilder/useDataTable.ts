import { computed, ref, toValue, watch } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
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
    mode?: MaybeRefOrGetter<'local' | 'remote' | undefined>
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

    const [path = ''] = url.split('?')

    return path.startsWith('/') ? path : `/${path}`
}

function currentTab(url: string): string | undefined {
    const [, query = ''] = url.split('?')
    const tab = new URLSearchParams(query).get('tab')

    return tab ?? undefined
}

export function useDataTable(
    schema: MaybeRefOrGetter<Readonly<DataTableSchema>>,
    options: DataTableReloadOptions = {},
) {
    const page = usePage<PageLike>()
    const currentSchema = computed(() => toValue(schema))
    const resolvedMode = computed(
        () => toValue(options.mode) ?? currentSchema.value.mode,
    )
    const isLocal = computed(() => resolvedMode.value === 'local')
    const selectedRows = ref<DataTableRow[]>([])
    const search = ref(currentSchema.value.state.search ?? '')
    const filters = ref<Record<string, unknown>>({
        ...(currentSchema.value.state.filters ?? {}),
    })
    const visibleColumns = ref<string[]>(
        currentSchema.value.state.visibleColumns.length > 0
            ? [...currentSchema.value.state.visibleColumns]
            : currentSchema.value.columns
                  .filter((column) => column.visible)
                  .map((column) => column.key),
    )
    const sort = ref(currentSchema.value.state.sort ?? '')
    const isLoading = ref(false)
    const currentPage = ref(currentSchema.value.pagination.page ?? 1)
    const perPage = ref(currentSchema.value.pagination.perPage ?? 10)

    let searchDebounce: ReturnType<typeof setTimeout> | null = null

    const columnMap = computed<Record<string, DataTableColumn>>(() =>
        Object.fromEntries(
            currentSchema.value.columns.map((column) => [column.key, column]),
        ),
    )

    const activeColumns = computed(() =>
        currentSchema.value.columns.filter(
            (column) =>
                visibleColumns.value.length === 0 ||
                visibleColumns.value.includes(column.key),
        ),
    )

    const rowActions = computed(() =>
        currentSchema.value.actions.filter((action) => !action.bulk),
    )
    const bulkActions = computed(() => currentSchema.value.bulkActions)
    const sortField = computed(() => sort.value.replace(/^-/, ''))
    const sortOrder = computed(() => (sort.value.startsWith('-') ? -1 : 1))

    function isEmptyFilterValue(value: unknown): boolean {
        if (value === null || value === undefined || value === '') {
            return true
        }

        if (Array.isArray(value)) {
            return (
                value.length === 0 ||
                value.every((entry) => isEmptyFilterValue(entry))
            )
        }

        if (typeof value === 'object') {
            const entries = Object.values(value as Record<string, unknown>)

            return (
                entries.length === 0 ||
                entries.every((entry) => isEmptyFilterValue(entry))
            )
        }

        return false
    }

    function normalizeFiltersRecord(
        nextFilters: Record<string, unknown>,
    ): Record<string, unknown> {
        return Object.fromEntries(
            Object.entries(nextFilters).filter(
                ([, value]) => !isEmptyFilterValue(value),
            ),
        )
    }

    function resolveSearchableValues(
        row: DataTableRow,
        column: DataTableColumn,
    ): string[] {
        const metaSearchKeys = Array.isArray(column.meta?.searchKeys)
            ? (column.meta?.searchKeys as unknown[])
            : []

        const keys = [
            column.key,
            ...metaSearchKeys.filter(
                (value): value is string =>
                    typeof value === 'string' && value !== '',
            ),
        ]

        return keys
            .map((key) => row[key])
            .filter((value) => value !== null && value !== undefined)
            .flatMap((value) =>
                Array.isArray(value)
                    ? value.map((entry) => String(entry ?? ''))
                    : [String(value)],
            )
    }

    function compareValues(
        leftValue: unknown,
        rightValue: unknown,
        column?: DataTableColumn,
    ): number {
        const sortType = column?.meta?.localSortType

        if (sortType === 'date') {
            const leftDate = leftValue
                ? new Date(String(leftValue)).getTime()
                : 0
            const rightDate = rightValue
                ? new Date(String(rightValue)).getTime()
                : 0

            return leftDate - rightDate
        }

        if (sortType === 'number') {
            return Number(leftValue ?? 0) - Number(rightValue ?? 0)
        }

        if (typeof leftValue === 'number' && typeof rightValue === 'number') {
            return leftValue - rightValue
        }

        return String(leftValue ?? '').localeCompare(String(rightValue ?? ''))
    }

    const localRows = computed(() => {
        if (!isLocal.value) {
            return currentSchema.value.rows
        }

        const normalizedSearch = search.value.trim().toLowerCase()

        const searchedRows =
            normalizedSearch === ''
                ? currentSchema.value.rows
                : currentSchema.value.rows.filter((row) =>
                      currentSchema.value.columns
                          .filter((column) => column.searchable)
                          .some((column) =>
                              resolveSearchableValues(row, column).some(
                                  (value) =>
                                      value
                                          .toLowerCase()
                                          .includes(normalizedSearch),
                              ),
                          ),
                  )

        if (sort.value === '') {
            return searchedRows
        }

        const activeSortField = sortField.value
        const activeColumn =
            currentSchema.value.columns.find((column) => {
                const sortKey = column.meta?.sortKey

                return typeof sortKey === 'string' && sortKey !== ''
                    ? sortKey === activeSortField
                    : column.key === activeSortField
            }) ??
            currentSchema.value.columns.find(
                (column) => column.key === activeSortField,
            )

        return [...searchedRows].sort((left, right) => {
            const leftValue = left[activeSortField]
            const rightValue = right[activeSortField]

            return (
                compareValues(leftValue, rightValue, activeColumn) *
                sortOrder.value
            )
        })
    })

    const rows = computed(() => {
        if (!isLocal.value) {
            return currentSchema.value.rows
        }

        const start = (currentPage.value - 1) * perPage.value

        return localRows.value.slice(start, start + perPage.value)
    })

    const pagination = computed(() => {
        if (!isLocal.value) {
            return currentSchema.value.pagination
        }

        const total = localRows.value.length
        const from =
            total === 0 ? null : (currentPage.value - 1) * perPage.value + 1
        const to =
            total === 0
                ? null
                : Math.min(currentPage.value * perPage.value, total)

        return {
            from,
            lastPage: Math.max(1, Math.ceil(total / perPage.value)),
            page: currentPage.value,
            perPage: perPage.value,
            to,
            total,
        }
    })

    function syncQuery(overrides: Partial<DataTableState> = {}): void {
        if (isLocal.value) {
            filters.value = normalizeFiltersRecord({
                ...filters.value,
                ...(overrides.filters ?? {}),
            })
            search.value = overrides.search ?? search.value
            sort.value = overrides.sort ?? sort.value
            visibleColumns.value =
                overrides.visibleColumns ?? visibleColumns.value
            currentPage.value = 1

            return
        }

        const nextState: DataTableState = {
            filters: normalizeFiltersRecord({
                ...filters.value,
                ...(overrides.filters ?? {}),
            }),
            search: overrides.search ?? search.value,
            sort: overrides.sort ?? sort.value,
            visibleColumns: overrides.visibleColumns ?? visibleColumns.value,
        }

        filters.value = nextState.filters

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
        const nextFilters = {
            ...filters.value,
            [key]: value,
        }

        filters.value = normalizeFiltersRecord(nextFilters)

        syncQuery({
            filters: filters.value,
        })
    }

    function resetFilters(): void {
        filters.value = {}

        syncQuery({
            filters: {},
        })
    }

    function setPage(event: { page: number; rows: number }): void {
        if (isLocal.value) {
            currentPage.value = event.page + 1
            perPage.value = event.rows

            return
        }

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

        if (isLocal.value) {
            currentPage.value = 1

            return
        }

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
        [() => currentSchema.value.state, () => currentSchema.value.pagination],
        ([nextState, nextPagination]) => {
            filters.value = { ...(nextState.filters ?? {}) }
            search.value = nextState.search ?? ''
            sort.value = nextState.sort ?? ''
            visibleColumns.value =
                nextState.visibleColumns.length > 0
                    ? [...nextState.visibleColumns]
                    : currentSchema.value.columns
                          .filter((column) => column.visible)
                          .map((column) => column.key)
            currentPage.value = nextPagination.page ?? 1
            perPage.value = nextPagination.perPage ?? 10
        },
    )

    watch(
        () => pagination.value.lastPage,
        (lastPage) => {
            if (currentPage.value > lastPage) {
                currentPage.value = lastPage
            }
        },
    )

    return {
        activeColumns,
        bulkActions,
        columnMap,
        filters,
        isLocal,
        isLoading,
        pagination,
        rowActions,
        resetFilters,
        rows,
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
