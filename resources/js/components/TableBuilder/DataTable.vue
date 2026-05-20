<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { trans } from 'laravel-vue-i18n'
import PrimeDataTable from 'primevue/datatable'
import PrimeColumn from 'primevue/column'
import type { DataTableSortEvent } from 'primevue/datatable'

import AppIcon from '../AppIcon.vue'
import type {
    DataTableAction,
    DataTableColumn,
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
            />
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
                    v-for="column in table.activeColumns.value"
                    :key="column.key"
                    :field="column.key"
                    :header="resolveColumnLabel(column)"
                    :sortable="column.sortable"
                >
                    <template #body="{ data }">
                        <slot :name="`cell-${column.key}`" :row="data">
                            <Tag
                                v-if="column.type === 'badge'"
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

        <TablePagination
            class="-mt-[0.15rem]"
            :pagination="schema.pagination"
            @page="table.setPage"
        />
    </section>
</template>
