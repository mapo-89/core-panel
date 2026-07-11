<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import PrimeColumn from 'primevue/column'
import PrimeDataTable from 'primevue/datatable'
import { useToast } from 'primevue/usetoast'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import LogBadge from '@/pages/Admin/Logs/components/LogBadge.vue'
import logFiles from '@/routes/core-panel/log-files'
import logsPage from '@/routes/core-panel/logs'
import type {
    DataTablePagination,
    LogEntryRecord,
    LogFileRecord,
} from '@/types/core-panel'
import TablePagination from '@core-panel/components/TableBuilder/TablePagination.vue'

type DisplayLogEntryRecord = LogEntryRecord & {
    _entryKey: string
    _levelSort: number
    _timestampSort: number
}

const props = defineProps<{
    file: LogFileRecord
    files: LogFileRecord[]
    initialEntries: LogEntryRecord[]
    initialEof: boolean
    initialNextCursor: number | null
}>()

const LEVEL_OPTIONS = [
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug',
]

const toast = useToast()
const entries = ref<LogEntryRecord[]>(props.initialEntries)
const cursor = ref<number | null>(props.initialNextCursor)
const eof = ref(props.initialEof)
const filterMenu = ref<{ toggle: (event: Event) => void } | null>(null)
const first = ref(0)
const stickyHeadRef = ref<HTMLElement | null>(null)
const stickySentinelRef = ref<HTMLElement | null>(null)
const tableSurfaceRef = ref<HTMLElement | null>(null)
const isStickyToolbar = ref(false)
const loading = ref(false)
const rowsPerPage = ref(10)
const expandedRows = ref<Record<string, boolean>>({})
const sortField = ref<'_levelSort' | '_timestampSort' | ''>('_timestampSort')
const sortOrder = ref<-1 | 1>(-1)
const stickyHeaderColumnWidths = ref<number[]>([])
const filters = ref({
    from: null as Date | null,
    keyword: '',
    levels: [] as string[],
    to: null as Date | null,
})
const tableEntries = computed<DisplayLogEntryRecord[]>(() =>
    entries.value.map((entry, index) => ({
        ...entry,
        _entryKey: buildEntryKey(entry, index),
        _levelSort: levelSortRank(entry.level),
        _timestampSort: entry.timestamp
            ? new Date(entry.timestamp).getTime()
            : 0,
    })),
)
const tableHeaderClass =
    '!border-b !border-[var(--cp-surface-border)] !bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_70%,transparent)] !px-4 !py-[1.1rem] !text-left !text-[0.81rem] !font-[750] !tracking-[0.02em] !text-[var(--cp-text-muted)]'
const tableCellClass =
    '!border-b !border-[color:color-mix(in_srgb,var(--cp-surface-border)_74%,transparent)] !px-4 !py-[0.95rem] !align-middle'
const tableHeaderLabelClass =
    'flex min-h-[1.6rem] items-center leading-[1.25] text-[var(--cp-text-muted)]'
const activeFilterChips = computed<
    Array<{
        key: 'from' | 'keyword' | 'levels' | 'to'
        label: string
        value: string
    }>
