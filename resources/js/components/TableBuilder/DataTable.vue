<script setup lang="ts">
import { computed, nextTick, ref, useSlots } from 'vue'
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
    emptyMessage?: string
    only?: string[]
    schema: DataTableSchema
}>()

const slots = useSlots()
const table = useDataTable(props.schema, {
    only: props.only,
})

const sortField = computed(() => table.sort.value.replace(/^-/, ''))
const sortOrder = computed(() => (table.sort.value.startsWith('-') ? -1 : 1))
const hasRowActionsSlot = computed(() => slots['row-actions'] !== undefined)
const hasBulkActions = computed(() => table.bulkActions.value.length > 0)
const idPopoverRef = ref<{ toggle: (event: Event) => void } | null>(null)
const idPopoverValue = ref<string | number | null>(null)
const idCopied = ref(false)
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
</script>

<template>
    <section class="grid gap-4">
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

        <div class="cp-card cp-datatable__surface">
            <PrimeDataTable
                v-model:selection="table.selectedRows.value"
                class="cp-datatable__table"
                :row-class="rowClass"
                :selection-mode="hasBulkActions ? 'multiple' : undefined"
                :sort-field="sortField"
                :sort-order="sortOrder"
                :value="schema.rows"
                data-key="id"
                lazy
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
                    header-style="width: 6rem"
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

            <div
                v-if="table.isLoading.value"
                class="cp-datatable__body-overlay"
            >
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
            class="-mt-[0.15rem]"
            :pagination="schema.pagination"
            @page="table.setPage"
        />
    </section>
</template>
