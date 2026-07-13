<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { useToast } from 'primevue/usetoast'

import AppIcon from '@core-panel/components/AppIcon.vue'
import {
    formatAuthenticationDeviceLabel,
    formatAuthenticationMethodLabel,
    formatAuthenticationResultLabel,
    resolveAuthenticationResultTone,
} from '@core-panel/pages/Admin/Logs/components/authenticationLogPresentation'
import AuthenticationLogDetail from '@core-panel/pages/Admin/Logs/components/AuthenticationLogDetail.vue'
import LogBadge from '@core-panel/pages/Admin/Logs/components/LogBadge.vue'
import LogUserAvatar from '@core-panel/pages/Admin/Logs/components/LogUserAvatar.vue'
import authenticationLogs from '@/routes/core-panel/authentication-logs'
import logsPage from '@/routes/core-panel/logs'
import type {
    AuthenticationLogRecord,
    DataTablePagination,
    DataTableSchema,
} from '@core-panel/types/core-panel'
import ColumnVisibilityDropdown from '@core-panel/components/TableBuilder/ColumnVisibilityDropdown.vue'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'

const props = defineProps<{
    filters: {
        date_from: string | null
        date_to: string | null
        guard: string | null
        result: string | null
        search: string
        user: string | null
    }
    logs: {
        currentPage: number
        data: AuthenticationLogRecord[]
        lastPage: number
        perPage: number
        total: number
    }
    options: {
        guards: Array<{ label: string; value: string }>
        results: Array<{ label: string; value: string }>
        users: Array<{ label: string; value: string }>
    }
}>()

const toast = useToast()
const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref<AuthenticationLogRecord | null>(null)
const filterMenu = ref<{ toggle: (event: Event) => void } | null>(null)
const dateFromValue = ref<Date | null>(
    props.filters.date_from ? new Date(props.filters.date_from) : null,
)
const dateToValue = ref<Date | null>(
    props.filters.date_to ? new Date(props.filters.date_to) : null,
)
const defaultColumns = [
    'loginSuccessful',
    'authMethodLabel',
    'userLabel',
    'deviceLabel',
    'ipAddress',
    'guard',
    'loginAt',
    'logoutAt',
]

const guardLabels = computed(() =>
    Object.fromEntries(
        props.options.guards.map((option) => [option.value, option.label]),
    ),
)
const resultLabels = computed(() =>
    Object.fromEntries(
        props.options.results.map((option) => [option.value, option.label]),
    ),
)
const userLabels = computed(() =>
    Object.fromEntries(
        props.options.users.map((option) => [option.value, option.label]),
    ),
)

