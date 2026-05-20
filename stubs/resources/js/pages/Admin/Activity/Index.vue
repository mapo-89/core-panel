<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

import { useToast } from 'primevue/usetoast'

import activity from '@/routes/core-panel/activity'
import AppLayout from '@/layouts/AppLayout.vue'
import type { ActivityLogRecord } from '@/types/core-panel'

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
const loading = ref(false)
const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref<ActivityLogRecord | null>(null)
const debounceHandle = ref<number | null>(null)
const dateFromValue = ref<Date | null>(
    props.filters.date_from ? new Date(props.filters.date_from) : null,
)
const dateToValue = ref<Date | null>(
    props.filters.date_to ? new Date(props.filters.date_to) : null,
)

const filters = ref({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    event: props.filters.event ?? '',
    search: props.filters.search ?? '',
    subject_type: props.filters.subject_type ?? '',
    user: props.filters.user ?? '',
})

const pageReport = computed(() => {
    if (props.logs.total === 0) {
        return '0 / 0'
    }

    const from = (props.logs.currentPage - 1) * props.logs.perPage + 1
    const to = Math.min(
        props.logs.currentPage * props.logs.perPage,
        props.logs.total,
    )

    return `${from}-${to} / ${props.logs.total}`
})

function applyFilters(page = 1): void {
    loading.value = true

    router.get(
        activity.index.url(),
        {
            date_from: filters.value.date_from || undefined,
            date_to: filters.value.date_to || undefined,
            event: filters.value.event || undefined,
            page,
            search: filters.value.search || undefined,
            subject_type: filters.value.subject_type || undefined,
            user: filters.value.user || undefined,
        },
        {
            only: ['filters', 'logs', 'options'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                loading.value = false
            },
        },
    )
}

function onDateChange(
    field: 'date_from' | 'date_to',
    value: Date | Date[] | Array<Date | null> | null | undefined,
): void {
    const date = Array.isArray(value) ? value[0] : value
    filters.value[field] =
        date instanceof Date ? date.toISOString().slice(0, 10) : ''
    applyFilters()
}

