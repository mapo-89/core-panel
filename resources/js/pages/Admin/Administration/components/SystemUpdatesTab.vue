<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import PrimePopover from 'primevue/popover'
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import { useCan } from '@core-panel/composables/useCan'

type UpdateImage = {
    available_digest: string | null
    current_digest: string | null
    image: string
    manual_update_required?: boolean
    service: string
    update_available: boolean
}

type UpdateLogEntry = {
    level: string
    message: string
    timestamp: string
}

type UpdateLogs = {
    entries?: UpdateLogEntry[]
}

type UpdateStatus = {
    configured: boolean
    error: string | null
    images?: UpdateImage[]
    last_check_at?: string | null
    last_update_at?: string | null
    last_update_state?: string | null
    update_available: boolean
    update_running: boolean
}

type UpdateStatusResponse = {
    logs?: UpdateLogs
    status?: UpdateStatus
}

const props = defineProps<{
    automatic: {
        enabled: boolean
        forceUpdateEnabled: boolean
        inactiveMinutes: number
        timezone: string
        windowEnd: string
        windowStart: string
    }
    logs: UpdateLogs
    routes: {
        check: string
        status: string
        update: string
    }
    status: UpdateStatus
}>()

const { can } = useCan()
const digestPopoverRef = ref<{ toggle: (event: Event) => void } | null>(null)
const digestPopoverImage = ref<UpdateImage | null>(null)
const copiedDigest = ref<'available' | 'current' | null>(null)
const logsPayload = ref<UpdateLogs>(props.logs)
const statusPayload = ref<UpdateStatus>(props.status)
const updateMonitorSawRunning = ref(false)
const updateMonitorStartedAt = ref<number | null>(null)
const updateNotice = ref<string | null>(null)
const updateNoticeSeverity = ref<'error' | 'info' | 'success'>('info')
const updateStatusTimer = ref<number | null>(null)

const statusState = computed(() => statusPayload.value)
const updateMonitorActive = computed(() => updateStatusTimer.value !== null)
const images = computed(() => statusState.value.images ?? [])
const displayedImages = computed(() =>
    [...images.value].sort(
        (left, right) =>
            Number(right.update_available) - Number(left.update_available) ||
            left.service.localeCompare(right.service),
    ),
)
const logEntries = computed(() => logsPayload.value.entries ?? [])
const manuallyUpdatedImages = computed(() =>
    images.value.filter(
        (image) => image.update_available && image.manual_update_required,
    ),
)
const automaticUpdateAvailable = computed(() =>
    images.value.some(
        (image) => image.update_available && !image.manual_update_required,
    ),
)
const manualUpdateServices = computed(() =>
    manuallyUpdatedImages.value.map((image) => image.service).join(', '),
)
const canUpdate = computed(
    () =>
        can('system-updates.update') &&
        statusState.value.configured &&
        !statusState.value.update_running &&
        !updateMonitorActive.value,
)
const statusSeverity = computed(() => {
    if (statusState.value.update_running) {
        return 'warn'
    }

    return statusState.value.update_available ? 'danger' : 'success'
})
const statusLabel = computed(() => {
    if (statusState.value.update_running) {
        return trans('system_updates.running')
    }

    return statusState.value.update_available
        ? trans('system_updates.status_available')
        : trans('system_updates.status_current')
})

watch(
    () => props.status,
    (status) => {
        statusPayload.value = status
    },
    { deep: true },
)

watch(
    () => props.logs,
    (logs) => {
        logsPayload.value = logs
    },
    { deep: true },
)