>(() => {
    const chips: Array<{
        key: 'from' | 'keyword' | 'levels' | 'to'
        label: string
        value: string
    }> = []

    if (filters.value.keyword.trim() !== '') {
        chips.push({
            key: 'keyword',
            label: trans('page-log-files.filters.keyword'),
            value: filters.value.keyword.trim(),
        })
    }

    if (filters.value.levels.length > 0) {
        chips.push({
            key: 'levels',
            label: trans('page-log-files.filters.level'),
            value: filters.value.levels
                .map((level) => level.toUpperCase())
                .join(', '),
        })
    }

    if (filters.value.from !== null) {
        chips.push({
            key: 'from',
            label: trans('page-log-files.filters.from'),
            value: formatDateTime(filters.value.from.toISOString()),
        })
    }

    if (filters.value.to !== null) {
        chips.push({
            key: 'to',
            label: trans('page-log-files.filters.to'),
            value: formatDateTime(filters.value.to.toISOString()),
        })
    }

    return chips
})
const levelEntryCounts = computed<Record<string, number>>(() =>
    entries.value.reduce<Record<string, number>>((counts, entry) => {
        counts[entry.level] = (counts[entry.level] ?? 0) + 1

        return counts
    }, {}),
)
const quickLevelFilters = computed(() =>
    LEVEL_OPTIONS.map((level) => ({
        active: filters.value.levels.includes(level),
        count: levelEntryCounts.value[level] ?? 0,
        icon: levelIcon(level),
        key: level,
        label: level.toUpperCase(),
    })),
)
const sortedTableEntries = computed<DisplayLogEntryRecord[]>(() => {
    if (sortField.value === '') {
        return tableEntries.value
    }

    const activeSortField: '_levelSort' | '_timestampSort' = sortField.value

    return [...tableEntries.value].sort((left, right) => {
        const leftValue = left[activeSortField]
        const rightValue = right[activeSortField]

        if (leftValue === rightValue) {
            return 0
        }

        return leftValue > rightValue ? sortOrder.value : -sortOrder.value
    })
})
const effectiveTotalRecords = computed(() =>
    eof.value
        ? sortedTableEntries.value.length
        : sortedTableEntries.value.length + rowsPerPage.value,
)
const paginatedTableEntries = computed<DisplayLogEntryRecord[]>(() =>
    sortedTableEntries.value.slice(
        first.value,
        first.value + rowsPerPage.value,
    ),
)
const paginationState = computed<DataTablePagination>(() => {
    const total = effectiveTotalRecords.value
    const page = Math.floor(first.value / rowsPerPage.value) + 1
    const visibleCount =
        paginatedTableEntries.value.length > 0
            ? paginatedTableEntries.value.length
            : Math.max(0, Math.min(rowsPerPage.value, total - first.value))

    return {
        from: total === 0 ? null : first.value + 1,
        lastPage: Math.max(1, Math.ceil(total / rowsPerPage.value)),
        page,
        perPage: rowsPerPage.value,
        to: total === 0 ? null : first.value + visibleCount,
        total,
    }
})
const hasEmbeddedFooter = computed(() => entries.value.length > 0)
const stickyHeaderGridTemplate = computed(() => {
    if (stickyHeaderColumnWidths.value.length === 5) {
        return stickyHeaderColumnWidths.value
            .map((width) => `${Math.max(width, 1)}px`)
            .join(' ')
    }

    return '3rem 10rem 14rem minmax(0,1fr) 5rem'
})

let stickyScrollParent: HTMLElement | null = null
let cleanupStickyScrollListener: (() => void) | null = null
let stickyHeadResizeObserver: ResizeObserver | null = null
let tableSurfaceResizeObserver: ResizeObserver | null = null

function findScrollParent(element: HTMLElement | null): HTMLElement | null {
    let current = element?.parentElement ?? null

    while (current !== null) {
        const styles = window.getComputedStyle(current)
        const overflowY = styles.overflowY

        if (overflowY === 'auto' || overflowY === 'scroll') {
            return current
        }

        current = current.parentElement
    }

    return null
}

function updateStickyToolbarState(): void {
    if (stickySentinelRef.value === null) {
        isStickyToolbar.value = false

        return
    }

    const pageHeaderHeight = Number.parseFloat(
        window
            .getComputedStyle(document.documentElement)
            .getPropertyValue('--cp-page-header-height'),
    )
    const stickyTop = Number.isNaN(pageHeaderHeight) ? 0 : pageHeaderHeight

    isStickyToolbar.value =
        stickySentinelRef.value.getBoundingClientRect().top <= stickyTop
}

