<script setup lang="ts">
import { progress, router, usePage } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import PrimePopover from 'primevue/popover'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import SystemUpdateRestartDialog from '@core-panel/components/Dialogs/SystemUpdateRestartDialog.vue'
import { useCan } from '@core-panel/composables/useCan'
import { useDateTime } from '@core-panel/composables/useDateTime'
import { useSystemRestartPolling } from '@core-panel/composables/useSystemRestartPolling'
import SystemUpdateSettingsDialog from '@core-panel/pages/Admin/Administration/components/SystemUpdateSettingsDialog.vue'

type SelectOption = {
    label: string
    value: string
}

type UpdateImage = {
    available_digest: string | null
    current_digest: string | null
    image: string
    manual_update_required?: boolean
    service: string
    services?: string[]
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

type RestartMarker = {
    attemptId?: string
    outcome?: 'failed' | 'success'
    phase: 'completed' | 'recovered' | 'waiting'
    restartObserved?: boolean
    runningObserved?: boolean
    startedAt: number
}

type UpdateStatus = {
    configured: boolean
    error: string | null
    images?: UpdateImage[]
    last_check_at?: string | null
    last_update_at?: string | null
    last_update_state?: string | null
    update_attempt_id?: string | null
    update_available: boolean
    update_running: boolean
}

const PRE_RESTART_TIMEOUT_MS = 10 * 60_000

const props = defineProps<{
    automatic: {
        canUpdate: boolean
        enabled: boolean
        interval: 'daily' | 'weekly'
        intervalOptions: SelectOption[]
        lastAutomaticRunAt: string | null
        maintenanceWindowEnabled: boolean
        mode: 'check' | 'install'
        modeOptions: SelectOption[]
        time: string
        timezone: string
        weekday: string | null
        weekdayOptions: SelectOption[]
        windowEnd: string
        windowStart: string
    } | null
    forceUpdateEnabled: boolean
    logs: UpdateLogs
    routes: {
        check: string
        status: string
        update: string
        health: string
    }
    status: UpdateStatus
}>()

const { can } = useCan()
const page = usePage<{ appName?: string }>()
const toast = useToast()
const appName = computed(() => page.props.appName?.trim() || 'CorePanel')
const checkStarting = ref(false)
const restartDialogVisible = ref(false)
const restartDialogState = ref<'failed' | 'running' | 'success' | 'timeout'>(
    'running',
)
const restartStorageKey = 'core-panel:system-update-restart'
const updateStarting = ref(false)
const {
    start: startRestartPolling,
    stop: stopRestartPolling,
    timedOut: restartTimedOut,
    unavailable: restartUnavailable,
} = useSystemRestartPolling(props.routes.health, {
    isRestartConfirmed: () => readRestartMarker()?.restartObserved === true,
    onRecovered: handleRestartRecovered,
})
const digestPopoverRef = ref<{ toggle: (event: Event) => void } | null>(null)
const digestPopoverImage = ref<UpdateImage | null>(null)
const copiedDigest = ref<'available' | 'current' | null>(null)
const logsPayload = ref<UpdateLogs>(props.logs)
const statusPayload = ref<UpdateStatus>(props.status)
const settingsDialogVisible = ref(false)
const { formatDateTime } = useDateTime()
let restartStatusTimer: number | null = null
let restartDeadlineTimer: number | null = null
let restartStatusRequestController: AbortController | null = null
let restartStatusPollingActive = false
let restartStatusDeadline = 0
let preRestartDeadline = 0
let restartMarkerFallback: RestartMarker | null = null
let restartMarkerStorageUsable = true
let checkLogsTimer: number | null = null
let checkLogsRequestController: AbortController | null = null

function isRestartMarker(
    marker: Partial<RestartMarker> | null,
): marker is RestartMarker {
    return (
        marker !== null &&
        (marker.phase === 'waiting' ||
            marker.phase === 'recovered' ||
            marker.phase === 'completed') &&
        typeof marker.startedAt === 'number'
    )
}

function readRestartMarker(): RestartMarker | null {
    if (!restartMarkerStorageUsable) {
        return restartMarkerFallback
    }

    try {
        const marker = JSON.parse(
            window.sessionStorage.getItem(restartStorageKey) ?? 'null',
        ) as Partial<RestartMarker> | null

        if (isRestartMarker(marker)) {
            restartMarkerFallback = marker
            return marker
        }
    } catch {
        restartMarkerStorageUsable = false
        return restartMarkerFallback
    }

    return restartMarkerFallback
}

function writeRestartMarker(marker: RestartMarker): boolean {
    restartMarkerFallback = marker

    try {
        window.sessionStorage.setItem(restartStorageKey, JSON.stringify(marker))
        restartMarkerStorageUsable = true

        return true
    } catch {
        restartMarkerStorageUsable = false
        // The in-memory marker keeps restart monitoring active when storage is unavailable.

        return false
    }
}

function removeRestartMarker(): void {
    restartMarkerFallback = null

    try {
        window.sessionStorage.removeItem(restartStorageKey)
    } catch {
        // Browser storage may remain unavailable while the dialog is open.
    }
}

function handleRestartRecovered(): void {
    const marker = readRestartMarker()

    if (marker === null) {
        return
    }

    const markerPersisted = writeRestartMarker({
        ...marker,
        phase: 'recovered',
    })

    if (!markerPersisted) {
        setRestartDeadlines(0, Date.now() + 120_000)
        startRestartStatusPolling()

        return
    }

    window.location.reload()
}

function stopRestartStatusPolling(): void {
    restartStatusPollingActive = false

    if (restartStatusTimer !== null) {
        window.clearTimeout(restartStatusTimer)
        restartStatusTimer = null
    }

    if (restartDeadlineTimer !== null) {
        window.clearTimeout(restartDeadlineTimer)
        restartDeadlineTimer = null
    }

    restartStatusRequestController?.abort()
    restartStatusRequestController = null
}

function activeRestartDeadline(): number {
    if (preRestartDeadline > 0 && restartStatusDeadline > 0) {
        return Math.min(preRestartDeadline, restartStatusDeadline)
    }

    return preRestartDeadline || restartStatusDeadline
}

function expireRestartMonitoring(): void {
    if (restartDialogState.value !== 'running') {
        return
    }

    stopRestartPolling()
    stopRestartStatusPolling()
    restartDialogState.value = 'timeout'
}

function scheduleRestartDeadline(): void {
    if (restartDeadlineTimer !== null) {
        window.clearTimeout(restartDeadlineTimer)
        restartDeadlineTimer = null
    }

    if (!restartStatusPollingActive || restartDialogState.value !== 'running') {
        return
    }

    const deadline = activeRestartDeadline()

    if (deadline <= 0) {
        return
    }

    restartDeadlineTimer = window.setTimeout(
        () => {
            restartDeadlineTimer = null

            if (Date.now() < activeRestartDeadline()) {
                scheduleRestartDeadline()
                return
            }

            expireRestartMonitoring()
        },
        Math.max(0, deadline - Date.now()),
    )
}

function setRestartDeadlines(preRestart: number, restartStatus: number): void {
    preRestartDeadline = preRestart
    restartStatusDeadline = restartStatus
    scheduleRestartDeadline()
}

function observeRestartTransition(): void {
    const marker = readRestartMarker()

    if (
        marker === null ||
        marker.phase !== 'waiting' ||
        marker.runningObserved !== true ||
        marker.restartObserved === true
    ) {
        return
    }

    writeRestartMarker({ ...marker, restartObserved: true })
}

function startRestartStatusPolling(): void {
    if (restartStatusPollingActive) {
        return
    }

    restartStatusPollingActive = true
    scheduleRestartDeadline()
    void checkRestartStatus()
}

function scheduleRestartStatusCheck(): void {
    if (!restartStatusPollingActive || restartDialogState.value !== 'running') {
        return
    }

    if (
        (preRestartDeadline > 0 && Date.now() >= preRestartDeadline) ||
        (restartStatusDeadline > 0 && Date.now() >= restartStatusDeadline)
    ) {
        expireRestartMonitoring()
        return
    }

    restartStatusTimer = window.setTimeout(
        () => void checkRestartStatus(),
        1_500,
    )
}

async function checkRestartStatus(): Promise<void> {
    if (!restartStatusPollingActive) {
        return
    }

    const requestController = new AbortController()
    restartStatusRequestController = requestController
    const requestedMarker = readRestartMarker()
    const statusUrl = new URL(props.routes.status, window.location.href)

    if (requestedMarker?.attemptId) {
        statusUrl.searchParams.set('attempt_id', requestedMarker.attemptId)
    }

    try {
        const response = await fetch(statusUrl, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: requestController.signal,
        })
        const payload = (await response.json().catch(() => ({}))) as {
            logs?: UpdateLogs
            status?: UpdateStatus
        }
        const status = payload.status

        if (response.ok && payload.logs) {
            logsPayload.value = payload.logs
        }

        if (!response.ok && response.status >= 500) {
            observeRestartTransition()
        }

        let marker = readRestartMarker()
        const updaterAttemptId = status?.update_attempt_id?.trim()
        const matchesAttempt =
            marker?.attemptId !== undefined &&
            updaterAttemptId === marker.attemptId
        const supportsAttemptCorrelation = Boolean(updaterAttemptId)

        if (
            response.ok &&
            marker !== null &&
            status?.update_running &&
            (!supportsAttemptCorrelation || matchesAttempt) &&
            marker.runningObserved !== true
        ) {
            marker = { ...marker, runningObserved: true }
            writeRestartMarker(marker)
        }

        const belongsToCurrentUpdate =
            marker !== null &&
            (matchesAttempt ||
                (!supportsAttemptCorrelation &&
                    (marker.runningObserved === true ||
                        marker.phase === 'recovered' ||
                        (status?.last_update_state === 'failed' &&
                            Boolean(status.error)))))

        if (
            response.ok &&
            marker !== null &&
            belongsToCurrentUpdate &&
            status?.last_update_state === 'success' &&
            !status.update_running
        ) {
            stopRestartPolling()
            stopRestartStatusPolling()
            statusPayload.value = status
            const completionMarkerPersisted = writeRestartMarker({
                ...marker,
                outcome: 'success',
                phase: 'completed',
            })

            if (marker.phase !== 'recovered' && completionMarkerPersisted) {
                window.location.reload()
                return
            }

            restartDialogState.value = 'success'
            return
        }

        if (
            response.ok &&
            marker !== null &&
            belongsToCurrentUpdate &&
            status?.last_update_state === 'failed' &&
            !status.update_running
        ) {
            stopRestartPolling()
            stopRestartStatusPolling()
            statusPayload.value = status
            writeRestartMarker({
                ...marker,
                outcome: 'failed',
                phase: 'completed',
            })
            restartDialogState.value = 'failed'
            return
        }
    } catch {
        if (!requestController.signal.aborted) {
            observeRestartTransition()
        }

        // Temporary status errors are expected while the application recovers.
    } finally {
        if (restartStatusRequestController === requestController) {
            restartStatusRequestController = null
        }
    }

    scheduleRestartStatusCheck()
}

