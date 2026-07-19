<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useSlots,
    watch,
} from 'vue'
import { trans } from 'laravel-vue-i18n'
import PrimeDataTable from 'primevue/datatable'
import PrimeColumn from 'primevue/column'
import PrimePopover from 'primevue/popover'
import type { DataTableSortEvent } from 'primevue/datatable'

import AppIcon from '../AppIcon.vue'
import type {
    DataTableAction,
    DataTableColumn,
    DataTableFilter,
    DataTableRow,
    DataTableSchema,
} from './types'
import BulkActionBar from './BulkActionBar.vue'
import ColumnVisibilityDropdown from './ColumnVisibilityDropdown.vue'
import TableActions from './TableActions.vue'
import TableFilterDropdown from './TableFilterDropdown.vue'
import TablePagination from './TablePagination.vue'
import { useDataTable } from './useDataTable'

const props = defineProps<{
    actionColumnWidth?: string
    emptyMessage?: string
    loading?: boolean
    mode?: 'local' | 'remote'
    only?: string[]
    schema: DataTableSchema
    surfaceClass?: string
}>()

const slots = useSlots()
const rootRef = ref<HTMLElement | null>(null)
const stickyHeadRef = ref<HTMLElement | null>(null)
const tableSurfaceRef = ref<HTMLElement | null>(null)
const table = useDataTable(() => props.schema, {
    mode: () => props.mode,
    only: props.only,
})

const sortField = computed(() => table.sort.value.replace(/^-/, ''))
const sortOrder = computed(() => (table.sort.value.startsWith('-') ? -1 : 1))
const hasRowActionsSlot = computed(() => slots['row-actions'] !== undefined)
const hasBulkActions = computed(() => table.bulkActions.value.length > 0)
const idPopoverRef = ref<{ toggle: (event: Event) => void } | null>(null)
const idPopoverValue = ref<string | number | null>(null)
const idCopied = ref(false)
const stickyHeaderColumnWidths = ref<number[]>([])
let stickyHeadResizeObserver: ResizeObserver | null = null
let tableSurfaceResizeObserver: ResizeObserver | null = null
const isBusy = computed(() => props.loading === true || table.isLoading.value)
const shouldInjectIdColumn = computed(() => {
    if (props.schema.columns.some((column) => column.key === 'id')) {
        return false
    }

    return props.schema.rows.some(
        (row) => typeof row.id === 'string' || typeof row.id === 'number',
    )
})
const displayColumns = computed<DataTableColumn[]>(() => {
    if (!shouldInjectIdColumn.value) {
        return table.activeColumns.value
    }

    return [
        {
            key: 'id',
            label: 'ID',
            searchable: false,
            sortable: false,
            toggleable: false,
            type: 'text',
            visible: true,
        },
        ...table.activeColumns.value,
    ]
})
const activeFilterChips = computed(() =>
    props.schema.filters
        .map((filter) => {
            const value = table.filters.value[filter.key]

            if (isEmptyFilterValue(value)) {
                return null
            }

            return {
                key: filter.key,
                label: resolveFilterLabel(filter),
                value: resolveFilterValueLabel(filter, value),
            }
        })
        .filter(
            (
                chip,
            ): chip is {
                key: string
                label: string
                value: string
            } => chip !== null,
        ),
)

function handleSort(event: DataTableSortEvent): void {
    table.setSort(
        typeof event.sortField === 'string' ? event.sortField : '',
        event.sortOrder === 1 || event.sortOrder === -1
            ? event.sortOrder
            : undefined,
    )
}

function handleStickyHeaderSort(column: DataTableColumn): void {
    if (!column.sortable) {
        return
    }

    const field = resolveSortField(column)

    if (sortField.value !== field) {
        table.setSort(field, 1)

        return
    }

    if (sortOrder.value === 1) {
        table.setSort(field, -1)

        return
    }

    table.setSort('', undefined)
}

function rowClass(): string {
    return 'bg-[var(--cp-surface-panel)]'
}

function runRowAction(action: DataTableAction, row: DataTableRow): void {
    table.runAction(action, row)
}

function resolveColumnLabel(column: DataTableColumn): string {
    const labelKey = column.meta?.labelKey

    if (typeof labelKey === 'string' && labelKey !== '') {
        return trans(labelKey)
    }

    if (column.label === null) {
        return ''
    }

    return column.label || column.key
}

function resolveSortField(column: DataTableColumn): string {
    const sortKey = column.meta?.sortKey

    return typeof sortKey === 'string' && sortKey !== '' ? sortKey : column.key
}

