<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { computed, watch } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import { useDateTime } from '@core-panel/composables/useDateTime'
import systemUpdates from '@/routes/core-panel/system-updates'

type SelectOption = {
    label: string
    value: string
}

type AutomaticSystemUpdateSettings = {
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
}

const props = defineProps<{
    settings: AutomaticSystemUpdateSettings
    visible: boolean
}>()

const emit = defineEmits<{
    'update:visible': [visible: boolean]
}>()

const { formatDateTime } = useDateTime()

const dialogVisible = computed({
    get: () => props.visible,
    set: (visible: boolean) => emit('update:visible', visible),
})

const settingsForm = useForm({
    automatic_enabled: props.settings.enabled,
    interval: props.settings.interval,
    maintenance_window_enabled: props.settings.maintenanceWindowEnabled,
    mode: props.settings.mode,
    time: props.settings.time,
    weekday: props.settings.weekday ?? 'monday',
    window_end: props.settings.windowEnd,
    window_start: props.settings.windowStart,
})

watch(
    () => props.visible,
    (visible) => {
        if (!visible) {
            return
        }

        settingsForm.defaults({
            automatic_enabled: props.settings.enabled,
            interval: props.settings.interval,
            maintenance_window_enabled: props.settings.maintenanceWindowEnabled,
            mode: props.settings.mode,
            time: props.settings.time,
            weekday: props.settings.weekday ?? 'monday',
            window_end: props.settings.windowEnd,
            window_start: props.settings.windowStart,
        })
        settingsForm.reset()
        settingsForm.clearErrors()
    },
)