onMounted(() => {
    const restartMarker = readRestartMarker()

    if (restartMarker === null) {
        return
    }

    restartDialogVisible.value = true
    restartDialogState.value = 'running'
    if (restartMarker.phase === 'completed' && restartMarker.outcome) {
        restartDialogState.value = restartMarker.outcome
        return
    }

    if (restartMarker.phase === 'recovered') {
        setRestartDeadlines(0, Date.now() + 120_000)
        startRestartStatusPolling()
        return
    }

    setRestartDeadlines(restartMarker.startedAt + PRE_RESTART_TIMEOUT_MS, 0)
    startRestartPolling()
    startRestartStatusPolling()
})

onUnmounted(() => {
    stopRestartStatusPolling()
    stopCheckLogsPolling()
})

watch(restartTimedOut, (timedOut) => {
    if (timedOut) {
        expireRestartMonitoring()
    }
})

watch(restartUnavailable, (unavailable) => {
    if (unavailable && readRestartMarker()?.restartObserved === true) {
        setRestartDeadlines(0, restartStatusDeadline)
    }
})

const statusState = computed(() => statusPayload.value)
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
const servicesForImage = (image: UpdateImage): string[] =>
    image.services?.length ? image.services : [image.service]
const manualUpdateServices = computed(() =>
    [
        ...new Set(
            manuallyUpdatedImages.value.flatMap((image) =>
                servicesForImage(image),
            ),
        ),
    ]
        .sort((left, right) => left.localeCompare(right))
        .join(', '),
)
const canUpdate = computed(
    () =>
        can('system-updates.update') &&
        statusState.value.configured &&
        !statusState.value.update_running &&
        !checkStarting.value &&
        !updateStarting.value &&
        !restartDialogVisible.value,
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
const automaticIntervalLabel = computed(() =>
    selectOptionLabel(
        props.automatic?.intervalOptions ?? [],
        props.automatic?.interval,
    ),
)
const automaticModeLabel = computed(() =>
    selectOptionLabel(
        props.automatic?.modeOptions ?? [],
        props.automatic?.mode,
    ),
)
const automaticWeekdayLabel = computed(() =>
    selectOptionLabel(
        props.automatic?.weekdayOptions ?? [],
        props.automatic?.weekday,
    ),
)
const automaticMaintenanceWindowLabel = computed(() => {
    if (!props.automatic?.maintenanceWindowEnabled) {
        return trans('system_updates.disabled')
    }

    return trans('system_updates.maintenance_window', {
        end: props.automatic.windowEnd,
        start: props.automatic.windowStart,
        timezone: props.automatic.timezone,
    })
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
    return value ? formatDateTime(value) : '-'
}

function selectOptionLabel(
    options: SelectOption[],
    value?: string | null,
): string {
    return options.find((option) => option.value === value)?.label ?? '-'
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

function stopCheckLogsPolling(): void {
    if (checkLogsTimer !== null) {
        window.clearTimeout(checkLogsTimer)
        checkLogsTimer = null
    }

    checkLogsRequestController?.abort()
    checkLogsRequestController = null
}

function scheduleCheckLogsPoll(): void {
    if (!checkStarting.value) {
        return
    }

    checkLogsTimer = window.setTimeout(() => void pollCheckLogs(), 500)
}

async function refreshCheckLogs(): Promise<void> {
    const requestController = new AbortController()
    checkLogsRequestController = requestController
    const statusUrl = new URL(props.routes.status, window.location.href)
    statusUrl.searchParams.set('logs_only', '1')

    try {
        const response = await fetch(statusUrl, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: requestController.signal,
        })
        const payload = (await response.json().catch(() => ({}))) as {
            logs?: UpdateLogs
        }

        if (response.ok && payload.logs) {
            logsPayload.value = payload.logs
        }
    } catch {
        // A later poll retries transient failures while the check is running.
    } finally {
        if (checkLogsRequestController === requestController) {
            checkLogsRequestController = null
        }
    }
}

async function pollCheckLogs(): Promise<void> {
    if (!checkStarting.value) {
        return
    }

    await refreshCheckLogs()

    scheduleCheckLogsPoll()
}

async function runCheck(): Promise<void> {
    if (checkStarting.value) {
        return
    }

    checkStarting.value = true
    progress.start()
    void pollCheckLogs()
    toast.add({
        detail: trans('system_updates.check_started'),
        life: 4000,
        severity: 'info',
        summary: trans('common.ui.status'),
    })

    try {
        const token = csrfToken()
        const xsrf = xsrfToken()
        const response = await fetch(props.routes.check, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
            },
            method: 'POST',
        })
        const body = (await response.json().catch(() => ({}))) as {
            message?: string
            status?: UpdateStatus
        }

        if (!response.ok) {
            toast.add({
                detail: body.message ?? trans('system_updates.action_failed'),
                life: 5000,
                severity: 'error',
                summary: trans('common.ui.error'),
            })

            return
        }

        if (body.status) {
            statusPayload.value = body.status
        }

        await refreshCheckLogs()
        toast.add({
            detail: body.message ?? trans('system_updates.check_completed'),
            life: 4000,
            severity: 'success',
            summary: trans('system_updates.status_title'),
        })
    } catch {
        toast.add({
            detail: trans('system_updates.action_failed'),
            life: 5000,
            severity: 'error',
            summary: trans('common.ui.error'),
        })
    } finally {
        checkStarting.value = false
        stopCheckLogsPolling()
        progress.finish()
    }
}