function resolveFilterLabel(filter: DataTableFilter): string {
    const labelKey = filter.meta?.labelKey

    if (typeof labelKey === 'string' && labelKey !== '') {
        return trans(labelKey)
    }

    if (filter.label === null) {
        return filter.key
    }

    return filter.label || filter.key
}

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

function resolveFilterValueLabel(
    filter: DataTableFilter,
    value: unknown,
): string {
    if (filter.type === 'select') {
        const option = filter.options?.[String(value)]

        return option ?? String(value)
    }

    if (
        filter.type === 'date-range' &&
        typeof value === 'object' &&
        value !== null
    ) {
        const range = value as Record<string, unknown>
        const from = String(range.from ?? '').trim()
        const to = String(range.to ?? '').trim()

        if (from !== '' && to !== '') {
            return `${from} - ${to}`
        }

        return from || to
    }

    return String(value)
}

function openIdPopover(event: Event, value: unknown): void {
    if (typeof value !== 'string' && typeof value !== 'number') {
        return
    }

    idPopoverValue.value = value
    idCopied.value = false

    nextTick(() => {
        idPopoverRef.value?.toggle(event)
    })
}

async function copyCurrentId(): Promise<void> {
    if (idPopoverValue.value === null) {
        return
    }

    try {
        await navigator.clipboard.writeText(String(idPopoverValue.value))
        idCopied.value = true
    } catch {
        idCopied.value = false
    }
}

function syncStickyHeadMetrics(): void {
    if (rootRef.value === null) {
        return
    }

    rootRef.value.style.setProperty(
        '--cp-datatable-sticky-head-height',
        `${stickyHeadRef.value?.offsetHeight ?? 0}px`,
    )

    const bodyCells = rootRef.value.querySelectorAll<HTMLElement>(
        '.cp-datatable__table .p-datatable-tbody > tr:first-child > td',
    )

    stickyHeaderColumnWidths.value = Array.from(bodyCells).map(
        (cell) => cell.getBoundingClientRect().width,
    )
}

const stickyHeaderGridTemplate = computed(() => {
    const totalColumns =
        displayColumns.value.length +
        (hasBulkActions.value ? 1 : 0) +
        (table.rowActions.value.length > 0 || hasRowActionsSlot.value ? 1 : 0)

    if (stickyHeaderColumnWidths.value.length === totalColumns) {
        return stickyHeaderColumnWidths.value
            .map((width) => `${Math.max(width, 1)}px`)
            .join(' ')
    }

    const fallbackColumns: string[] = []

    if (hasBulkActions.value) {
        fallbackColumns.push('3rem')
    }

    fallbackColumns.push(...displayColumns.value.map(() => 'minmax(0, 1fr)'))

    if (table.rowActions.value.length > 0 || hasRowActionsSlot.value) {
        fallbackColumns.push(props.actionColumnWidth ?? '6rem')
    }

    return fallbackColumns.join(' ')
})

const allRowsSelected = computed(
    () =>
        props.schema.rows.length > 0 &&
        table.selectedRows.value.length === props.schema.rows.length,
)

const someRowsSelected = computed(
    () => table.selectedRows.value.length > 0 && !allRowsSelected.value,
)

function toggleAllRows(checked: boolean): void {
    table.selectedRows.value = checked ? [...props.schema.rows] : []
}

function sortIconName(column: DataTableColumn): string | null {
    if (!column.sortable) {
        return null
    }

    const field = resolveSortField(column)

    if (sortField.value !== field) {
        return 'chevron-down'
    }

    return sortOrder.value === -1 ? 'chevron-down' : 'chevron-up'
}

onMounted(() => {
    void nextTick(() => {
        syncStickyHeadMetrics()

        if (stickyHeadRef.value === null) {
            return
        }

        stickyHeadResizeObserver = new ResizeObserver(() => {
            syncStickyHeadMetrics()
        })

        stickyHeadResizeObserver.observe(stickyHeadRef.value)

        if (tableSurfaceRef.value !== null) {
            tableSurfaceResizeObserver = new ResizeObserver(() => {
                syncStickyHeadMetrics()
            })

            tableSurfaceResizeObserver.observe(tableSurfaceRef.value)
        }
    })
})

onBeforeUnmount(() => {
    stickyHeadResizeObserver?.disconnect()
    tableSurfaceResizeObserver?.disconnect()
})

