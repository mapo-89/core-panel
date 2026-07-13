<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { useToast } from 'primevue/usetoast'

import AppIcon from '@core-panel/components/AppIcon.vue'
import activity from '@/routes/core-panel/activity'
import logsPage from '@/routes/core-panel/logs'
import ActivityLogDetail from '@core-panel/pages/Admin/Logs/components/ActivityLogDetail.vue'
import LogBadge from '@core-panel/pages/Admin/Logs/components/LogBadge.vue'
import LogUserAvatar from '@core-panel/pages/Admin/Logs/components/LogUserAvatar.vue'
import type {
    ActivityLogRecord,
    DataTablePagination,
    DataTableSchema,
} from '@core-panel/types/core-panel'
import ColumnVisibilityDropdown from '@core-panel/components/TableBuilder/ColumnVisibilityDropdown.vue'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'

const props = defineProps<{
    filters: {
        date_from: string | null
        date_to: string | null
        event: string | null
        search: string
        subject_type: string | null
        user: string | null
    }
    logs: {
        currentPage: number
        data: ActivityLogRecord[]
        lastPage: number
        perPage: number
        total: number
    }
    options: {
        events: Array<{ label: string; value: string }>
        subjectTypes: Array<{ label: string; value: string }>
        users: Array<{ label: string; value: string }>
    }
}>()

const toast = useToast()
const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref<ActivityLogRecord | null>(null)
const filterMenu = ref<{ toggle: (event: Event) => void } | null>(null)
const dateFromValue = ref<Date | null>(
    props.filters.date_from ? new Date(props.filters.date_from) : null,
)
const dateToValue = ref<Date | null>(
    props.filters.date_to ? new Date(props.filters.date_to) : null,
)
const defaultColumns = [
    'event',
    'subjectId',
    'subjectType',
    'causerName',
    'createdAt',
]
const subjectTypeLabels = computed(() =>
    Object.fromEntries(
        props.options.subjectTypes.map((option) => [
            option.value,
            option.label,
        ]),
    ),
)
const eventLabels = computed(() =>
    Object.fromEntries(
        props.options.events.map((option) => [option.value, option.label]),
    ),
)
const userLabels = computed(() =>
    Object.fromEntries(
        props.options.users.map((option) => [option.value, option.label]),
    ),
)

