<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '@/components/AppIcon.vue'
import LogBadge from '@/pages/Admin/Logs/components/LogBadge.vue'
import logFiles from '@/routes/core-panel/log-files'
import type {
    DataTablePagination,
    DataTableSchema,
    LogFileRecord,
} from '@/types/core-panel'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'

const props = defineProps<{
    filters: {
        direction: string
        search: string
        sort: string
    }
    files: {
        currentPage: number
        data: LogFileRecord[]
        lastPage: number
        perPage: number
        total: number
    }
}>()

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

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString()
}

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

function openFile(file: LogFileRecord): void {
    router.visit(logFiles.show.url(file.name))
}
</script>

<template>
    <div class="grid gap-1">
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
            <template #cell-name="{ row }">
                <span class="text-sm font-medium text-[var(--cp-text-primary)]">
                    {{ row.name }}
                </span>
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
                <span v-else class="text-sm text-[var(--cp-text-muted)]"
                    >—</span
                >
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <Button
                        :aria-label="$t('page-log-files.actions.view')"
                        class="cp-datatable__action-button"
                        outlined
                        severity="secondary"
                        size="small"
                        @click="openFile(row as LogFileRecord)"
                    >
                        <AppIcon name="eye" />
                    </Button>
                </div>
            </template>
        </TableBuilderDataTable>
    </div>
</template>