watch(
    [
        displayColumns,
        () => props.schema.rows,
        () => hasBulkActions.value,
        () => hasRowActionsSlot.value,
        () => table.rowActions.value.length,
    ],
    async () => {
        await nextTick()
        syncStickyHeadMetrics()
    },
    { deep: true },
)
</script>

<template>
    <section ref="rootRef" class="grid gap-0 cp-datatable">
        <div ref="stickyHeadRef" class="cp-datatable__sticky-head">
            <div class="grid gap-3 px-[1.125rem] pt-[1.125rem] pb-1">
                <div class="cp-datatable__toolbar">
                    <div class="cp-datatable__search">
                        <span class="cp-datatable__search-icon">
                            <AppIcon name="search" />
                        </span>
                        <InputText
                            :model-value="table.search.value"
                            class="cp-datatable__search-input"
                            :placeholder="$t('table-builder.labels.search')"
                            @update:model-value="
                                table.setSearch(String($event ?? ''))
                            "
                        />
                    </div>

                    <div class="cp-datatable__toolbar-actions">
                        <slot
                            name="toolbar"
                            :columns="schema.columns"
                            :filters="table.filters.value"
                            :visible-columns="table.visibleColumns.value"
                            :set-filter="table.setFilter"
                            :set-visible-columns="table.setVisibleColumns"
                        />
                        <slot
                            name="toolbar-actions"
                            :columns="schema.columns"
                            :filters="table.filters.value"
                            :visible-columns="table.visibleColumns.value"
                            :set-filter="table.setFilter"
                            :set-visible-columns="table.setVisibleColumns"
                        >
                            <TableFilterDropdown
                                :filters="table.filters.value"
                                :schema="schema.filters"
                                @change="table.setFilter"
                            />
                            <ColumnVisibilityDropdown
                                v-model="table.visibleColumns.value"
                                :columns="schema.columns"
                                @update:model-value="
                                    table.setVisibleColumns($event)
                                "
                            />
                        </slot>
                    </div>
                </div>

                <slot
                    name="toolbar-footer"
                    :filters="table.filters.value"
                    :set-filter="table.setFilter"
                    :reset-filters="table.resetFilters"
                >
                    <div
                        v-if="activeFilterChips.length > 0"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <button
                            v-for="chip in activeFilterChips"
                            :key="chip.key"
                            class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                            type="button"
                            @click="table.setFilter(chip.key, undefined)"
                        >
                            <span>{{ chip.label }}: {{ chip.value }}</span>
                            <AppIcon name="x" />
                        </button>
                        <Button
                            outlined
                            severity="secondary"
                            size="small"
                            class="cp-datatable__toolbar-button"
                            @click="table.resetFilters()"
                        >
                            {{ $t('table-builder.actions.reset_filters') }}
                        </Button>
                    </div>
                </slot>
            </div>

            <BulkActionBar
                class="-mt-[0.15rem]"
                :actions="table.bulkActions.value"
                :selected-count="table.selectedRows.value.length"
                @run="table.runBulkAction"
            />

            <div
                class="cp-datatable__sticky-header-row"
                :style="{ gridTemplateColumns: stickyHeaderGridTemplate }"
            >
                <div
                    v-if="hasBulkActions"
                    class="cp-datatable__sticky-header-cell cp-datatable__sticky-header-cell--selection"
                >
                    <Checkbox
                        binary
                        :indeterminate="someRowsSelected"
                        :model-value="allRowsSelected"
                        @update:model-value="toggleAllRows(Boolean($event))"
                    />
                </div>

                <component
                    :is="column.sortable ? 'button' : 'div'"
                    v-for="column in displayColumns"
                    :key="column.key"
                    class="cp-datatable__sticky-header-cell"
                    :class="{
                        'cp-datatable__sticky-header-cell--sortable':
                            column.sortable,
                    }"
                    type="button"
                    @click="
                        column.sortable
                            ? handleStickyHeaderSort(column)
                            : undefined
                    "
                >
                    <span class="cp-datatable__sticky-header-label">
                        <slot :name="`header-${column.key}`" :column="column">
                            {{ resolveColumnLabel(column) }}
                        </slot>
                    </span>
                    <AppIcon
                        v-if="sortIconName(column) !== null"
                        :name="sortIconName(column) ?? 'chevron-down'"
                        class="cp-datatable__sticky-header-icon"
                        :class="{
                            'opacity-45':
                                sortField !== resolveSortField(column),
                        }"
                    />
                </component>

                <div
                    v-if="
                        table.rowActions.value.length > 0 || hasRowActionsSlot
                    "
                    class="cp-datatable__sticky-header-cell cp-datatable__sticky-header-cell--actions"
                >
                    {{ $t('common.ui.actions') }}
                </div>
            </div>
        </div>

        <div
            ref="tableSurfaceRef"
            class="cp-card cp-datatable__surface"
            :class="props.surfaceClass"
        >
            <PrimeDataTable
                v-model:selection="table.selectedRows.value"
                class="cp-datatable__table cp-datatable__table--sticky-head"
                :row-class="rowClass"
                :selection-mode="hasBulkActions ? 'multiple' : undefined"
                :sort-field="sortField"
                :sort-order="sortOrder"
                :value="table.rows.value"
                data-key="id"
                :lazy="!table.isLocal.value"
                removable-sort
                @sort="handleSort"
            >
                <template #empty>
                    <slot name="empty-state">
                        <div
                            class="grid justify-items-center gap-3 px-4 py-12 text-center"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--cp-surface-border)_18%,transparent)] text-[var(--cp-text-muted)]"
                            >
                                <AppIcon name="inbox" />
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                >
                                    {{ $t('table-builder.states.empty_title') }}
                                </h3>
                            </div>
                        </div>
                    </slot>
                </template>

                <PrimeColumn
                    v-if="hasBulkActions"
                    selection-mode="multiple"
                    header-style="width: 3rem"
                />

                <PrimeColumn
                    v-for="column in displayColumns"
                    :key="column.key"
                    :field="column.key"
                    :sort-field="resolveSortField(column)"
                    :sortable="column.sortable"
                >
                    <template #header>
                        <slot :name="`header-${column.key}`" :column="column">
                            {{ resolveColumnLabel(column) }}
                        </slot>
                    </template>
                    <template #body="{ data }">
                        <slot :name="`cell-${column.key}`" :row="data">
                            <div
                                v-if="column.key === 'id'"
                                class="cp-datatable__id-cell"
                            >
                                <button
                                    v-if="
                                        typeof data[column.key] === 'string' ||
                                        typeof data[column.key] === 'number'
                                    "
                                    class="cp-datatable__id-trigger"
                                    type="button"
                                    @click.stop="
                                        openIdPopover($event, data[column.key])
                                    "
                                >
                                    <AppIcon name="info" />
                                </button>
                            </div>
                            <Tag
                                v-else-if="column.type === 'badge'"
                                :value="String(data[column.key] ?? '')"
                                severity="secondary"
                            />
                            <Checkbox
                                v-else-if="column.type === 'boolean'"
                                binary
                                :model-value="Boolean(data[column.key])"
                                disabled
                            />
                            <span v-else>{{ data[column.key] }}</span>
                        </slot>
                    </template>
                </PrimeColumn>

                <PrimeColumn
                    v-if="
                        table.rowActions.value.length > 0 || hasRowActionsSlot
                    "
                    :header="$t('common.ui.actions')"
                    header-class="cp-datatable__actions-header"
                    :header-style="`width: ${props.actionColumnWidth ?? '6rem'}`"
                >
                    <template #body="{ data }">
                        <slot name="row-actions" :row="data">
                            <TableActions
                                :actions="table.rowActions.value"
                                :row="data"
                                @run="runRowAction"
                            />
                        </slot>
                    </template>
                </PrimeColumn>
            </PrimeDataTable>

            <div v-if="isBusy" class="cp-datatable__body-overlay">
                <div class="cp-datatable__body-overlay-card">
                    <AppIcon
                        name="refresh"
                        class="cp-datatable__body-overlay-icon"
                    />
                    <span class="cp-datatable__body-overlay-text">
                        {{ $t('table-builder.states.loading') }}
                    </span>
                </div>
            </div>
        </div>

        <PrimePopover ref="idPopoverRef">
            <div class="cp-datatable__id-popover">
                <div class="cp-datatable__id-popover-row">
                    <span class="cp-datatable__id-popover-label"> ID </span>
                    <strong class="cp-datatable__id-popover-value">
                        {{ idPopoverValue }}
                    </strong>
                    <span class="cp-datatable__id-popover-separator" />
                    <button
                        class="cp-datatable__id-popover-copy-button"
                        type="button"
                        @click="copyCurrentId"
                    >
                        <AppIcon :name="idCopied ? 'check' : 'copy'" />
                    </button>
                </div>
            </div>
        </PrimePopover>

        <TablePagination
            class="mt-4"
            :pagination="table.pagination.value"
            @page="table.setPage"
        />
    </section>
</template>