const authenticationTableSchema = computed<DataTableSchema>(() => ({
    actions: [],
    bulkActions: [],
    columns: [
        {
            key: 'loginSuccessful',
            label: null,
            meta: { labelKey: 'page-authentication-logs.columns.result' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'badge',
            visible: true,
        },
        {
            key: 'userLabel',
            label: null,
            meta: { labelKey: 'page-authentication-logs.columns.user' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'authMethodLabel',
            label: null,
            meta: { labelKey: 'page-authentication-logs.columns.method' },
            searchable: false,
            sortable: false,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'deviceLabel',
            label: null,
            meta: { labelKey: 'page-authentication-logs.columns.device' },
            searchable: false,
            sortable: false,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'ipAddress',
            label: null,
            meta: { labelKey: 'page-authentication-logs.columns.ip_address' },
            searchable: false,
            sortable: false,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'guard',
            label: null,
            meta: { labelKey: 'page-authentication-logs.columns.guard' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'loginAt',
            label: null,
            meta: { labelKey: 'page-authentication-logs.columns.login_at' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'logoutAt',
            label: null,
            meta: { labelKey: 'page-authentication-logs.columns.logout_at' },
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
        authMethodLabel: formatAuthenticationMethodLabel(log),
        deviceLabel: formatAuthenticationDeviceLabel(log),
        resultLabel: formatAuthenticationResultLabel(log),
        userLabel: resolveUserLabel(log),
    })),
    state: {
        filters: {
            date_from: props.filters.date_from ?? '',
            date_to: props.filters.date_to ?? '',
            guard: props.filters.guard ?? '',
            result: props.filters.result ?? '',
            user: props.filters.user ?? '',
        },
        search: props.filters.search ?? '',
        sort: currentSort(),
        visibleColumns: currentColumns(defaultColumns),
    },
}))

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString()
}

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
            '-guard': '-guard',
            '-login_at': '-loginAt',
            '-login_successful': '-loginSuccessful',
            '-logout_at': '-logoutAt',
            '-user_id': '-userLabel',
            guard: 'guard',
            login_at: 'loginAt',
            login_successful: 'loginSuccessful',
            logout_at: 'logoutAt',
            user_id: 'userLabel',
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
    key: 'date_from' | 'date_to' | 'guard' | 'result' | 'user',
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
        guard: overrideOrCurrent(overrides, 'guard'),
        result: overrideOrCurrent(overrides, 'result'),
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
            tab: 'authentication',
        },
        {
            only: ['activeTab', 'authenticationTab'],
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
        guard: undefined,
        result: undefined,
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

function resolveUserLabel(log: AuthenticationLogRecord): string {
    return log.userName ?? log.userEmail ?? log.login ?? '—'
}

async function showDetail(log: AuthenticationLogRecord): Promise<void> {
    detailLoading.value = true
    detailVisible.value = true

    try {
        const response = await fetch(authenticationLogs.show.url(log.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) {
            throw new Error(`Failed to load authentication log ${log.id}`)
        }

        const payload = (await response.json()) as {
            data: AuthenticationLogRecord
        }

        detail.value = payload.data
    } catch {
        detailVisible.value = false
        toast.add({
            detail: trans('page-authentication-logs.details_load_failed'),
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
                {{ trans('page-authentication-logs.title') }}
            </h2>
            <p class="text-sm text-[var(--cp-text-muted)]">
                {{ trans('page-authentication-logs.description') }}
            </p>
        </div>

        <TableBuilderDataTable
            :empty-message="$t('page-authentication-logs.empty')"
            :only="['activeTab', 'authenticationTab']"
            :schema="authenticationTableSchema"
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
                        props.filters.user ||
                        props.filters.guard ||
                        props.filters.result ||
                        props.filters.date_from ||
                        props.filters.date_to
                    "
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        v-if="props.filters.user"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('user')"
                    >
                        <span>
                            {{ $t('page-authentication-logs.filters.user') }}:
                            {{
                                userLabels[props.filters.user] ??
                                props.filters.user
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.guard"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('guard')"
                    >
                        <span>
                            {{ $t('page-authentication-logs.filters.guard') }}:
                            {{
                                guardLabels[props.filters.guard] ??
                                props.filters.guard
                            }}
                        </span>
                        <AppIcon name="x" />
                    </button>
                    <button
                        v-if="props.filters.result"
                        class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                        type="button"
                        @click="clearToolbarFilter('result')"
                    >
                        <span>
                            {{ $t('page-authentication-logs.filters.result') }}:
                            {{
                                resultLabels[props.filters.result] ??
                                props.filters.result
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
                            {{
                                $t(
                                    'page-authentication-logs.filters.date_from',
                                )
                            }}:
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
                            {{
                                $t('page-authentication-logs.filters.date_to')
                            }}:
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

            <template #cell-loginSuccessful="{ row }">
                <LogBadge
                    dot
                    :label="row.resultLabel"
                    :tone="
                        resolveAuthenticationResultTone(
                            row as AuthenticationLogRecord,
                        )
                    "
                />
            </template>

            <template #cell-userLabel="{ row }">
                <LogUserAvatar
                    :avatar-url="row.userAvatarUrl ?? null"
                    :label="row.userLabel"
                    size="sm"
                />
            </template>

            <template #cell-authMethodLabel="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ row.authMethodLabel }}
                </span>
            </template>

            <template #cell-deviceLabel="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ row.deviceLabel }}
                </span>
            </template>

            <template #cell-ipAddress="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ row.ipAddress ?? '—' }}
                </span>
            </template>

            <template #cell-guard="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ row.guard ?? '—' }}
                </span>
            </template>

            <template #cell-loginAt="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ formatDateTime(row.loginAt ?? null) }}
                </span>
            </template>

            <template #cell-logoutAt="{ row }">
                <span class="text-sm text-[var(--cp-text-primary)]">
                    {{ formatDateTime(row.logoutAt ?? null) }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                    <Button
                        :aria-label="$t('common.ui.view')"
                        class="cp-datatable__action-button"
                        outlined
                        severity="secondary"
                        size="small"
                        @click="showDetail(row as AuthenticationLogRecord)"
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
                        :placeholder="
                            $t('page-authentication-logs.filters.user')
                        "
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
                        :model-value="props.filters.guard ?? null"
                        :options="props.options.guards"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="
                            $t('page-authentication-logs.filters.guard')
                        "
                        @update:model-value="
                            updateFilters({
                                guard:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                    <Select
                        :model-value="props.filters.result ?? null"
                        :options="props.options.results"
                        option-label="label"
                        option-value="value"
                        show-clear
                        :placeholder="
                            $t('page-authentication-logs.filters.result')
                        "
                        @update:model-value="
                            updateFilters({
                                result:
                                    typeof $event === 'string'
                                        ? $event
                                        : undefined,
                            })
                        "
                    />
                    <DatePicker
                        v-model="dateFromValue"
                        show-icon
                        :placeholder="
                            $t('page-authentication-logs.filters.date_from')
                        "
                        @update:model-value="onDateChange('date_from', $event)"
                    />
                    <DatePicker
                        v-model="dateToValue"
                        show-icon
                        :placeholder="
                            $t('page-authentication-logs.filters.date_to')
                        "
                        @update:model-value="onDateChange('date_to', $event)"
                    />
                </div>
            </template>
        </Menu>

        <Dialog
            v-model:visible="detailVisible"
            :header="trans('page-authentication-logs.detail_title')"
            modal
            style="width: min(42rem, 92vw)"
        >
            <div v-if="detailLoading" class="grid gap-3">
                <Skeleton height="1.5rem" />
                <Skeleton height="1.5rem" />
                <Skeleton height="8rem" />
            </div>

            <AuthenticationLogDetail
                v-else-if="detail"
                :data="detail"
                @cancel="detailVisible = false"
            />
        </Dialog>
    </div>
</template>
