<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { computed, onUnmounted, ref, watch } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'

type DatabaseBackup = {
    created_at: string
    encrypted: boolean
    name: string
    source: 'automatic' | 'custom' | 'imported' | 'manual'
    size: number
    size_for_humans: string
}

type TableOption = {
    dependencies: string[]
    label: string
    value: string
}

type RestoreErrorResponse = {
    errors?: Record<string, string[]>
    message?: string
}

type RestoreStartResponse = {
    message?: string
    restore?: {
        id: string
        status_url: string
    } | null
}

type RestoreStatusResponse = {
    restore?: {
        finished_at: string | null
        id: string
        message: string | null
        message_key: string | null
        status: 'running' | 'completed' | 'failed' | 'unknown'
    }
}

const props = defineProps<{
    backup: DatabaseBackup | null
    restoreUrl: string
    statusUrlTemplate: string
    tableOptions: TableOption[]
    visible: boolean
}>()

const emit = defineEmits<{
    'restore-accepted': [message: string]
    'restore-failed': [message: string]
    'restore-started': [message: string]
    'update:visible': [visible: boolean]
}>()

const dialogVisible = computed({
    get: () => props.visible,
    set: (visible: boolean) => emit('update:visible', visible),
})

const restoreForm = useForm({
    confirmation: '',
    mode: 'all' as 'all' | 'tables',
    tables: [] as string[],
})
const directTables = ref<string[]>([])

const dependencyMap = computed(() =>
    props.tableOptions.reduce<Record<string, string[]>>(
        (dependencies, table) => {
            dependencies[table.value] = table.dependencies

            return dependencies
        },
        {},
    ),
)

const tableLabels = computed(() =>
    props.tableOptions.reduce<Record<string, string>>((labels, table) => {
        labels[table.value] = table.label

        return labels
    }, {}),
)

const selectedTables = computed(() => expandTables(directTables.value))
const dependencyTables = computed(() =>
    selectedTables.value.filter((table) => !directTables.value.includes(table)),
)
const dependencySummary = computed(() =>
    dependencyTables.value.map((table) => tableLabels.value[table] ?? table),
)
const confirmationAccepted = computed(
    () => restoreForm.confirmation.trim().toUpperCase() === 'RESTORE',
)
const restoreProcessing = ref(false)
const restoreStatus = ref<'idle' | 'running' | 'accepted' | 'failed'>('idle')
const acceptedRestoreMessage = ref<string | null>(null)
const restoreStatusUrl = ref<string | null>(null)
const restoreStatusTimer = ref<number | null>(null)
const submittedMode = ref<'all' | 'tables'>('all')
const acceptedMessage = computed(
    () =>
        acceptedRestoreMessage.value ??
        (submittedMode.value === 'all'
            ? trans('database_backups.restore_started')
            : trans('database_backups.restored')),
)
const restoreError = computed(
    () =>
        (restoreForm.errors as Record<string, string | undefined>).restore ??
        null,
)
const statusMessage = computed(() => {
    if (
        restoreStatus.value === 'failed' &&
        !restoreForm.errors.confirmation &&
        !restoreForm.errors.mode &&
        !restoreError.value &&
        !restoreForm.errors.tables
    ) {
        return trans('database_backups.restore_request_failed')
    }

    return null
})
const statusSeverity = computed<'error' | 'info' | 'success'>(() => {
    if (restoreStatus.value === 'failed') {
        return 'error'
    }

    return 'info'
})

watch(
    () => props.visible,
    (visible) => {
        if (!visible) {
            return
        }

        restoreForm.defaults({
            confirmation: '',
            mode: 'all',
            tables: [],
        })
        restoreForm.reset()
        restoreForm.clearErrors()
        directTables.value = []
        acceptedRestoreMessage.value = null
        restoreStatusUrl.value = null
        stopRestoreStatusPolling()
        restoreStatus.value = 'idle'
        submittedMode.value = 'all'
    },
)

watch(selectedTables, (tables) => {
    restoreForm.tables = tables
})

function expandTables(tables: string[]): string[] {
    const expanded: string[] = []
    const queue = [...new Set(tables)]

    while (queue.length > 0) {
        const table = queue.shift()

        if (!table || expanded.includes(table)) {
            continue
        }

        expanded.push(table)

        for (const dependency of dependencyMap.value[table] ?? []) {
            if (!expanded.includes(dependency)) {
                queue.push(dependency)
            }
        }
    }

    return expanded
}

function isDependencyOnly(table: string): boolean {
    return (
        selectedTables.value.includes(table) &&
        !directTables.value.includes(table)
    )
}

function toggleTable(table: string): void {
    if (isDependencyOnly(table)) {
        return
    }

    directTables.value = directTables.value.includes(table)
        ? directTables.value.filter((value) => value !== table)
        : [...directTables.value, table]
}

