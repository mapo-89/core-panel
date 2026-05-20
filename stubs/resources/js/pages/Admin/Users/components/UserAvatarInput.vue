<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { useToast } from 'primevue/usetoast'
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import AvatarUploadDropzone from '@/components/AvatarUploadDropzone.vue'

const props = defineProps<{
    currentAvatarUrl?: string | null
    disabled?: boolean
    error?: string
    initials: string
    modelValue: File | null
    removeAvatar?: boolean
}>()

const emit = defineEmits<{
    'update:removeAvatar': [value: boolean]
    'update:modelValue': [value: File | null]
}>()

const toast = useToast()
const effectiveCurrentAvatarUrl = computed(() => {
    return props.removeAvatar ? null : (props.currentAvatarUrl ?? null)
})

function notifyInvalidFile(): void {
    toast.add({
        severity: 'error',
        summary: trans('common.ui.error'),
        detail: trans('page-settings.avatar_invalid_type'),
        life: 3000,
    })
}

function handleAvatarSelection(file: File | null): void {
    emit('update:modelValue', file)

    if (file !== null) {
        emit('update:removeAvatar', false)
    }
}

function clearAvatar(clearSelectedFile: () => void): void {
    clearSelectedFile()
    emit('update:removeAvatar', Boolean(props.currentAvatarUrl))
}
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-[0.35rem]">
            <h3
                class="m-0 text-base font-semibold text-[var(--cp-text-primary)]"
            >
                {{ $t('page-users.avatar') }}
            </h3>
        </div>

        <div
            class="grid gap-4 rounded-[var(--cp-radius-lg)] border border-[var(--cp-border-subtle)] bg-[var(--cp-surface-panel)] p-4"
        >
            <AvatarUploadDropzone
                :current-avatar-url="effectiveCurrentAvatarUrl"
                :disabled="disabled"
                :hint="$t('page-users.avatar_description')"
                :initials="initials"
                layout="stacked"
                :model-value="modelValue"
                size="xl"
                variant="compact"
                @invalid-file="notifyInvalidFile"
                @update:model-value="handleAvatarSelection"
            >
                <template
                    #actions="{ clearSelectedFile, hasSelection, openPicker }"
                >
                    <div class="flex flex-wrap justify-center gap-2">
                        <Button
                            outlined
                            severity="primary"
                            size="small"
                            type="button"
                            @click="openPicker"
                        >
                            <AppIcon name="upload" />
                            <span>{{ $t('common.ui.upload') }}</span>
                        </Button>

                        <Button
                            v-if="hasSelection || effectiveCurrentAvatarUrl"
                            severity="secondary"
                            size="small"
                            text
                            type="button"
                            @click="clearAvatar(clearSelectedFile)"
                        >
                            <AppIcon name="x" />
                            <span>{{ $t('common.ui.remove') }}</span>
                        </Button>
                    </div>
                </template>
            </AvatarUploadDropzone>
        </div>

        <Message v-if="error" severity="error" size="small" variant="simple">
            {{ error }}
        </Message>
    </div>
</template>
