<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { computed } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'

type RestartState = 'failed' | 'running' | 'success' | 'timeout'

const props = defineProps<{
    appName: string
    failureMessage?: string | null
    state: RestartState
    visible: boolean
}>()

const emit = defineEmits<{
    close: []
}>()

const canClose = computed(() => props.state !== 'running')
const icon = computed(() => {
    if (props.state === 'success') {
        return 'circle-check-big'
    }

    if (props.state === 'failed') {
        return 'x'
    }

    if (props.state === 'timeout') {
        return 'triangle-alert'
    }

    return 'rotate-cw'
})
const iconClass = computed(() => ({
    'animate-spin text-[var(--p-primary-color)]': props.state === 'running',
    'text-[var(--cp-color-danger)]': props.state === 'failed',
    'text-[var(--cp-color-success)]': props.state === 'success',
    'text-[var(--cp-color-warning)]': props.state === 'timeout',
}))
const message = computed(() => {
    if (props.state === 'success') {
        return trans('system_updates.update_completed')
    }

    if (props.state === 'failed') {
        return props.failureMessage || trans('system_updates.update_failed')
    }

    return trans('system_updates.restart_dialog_message')
})

function handleVisibleChange(visible: boolean): void {
    if (!visible && canClose.value) {
        emit('close')
    }
}
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        block-scroll
        :closable="canClose"
        :close-on-escape="canClose"
        :dismissable-mask="false"
        class="mx-4 w-[calc(100vw-2rem)] max-w-lg"
        :header="trans('system_updates.restart_dialog_title', { appName })"
        :pt="{
            header: { class: 'relative' },
            headerActions: { class: 'absolute right-5' },
            title: { class: 'w-full text-center' },
        }"
        @update:visible="handleVisibleChange"
    >
        <div class="flex flex-col items-center gap-4 px-2 py-4 text-center">
            <AppIcon
                :name="icon"
                class="cp-icon h-10 w-10"
                :class="iconClass"
            />
            <p class="max-w-md text-base text-[var(--cp-text-primary)]">
                {{ message }}
            </p>
            <p
                v-if="state === 'running' || state === 'timeout'"
                class="max-w-md text-sm text-[var(--cp-text-secondary)]"
                role="status"
            >
                {{
                    state === 'timeout'
                        ? trans('system_updates.restart_dialog_timeout')
                        : trans('system_updates.restart_dialog_reload')
                }}
            </p>
            <Button
                v-if="canClose"
                :label="trans('system_updates.restart_dialog_close')"
                severity="secondary"
                @click="emit('close')"
            />
        </div>
    </Dialog>
</template>
