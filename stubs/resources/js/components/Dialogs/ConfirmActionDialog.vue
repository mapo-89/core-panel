<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'

const props = withDefaults(
    defineProps<{
        cancelLabel: string
        confirmLabel: string
        confirmSeverity?:
            | 'contrast'
            | 'danger'
            | 'help'
            | 'info'
            | 'primary'
            | 'secondary'
            | 'success'
            | 'warn'
        description?: string | null
        disabled?: boolean
        icon?: string
        loading?: boolean
        message: string
        title: string
        tone?: 'danger' | 'info' | 'success' | 'warning'
        visible: boolean
        widthClass?: string
    }>(),
    {
        confirmSeverity: 'primary',
        description: null,
        disabled: false,
        icon: 'triangle-alert',
        loading: false,
        tone: 'warning',
        widthClass: 'w-full max-w-md',
    },
)

const emit = defineEmits<{
    cancel: []
    confirm: []
    'update:visible': [value: boolean]
}>()

const dialogVisible = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value),
})

const iconToneClass = computed(() => {
    return {
        'cp-confirm-dialog__icon--danger': props.tone === 'danger',
        'cp-confirm-dialog__icon--info': props.tone === 'info',
        'cp-confirm-dialog__icon--success': props.tone === 'success',
        'cp-confirm-dialog__icon--warning': props.tone === 'warning',
    }
})

function close(): void {
    if (props.loading) {
        return
    }

    dialogVisible.value = false
}

function cancel(): void {
    close()
    emit('cancel')
}

function confirm(): void {
    emit('confirm')
}
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :class="widthClass"
        :closable="!loading"
        :dismissable-mask="!loading"
        :header="title"
    >
        <div class="cp-confirm-dialog">
            <div class="cp-confirm-dialog__icon" :class="iconToneClass">
                <AppIcon :name="icon" />
            </div>

            <div class="cp-confirm-dialog__copy">
                <p class="cp-confirm-dialog__message">
                    {{ message }}
                </p>
                <p v-if="description" class="cp-confirm-dialog__description">
                    {{ description }}
                </p>
            </div>

            <div class="cp-confirm-dialog__actions">
                <Button
                    :label="cancelLabel"
                    severity="secondary"
                    text
                    type="button"
                    :disabled="disabled || loading"
                    @click="cancel"
                />
                <Button
                    :label="confirmLabel"
                    :severity="confirmSeverity"
                    type="button"
                    :disabled="disabled"
                    :loading="loading"
                    @click="confirm"
                />
            </div>
        </div>
    </Dialog>
</template>