function csrfToken(): string | null {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    )
}

function xsrfToken(): string | null {
    const matches = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)

    return matches?.[2] ? decodeURIComponent(matches[2]) : null
}

function createUpdateAttemptId(): string {
    if (typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID()
    }

    const bytes = new Uint8Array(16)
    window.crypto.getRandomValues(bytes)
    bytes[6] = ((bytes[6] ?? 0) & 0x0f) | 0x40
    bytes[8] = ((bytes[8] ?? 0) & 0x3f) | 0x80
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0'))

    return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10).join('')}`
}

function beginRestartMonitoring(attemptId: string, startedAt: number): void {
    writeRestartMarker({
        attemptId,
        phase: 'waiting',
        restartObserved: false,
        runningObserved: false,
        startedAt,
    })
    setRestartDeadlines(startedAt + PRE_RESTART_TIMEOUT_MS, 0)
    restartDialogState.value = 'running'
    restartDialogVisible.value = true
    startRestartPolling()
    startRestartStatusPolling()
}

async function submitUpdate(force = false): Promise<void> {
    if (updateStarting.value || restartDialogVisible.value) {
        return
    }

    updateStarting.value = true
    const attemptId = createUpdateAttemptId()
    const restartStartedAt = Date.now()

    try {
        const token = csrfToken()
        const xsrf = xsrfToken()
        const response = await fetch(props.routes.update, {
            body: JSON.stringify({ attempt_id: attemptId, force }),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
            },
            method: 'POST',
        })
        const body = (await response.json().catch(() => ({}))) as {
            accepted?: boolean
            attempt_id?: string
            message?: string
        }

        if (response.status !== 202 || body.accepted !== true) {
            toast.add({
                detail: body.message ?? trans('system_updates.action_failed'),
                life: 5000,
                severity: 'error',
                summary: trans('common.error'),
            })

            return
        }

        beginRestartMonitoring(
            body.attempt_id?.trim() || attemptId,
            restartStartedAt,
        )
    } catch {
        beginRestartMonitoring(attemptId, restartStartedAt)
    } finally {
        updateStarting.value = false
    }
}

function closeRestartDialog(): void {
    if (restartDialogState.value === 'running') {
        return
    }

    stopRestartPolling()
    stopRestartStatusPolling()

    removeRestartMarker()
    setRestartDeadlines(0, 0)
    restartDialogVisible.value = false
}

function installUpdate(): void {
    void submitUpdate()
}

function forceUpdate(): void {
    if (!window.confirm(String(trans('system_updates.force_update_confirm')))) {
        return
    }

    void submitUpdate(true)
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <SystemUpdateRestartDialog
            :app-name="appName"
            :failure-message="statusPayload.error"
            :state="restartDialogState"
            :visible="restartDialogVisible"
            @close="closeRestartDialog"
        />

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
                        v-if="automatic?.canUpdate"
                        severity="secondary"
                        type="button"
                        @click="settingsDialogVisible = true"
                    >
                        <AppIcon name="settings" class="cp-icon" />
                        <span>{{
                            trans('system_updates.settings_button')
                        }}</span>
                    </Button>
                    <Button
                        :disabled="!canUpdate"
                        :loading="checkStarting"
                        severity="secondary"
                        type="button"
                        @click="runCheck"
                    >
                        <AppIcon
                            name="refresh-cw"
                            class="cp-icon"
                            :class="{ 'animate-spin': checkStarting }"
                        />
                        <span>{{ trans('system_updates.check_updates') }}</span>
                    </Button>
                    <Button
                        :disabled="!canUpdate || !automaticUpdateAvailable"
                        :loading="updateStarting"
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
                        v-if="forceUpdateEnabled"
                        :disabled="!canUpdate"
                        :loading="updateStarting"
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
                                {{ servicesForImage(image).join(', ') }}
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
            v-if="automatic"
            class="rounded-lg border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5 shadow-sm"
        >
            <div class="flex flex-col gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2
                            class="text-lg font-semibold text-[var(--cp-text-primary)]"
                        >
                            {{ trans('system_updates.automatic_title') }}
                        </h2>
                        <Badge
                            :severity="
                                automatic.enabled ? 'success' : 'secondary'
                            "
                            :value="
                                automatic.enabled
                                    ? trans('system_updates.enabled')
                                    : trans('system_updates.disabled')
                            "
                        />
                    </div>
                    <p class="mt-1 text-sm text-[var(--cp-text-secondary)]">
                        {{ trans('system_updates.automatic_description') }}
                    </p>
                </div>
                <dl
                    class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 xl:grid-cols-4"
                >
                    <div>
                        <dt class="text-[var(--cp-text-muted)]">
                            {{ trans('system_updates.mode') }}
                        </dt>
                        <dd class="font-medium text-[var(--cp-text-primary)]">
                            {{ automaticModeLabel }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[var(--cp-text-muted)]">
                            {{ trans('system_updates.interval') }}
                        </dt>
                        <dd class="font-medium text-[var(--cp-text-primary)]">
                            {{ automaticIntervalLabel }}
                        </dd>
                    </div>
                    <div v-if="automatic.interval === 'weekly'">
                        <dt class="text-[var(--cp-text-muted)]">
                            {{ trans('system_updates.weekday') }}
                        </dt>
                        <dd class="font-medium text-[var(--cp-text-primary)]">
                            {{ automaticWeekdayLabel }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[var(--cp-text-muted)]">
                            {{ trans('system_updates.execution_time') }}
                        </dt>
                        <dd class="font-medium text-[var(--cp-text-primary)]">
                            {{ automatic.time }}
                        </dd>
                    </div>
                    <div v-if="automatic.mode === 'install'">
                        <dt class="text-[var(--cp-text-muted)]">
                            {{
                                trans('system_updates.maintenance_window_label')
                            }}
                        </dt>
                        <dd class="font-medium text-[var(--cp-text-primary)]">
                            {{ automaticMaintenanceWindowLabel }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[var(--cp-text-muted)]">
                            {{ trans('system_updates.timezone') }}
                        </dt>
                        <dd class="font-medium text-[var(--cp-text-primary)]">
                            {{ automatic.timezone }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[var(--cp-text-muted)]">
                            {{ trans('system_updates.last_automatic_run') }}
                        </dt>
                        <dd class="font-medium text-[var(--cp-text-primary)]">
                            {{
                                automatic.lastAutomaticRunAt
                                    ? formatDate(automatic.lastAutomaticRunAt)
                                    : trans(
                                          'system_updates.last_automatic_run_never',
                                      )
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <SystemUpdateSettingsDialog
            v-if="automatic"
            v-model:visible="settingsDialogVisible"
            :settings="automatic"
        />

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