function onSearchInput(): void {
    if (debounceHandle.value !== null) {
        window.clearTimeout(debounceHandle.value)
    }

    debounceHandle.value = window.setTimeout(() => applyFilters(), 250)
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
            severity: 'error',
            summary: trans('common.ui.error'),
            detail: trans('activity.details_load_failed'),
            life: 2400,
        })
    } finally {
        detailLoading.value = false
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="trans('activity.labels.activity')" />

        <div class="grid gap-6 px-4 py-8">
            <div class="grid gap-2">
                <h1
                    class="text-2xl font-semibold text-[var(--cp-text-primary)]"
                >
                    {{ trans('activity.labels.activity') }}
                </h1>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ trans('activity.description') }}
                </p>
            </div>

            <section class="cp-card grid gap-4">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <label class="grid gap-2">
                        <span
                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >{{ trans('common.ui.search') }}</span
                        >
                        <InputText
                            v-model="filters.search"
                            :placeholder="trans('activity.filters.search')"
                            @input="onSearchInput"
                        />
                    </label>

                    <label class="grid gap-2">
                        <span
                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >{{ trans('activity.filters.user') }}</span
                        >
                        <Select
                            v-model="filters.user"
                            :options="props.options.users"
                            option-label="label"
                            option-value="value"
                            show-clear
                            @change="applyFilters()"
                        />
                    </label>

                    <label class="grid gap-2">
                        <span
                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >{{ trans('activity.filters.event') }}</span
                        >
                        <Select
                            v-model="filters.event"
                            :options="props.options.events"
                            option-label="label"
                            option-value="value"
                            show-clear
                            @change="applyFilters()"
                        />
                    </label>

                    <label class="grid gap-2">
                        <span
                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >{{ trans('activity.filters.subject_type') }}</span
                        >
                        <Select
                            v-model="filters.subject_type"
                            :options="props.options.subjectTypes"
                            option-label="label"
                            option-value="value"
                            show-clear
                            @change="applyFilters()"
                        />
                    </label>

                    <label class="grid gap-2">
                        <span
                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >{{ trans('activity.filters.date_from') }}</span
                        >
                        <DatePicker
                            v-model="dateFromValue"
                            date-format="yy-mm-dd"
                            show-icon
                            @update:model-value="
                                onDateChange('date_from', $event)
                            "
                        />
                    </label>

                    <label class="grid gap-2">
                        <span
                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >{{ trans('activity.filters.date_to') }}</span
                        >
                        <DatePicker
                            v-model="dateToValue"
                            date-format="yy-mm-dd"
                            show-icon
                            @update:model-value="
                                onDateChange('date_to', $event)
                            "
                        />
                    </label>
                </div>
            </section>

            <section class="cp-card overflow-hidden">
                <DataTable
                    :loading="loading"
                    :value="props.logs.data"
                    data-key="id"
                    table-style="min-width: 100%"
                >
                    <template #empty>
                        <div
                            class="px-4 py-8 text-sm text-[var(--cp-text-muted)]"
                        >
                            {{ trans('activity.empty') }}
                        </div>
                    </template>

                    <Column
                        field="createdAt"
                        :header="trans('activity.columns.created_at')"
                    />
                    <Column
                        field="event"
                        :header="trans('activity.filters.event')"
                    >
                        <template #body="{ data }">
                            <Tag
                                :value="data.event ?? 'event'"
                                severity="secondary"
                            />
                        </template>
                    </Column>
                    <Column
                        field="description"
                        :header="trans('activity.columns.description')"
                    />
                    <Column
                        field="subjectLabel"
                        :header="trans('activity.filters.subject_type')"
                    >
                        <template #body="{ data }">
                            <div class="grid gap-0.5">
                                <span
                                    class="text-sm text-[var(--cp-text-primary)]"
                                    >{{
                                        data.subjectLabel ??
                                        data.subjectId ??
                                        '—'
                                    }}</span
                                >
                                <span
                                    class="text-xs text-[var(--cp-text-muted)]"
                                    >{{ data.subjectType ?? '—' }}</span
                                >
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="causerName"
                        :header="trans('activity.filters.user')"
                    />
                    <Column :header="trans('activity.labels.details')">
                        <template #body="{ data }">
                            <Button
                                :label="trans('common.ui.view')"
                                severity="secondary"
                                text
                                @click="showDetail(data)"
                            />
                        </template>
                    </Column>
                </DataTable>

                <div
                    class="flex items-center justify-between gap-4 border-t border-[var(--cp-surface-border)] px-4 py-3"
                >
                    <span class="text-sm text-[var(--cp-text-muted)]">{{
                        pageReport
                    }}</span>
                    <Paginator
                        :first="
                            (props.logs.currentPage - 1) * props.logs.perPage
                        "
                        :rows="props.logs.perPage"
                        :total-records="props.logs.total"
                        @page="applyFilters(($event.page ?? 0) + 1)"
                    />
                </div>
            </section>
        </div>

        <Drawer
            v-model:visible="detailVisible"
            :header="trans('activity.details_title')"
            position="right"
            class="w-full max-w-2xl"
        >
            <div v-if="detailLoading" class="grid gap-3">
                <Skeleton height="2rem" />
                <Skeleton height="10rem" />
                <Skeleton height="10rem" />
            </div>

            <div v-else-if="detail" class="grid gap-6">
                <div class="grid gap-2">
                    <div class="flex flex-wrap gap-2">
                        <Tag
                            :value="detail.event ?? 'event'"
                            severity="secondary"
                        />
                    </div>
                    <h2
                        class="text-lg font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ detail.description }}
                    </h2>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ detail.createdAt }} ·
                        {{
                            detail.causerName ??
                            detail.causerId ??
                            trans('activity.system')
                        }}
                    </p>
                </div>

                <div class="grid gap-4">
                    <section
                        class="grid gap-2 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] p-4"
                    >
                        <strong
                            class="text-sm font-semibold text-[var(--cp-text-primary)]"
                            >{{ trans('activity.labels.properties') }}</strong
                        >
                        <pre
                            class="overflow-x-auto text-xs text-[var(--cp-text-muted)]"
                            >{{
                                JSON.stringify(detail.properties, null, 2)
                            }}</pre
                        >
                    </section>

                    <section
                        class="grid gap-2 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] p-4"
                    >
                        <strong
                            class="text-sm font-semibold text-[var(--cp-text-primary)]"
                            >{{ trans('activity.labels.changes') }}</strong
                        >
                        <pre
                            class="overflow-x-auto text-xs text-[var(--cp-text-muted)]"
                            >{{ JSON.stringify(detail.changes, null, 2) }}</pre
                        >
                    </section>
                </div>
            </div>
        </Drawer>
    </AppLayout>
</template>