function formatDate(value?: string | null): string {
    if (!value) {
        return '-'
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

function imageStatusSeverity(image: UpdateImage): 'danger' | 'success' {
    return image.update_available ? 'danger' : 'success'
}

function imageStatusLabel(image: UpdateImage): string {
    return image.update_available
        ? trans('system_updates.status_available')
        : trans('system_updates.status_current')
}

function openDigestPopover(event: Event, image: UpdateImage): void {
    digestPopoverImage.value = image
    copiedDigest.value = null

    nextTick(() => {
        digestPopoverRef.value?.toggle(event)
    })
}

async function copyDigest(
    value: string | null,
    key: 'available' | 'current',
): Promise<void> {
    if (!value) {
        return
    }

    try {
        await navigator.clipboard.writeText(value)
        copiedDigest.value = key
    } catch {
        copiedDigest.value = null
    }
}

function runCheck(): void {
    router.post(props.routes.check, undefined, {
        preserveScroll: true,
    })
}

function installUpdate(): void {
    router.post(props.routes.update, undefined, {
        onSuccess: () => startUpdateStatusPolling(),
        preserveScroll: true,
    })
}

function forceUpdate(): void {
    if (!window.confirm(String(trans('system_updates.force_update_confirm')))) {
        return
    }

    router.post(
        props.routes.update,
        { force: true },
        {
            onSuccess: () => startUpdateStatusPolling(),
            preserveScroll: true,
        },
    )
}

function stopUpdateStatusPolling(): void {
    if (updateStatusTimer.value === null) {
        return
    }

    window.clearInterval(updateStatusTimer.value)
    updateStatusTimer.value = null
}

function startUpdateStatusPolling(): void {
    stopUpdateStatusPolling()

    updateMonitorSawRunning.value = statusState.value.update_running
    updateMonitorStartedAt.value = Date.now()
    updateNotice.value = trans('system_updates.update_monitoring')
    updateNoticeSeverity.value = 'info'
    updateStatusTimer.value = window.setInterval(() => {
        void pollUpdateStatus()
    }, 4000)

    window.setTimeout(() => {
        void pollUpdateStatus()
    }, 1500)
}

function isCurrentUpdateResult(status: UpdateStatus): boolean {
    if (updateMonitorSawRunning.value) {
        return true
    }

    if (updateMonitorStartedAt.value === null || !status.last_update_at) {
        return false
    }

    return (
        new Date(status.last_update_at).getTime() >=
        updateMonitorStartedAt.value - 5000
    )
}

async function pollUpdateStatus(): Promise<void> {
    let body: UpdateStatusResponse | undefined

    try {
        const response = await fetch(props.routes.status, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
            method: 'GET',
        })

        if (!response.ok) {
            return
        }

        body = (await response.json().catch(() => ({}))) as
            | UpdateStatusResponse
            | undefined
    } catch {
        return
    }

    if (body?.status) {
        statusPayload.value = body.status
    }

    if (body?.logs) {
        logsPayload.value = body.logs
    }

    const status = statusState.value

    if (status.update_running) {
        updateMonitorSawRunning.value = true

        return
    }

    if (!isCurrentUpdateResult(status)) {
        return
    }

    if (status.last_update_state === 'success') {
        stopUpdateStatusPolling()
        updateNotice.value = trans('system_updates.update_completed')
        updateNoticeSeverity.value = 'success'

        return
    }

    if (status.last_update_state === 'failed') {
        stopUpdateStatusPolling()
        updateNotice.value = trans('system_updates.update_failed')
        updateNoticeSeverity.value = 'error'
    }
}

onUnmounted(() => {
    stopUpdateStatusPolling()
})
</script>

<template>
    <div class="flex flex-col gap-6">
        <Message
            v-if="updateNotice"
            :closable="false"
            :severity="updateNoticeSeverity"
        >
            {{ updateNotice }}
        </Message>

        <section
            class="rounded-lg border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5 shadow-sm"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="flex min-w-0 flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2
                            class="text-lg font-semibold text-[var(--cp-text-primary)]"
                        >
                            {{ trans('system_updates.status_title') }}
                        </h2>
                        <Badge
                            :severity="statusSeverity"
                            :value="statusLabel"
                        />
                    </div>
                    <p
                        v-if="statusState.error"
                        class="text-sm text-[var(--cp-danger-text,var(--cp-color-danger))]"
                    >
                        {{ statusState.error }}
                    </p>
                    <div
                        class="grid gap-3 text-sm text-[var(--cp-text-secondary)] sm:grid-cols-2"
                    >
                        <div>
                            <span
                                class="block text-xs uppercase tracking-normal text-[var(--cp-text-muted)]"
                            >
                                {{ trans('system_updates.last_check') }}
                            </span>
                            {{ formatDate(statusState.last_check_at) }}
                        </div>
                        <div>
                            <span
                                class="block text-xs uppercase tracking-normal text-[var(--cp-text-muted)]"
                            >
                                {{ trans('system_updates.last_update') }}
                            </span>
                            {{ formatDate(statusState.last_update_at) }}
                        </div>
                    </div>
                    <div
                        v-if="manuallyUpdatedImages.length > 0"
                        class="flex items-start gap-2 rounded-md border border-[var(--cp-warning-border,var(--cp-color-warning))] bg-[var(--cp-warning-bg,var(--cp-surface-muted))] px-3 py-2 text-sm text-[var(--cp-warning-text,var(--cp-text-primary))]"
                    >
                        <AppIcon name="info" class="cp-icon mt-0.5 shrink-0" />
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{
                                    trans(
                                        'system_updates.manual_update_available',
                                    )
                                }}
                            </p>
                            <p class="text-[var(--cp-text-secondary)]">
                                {{
                                    trans('system_updates.manual_update_hint', {
                                        services: manualUpdateServices,
                                    })
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        :disabled="!canUpdate"
                        severity="secondary"
                        type="button"
                        @click="runCheck"
                    >
                        <AppIcon name="refresh-cw" class="cp-icon" />
                        <span>{{ trans('system_updates.check_updates') }}</span>
                    </Button>
                    <Button
                        :disabled="!canUpdate || !automaticUpdateAvailable"
                        severity="danger"
                        type="button"
                        @click="installUpdate"
                    >
                        <AppIcon name="download" class="cp-icon" />
                        <span>{{
                            trans('system_updates.install_update')
                        }}</span>
                    </Button>
                    <Button
                        v-if="automatic.forceUpdateEnabled"
                        :disabled="!canUpdate"
                        severity="warn"
                        type="button"
                        @click="forceUpdate"
                    >
                        <AppIcon name="rotate-cw" class="cp-icon" />
                        <span>{{ trans('system_updates.force_update') }}</span>
                    </Button>
                </div>
            </div>
        </section>

        <section
            class="rounded-lg border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5 shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead
                        class="text-xs uppercase text-[var(--cp-text-muted)]"
                    >
                        <tr>
                            <th class="px-3 py-2 font-semibold">
                                {{ trans('system_updates.image') }}
                            </th>
                            <th class="px-3 py-2 font-semibold">
                                {{ trans('system_updates.status') }}
                            </th>
                            <th class="px-3 py-2 font-semibold">
                                {{ trans('system_updates.details') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--cp-surface-border)]">
                        <tr
                            v-for="image in displayedImages"
                            :key="image.service"
                            :class="
                                image.update_available
                                    ? 'bg-[var(--cp-warning-bg,var(--cp-surface-muted))]/40'
                                    : ''
                            "
                        >
                            <td
                                class="px-3 py-3 font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ image.service }}
                                <span
                                    class="block font-mono text-xs text-[var(--cp-text-muted)]"
                                >
                                    {{ image.image }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <Badge
                                    :severity="imageStatusSeverity(image)"
                                    :value="imageStatusLabel(image)"
                                />
                            </td>
                            <td class="px-3 py-3">
                                <div class="cp-datatable__id-cell">
                                    <button
                                        :aria-label="
                                            trans(
                                                'system_updates.digest_details',
                                            )
                                        "
                                        class="cp-datatable__id-trigger"
                                        type="button"
                                        @click="
                                            openDigestPopover($event, image)
                                        "
                                    >
                                        <AppIcon name="info" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="displayedImages.length === 0">
                            <td
                                class="px-3 py-6 text-[var(--cp-text-muted)]"
                                colspan="3"
                            >
                                {{ statusState.error ?? '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <PrimePopover ref="digestPopoverRef">
                    <div
                        v-if="digestPopoverImage"
                        class="cp-datatable__id-popover grid min-w-[24rem] gap-3"
                    >
                        <div class="cp-datatable__id-popover-row">
                            <span class="cp-datatable__id-popover-label">
                                {{ trans('system_updates.current_digest') }}
                            </span>
                            <strong
                                class="cp-datatable__id-popover-value truncate"
                            >
                                {{ digestPopoverImage.current_digest ?? '-' }}
                            </strong>
                            <span class="cp-datatable__id-popover-separator" />
                            <button
                                :disabled="!digestPopoverImage.current_digest"
                                class="cp-datatable__id-popover-copy-button"
                                type="button"
                                @click="
                                    copyDigest(
                                        digestPopoverImage.current_digest,
                                        'current',
                                    )
                                "
                            >
                                <AppIcon
                                    :name="
                                        copiedDigest === 'current'
                                            ? 'check'
                                            : 'copy'
                                    "
                                />
                            </button>
                        </div>
                        <div class="cp-datatable__id-popover-row">
                            <span class="cp-datatable__id-popover-label">
                                {{ trans('system_updates.available_digest') }}
                            </span>
                            <strong
                                class="cp-datatable__id-popover-value truncate"
                            >
                                {{ digestPopoverImage.available_digest ?? '-' }}
                            </strong>
                            <span class="cp-datatable__id-popover-separator" />
                            <button
                                :disabled="!digestPopoverImage.available_digest"
                                class="cp-datatable__id-popover-copy-button"
                                type="button"
                                @click="
                                    copyDigest(
                                        digestPopoverImage.available_digest,
                                        'available',
                                    )
                                "
                            >
                                <AppIcon
                                    :name="
                                        copiedDigest === 'available'
                                            ? 'check'
                                            : 'copy'
                                    "
                                />
                            </button>
                        </div>
                    </div>
                </PrimePopover>
            </div>
        </section>

        <section
            class="rounded-lg border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5 shadow-sm"
        >
            <h2
                class="mb-3 text-lg font-semibold text-[var(--cp-text-primary)]"
            >
                {{ trans('system_updates.automatic_title') }}
            </h2>
            <div class="flex flex-wrap gap-2 text-sm">
                <Badge
                    :severity="automatic.enabled ? 'success' : 'secondary'"
                    :value="
                        automatic.enabled
                            ? trans('system_updates.enabled')
                            : trans('system_updates.disabled')
                    "
                />
                <span
                    class="rounded-md bg-[var(--cp-surface-muted)] px-2.5 py-1"
                >
                    {{
                        trans('system_updates.maintenance_window', {
                            end: automatic.windowEnd,
                            start: automatic.windowStart,
                            timezone: automatic.timezone,
                        })
                    }}
                </span>
                <span
                    class="rounded-md bg-[var(--cp-surface-muted)] px-2.5 py-1"
                >
                    {{
                        trans('system_updates.inactive_minutes', {
                            minutes: String(automatic.inactiveMinutes),
                        })
                    }}
                </span>
            </div>
        </section>

        <section
            class="rounded-lg border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5 shadow-sm"
        >
            <h2
                class="mb-3 text-lg font-semibold text-[var(--cp-text-primary)]"
            >
                {{ trans('system_updates.logs_title') }}
            </h2>
            <div
                v-if="logEntries.length > 0"
                class="flex max-h-[28rem] flex-col gap-2 overflow-y-auto"
            >
                <div
                    v-for="entry in logEntries"
                    :key="`${entry.timestamp}-${entry.message}`"
                    class="grid gap-2 rounded-md bg-[var(--cp-surface-muted)] px-3 py-2 text-sm md:grid-cols-[11rem_5rem_1fr]"
                >
                    <span class="font-mono text-xs text-[var(--cp-text-muted)]">
                        {{ formatDate(entry.timestamp) }}
                    </span>
                    <span
                        class="font-semibold uppercase text-[var(--cp-text-secondary)]"
                    >
                        {{ entry.level }}
                    </span>
                    <span class="text-[var(--cp-text-primary)]">
                        {{ entry.message }}
                    </span>
                </div>
            </div>
            <p v-else class="text-sm text-[var(--cp-text-muted)]">
                {{ trans('system_updates.logs_empty') }}
            </p>
        </section>
    </div>
</template>