const activityTableSchema = computed<DataTableSchema>(() => ({
    actions: [],
    bulkActions: [],
    columns: [
        {
            key: 'event',
            label: null,
            meta: { labelKey: 'activity.filters.event' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'badge',
            visible: true,
        },
        {
            key: 'subjectId',
            label: null,
            meta: { labelKey: 'activity.columns.subject_id' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'subjectType',
            label: null,
            meta: { labelKey: 'activity.columns.subject_type' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'causerName',
            label: null,
            meta: { labelKey: 'activity.columns.causer' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'createdAt',
            label: null,
            meta: { labelKey: 'activity.columns.created_at' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
    ],
    filters: [],
    pagination: buildPagination(props.logs),
    rows: props.logs.data.map((log) => ({
        ...log,
        causerName: log.systemCauser
            ? trans('activity.system')
            : (log.causerName ?? log.causerId ?? trans('activity.system')),
        event: log.event ?? 'event',
        subjectTypeLabel: subjectTypeLabel(log.subjectType),
    })),
    state: {
        filters: {
            date_from: props.filters.date_from ?? '',
            date_to: props.filters.date_to ?? '',
            event: props.filters.event ?? '',
            subject_type: props.filters.subject_type ?? '',
            user: props.filters.user ?? '',
        },
        search: props.filters.search ?? '',
        sort: currentSort(),
        visibleColumns: currentColumns(defaultColumns),
    },
}))

function buildPagination(logs: {
    currentPage: number
    lastPage: number
    perPage: number
    total: number
}): DataTablePagination {
    const from =
        logs.total === 0 ? null : (logs.currentPage - 1) * logs.perPage + 1
    const to =
        logs.total === 0
            ? null
            : Math.min(logs.currentPage * logs.perPage, logs.total)

    return {
        from,
        lastPage: logs.lastPage,
        page: logs.currentPage,
        perPage: logs.perPage,
        to,
        total: logs.total,
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
    const sort = currentQuery().get('sort') ?? ''

    return (
        {
            '-causer_id': '-causerName',
            '-created_at': '-createdAt',
            '-subject_id': '-subjectId',
            '-subject_type': '-subjectType',
            causer_id: 'causerName',
            created_at: 'createdAt',
            subject_id: 'subjectId',
            subject_type: 'subjectType',
        }[sort] ?? sort
    )
}

function currentPerPage(): number | undefined {
    const perPage = currentQuery().get('per_page')

    return perPage ? Number(perPage) : undefined
}

function currentFilterValue(key: string): string {
    return currentQuery().get(`filter[${key}]`) ?? ''
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

function clearToolbarFilter(
    key: 'date_from' | 'date_to' | 'event' | 'subject_type' | 'user',
): void {
    if (key === 'date_from') {
        dateFromValue.value = null
    }

    if (key === 'date_to') {
        dateToValue.value = null
    }

    updateFilters({
        [key]: undefined,
    })
}

function updateFilters(overrides: Record<string, string | undefined>): void {
    const filters = {
        date_from: overrideOrCurrent(overrides, 'date_from'),
        date_to: overrideOrCurrent(overrides, 'date_to'),
        event: overrideOrCurrent(overrides, 'event'),
        subject_type: overrideOrCurrent(overrides, 'subject_type'),
        user: overrideOrCurrent(overrides, 'user'),
    }

    router.get(
        logsPage.index.url(),
        {
            columns: currentQuery().get('columns') || undefined,
            filter: filters,
            per_page: currentPerPage(),
            search: currentQuery().get('search') || undefined,
            sort: currentSort() || undefined,
            tab: 'activity',
        },
        {
            only: ['activeTab', 'activityTab'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}

function resetFilters(): void {
    dateFromValue.value = null
    dateToValue.value = null

    updateFilters({
        date_from: undefined,
        date_to: undefined,
        event: undefined,
        subject_type: undefined,
        user: undefined,
    })
}

function onDateChange(
    field: 'date_from' | 'date_to',
    value: Date | Date[] | Array<Date | null> | null | undefined,
): void {
    const date = Array.isArray(value) ? value[0] : value

    updateFilters({
        [field]:
            date instanceof Date ? date.toISOString().slice(0, 10) : undefined,
    })
}

function eventLabel(event: string | null): string {
    if (!event) {
        return '—'
    }

    const translatedEvent = trans(`activity.${event}`)

    return translatedEvent === `activity.${event}` ? event : translatedEvent
}

function eventTone(
    event: string | null,
): 'danger' | 'info' | 'neutral' | 'success' | 'warning' {
    return (
        ({
            created: 'success',
            deleted: 'danger',
            logged_in: 'info',
            restored: 'warning',
            updated: 'info',
        }[event ?? ''] as
            | 'danger'
            | 'info'
            | 'neutral'
            | 'success'
            | 'warning'
            | undefined) ?? 'neutral'
    )
}

function subjectTypeLabel(subjectType: string | null): string {
    if (!subjectType) {
        return '—'
    }

    return (
        subjectTypeLabels.value[subjectType] ??
        subjectType.split('\\').pop() ??
        subjectType
    )
}

async function showDetail(log: ActivityLogRecord): Promise<void> {
    detailLoading.value = true
    detailVisible.value = true

    try {
        const response = await fetch(activity.show.url(log.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) {
            throw new Error(`Failed to load activity ${log.id}`)
        }

        const payload = (await response.json()) as { data: ActivityLogRecord }
        detail.value = payload.data
    } catch {
        detailVisible.value = false
        toast.add({
            detail: trans('activity.details_load_failed'),
            life: 2400,
            severity: 'error',
            summary: trans('common.ui.error'),
        })
    } finally {
        detailLoading.value = false
    }
}
</script>

<template>
    <div class="grid gap-1">
        <div class="grid gap-1 px-5 pt-5">
            <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                {{ trans('activity.labels.activity') }}
            </h2>
            <p class="text-sm text-[var(--cp-text-muted)]">
                {{ trans('activity.description') }}
            </p>
        </div>

        <TableBuilderDataTable
            :empty-message="$t('activity.empty')"
            :only="['activeTab', 'activityTab']"
            :schema="activityTableSchema"
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
                    v-if="
                        props.filters.event ||
                        props.filters.user ||
                        props.filters.subject_type ||
                        props.filters.date_from ||
                        props.filters.date_to
                    "
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        v-if="props.filters.event"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('event')"
                    >
                        <span>
                            {{ $t('activity.filters.event') }}:
                            {{
                                eventLabels[props.filters.event] ??
                                props.filters.event
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.subject_type"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('subject_type')"
                    >
                        <span>
                            {{ $t('activity.filters.subject_type') }}:
                            {{
                                subjectTypeLabels[props.filters.subject_type] ??
                                props.filters.subject_type
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.user"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('user')"
                    >
                        <span>
                            {{ $t('activity.filters.user') }}:
                            {{
                                userLabels[props.filters.user] ??
                                props.filters.user
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.date_from"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('date_from')"
                    >
                        <span>
                            {{ $t('activity.filters.date_from') }}:
                            {{ props.filters.date_from }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.date_to"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('date_to')"
                    >
                        <span>
                            {{ $t('activity.filters.date_to') }}:
                            {{ props.filters.date_to }}
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

            <template #cell-createdAt="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{
                        row.createdAt
                            ? new Date(row.createdAt).toLocaleString()
                            : '—'
                    }}
                </span>
            </template>

            <template #cell-event="{ row }">
                <LogBadge
                    dot
                    :label="eventLabel(String(row.event ?? null))"
                    :tone="eventTone(String(row.event ?? null))"
                />
            </template>

            <template #cell-subjectId="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ row.subjectId ?? '—' }}
                </span>
            </template>

            <template #cell-subjectType="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ row.subjectTypeLabel }}
                </span>
            </template>

            <template #cell-causerName="{ row }">
                <LogUserAvatar
                    :avatar-url="row.causerAvatarUrl ?? null"
                    :label="row.causerName ?? null"
                    :system="row.systemCauser === true"
                    size="sm"
                />
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <Button
                        :aria-label="$t('activity.labels.details')"
                        class="cp-datatable__action-button"
                        outlined
                        severity="secondary"
                        size="small"
                        @click="showDetail(row as ActivityLogRecord)"
                    >
                        <AppIcon name="eye" />
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
                        :model-value="props.filters.user ?? null"
                        :options="props.options.users"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('activity.filters.user')"
                        @update:model-value="
                            updateFilters({
                                user:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                    <Select
                        :model-value="props.filters.event ?? null"
                        :options="props.options.events"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('activity.filters.event')"
                        @update:model-value="
                            updateFilters({
                                event:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                    <Select
                        :model-value="props.filters.subject_type ?? null"
                        :options="props.options.subjectTypes"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="$t('activity.filters.subject_type')"
                        @update:model-value="
                            updateFilters({
                                subject_type:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                    <DatePicker
                        v-model="dateFromValue"
                        show-icon
                        :placeholder="$t('activity.filters.date_from')"
                        @update:model-value="onDateChange('date_from', $event)"
                    />
                    <DatePicker
                        v-model="dateToValue"
                        show-icon
                        :placeholder="$t('activity.filters.date_to')"
                        @update:model-value="onDateChange('date_to', $event)"
                    />
                </div>
            </template>
        </Menu>

        <Dialog
            v-model:visible="detailVisible"
            :header="trans('activity.details_title')"
            modal
            style="width: min(48rem, 92vw)"
        >
            <div v-if="detailLoading" class="grid gap-3">
                <Skeleton height="1.5rem" />
                <Skeleton height="1.5rem" />
                <Skeleton height="12rem" />
            </div>

            <ActivityLogDetail
                v-else-if="detail"
                :data="detail"
                @cancel="detailVisible = false"
            />
        </Dialog>
    </div>
</template>