function selectRestoreMode(mode: 'all' | 'tables'): void {
    restoreForm.mode = mode

    if (mode === 'all') {
        directTables.value = []
        restoreForm.tables = []
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

function markRestoreAccepted(message?: string): void {
    acceptedRestoreMessage.value = message ?? null
    restoreStatus.value = 'accepted'
    emit('restore-accepted', acceptedMessage.value)
    dialogVisible.value = false
}

function markRestoreFailed(message?: string): void {
    const restoreFailedMessage =
        message ?? trans('database_backups.restore_failed')

    restoreStatus.value = 'failed'
    emit('restore-failed', restoreFailedMessage)
    dialogVisible.value = false
}

function statusUrlFor(restoreId: string): string {
    return props.statusUrlTemplate.replace(
        '__RESTORE__',
        encodeURIComponent(restoreId),
    )
}

function stopRestoreStatusPolling(): void {
    if (restoreStatusTimer.value === null) {
        return
    }

    window.clearInterval(restoreStatusTimer.value)
    restoreStatusTimer.value = null
}

function restoreStatusMessage(
    restore: RestoreStatusResponse['restore'] | undefined,
    fallbackKey: string,
): string {
    if (restore?.message_key) {
        return trans(restore.message_key)
    }

    return restore?.message ?? trans(fallbackKey)
}

async function pollRestoreStatus(): Promise<void> {
    if (restoreStatusUrl.value === null) {
        return
    }

    let body: RestoreStatusResponse | undefined

    try {
        const response = await fetch(restoreStatusUrl.value, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
            method: 'GET',
        })

        body = (await response.json().catch(() => ({}))) as
            | RestoreStatusResponse
            | undefined
    } catch {
        return
    }

    const status = body?.restore?.status

    if (status === 'completed') {
        stopRestoreStatusPolling()
        markRestoreAccepted(
            restoreStatusMessage(body?.restore, 'database_backups.restored'),
        )

        return
    }

    if (status === 'failed' || status === 'unknown') {
        markRestoreFailed(
            restoreStatusMessage(
                body?.restore,
                'database_backups.restore_failed',
            ),
        )
        stopRestoreStatusPolling()
    }
}

function startRestoreStatusPolling(statusUrl: string): void {
    restoreStatusUrl.value = statusUrl
    stopRestoreStatusPolling()
    restoreStatusTimer.value = window.setInterval(() => {
        void pollRestoreStatus()
    }, 2000)
    void pollRestoreStatus()
}

function setRestoreErrors(errors: Record<string, string[]>): void {
    Object.entries(errors).forEach(([field, messages]) => {
        const message = messages[0]

        if (message) {
            restoreForm.setError(
                field as keyof typeof restoreForm.errors,
                message,
            )
        }
    })
}

async function submitRestore(): Promise<void> {
    if (props.backup === null || props.restoreUrl === '') {
        return
    }

    const mode = restoreForm.mode

    submittedMode.value = mode
    restoreForm.confirmation = restoreForm.confirmation.trim().toUpperCase()
    restoreForm.tables = mode === 'tables' ? selectedTables.value : []

    restoreForm.clearErrors()
    acceptedRestoreMessage.value = null
    restoreProcessing.value = true
    restoreStatus.value = 'running'

    try {
        const token = csrfToken()
        const xsrf = xsrfToken()
        const response = await fetch(props.restoreUrl, {
            body: JSON.stringify({
                confirmation: restoreForm.confirmation,
                mode,
                tables: mode === 'tables' ? selectedTables.value : [],
            }),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
            },
            method: 'POST',
        })

        const body = (await response.json().catch(() => ({}))) as
            | (RestoreErrorResponse & RestoreStartResponse)
            | undefined

        if (!response.ok) {
            if (body?.errors) {
                setRestoreErrors(body.errors)
                markRestoreFailed(Object.values(body.errors).flat()[0])
            } else {
                markRestoreFailed(
                    body?.message ??
                        trans('database_backups.restore_request_failed'),
                )
            }

            return
        }

        acceptedRestoreMessage.value = body?.message ?? null
        emit(
            'restore-started',
            body?.message ?? trans('database_backups.restore_requesting'),
        )
        dialogVisible.value = false

        if (body?.restore?.status_url) {
            startRestoreStatusPolling(body.restore.status_url)
        } else if (body?.restore?.id) {
            startRestoreStatusPolling(statusUrlFor(body.restore.id))
        } else {
            markRestoreAccepted(body?.message)
        }
    } catch {
        markRestoreFailed(trans('database_backups.restore_request_failed'))
    } finally {
        restoreProcessing.value = false
    }
}

