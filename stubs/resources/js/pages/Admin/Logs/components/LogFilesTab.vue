<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '@/components/AppIcon.vue'
import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'
import LogBadge from '@/pages/Admin/Logs/components/LogBadge.vue'
import logFiles from '@/routes/core-panel/log-files'
import logsPage from '@/routes/core-panel/logs'
import type {
    DataTablePagination,
    DataTableSchema,
    LogFileRecord,
} from '@/types/core-panel'
import ColumnVisibilityDropdown from '@core-panel/components/TableBuilder/ColumnVisibilityDropdown.vue'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'

const props = defineProps<{
    filters: {
        channel: string | null
        direction: string
        search: string
        sort: string
        state: string | null
    }
    files: {
        currentPage: number
        data: LogFileRecord[]
        lastPage: number
        perPage: number
        total: number
    }
    options: {
        channels: Array<{ label: string; value: string }>
        states: Array<{ label: string; value: string }>
    }
}>()

const actionProcessing = ref(false)
const clearDialogVisible = ref(false)
const deleteDialogVisible = ref(false)
const filterMenu = ref<{ toggle: (event: Event) => void } | null>(null)
const pendingClearFile = ref<LogFileRecord | null>(null)
const pendingDeleteFile = ref<LogFileRecord | null>(null)

