<script setup lang="ts">
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

import LogUserAvatar from '@core-panel/pages/Admin/Logs/components/LogUserAvatar.vue'
import LogBadge from '@core-panel/pages/Admin/Logs/components/LogBadge.vue'
import type { ActivityLogRecord } from '@core-panel/types/core-panel'

const props = defineProps<{
    data: ActivityLogRecord
}>()

const emit = defineEmits<{
    cancel: []
}>()

const propertiesView = ref<'json' | 'table'>('table')

const oldValues = computed<Record<string, unknown>>(() => {
    const changes = props.data.changes
    const old = changes.old

    return old && typeof old === 'object' && !Array.isArray(old)
        ? (old as Record<string, unknown>)
        : {}
})

const newValues = computed<Record<string, unknown>>(() => {
    const changes = props.data.changes
    const attributes = changes.attributes

    return attributes &&
        typeof attributes === 'object' &&
        !Array.isArray(attributes)
        ? (attributes as Record<string, unknown>)
        : {}
})

const changedKeys = computed(() => {
    return Array.from(
        new Set([
            ...Object.keys(oldValues.value),
            ...Object.keys(newValues.value),
        ]),
    )
})

const propertyEntries = computed(() => {
    return Object.entries(props.data.properties)
})

function modelShortName(fqcn: string | null): string {
    if (!fqcn) {
        return '—'
    }

    if (fqcn.endsWith('\\User')) {
        return trans('activity.models.user')
    }

    const parts = fqcn.split('\\')

    return parts[parts.length - 1] ?? fqcn
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

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—'
    }

    if (typeof value === 'object') {
        return JSON.stringify(value)
    }

    return String(value)
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString()
}
</script>

<template>
    <div class="space-y-5 p-1">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <span
                    class="block text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                >
                    {{ $t('activity.filters.event') }}
                </span>
                <div class="mt-1">
                    <LogBadge
                        dot
                        :label="eventLabel(data.event)"
                        :tone="eventTone(data.event)"
                    />
                </div>
            </div>

            <div>
                <span
                    class="block text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                >
                    {{ $t('activity.labels.log_name') }}
                </span>
                <span class="mt-1 block text-sm text-[var(--cp-text-primary)]">
                    {{ data.logName ?? '—' }}
                </span>
            </div>

            <div>
                <span
                    class="block text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                >
                    {{ $t('activity.filters.subject_type') }}
                </span>
                <span class="mt-1 block text-sm text-[var(--cp-text-primary)]">
                    {{ modelShortName(data.subjectType) }}
                    <span
                        v-if="data.subjectId"
                        class="text-[var(--cp-text-muted)]"
                    >
                        #{{ data.subjectId }}
                    </span>
                </span>
            </div>

            <div>
                <span
                    class="block text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                >
                    {{ $t('activity.filters.user') }}
                </span>
                <div class="mt-1">
                    <LogUserAvatar
                        :avatar-url="data.causerAvatarUrl ?? null"
                        :label="
                            data.systemCauser
                                ? $t('activity.system')
                                : (data.causerName ?? null)
                        "
                        :system="data.systemCauser === true"
                        size="sm"
                    />
                </div>
            </div>

            <div>
                <span
                    class="block text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                >
                    {{ $t('activity.columns.created_at') }}
                </span>
                <span class="mt-1 block text-sm text-[var(--cp-text-primary)]">
                    {{ formatDateTime(data.createdAt) }}
                </span>
            </div>

            <div>
                <span
                    class="block text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                >
                    {{ $t('activity.columns.description') }}
                </span>
                <span class="mt-1 block text-sm text-[var(--cp-text-primary)]">
                    {{ data.description ?? '—' }}
                </span>
            </div>
        </div>

        <div v-if="changedKeys.length > 0">
            <h3
                class="mb-2 text-sm font-semibold text-[var(--cp-text-primary)]"
            >
                {{ $t('activity.labels.changes') }}
            </h3>
            <div
                class="overflow-hidden rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)]"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-[var(--cp-surface-muted)]">
                            <th
                                class="px-3 py-2 text-left text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                            >
                                {{ $t('activity.labels.field') }}
                            </th>
                            <th
                                class="px-3 py-2 text-left text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                            >
                                {{ $t('activity.labels.old') }}
                            </th>
                            <th
                                class="px-3 py-2 text-left text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                            >
                                {{ $t('activity.labels.new') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="key in changedKeys"
                            :key="key"
                            class="border-t border-[var(--cp-surface-border)]"
                        >
                            <td
                                class="px-3 py-2 font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ key }}
                            </td>
                            <td
                                class="px-3 py-2 text-red-600 dark:text-red-400"
                            >
                                {{ formatValue(oldValues[key]) }}
                            </td>
                            <td
                                class="px-3 py-2 text-green-600 dark:text-green-400"
                            >
                                {{ formatValue(newValues[key]) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="Object.keys(data.properties).length > 0">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-[var(--cp-text-primary)]">
                    {{ $t('activity.labels.properties') }}
                </h3>
                <div
                    class="inline-flex rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] p-1"
                >
                    <Button
                        :label="$t('activity.labels.table')"
                        :outlined="propertiesView !== 'table'"
                        severity="secondary"
                        size="small"
                        @click="propertiesView = 'table'"
                    />
                    <Button
                        :label="$t('activity.labels.json')"
                        :outlined="propertiesView !== 'json'"
                        severity="secondary"
                        size="small"
                        @click="propertiesView = 'json'"
                    />
                </div>
            </div>

            <div
                v-if="propertiesView === 'table'"
                class="overflow-hidden rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)]"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-[var(--cp-surface-muted)]">
                            <th
                                class="px-3 py-2 text-left text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                            >
                                {{ $t('activity.labels.field') }}
                            </th>
                            <th
                                class="px-3 py-2 text-left text-xs font-medium uppercase text-[var(--cp-text-muted)]"
                            >
                                {{ $t('activity.labels.value') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="[key, value] in propertyEntries"
                            :key="key"
                            class="border-t border-[var(--cp-surface-border)]"
                        >
                            <td
                                class="px-3 py-2 font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ key }}
                            </td>
                            <td class="px-3 py-2 text-[var(--cp-text-primary)]">
                                {{ formatValue(value) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <pre
                v-else
                class="overflow-auto rounded-[var(--cp-radius-md)] bg-[var(--cp-surface-muted)] p-3 text-xs text-[var(--cp-text-muted)]"
                >{{ JSON.stringify(data.properties, null, 2) }}</pre
            >
        </div>

        <div class="flex justify-end pt-2">
            <Button
                :label="$t('common.ui.close')"
                icon="pi pi-times"
                outlined
                severity="secondary"
                @click="emit('cancel')"
            />
        </div>
    </div>
</template>