function saveSettings(): void {
    settingsForm.put(systemUpdates.settings.update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false
        },
    })
}
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :draggable="false"
        :header="trans('system_updates.settings_title')"
        class="w-[min(46rem,calc(100vw-2rem))]"
    >
        <form class="flex flex-col gap-5" @submit.prevent="saveSettings">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3
                        class="text-base font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ trans('system_updates.automatic_title') }}
                    </h3>
                    <p class="mt-1 text-sm text-[var(--cp-text-secondary)]">
                        {{ trans('system_updates.automatic_description') }}
                    </p>
                </div>
                <div
                    class="flex min-w-[4.5rem] shrink-0 justify-end self-start"
                >
                    <ToggleSwitch
                        v-model="settingsForm.automatic_enabled"
                        :disabled="settingsForm.processing"
                        :aria-label="trans('system_updates.automatic_enabled')"
                    />
                </div>
            </div>

            <Message
                v-if="settingsForm.errors.automatic_updates"
                :closable="false"
                severity="error"
            >
                {{ settingsForm.errors.automatic_updates }}
            </Message>

            <div class="grid gap-4 md:grid-cols-2">
                <label
                    class="flex flex-col gap-1 text-sm font-medium text-[var(--cp-text-secondary)]"
                >
                    {{ trans('system_updates.interval') }}
                    <Select
                        v-model="settingsForm.interval"
                        :disabled="
                            !settingsForm.automatic_enabled ||
                            settingsForm.processing
                        "
                        option-label="label"
                        option-value="value"
                        :options="settings.intervalOptions"
                    />
                    <small
                        v-if="settingsForm.errors.interval"
                        class="text-red-600"
                    >
                        {{ settingsForm.errors.interval }}
                    </small>
                </label>

                <label
                    class="flex flex-col gap-1 text-sm font-medium text-[var(--cp-text-secondary)]"
                >
                    {{ trans('system_updates.execution_time') }}
                    <input
                        v-model="settingsForm.time"
                        class="rounded-md border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-3 py-2 text-[var(--cp-text-primary)] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="
                            !settingsForm.automatic_enabled ||
                            settingsForm.processing
                        "
                        type="time"
                    />
                    <small v-if="settingsForm.errors.time" class="text-red-600">
                        {{ settingsForm.errors.time }}
                    </small>
                </label>

                <label
                    v-if="settingsForm.interval === 'weekly'"
                    class="flex flex-col gap-1 text-sm font-medium text-[var(--cp-text-secondary)]"
                >
                    {{ trans('system_updates.weekday') }}
                    <Select
                        v-model="settingsForm.weekday"
                        :disabled="
                            !settingsForm.automatic_enabled ||
                            settingsForm.processing
                        "
                        option-label="label"
                        option-value="value"
                        :options="settings.weekdayOptions"
                    />
                    <small
                        v-if="settingsForm.errors.weekday"
                        class="text-red-600"
                    >
                        {{ settingsForm.errors.weekday }}
                    </small>
                </label>

                <label
                    class="flex flex-col gap-1 text-sm font-medium text-[var(--cp-text-secondary)]"
                >
                    {{ trans('system_updates.mode') }}
                    <Select
                        v-model="settingsForm.mode"
                        :disabled="
                            !settingsForm.automatic_enabled ||
                            settingsForm.processing
                        "
                        option-label="label"
                        option-value="value"
                        :options="settings.modeOptions"
                    />
                    <small v-if="settingsForm.errors.mode" class="text-red-600">
                        {{ settingsForm.errors.mode }}
                    </small>
                </label>
            </div>

            <div
                v-if="settingsForm.mode === 'install'"
                class="grid gap-4 rounded-md bg-[var(--cp-surface-muted)] p-4"
            >
                <div class="flex items-center justify-between gap-4">
                    <span
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                    >
                        {{ trans('system_updates.maintenance_window_enabled') }}
                    </span>
                    <ToggleSwitch
                        v-model="settingsForm.maintenance_window_enabled"
                        :disabled="
                            !settingsForm.automatic_enabled ||
                            settingsForm.processing
                        "
                        :aria-label="
                            trans('system_updates.maintenance_window_enabled')
                        "
                    />
                </div>
                <div
                    v-if="settingsForm.maintenance_window_enabled"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <label
                        class="flex flex-col gap-1 text-sm font-medium text-[var(--cp-text-secondary)]"
                    >
                        {{ trans('system_updates.window_start') }}
                        <input
                            v-model="settingsForm.window_start"
                            class="rounded-md border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-3 py-2 text-[var(--cp-text-primary)] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="
                                !settingsForm.automatic_enabled ||
                                settingsForm.processing
                            "
                            type="time"
                        />
                        <small
                            v-if="settingsForm.errors.window_start"
                            class="text-red-600"
                        >
                            {{ settingsForm.errors.window_start }}
                        </small>
                    </label>
                    <label
                        class="flex flex-col gap-1 text-sm font-medium text-[var(--cp-text-secondary)]"
                    >
                        {{ trans('system_updates.window_end') }}
                        <input
                            v-model="settingsForm.window_end"
                            class="rounded-md border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-3 py-2 text-[var(--cp-text-primary)] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="
                                !settingsForm.automatic_enabled ||
                                settingsForm.processing
                            "
                            type="time"
                        />
                        <small
                            v-if="settingsForm.errors.window_end"
                            class="text-red-600"
                        >
                            {{ settingsForm.errors.window_end }}
                        </small>
                    </label>
                </div>
            </div>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-[var(--cp-text-muted)]">
                        {{ trans('system_updates.timezone') }}
                    </dt>
                    <dd class="font-medium text-[var(--cp-text-primary)]">
                        {{ settings.timezone }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[var(--cp-text-muted)]">
                        {{ trans('system_updates.last_automatic_run') }}
                    </dt>
                    <dd class="font-medium text-[var(--cp-text-primary)]">
                        {{
                            settings.lastAutomaticRunAt
                                ? formatDateTime(settings.lastAutomaticRunAt)
                                : trans(
                                      'system_updates.last_automatic_run_never',
                                  )
                        }}
                    </dd>
                </div>
            </dl>

            <div
                class="flex justify-end gap-2 border-t border-[var(--cp-surface-border)] pt-4"
            >
                <Button
                    :disabled="settingsForm.processing"
                    severity="secondary"
                    type="button"
                    @click="dialogVisible = false"
                >
                    {{ trans('common.ui.cancel') }}
                </Button>
                <Button :disabled="settingsForm.processing" type="submit">
                    <AppIcon
                        :name="settingsForm.processing ? 'refresh-cw' : 'save'"
                        class="cp-icon"
                        :class="{ 'animate-spin': settingsForm.processing }"
                    />
                    <span>{{ trans('system_updates.save_settings') }}</span>
                </Button>
            </div>
        </form>
    </Dialog>
</template>