onUnmounted(() => {
    stopRestoreStatusPolling()
})
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :draggable="false"
        :header="trans('database_backups.restore_title')"
        class="w-[min(42rem,calc(100vw-2rem))]"
    >
        <form class="grid gap-5" @submit.prevent="submitRestore">
            <div
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
            >
                <div class="flex items-start gap-3">
                    <AppIcon name="triangle-alert" class="mt-0.5 shrink-0" />
                    <p>
                        {{ trans('database_backups.restore_warning') }}
                    </p>
                </div>
            </div>

            <div v-if="backup" class="grid gap-1">
                <span class="text-xs text-[var(--cp-text-muted)]">
                    {{ trans('database_backups.file') }}
                </span>
                <strong
                    class="break-all font-mono text-sm text-[var(--cp-text-primary)]"
                >
                    {{ backup.name }}
                </strong>
            </div>

            <Message
                v-if="statusMessage"
                :closable="false"
                :severity="statusSeverity"
            >
                {{ statusMessage }}
            </Message>

            <section class="grid gap-3">
                <h3
                    class="text-base font-semibold text-[var(--cp-text-primary)]"
                >
                    {{ trans('database_backups.restore_scope') }}
                </h3>
                <div class="grid gap-2 sm:grid-cols-2">
                    <Button
                        :outlined="restoreForm.mode !== 'all'"
                        severity="secondary"
                        type="button"
                        @click="selectRestoreMode('all')"
                    >
                        {{ trans('database_backups.restore_all') }}
                    </Button>
                    <Button
                        :disabled="tableOptions.length === 0"
                        :outlined="restoreForm.mode !== 'tables'"
                        severity="secondary"
                        type="button"
                        @click="selectRestoreMode('tables')"
                    >
                        {{ trans('database_backups.restore_tables') }}
                    </Button>
                </div>
            </section>

            <section v-if="restoreForm.mode === 'tables'" class="grid gap-3">
                <div
                    class="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900"
                >
                    <div class="flex items-start gap-2">
                        <AppIcon name="info" class="mt-0.5 shrink-0" />
                        <p>
                            {{
                                trans('database_backups.restore_tables_warning')
                            }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="dependencySummary.length > 0"
                    class="rounded-md border border-[var(--cp-surface-border)] bg-[var(--cp-surface-muted)] p-3 text-sm text-[var(--cp-text-secondary)]"
                >
                    {{
                        trans('database_backups.restore_dependency_summary', {
                            tables: dependencySummary.join(', '),
                        })
                    }}
                </div>
                <div
                    class="grid max-h-56 gap-2 overflow-y-auto rounded-md border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-3 sm:grid-cols-2"
                >
                    <label
                        v-for="table in tableOptions"
                        :key="table.value"
                        class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-[var(--cp-text-primary)]"
                    >
                        <Checkbox
                            :model-value="selectedTables.includes(table.value)"
                            binary
                            :disabled="isDependencyOnly(table.value)"
                            @update:model-value="toggleTable(table.value)"
                        />
                        <span class="truncate font-mono">{{
                            table.label
                        }}</span>
                        <Badge
                            v-if="isDependencyOnly(table.value)"
                            :value="
                                trans(
                                    'database_backups.restore_dependency_badge',
                                )
                            "
                            severity="info"
                        />
                    </label>
                </div>
                <small v-if="restoreForm.errors.tables" class="text-red-600">
                    {{ restoreForm.errors.tables }}
                </small>
            </section>

            <label
                class="grid gap-1 text-sm font-medium text-[var(--cp-text-secondary)]"
            >
                {{ trans('database_backups.restore_confirmation_label') }}
                <input
                    v-model="restoreForm.confirmation"
                    class="rounded-md border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-3 py-2 font-mono text-[var(--cp-text-primary)]"
                    type="text"
                />
            </label>
            <small v-if="restoreForm.errors.confirmation" class="text-red-600">
                {{ restoreForm.errors.confirmation }}
            </small>
            <small v-if="restoreForm.errors.mode" class="text-red-600">
                {{ restoreForm.errors.mode }}
            </small>
            <small v-if="restoreError" class="text-red-600">
                {{ restoreError }}
            </small>
            <div
                class="flex justify-end gap-2 border-t border-[var(--cp-surface-border)] pt-4"
            >
                <Button
                    severity="secondary"
                    type="button"
                    :disabled="restoreStatus === 'running'"
                    @click="dialogVisible = false"
                >
                    {{ trans('database_backups.cancel') }}
                </Button>
                <Button
                    :disabled="
                        !confirmationAccepted ||
                        restoreStatus === 'accepted' ||
                        restoreStatus === 'running' ||
                        (restoreForm.mode === 'tables' &&
                            selectedTables.length === 0)
                    "
                    severity="danger"
                    type="button"
                    @click="submitRestore"
                >
                    <AppIcon
                        name="refresh-cw"
                        class="cp-icon"
                        :class="{
                            'animate-spin':
                                restoreProcessing ||
                                restoreStatus === 'running',
                        }"
                    />
                    <span>{{ trans('database_backups.restore') }}</span>
                </Button>
            </div>
        </form>
    </Dialog>
</template>