function syncStickyHeadMetrics(): void {
    const bodyCells = tableSurfaceRef.value?.querySelectorAll<HTMLElement>(
        '.cp-datatable__table .p-datatable-tbody > tr:first-child > td',
    )

    if (bodyCells === undefined || bodyCells.length === 0) {
        stickyHeaderColumnWidths.value = []

        return
    }

    stickyHeaderColumnWidths.value = Array.from(bodyCells).map(
        (cell) => cell.getBoundingClientRect().width,
    )
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

function levelTone(
    level: string,
): 'danger' | 'info' | 'neutral' | 'success' | 'warning' {
    return (
        ({
            alert: 'danger',
            critical: 'danger',
            debug: 'neutral',
            emergency: 'danger',
            error: 'danger',
            info: 'info',
            notice: 'neutral',
            warning: 'warning',
        }[level] as
            | 'danger'
            | 'info'
            | 'neutral'
            | 'success'
            | 'warning'
            | undefined) ?? 'neutral'
    )
}

function levelBadgeClass(level: string): string {
    return {
        danger: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        neutral:
            'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400',
        success:
            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        warning:
            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    }[levelTone(level)]
}

function levelQuickFilterClass(level: string, active: boolean): string {
    const tone = levelTone(level)

    if (!active) {
        return {
            danger: 'border-red-200/80 bg-red-50/70 text-red-700 hover:border-red-300 hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300',
            info: 'border-blue-200/80 bg-blue-50/70 text-blue-700 hover:border-blue-300 hover:bg-blue-100 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-300',
            neutral:
                'border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] text-[var(--cp-text-muted)] hover:bg-[var(--cp-surface-muted)] dark:bg-[var(--cp-surface-panel)]',
            success:
                'border-green-200/80 bg-green-50/70 text-green-700 hover:border-green-300 hover:bg-green-100 dark:border-green-900/60 dark:bg-green-950/30 dark:text-green-300',
            warning:
                'border-amber-200/80 bg-amber-50/70 text-amber-700 hover:border-amber-300 hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300',
        }[tone]
    }

    return {
        danger: 'border-red-500 bg-red-100 text-red-800 shadow-sm dark:border-red-700 dark:bg-red-900/50 dark:text-red-200',
        info: 'border-blue-500 bg-blue-100 text-blue-800 shadow-sm dark:border-blue-700 dark:bg-blue-900/50 dark:text-blue-200',
        neutral:
            'border-[var(--cp-text-primary)] bg-[var(--cp-surface-muted)] text-[var(--cp-text-primary)] shadow-sm',
        success:
            'border-green-500 bg-green-100 text-green-800 shadow-sm dark:border-green-700 dark:bg-green-900/50 dark:text-green-200',
        warning:
            'border-amber-500 bg-amber-100 text-amber-800 shadow-sm dark:border-amber-700 dark:bg-amber-900/50 dark:text-amber-200',
    }[tone]
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

function levelIcon(level: string): string {
    return (
        {
            alert: 'triangle-alert',
            critical: 'triangle-alert',
            debug: 'code',
            emergency: 'triangle-alert',
            error: 'circle-alert',
            info: 'info',
            notice: 'bell',
            warning: 'triangle-alert',
        }[level] ?? 'info'
    )
}

function levelSortRank(level: string): number {
    return (
        {
            emergency: 0,
            alert: 1,
            critical: 2,
            error: 3,
            warning: 4,
            notice: 5,
            info: 6,
            debug: 7,
        }[level] ?? 99
    )
}

function buildEntryKey(entry: LogEntryRecord, index: number): string {
    return `${entry.timestamp ?? 'raw'}-${entry.level}-${index}`
}

async function fetchEntries(append = false): Promise<void> {
    loading.value = true

    const params = new URLSearchParams()

    if (append && cursor.value !== null) {
        params.set('cursor', String(cursor.value))
    }

    if (filters.value.keyword !== '') {
        params.set('keyword', filters.value.keyword)
    }

    if (filters.value.from) {
        params.set('from', filters.value.from.toISOString())
    }

    if (filters.value.to) {
        params.set('to', filters.value.to.toISOString())
    }

    filters.value.levels.forEach((level) => params.append('levels[]', level))

    try {
        const response = await fetch(
            `${logFiles.entries.url(props.file.name)}?${params.toString()}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        )

        if (!response.ok) {
            throw new Error(`Failed to load log file ${props.file.name}`)
        }

        const payload = (await response.json()) as {
            data: {
                entries: LogEntryRecord[]
                eof: boolean
                next_cursor: number | null
            }
        }

        entries.value = append
            ? [...entries.value, ...payload.data.entries]
            : payload.data.entries
        cursor.value = payload.data.next_cursor
        eof.value = payload.data.eof

        if (!append) {
            expandedRows.value = {}
        }
    } catch {
        toast.add({
            detail: trans('page-log-files.read_failed'),
            life: 3000,
            severity: 'error',
            summary: trans('common.ui.error'),
        })
    } finally {
        loading.value = false
    }
}

function applyFilters(): void {
    first.value = 0
    cursor.value = null
    void fetchEntries(false)
}

function resetFilters(): void {
    filters.value = {
        from: null,
        keyword: '',
        levels: [],
        to: null,
    }
    first.value = 0
    cursor.value = null
    void fetchEntries(false)
}

function openFilterMenu(event: Event): void {
    filterMenu.value?.toggle(event)
}

function clearToolbarFilter(key: 'from' | 'keyword' | 'levels' | 'to'): void {
    if (key === 'keyword') {
        filters.value.keyword = ''
    }

    if (key === 'levels') {
        filters.value.levels = []
    }

    if (key === 'from') {
        filters.value.from = null
    }

    if (key === 'to') {
        filters.value.to = null
    }

    applyFilters()
}

function toggleQuickLevel(level: string): void {
    filters.value.levels = filters.value.levels.includes(level)
        ? filters.value.levels.filter((entry) => entry !== level)
        : [...filters.value.levels, level]

    applyFilters()
}

async function handlePage(event: {
    page: number
    rows: number
}): Promise<void> {
    first.value = event.page * event.rows
    rowsPerPage.value = event.rows

    const requestedRows = first.value + event.rows

    if (
        requestedRows <= tableEntries.value.length ||
        eof.value ||
        loading.value
    ) {
        return
    }

    await fetchEntries(true)
}

function toggleSort(field: '_levelSort' | '_timestampSort'): void {
    if (sortField.value !== field) {
        sortField.value = field
        sortOrder.value = field === '_timestampSort' ? -1 : 1

        return
    }

    sortOrder.value = sortOrder.value === 1 ? -1 : 1
}

function sortIconName(field: '_levelSort' | '_timestampSort'): string {
    if (sortField.value !== field) {
        return 'chevron-down'
    }

    return sortOrder.value === -1 ? 'chevron-down' : 'chevron-up'
}

onMounted(() => {
    stickyScrollParent = findScrollParent(stickySentinelRef.value)

    const onScroll = () => {
        updateStickyToolbarState()
    }

    window.addEventListener('resize', onScroll, { passive: true })

    if (stickyScrollParent !== null) {
        stickyScrollParent.addEventListener('scroll', onScroll, {
            passive: true,
        })
    }

    cleanupStickyScrollListener = () => {
        stickyScrollParent?.removeEventListener('scroll', onScroll)
        window.removeEventListener('resize', onScroll)
    }

    void nextTick(() => {
        updateStickyToolbarState()
        syncStickyHeadMetrics()

        if (stickyHeadRef.value !== null) {
            stickyHeadResizeObserver = new ResizeObserver(() => {
                syncStickyHeadMetrics()
            })

            stickyHeadResizeObserver.observe(stickyHeadRef.value)
        }

        if (tableSurfaceRef.value !== null) {
            tableSurfaceResizeObserver = new ResizeObserver(() => {
                syncStickyHeadMetrics()
            })

            tableSurfaceResizeObserver.observe(tableSurfaceRef.value)
        }
    })
})

onBeforeUnmount(() => {
    cleanupStickyScrollListener?.()
    cleanupStickyScrollListener = null
    stickyScrollParent = null
    stickyHeadResizeObserver?.disconnect()
    stickyHeadResizeObserver = null
    tableSurfaceResizeObserver?.disconnect()
    tableSurfaceResizeObserver = null
})

watch(
    [paginatedTableEntries, rowsPerPage, first, expandedRows],
    async () => {
        await nextTick()
        syncStickyHeadMetrics()
    },
    { deep: true },
)

function goBack(): void {
    router.visit(`${logsPage.index.url()}?tab=logs`)
}

function openFile(file: LogFileRecord): void {
    router.visit(logFiles.show.url(file.name))
}

function buildCopyPayload(entry: LogEntryRecord): string {
    const sections = [
        entry.timestamp ? `Timestamp: ${entry.timestamp}` : null,
        entry.isRaw ? 'Type: raw' : `Level: ${entry.level.toUpperCase()}`,
        `Message: ${entry.message}`,
        entry.context && Object.keys(entry.context).length > 0
            ? `Context:\n${JSON.stringify(entry.context, null, 2)}`
            : null,
        entry.stack ? `Stack:\n${entry.stack}` : null,
    ].filter((section): section is string => section !== null)

    return sections.join('\n\n')
}

async function copyEntry(entry: LogEntryRecord): Promise<void> {
    try {
        await navigator.clipboard.writeText(buildCopyPayload(entry))
        toast.add({
            detail: trans('page-log-files.entry_copied'),
            life: 2000,
            severity: 'success',
            summary: trans('common.ui.copy'),
        })
    } catch {
        toast.add({
            detail: trans('common.ui.error'),
            life: 2000,
            severity: 'error',
            summary: trans('common.ui.copy'),
        })
    }
}
</script>

<template>
    <AppLayout
        :subtitle="trans('page-log-files.detail_description')"
        :title="file.name"
    >
        <Head :title="file.name" />

        <template #page-actions>
            <Button
                :label="trans('page-log-files.actions.back')"
                icon="pi pi-arrow-left"
                outlined
                severity="secondary"
                @click="goBack"
            />
        </template>

        <div class="grid gap-5 xl:grid-cols-[18rem_minmax(0,1fr)]">
            <aside
                class="grid h-fit gap-3 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4 xl:sticky xl:top-[calc(var(--cp-page-header-height)+1rem)]"
            >
                <div class="grid gap-1">
                    <h2
                        class="text-sm font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ trans('page-log-files.files_title') }}
                    </h2>
                    <p class="text-xs text-[var(--cp-text-muted)]">
                        {{ trans('page-log-files.description') }}
                    </p>
                </div>

                <nav class="grid gap-1">
                    <button
                        v-for="logFile in props.files"
                        :key="logFile.name"
                        class="grid gap-1 rounded-[var(--cp-radius-md)] border px-3 py-2 text-left transition"
                        :class="
                            logFile.name === file.name
                                ? 'border-[var(--cp-primary)] bg-[color:color-mix(in_srgb,var(--cp-primary)_10%,transparent)]'
                                : 'border-transparent hover:border-[var(--cp-surface-border)] hover:bg-[var(--cp-surface-muted)]/50'
                        "
                        type="button"
                        @click="openFile(logFile)"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="truncate text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ logFile.name }}
                            </span>
                            <LogBadge
                                v-if="logFile.name === file.name"
                                :label="$t('common.ui.active')"
                                tone="info"
                            />
                        </div>
                        <span class="text-xs text-[var(--cp-text-muted)]">
                            {{ formatDateTime(logFile.modifiedAt) }}
                        </span>
                    </button>
                </nav>
            </aside>

            <div class="grid gap-5">
                <div
                    class="grid gap-3 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <LogBadge
                            :label="
                                $t(
                                    `page-log-files.channels.${file.channelType}`,
                                )
                            "
                            :tone="channelTone(file.channelType)"
                        />
                        <LogBadge
                            v-if="file.isActive"
                            :label="$t('page-log-files.active')"
                            tone="success"
                        />
                    </div>

                    <dl
                        class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4"
                    >
                        <div class="grid gap-1">
                            <dt class="text-[var(--cp-text-muted)]">
                                {{ trans('page-log-files.path') }}
                            </dt>
                            <dd
                                class="break-all font-mono text-xs text-[var(--cp-text-primary)]"
                            >
                                {{ file.path }}
                            </dd>
                        </div>
                        <div class="grid gap-1">
                            <dt class="text-[var(--cp-text-muted)]">
                                {{ trans('page-log-files.size') }}
                            </dt>
                            <dd class="text-[var(--cp-text-primary)]">
                                {{ formatSize(file.sizeBytes) }}
                            </dd>
                        </div>
                        <div class="grid gap-1">
                            <dt class="text-[var(--cp-text-muted)]">
                                {{ trans('page-log-files.modified') }}
                            </dt>
                            <dd class="text-[var(--cp-text-primary)]">
                                {{ formatDateTime(file.modifiedAt) }}
                            </dd>
                        </div>
                        <div class="grid gap-1">
                            <dt class="text-[var(--cp-text-muted)]">
                                {{ trans('page-log-files.name') }}
                            </dt>
                            <dd class="text-[var(--cp-text-primary)]">
                                {{ file.name }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-if="loading && entries.length === 0" class="grid gap-3">
                    <Skeleton height="1.5rem" />
                    <Skeleton height="6rem" />
                    <Skeleton height="6rem" />
                </div>

                <section
                    v-else
                    class="cp-datatable grid gap-0 rounded-[var(--cp-radius-lg)]"
                >
                    <div ref="stickySentinelRef" class="h-px w-full" />
                    <div
                        class="grid gap-3 rounded-t-[var(--cp-radius-lg)] border border-b-0 border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-5 py-5"
                    >
                        <div class="grid gap-1">
                            <h2
                                class="text-lg font-semibold text-[var(--cp-text-primary)]"
                            >
                                {{ trans('page-log-files.entries_title') }}
                            </h2>
                            <p class="text-sm text-[var(--cp-text-muted)]">
                                {{ trans('page-log-files.detail_description') }}
                            </p>
                        </div>
                    </div>

                    <div
                        ref="stickyHeadRef"
                        class="cp-datatable__sticky-head"
                        :class="
                            isStickyToolbar
                                ? 'rounded-t-[var(--cp-radius-lg)]'
                                : '!rounded-t-none'
                        "
                    >
                        <div
                            class="grid gap-3 border-x border-b border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-5 py-5"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-for="quickFilter in quickLevelFilters"
                                    :key="quickFilter.key"
                                    :aria-pressed="quickFilter.active"
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                                        levelQuickFilterClass(
                                            quickFilter.key,
                                            quickFilter.active,
                                        ),
                                    ]"
                                    type="button"
                                    @click="toggleQuickLevel(quickFilter.key)"
                                >
                                    <AppIcon
                                        :name="quickFilter.icon"
                                        class="cp-icon shrink-0"
                                    />
                                    <span>{{ quickFilter.label }}</span>
                                    <span
                                        class="inline-flex min-w-[1.35rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[0.7rem] font-bold leading-none"
                                        :class="
                                            quickFilter.active
                                                ? 'bg-black/10 text-current dark:bg-white/10'
                                                : 'bg-black/6 text-current dark:bg-white/8'
                                        "
                                    >
                                        {{ quickFilter.count }}
                                    </span>
                                </button>
                            </div>

                            <div class="cp-datatable__toolbar">
                                <div class="cp-datatable__search">
                                    <span class="cp-datatable__search-icon">
                                        <AppIcon name="search" />
                                    </span>
                                    <InputText
                                        v-model="filters.keyword"
                                        class="cp-datatable__search-input"
                                        :placeholder="
                                            $t('page-log-files.filters.keyword')
                                        "
                                        @keydown.enter="applyFilters"
                                    />
                                </div>

                                <div class="cp-datatable__toolbar-actions">
                                    <Button
                                        outlined
                                        severity="secondary"
                                        size="small"
                                        class="cp-datatable__toolbar-button"
                                        @click="openFilterMenu"
                                    >
                                        <AppIcon name="filter" />
                                        <span>{{
                                            $t('table-builder.labels.filters')
                                        }}</span>
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="activeFilterChips.length > 0"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <button
                                    v-for="chip in activeFilterChips"
                                    :key="chip.key"
                                    class="inline-flex items-center gap-2 rounded-full border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_60%,transparent)] px-3 py-1.5 text-xs font-medium text-[var(--cp-text-primary)]"
                                    type="button"
                                    @click="clearToolbarFilter(chip.key)"
                                >
                                    <span
                                        >{{ chip.label }}:
                                        {{ chip.value }}</span
                                    >
                                    <AppIcon name="x" />
                                </button>
                                <Button
                                    outlined
                                    severity="secondary"
                                    size="small"
                                    class="cp-datatable__toolbar-button"
                                    @click="resetFilters"
                                >
                                    {{
                                        $t(
                                            'table-builder.actions.reset_filters',
                                        )
                                    }}
                                </Button>
                            </div>
                        </div>

                        <div
                            class="cp-datatable__sticky-header-row !mt-0 border-x border-[var(--cp-surface-border)]"
                            :style="{
                                gridTemplateColumns: stickyHeaderGridTemplate,
                            }"
                        >
                            <div
                                class="cp-datatable__sticky-header-cell cp-datatable__sticky-header-cell--selection"
                            />

                            <button
                                class="cp-datatable__sticky-header-cell cp-datatable__sticky-header-cell--sortable"
                                type="button"
                                @click="toggleSort('_levelSort')"
                            >
                                <span class="cp-datatable__sticky-header-label">
                                    {{ trans('page-log-files.filters.level') }}
                                </span>
                                <AppIcon
                                    :name="sortIconName('_levelSort')"
                                    class="cp-datatable__sticky-header-icon"
                                    :class="{
                                        'opacity-45':
                                            sortField !== '_levelSort',
                                    }"
                                />
                            </button>

                            <button
                                class="cp-datatable__sticky-header-cell cp-datatable__sticky-header-cell--sortable"
                                type="button"
                                @click="toggleSort('_timestampSort')"
                            >
                                <span class="cp-datatable__sticky-header-label">
                                    {{ trans('page-log-files.modified') }}
                                </span>
                                <AppIcon
                                    :name="sortIconName('_timestampSort')"
                                    class="cp-datatable__sticky-header-icon"
                                    :class="{
                                        'opacity-45':
                                            sortField !== '_timestampSort',
                                    }"
                                />
                            </button>

                            <div class="cp-datatable__sticky-header-cell">
                                <span class="cp-datatable__sticky-header-label">
                                    {{ trans('page-log-files.message') }}
                                </span>
                            </div>

                            <div
                                class="cp-datatable__sticky-header-cell cp-datatable__sticky-header-cell--actions"
                            >
                                {{ $t('common.ui.actions') }}
                            </div>
                        </div>
                    </div>

                    <div
                        ref="tableSurfaceRef"
                        class="cp-card cp-datatable__surface !border-x !border-b !border-[var(--cp-surface-border)]"
                        :class="{
                            'cp-log-file-table-surface--with-footer':
                                hasEmbeddedFooter,
                        }"
                    >
                        <div
                            v-if="entries.length === 0"
                            class="px-6 py-10 text-center text-sm text-[var(--cp-text-muted)]"
                        >
                            {{ trans('page-log-files.entries_empty') }}
                        </div>

                        <PrimeDataTable
                            v-else
                            v-model:expanded-rows="expandedRows"
                            :row-hover="true"
                            :value="paginatedTableEntries"
                            class="cp-datatable__table cp-datatable__table--sticky-head w-full"
                            data-key="_entryKey"
                            removable-sort
                            striped-rows
                            table-style="min-width: 100%"
                            scrollable
                        >
                            <PrimeColumn
                                expander
                                header-style="width: 3rem"
                                :header-class="tableHeaderClass"
                                :body-class="tableCellClass"
                            />

                            <PrimeColumn
                                field="level"
                                header-style="width: 10rem"
                                :header-class="tableHeaderClass"
                                :body-class="tableCellClass"
                            >
                                <template #header>
                                    <span
                                        :class="[
                                            'cp-datatable__sticky-header-label',
                                            tableHeaderLabelClass,
                                        ]"
                                    >
                                        {{
                                            trans(
                                                'page-log-files.filters.level',
                                            )
                                        }}
                                    </span>
                                </template>
                                <template #body="{ data }">
                                    <span
                                        class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            levelBadgeClass(String(data.level))
                                        "
                                    >
                                        <AppIcon
                                            :name="
                                                levelIcon(String(data.level))
                                            "
                                            class="cp-icon shrink-0"
                                        />
                                        <span>
                                            {{
                                                data.isRaw
                                                    ? trans(
                                                          'page-log-files.raw',
                                                      )
                                                    : String(
                                                          data.level,
                                                      ).toUpperCase()
                                            }}
                                        </span>
                                    </span>
                                </template>
                            </PrimeColumn>

                            <PrimeColumn
                                field="timestamp"
                                header-style="width: 14rem"
                                :header-class="tableHeaderClass"
                                :body-class="tableCellClass"
                            >
                                <template #header>
                                    <span
                                        :class="[
                                            'cp-datatable__sticky-header-label',
                                            tableHeaderLabelClass,
                                        ]"
                                    >
                                        {{ trans('page-log-files.modified') }}
                                    </span>
                                </template>
                                <template #body="{ data }">
                                    <span
                                        class="text-sm text-[var(--cp-text-primary)]"
                                    >
                                        {{
                                            formatDateTime(
                                                data.timestamp ?? null,
                                            )
                                        }}
                                    </span>
                                </template>
                            </PrimeColumn>

                            <PrimeColumn
                                field="message"
                                :header-class="tableHeaderClass"
                                :body-class="tableCellClass"
                            >
                                <template #header>
                                    <span
                                        :class="[
                                            'cp-datatable__sticky-header-label',
                                            tableHeaderLabelClass,
                                        ]"
                                    >
                                        {{ trans('page-log-files.message') }}
                                    </span>
                                </template>
                                <template #body="{ data }">
                                    <div class="grid gap-1 py-1">
                                        <span
                                            class="line-clamp-2 text-sm text-[var(--cp-text-primary)]"
                                        >
                                            {{ data.message }}
                                        </span>
                                        <span
                                            v-if="data.isRaw"
                                            class="text-xs text-[var(--cp-text-muted)]"
                                        >
                                            {{ trans('page-log-files.raw') }}
                                        </span>
                                    </div>
                                </template>
                            </PrimeColumn>

                            <PrimeColumn
                                header-style="width: 5rem"
                                :header-class="`${tableHeaderClass} cp-datatable__actions-header`"
                                :body-class="`${tableCellClass} !text-right`"
                            >
                                <template #header>
                                    <div
                                        class="flex w-full items-center justify-end"
                                    >
                                        <span
                                            :class="[
                                                'cp-datatable__sticky-header-label',
                                                tableHeaderLabelClass,
                                            ]"
                                        >
                                            {{ $t('common.ui.actions') }}
                                        </span>
                                    </div>
                                </template>
                                <template #body="{ data }">
                                    <div class="flex items-center justify-end">
                                        <Button
                                            :aria-label="
                                                trans(
                                                    'page-log-files.actions.copy_entry',
                                                )
                                            "
                                            class="cp-datatable__action-button"
                                            outlined
                                            severity="secondary"
                                            size="small"
                                            type="button"
                                            @click.stop="void copyEntry(data)"
                                        >
                                            <AppIcon name="copy" />
                                        </Button>
                                    </div>
                                </template>
                            </PrimeColumn>

                            <template #expansion="{ data }">
                                <div
                                    class="grid gap-4 border-t border-[var(--cp-surface-border)] px-5 py-4"
                                >
                                    <div class="grid gap-2">
                                        <h3
                                            class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                        >
                                            {{
                                                trans('page-log-files.message')
                                            }}
                                        </h3>
                                        <pre
                                            class="overflow-auto rounded-[var(--cp-radius-md)] bg-[var(--cp-surface-muted)] p-3 text-xs whitespace-pre-wrap break-words text-[var(--cp-text-primary)]"
                                            >{{ data.message }}</pre
                                        >
                                    </div>

                                    <div
                                        v-if="
                                            data.context &&
                                            Object.keys(data.context).length > 0
                                        "
                                        class="grid gap-2"
                                    >
                                        <h3
                                            class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                        >
                                            {{
                                                trans('page-log-files.context')
                                            }}
                                        </h3>
                                        <pre
                                            class="overflow-auto rounded-[var(--cp-radius-md)] bg-[var(--cp-surface-muted)] p-3 text-xs text-[var(--cp-text-primary)]"
                                            >{{
                                                JSON.stringify(
                                                    data.context,
                                                    null,
                                                    2,
                                                )
                                            }}</pre
                                        >
                                    </div>

                                    <div v-if="data.stack" class="grid gap-2">
                                        <h3
                                            class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                        >
                                            {{
                                                trans(
                                                    'page-log-files.stacktrace',
                                                )
                                            }}
                                        </h3>
                                        <pre
                                            class="overflow-auto rounded-[var(--cp-radius-md)] bg-[var(--cp-surface-muted)] p-3 text-xs text-[var(--cp-text-primary)]"
                                            >{{ data.stack }}</pre
                                        >
                                    </div>
                                </div>
                            </template>
                        </PrimeDataTable>

                        <div
                            v-if="entries.length > 0 && !eof"
                            class="flex justify-center border-t border-[var(--cp-surface-border)] px-4 py-3"
                        >
                            <Button
                                :label="
                                    trans('page-log-files.actions.load_more')
                                "
                                icon="pi pi-angle-down"
                                :loading="loading"
                                outlined
                                severity="secondary"
                                @click="fetchEntries(true)"
                            />
                        </div>

                        <TablePagination
                            v-if="entries.length > 0"
                            class="px-4 py-3"
                            :pagination="paginationState"
                            @page="void handlePage($event)"
                        />
                    </div>
                    <Menu
                        ref="filterMenu"
                        popup
                        :model="[]"
                        class="cp-users-tab__filter-menu"
                    >
                        <template #start>
                            <div class="cp-users-tab__filter-content">
                                <MultiSelect
                                    v-model="filters.levels"
                                    :options="LEVEL_OPTIONS"
                                    :placeholder="
                                        trans('page-log-files.all_levels')
                                    "
                                />
                                <DatePicker v-model="filters.from" show-time />
                                <DatePicker v-model="filters.to" show-time />
                                <div class="flex gap-2">
                                    <Button
                                        :label="
                                            trans(
                                                'page-log-files.actions.apply',
                                            )
                                        "
                                        :loading="loading"
                                        size="small"
                                        @click="applyFilters"
                                    />
                                    <Button
                                        :label="
                                            trans(
                                                'page-log-files.actions.reset',
                                            )
                                        "
                                        outlined
                                        severity="secondary"
                                        size="small"
                                        @click="resetFilters"
                                    />
                                </div>
                            </div>
                        </template>
                    </Menu>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.cp-log-file-table-surface--with-footer :deep(.cp-datatable-pagination) {
    border-top: 0;
}

.cp-log-file-table-surface--with-footer
    :deep(.p-datatable-tbody > tr:last-child > td) {
    border-bottom: 0;
}
</style>