const logFilesTableSchema = computed<DataTableSchema>(() => ({
    actions: [],
    bulkActions: [],
    columns: [
        {
            key: 'name',
            label: null,
            meta: { labelKey: 'page-log-files.name' },
            searchable: true,
            sortable: true,
            toggleable: false,
            type: 'text',
            visible: true,
        },
        {
            key: 'channelType',
            label: null,
            meta: { labelKey: 'page-log-files.channel' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'badge',
            visible: true,
        },
        {
            key: 'sizeBytes',
            label: null,
            meta: { labelKey: 'page-log-files.size' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'modifiedAt',
            label: null,
            meta: { labelKey: 'page-log-files.modified' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'isActive',
            label: null,
            meta: { labelKey: 'page-log-files.active' },
            searchable: false,
            sortable: false,
            toggleable: true,
            type: 'badge',
            visible: true,
        },
    ],
    filters: [],
    pagination: buildPagination(props.files),
    rows: props.files.data,
    state: {
        filters: {},
        search: props.filters.search,
        sort:
            props.filters.direction === 'desc'
                ? `-${props.filters.sort}`
                : props.filters.sort,
        visibleColumns: currentColumns([
            'name',
            'channelType',
            'sizeBytes',
            'modifiedAt',
            'isActive',
        ]),
    },
}))

function buildPagination(files: {
    currentPage: number
    lastPage: number
    perPage: number
    total: number
}): DataTablePagination {
    const from =
        files.total === 0 ? null : (files.currentPage - 1) * files.perPage + 1
    const to =
        files.total === 0
            ? null
            : Math.min(files.currentPage * files.perPage, files.total)

    return {
        from,
        lastPage: files.lastPage,
        page: files.currentPage,
        perPage: files.perPage,
        to,
        total: files.total,
    }
}

function channelTone(
    channel: string,
): 'danger' | 'info' | 'neutral' | 'success' | 'warning' {
    return (
        ({
            daily: 'info',
            other: 'neutral',
            single: 'warning',
        }[channel] as
            | 'danger'
            | 'info'
            | 'neutral'
            | 'success'
            | 'warning'
            | undefined) ?? 'neutral'
    )
}

function confirmClearFile(): void {
    if (pendingClearFile.value === null || actionProcessing.value) {
        return
    }

    actionProcessing.value = true

    router.delete(logFiles.clear.url(pendingClearFile.value.name), {
        onFinish: () => {
            actionProcessing.value = false
            clearDialogVisible.value = false
            pendingClearFile.value = null
        },
        preserveScroll: true,
        preserveState: true,
    })
}

function confirmDeleteFile(): void {
    if (pendingDeleteFile.value === null || actionProcessing.value) {
        return
    }

    actionProcessing.value = true

    router.delete(logFiles.destroy.url(pendingDeleteFile.value.name), {
        onFinish: () => {
            actionProcessing.value = false
            deleteDialogVisible.value = false
            pendingDeleteFile.value = null
        },
        preserveScroll: true,
        preserveState: true,
    })
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

function currentPerPage(): number | undefined {
    const perPage = currentQuery().get('per_page')

    return perPage ? Number(perPage) : undefined
}

function currentFilterValue(key: string): string {
    return currentQuery().get(`filter[${key}]`) ?? ''
}

function currentQuery(): URLSearchParams {
    if (typeof window === 'undefined') {
        return new URLSearchParams()
    }

    return new URLSearchParams(window.location.search)
}

function currentSort(): string | undefined {
    const sort = currentQuery().get('sort')

    if (sort) {
        return sort
    }

    if (props.filters.sort === '') {
        return undefined
    }

    return props.filters.direction === 'desc'
        ? `-${props.filters.sort}`
        : props.filters.sort
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString()
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`
    }

    if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
    }

    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`
}

function openClearDialog(file: LogFileRecord): void {
    pendingClearFile.value = file
    clearDialogVisible.value = true
}

function openDeleteDialog(file: LogFileRecord): void {
    pendingDeleteFile.value = file
    deleteDialogVisible.value = true
}

function openFile(file: LogFileRecord): void {
    router.visit(logFiles.show.url(file.name))
}

function openFilterMenu(event: Event): void {
    filterMenu.value?.toggle(event)
}

function hasOverride(
    overrides: Record<string, string | undefined>,
    key: string,
): boolean {
    return Object.prototype.hasOwnProperty.call(overrides, key)
}

function overrideOrCurrent(
    overrides: Record<string, string | undefined>,
    key: string,
): string | undefined {
    if (hasOverride(overrides, key)) {
        return overrides[key] || undefined
    }

    return currentFilterValue(key) || undefined
}

function clearToolbarFilter(key: 'channel' | 'state'): void {
    updateFilters({
        [key]: undefined,
    })
}

function updateFilters(overrides: Record<string, string | undefined>): void {
    const filters = {
        channel: overrideOrCurrent(overrides, 'channel'),
        state: overrideOrCurrent(overrides, 'state'),
    }

    router.get(
        logsPage.index.url(),
        {
            columns: currentQuery().get('columns') || undefined,
            filter: filters,
            per_page: currentPerPage(),
            search: currentQuery().get('search') || undefined,
            sort: currentSort(),
            tab: 'logs',
        },
        {
            only: ['activeTab', 'logsTab'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}

function resetFilters(): void {
    updateFilters({
        channel: undefined,
        state: undefined,
    })
}
</script>

<template>
    <div class="grid gap-1">
        <ConfirmActionDialog
            v-model:visible="clearDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('page-log-files.actions.clear')"
            confirm-severity="warn"
            :description="$t('page-log-files.confirmations.clear')"
            :disabled="actionProcessing"
            icon="refresh-cw"
            :loading="actionProcessing"
            :message="
                pendingClearFile?.name ?? $t('page-log-files.actions.clear')
            "
            :title="$t('page-log-files.actions.clear')"
            tone="warning"
            @confirm="confirmClearFile"
        />

        <ConfirmActionDialog
            v-model:visible="deleteDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('page-log-files.actions.delete')"
            confirm-severity="danger"
            :description="$t('page-log-files.confirmations.delete')"
            :disabled="actionProcessing"
            icon="trash"
            :loading="actionProcessing"
            :message="
                pendingDeleteFile?.name ?? $t('page-log-files.actions.delete')
            "
            :title="$t('page-log-files.actions.delete')"
            tone="danger"
            @confirm="confirmDeleteFile"
        />

        <div class="grid gap-1 px-5 pt-5">
            <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                {{ trans('page-log-files.title') }}
            </h2>
            <p class="text-sm text-[var(--cp-text-muted)]">
                {{ trans('page-log-files.description') }}
            </p>
        </div>

        <TableBuilderDataTable
            :empty-message="$t('page-log-files.empty')"
            :only="['activeTab', 'logsTab']"
            :schema="logFilesTableSchema"
        >
            <template
                #toolbar-actions="{
                    columns,
                    setVisibleColumns,
                    visibleColumns,
                }"
            >
                <div class="flex flex-wrap items-center justify-end gap-2.5">
                    <Button
                        outlined
                        severity="secondary"
                        size="small"
                        class="cp-datatable__toolbar-button"
                        @click="openFilterMenu"
                    >
                        <AppIcon name="filter" />
                        <span>{{ $t('table-builder.labels.filters') }}</span>
                    </Button>
                    <ColumnVisibilityDropdown
                        :columns="columns"
                        :model-value="visibleColumns"
                        @update:model-value="setVisibleColumns"
                    />
                </div>
            </template>

            <template #toolbar-footer>
                <div
                    v-if="props.filters.channel || props.filters.state"
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        v-if="props.filters.channel"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('channel')"
                    >
                        <span>
                            {{ $t('page-log-files.filters.channel') }}:
                            {{
                                props.options.channels.find(
                                    (option) =>
                                        option.value === props.filters.channel,
                                )?.label ?? props.filters.channel
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.state"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('state')"
                    >
                        <span>
                            {{ $t('page-log-files.filters.state') }}:
                            {{
                                props.options.states.find(
                                    (option) =>
                                        option.value === props.filters.state,
                                )?.label ?? props.filters.state
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <Button
                        outlined
                        severity="secondary"
                        size="small"
                        class="cp-datatable__toolbar-button"
                        @click="resetFilters"
                    >
                        {{ $t('table-builder.actions.reset_filters') }}
                    </Button>
                </div>
            </template>

            <template #cell-name="{ row }">
                <div class="grid gap-1">
                    <span
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{ row.name }}
                    </span>
                    <span class="truncate text-xs text-[var(--cp-text-muted)]">
                        {{ row.path }}
                    </span>
                </div>
            </template>

            <template #cell-channelType="{ row }">
                <LogBadge
                    :label="$t(`page-log-files.channels.${row.channelType}`)"
                    :tone="channelTone(row.channelType)"
                />
            </template>

            <template #cell-sizeBytes="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ formatSize(row.sizeBytes) }}
                </span>
            </template>

            <template #cell-modifiedAt="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ formatDateTime(row.modifiedAt) }}
                </span>
            </template>

            <template #cell-isActive="{ row }">
                <LogBadge
                    v-if="row.isActive"
                    :label="$t('page-log-files.active')"
                    tone="success"
                />
                <span v-else class="text-sm text-[var(--cp-text-muted)]">
                    {{ $t('page-log-files.states.archived') }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <Button
                        :aria-label="$t('page-log-files.actions.view')"
                        class="cp-datatable__action-button"
                        outlined
                        severity="secondary"
                        size="small"
                        type="button"
                        @click="openFile(row as LogFileRecord)"
                    >
                        <AppIcon name="eye" />
                    </Button>

                    <Button
                        v-if="(row as LogFileRecord).canClear"
                        :aria-label="$t('page-log-files.actions.clear')"
                        class="cp-datatable__action-button"
                        :disabled="actionProcessing"
                        outlined
                        severity="warn"
                        size="small"
                        type="button"
                        @click="openClearDialog(row as LogFileRecord)"
                    >
                        <AppIcon name="refresh-cw" />
                    </Button>

                    <Button
                        v-if="(row as LogFileRecord).canDelete"
                        :aria-label="$t('page-log-files.actions.delete')"
                        class="cp-datatable__action-button cp-datatable__action-button--danger"
                        :disabled="actionProcessing"
                        severity="danger"
                        size="small"
                        type="button"
                        @click="openDeleteDialog(row as LogFileRecord)"
                    >
                        <AppIcon name="trash" />
                    </Button>
                </div>
            </template>
        </TableBuilderDataTable>

        <Menu
            ref="filterMenu"
            popup
            :model="[]"
            class="cp-users-tab__filter-menu"
        >
            <template #start>
                <div class="cp-users-tab__filter-content">
                    <Select
                        :model-value="props.filters.channel ?? null"
                        :options="props.options.channels"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('page-log-files.filters.channel')"
                        @update:model-value="
                            updateFilters({
                                channel:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                    <Select
                        :model-value="props.filters.state ?? null"
                        :options="props.options.states"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('page-log-files.filters.state')"
                        @update:model-value="
                            updateFilters({
                                state:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                </div>
            </template>
        </Menu>
    </div>
</template>
