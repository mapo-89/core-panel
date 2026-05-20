<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { useToast } from 'primevue/usetoast'

import AppLayout from '@/layouts/AppLayout.vue'
import LogBadge from '@/pages/Admin/Logs/components/LogBadge.vue'
import logFiles from '@/routes/core-panel/log-files'
import logsPage from '@/routes/core-panel/logs'
import type { LogEntryRecord, LogFileRecord } from '@/types/core-panel'

const props = defineProps<{
    file: LogFileRecord
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
const loading = ref(false)
const expandedEntries = ref<number[]>([])
const filters = ref({
    from: null as Date | null,
    keyword: '',
    levels: [] as string[],
    to: null as Date | null,
})

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

function isExpanded(index: number): boolean {
    return expandedEntries.value.includes(index)
}

function toggleEntry(index: number): void {
    expandedEntries.value = isExpanded(index)
        ? expandedEntries.value.filter((value) => value !== index)
        : [...expandedEntries.value, index]
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
            expandedEntries.value = []
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
    cursor.value = null
    void fetchEntries(false)
}

function goBack(): void {
    router.visit(`${logsPage.index.url()}?tab=logs`)
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

        <div class="grid gap-5">
            <div
                class="grid gap-3 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <LogBadge
                        :label="
                            $t(`page-log-files.channels.${file.channelType}`)
                        "
                        :tone="channelTone(file.channelType)"
                    />
                    <LogBadge
                        v-if="file.isActive"
                        :label="$t('page-log-files.active')"
                        tone="success"
                    />
                </div>

                <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
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

            <div
                class="grid gap-4 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5 md:grid-cols-4"
            >
                <label class="grid gap-2">
                    <span
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{ trans('page-log-files.filters.level') }}
                    </span>
                    <MultiSelect
                        v-model="filters.levels"
                        :options="LEVEL_OPTIONS"
                        :placeholder="trans('page-log-files.all_levels')"
                    />
                </label>

                <label class="grid gap-2">
                    <span
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{ trans('page-log-files.filters.from') }}
                    </span>
                    <DatePicker v-model="filters.from" show-time />
                </label>

                <label class="grid gap-2">
                    <span
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{ trans('page-log-files.filters.to') }}
                    </span>
                    <DatePicker v-model="filters.to" show-time />
                </label>

                <label class="grid gap-2">
                    <span
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{ trans('page-log-files.filters.keyword') }}
                    </span>
                    <InputText v-model="filters.keyword" />
                </label>

                <div class="flex gap-2 md:col-span-4">
                    <Button
                        :label="trans('page-log-files.actions.apply')"
                        icon="pi pi-search"
                        :loading="loading"
                        @click="applyFilters"
                    />
                    <Button
                        :label="trans('page-log-files.actions.reset')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        @click="resetFilters"
                    />
                </div>
            </div>

            <div v-if="loading && entries.length === 0" class="grid gap-3">
                <Skeleton height="1.5rem" />
                <Skeleton height="6rem" />
                <Skeleton height="6rem" />
            </div>

            <div v-else class="grid gap-3">
                <div
                    v-if="entries.length === 0"
                    class="rounded-[var(--cp-radius-lg)] border border-dashed border-[var(--cp-surface-border)] px-6 py-10 text-center text-sm text-[var(--cp-text-muted)]"
                >
                    {{ trans('page-log-files.entries_empty') }}
                </div>

                <template v-else>
                    <article
                        v-for="(entry, index) in entries"
                        :key="`${entry.timestamp ?? 'raw'}-${index}`"
                        class="overflow-hidden rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)]"
                    >
                        <button
                            class="flex w-full flex-col gap-3 px-5 py-4 text-left transition hover:bg-[var(--cp-surface-muted)]/40 md:flex-row md:items-start md:justify-between"
                            type="button"
                            @click="toggleEntry(index)"
                        >
                            <div class="grid gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <LogBadge
                                        v-if="!entry.isRaw"
                                        :label="entry.level.toUpperCase()"
                                        :tone="levelTone(entry.level)"
                                    />
                                    <span
                                        class="text-xs text-[var(--cp-text-muted)]"
                                    >
                                        {{ formatDateTime(entry.timestamp) }}
                                    </span>
                                </div>
                                <p
                                    class="text-sm text-[var(--cp-text-primary)]"
                                >
                                    {{ entry.message }}
                                </p>
                            </div>

                            <i
                                class="pi text-sm text-[var(--cp-text-muted)]"
                                :class="
                                    isExpanded(index)
                                        ? 'pi-chevron-up'
                                        : 'pi-chevron-down'
                                "
                            />
                        </button>

                        <div
                            v-if="isExpanded(index)"
                            class="grid gap-4 border-t border-[var(--cp-surface-border)] px-5 py-4"
                        >
                            <div
                                v-if="
                                    entry.context &&
                                    Object.keys(entry.context).length > 0
                                "
                                class="grid gap-2"
                            >
                                <h3
                                    class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                >
                                    {{ trans('page-log-files.context') }}
                                </h3>
                                <pre
                                    class="overflow-auto rounded-[var(--cp-radius-md)] bg-[var(--cp-surface-muted)] p-3 text-xs text-[var(--cp-text-primary)]"
                                    >{{
                                        JSON.stringify(entry.context, null, 2)
                                    }}</pre
                                >
                            </div>

                            <div v-if="entry.stack" class="grid gap-2">
                                <h3
                                    class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                >
                                    {{ trans('page-log-files.stacktrace') }}
                                </h3>
                                <pre
                                    class="overflow-auto rounded-[var(--cp-radius-md)] bg-[var(--cp-surface-muted)] p-3 text-xs text-[var(--cp-text-primary)]"
                                    >{{ entry.stack }}</pre
                                >
                            </div>
                        </div>
                    </article>
                </template>

                <div
                    v-if="entries.length > 0 && !eof"
                    class="flex justify-center pt-2"
                >
                    <Button
                        :label="trans('page-log-files.actions.load_more')"
                        icon="pi pi-angle-down"
                        :loading="loading"
                        outlined
                        severity="secondary"
                        @click="fetchEntries(true)"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
