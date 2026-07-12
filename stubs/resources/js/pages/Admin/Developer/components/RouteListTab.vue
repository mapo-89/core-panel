<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import type {
    DataTablePagination,
    DataTableSchema,
    DeveloperRouteRecord,
} from '@core-panel/types/core-panel'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'

const props = defineProps<{
    emptyMessage: string
    filters: {
        method: string | null
        search: string
    }
    options: {
        methods: Array<{ label: string; value: string }>
    }
    routes: {
        currentPage: number
        data: DeveloperRouteRecord[]
        lastPage: number
        perPage: number
        total: number
    }
    tabLabel: string
}>()

const defaultColumns = [
    'method',
    'uri',
    'name',
    'action',
    'domain',
    'middleware',
]
const methodFilterItems = computed(() =>
    Object.fromEntries(
        props.options.methods.map((option) => [option.value, option.label]),
    ),
)
const routeTableSchema = computed<DataTableSchema>(() => ({
    actions: [],
    bulkActions: [],
    columns: [
        {
            key: 'method',
            label: null,
            meta: { labelKey: 'page-developer.columns.method' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'badge',
            visible: true,
        },
        {
            key: 'uri',
            label: null,
            meta: { labelKey: 'page-developer.columns.uri' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'name',
            label: null,
            meta: { labelKey: 'page-developer.columns.name' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'action',
            label: null,
            meta: { labelKey: 'page-developer.columns.action' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'domain',
            label: null,
            meta: { labelKey: 'page-developer.columns.domain' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'middleware',
            label: null,
            meta: { labelKey: 'page-developer.columns.middleware' },
            searchable: false,
            sortable: false,
            toggleable: true,
            type: 'text',
            visible: true,
        },
    ],
    filters: [
        {
            key: 'method',
            label: null,
            meta: { labelKey: 'page-developer.filters.method' },
            options: methodFilterItems.value,
            type: 'select',
        },
    ],
    pagination: buildPagination(props.routes),
    rows: props.routes.data,
    state: {
        filters: {
            method: props.filters.method ?? '',
        },
        search: props.filters.search ?? '',
        sort: currentSort(),
        visibleColumns: currentColumns(defaultColumns),
    },
}))

function buildPagination(routes: {
    currentPage: number
    lastPage: number
    perPage: number
    total: number
}): DataTablePagination {
    const from =
        routes.total === 0
            ? null
            : (routes.currentPage - 1) * routes.perPage + 1
    const to =
        routes.total === 0
            ? null
            : Math.min(routes.currentPage * routes.perPage, routes.total)

    return {
        from,
        lastPage: routes.lastPage,
        page: routes.currentPage,
        perPage: routes.perPage,
        to,
        total: routes.total,
    }
}

function currentQuery(): URLSearchParams {
    if (typeof window === 'undefined') {
        return new URLSearchParams()
    }

    return new URLSearchParams(window.location.search)
}

function currentColumns(fallback: string[]): string[] {
    const columns = currentQuery().get('columns')

    if (!columns) {
        return fallback
    }

    const visibleColumns = columns
        .split(',')
        .filter((column) => fallback.includes(column))

    return visibleColumns.length > 0 ? visibleColumns : fallback
}

function currentSort(): string {
    return currentQuery().get('sort') ?? 'uri'
}

function methodSeverity(
    method: string,
): 'danger' | 'info' | 'secondary' | 'success' | 'warning' {
    const primaryMethod = method.split('|')[0]

    switch (primaryMethod) {
        case 'DELETE':
            return 'danger'
        case 'GET':
            return 'success'
        case 'PATCH':
        case 'PUT':
            return 'warning'
        case 'POST':
            return 'info'
        default:
            return 'secondary'
    }
}
</script>

<template>
    <div>
        <div class="cp-section__header">
            <div class="grid min-w-0 flex-1 gap-1">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{ $t(tabLabel) }}
                </h2>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{
                        $t('page-developer.states.route_count', {
                            count: String(routes.total),
                        })
                    }}
                </p>
            </div>
        </div>

        <TableBuilderDataTable
            :empty-message="$t(emptyMessage)"
            :schema="routeTableSchema"
        >
            <template #cell-method="{ row }">
                <div class="cp-developer-routes__method-list">
                    <Tag
                        v-for="method in row.methods"
                        :key="String(method)"
                        :severity="methodSeverity(String(method))"
                        :value="String(method)"
                    />
                </div>
            </template>

            <template #cell-uri="{ row }">
                <code class="cp-developer-routes__mono">{{ row.uri }}</code>
            </template>

            <template #cell-name="{ row }">
                <span v-if="row.name" class="cp-developer-routes__mono">
                    {{ row.name }}
                </span>
                <span v-else class="cp-developer-routes__muted">
                    {{ $t('page-developer.states.unnamed') }}
                </span>
            </template>

            <template #cell-action="{ row }">
                <code class="cp-developer-routes__mono">{{ row.action }}</code>
            </template>

            <template #cell-domain="{ row }">
                <span v-if="row.domain" class="cp-developer-routes__mono">
                    {{ row.domain }}
                </span>
                <span v-else class="cp-developer-routes__muted">
                    {{ $t('page-developer.states.any_domain') }}
                </span>
            </template>

            <template #cell-middleware="{ row }">
                <div class="cp-developer-routes__middleware-list">
                    <Tag
                        v-for="middleware in row.middleware"
                        :key="String(middleware)"
                        severity="secondary"
                        :value="String(middleware)"
                    />
                </div>
            </template>

            <template #empty-state>
                <div
                    class="grid justify-items-center gap-3 px-4 py-12 text-center"
                >
                    <div class="cp-developer-routes__empty-icon">
                        <AppIcon name="files" />
                    </div>
                    <div class="space-y-1">
                        <h3
                            class="text-sm font-semibold text-[var(--cp-text-primary)]"
                        >
                            {{ $t(emptyMessage) }}
                        </h3>
                    </div>
                </div>
            </template>
        </TableBuilderDataTable>
    </div>
</template>
