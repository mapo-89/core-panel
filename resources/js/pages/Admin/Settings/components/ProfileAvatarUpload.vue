<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'

import AvatarUploadDropzone from '@core-panel/components/AvatarUploadDropzone.vue'
import AppIcon from '@core-panel/components/AppIcon.vue'
import ConfirmActionDialog from '@core-panel/components/Dialogs/ConfirmActionDialog.vue'
import userAvatarRoutes from '@/routes/core-panel/users/avatar'

const props = withDefaults(
    defineProps<{
        avatarUrl?: string | null
        initials: string
        presenceStatus?: 'online' | 'away' | 'offline' | null
        reloadKeys?: string[]
        userId: string
    }>(),
    {
        avatarUrl: null,
        presenceStatus: null,
        reloadKeys: () => ['auth', 'flash'],
    },
)

const toast = useToast()
const currentUrl = ref<string | null>(props.avatarUrl ?? null)
const removeDialogVisible = ref(false)
const pendingFile = ref<File | null>(null)
const uploading = ref(false)

watch(
    () => props.avatarUrl,
    (value) => {
        currentUrl.value = value ?? null
    },
)

function getCsrfToken(): string | undefined {
    const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)

    return match ? decodeURIComponent(match[2]) : undefined
}

function refreshAvatarState(): void {
    router.reload({
        only: props.reloadKeys,
    })
}

function notifyInvalidFileType(): void {
    toast.add({
        severity: 'error',
        summary: trans('common.ui.error'),
        detail: trans('page-settings.avatar_invalid_type'),
        life: 3000,
    })
}

async function handleAvatarSelection(file: File | null): Promise<void> {
    pendingFile.value = file

    if (file !== null) {
        await uploadAvatar()
    }
}

async function uploadAvatar(): Promise<void> {
    if (pendingFile.value === null || uploading.value) {
        return
    }

    const previousUrl = props.avatarUrl ?? null
    uploading.value = true

    try {
        const formData = new FormData()
        formData.append('avatar', pendingFile.value)

        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }

        const xsrfToken = getCsrfToken()

        if (xsrfToken !== undefined) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        const response = await fetch(userAvatarRoutes.store.url(props.userId), {
            body: formData,
            credentials: 'same-origin',
            headers,
            method: 'POST',
        })

        if (!response.ok) {
            currentUrl.value = previousUrl
            pendingFile.value = null
            toast.add({
                severity: 'error',
                summary: trans('common.ui.error'),
                detail: trans('page-settings.avatar_upload_failed'),
                life: 3500,
            })

            return
        }

        const payload = (await response.json()) as {
            data?: {
                avatar_url?: string | null
            }
        }

        currentUrl.value = payload.data?.avatar_url ?? currentUrl.value
        pendingFile.value = null
        toast.add({
            severity: 'success',
            summary: trans('common.ui.saved'),
            detail: trans('page-settings.avatar_uploaded_status'),
            life: 2500,
        })
        refreshAvatarState()
    } catch {
        currentUrl.value = previousUrl
        pendingFile.value = null
        toast.add({
            severity: 'error',
            summary: trans('common.ui.error'),
            detail: trans('page-settings.avatar_upload_failed'),
            life: 3500,
        })
    } finally {
        uploading.value = false
    }
}

async function removeAvatar(): Promise<void> {
    uploading.value = true

    try {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }

        const xsrfToken = getCsrfToken()

        if (xsrfToken !== undefined) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        const response = await fetch(
            userAvatarRoutes.destroy.url(props.userId),
            {
                credentials: 'same-origin',
                headers,
                method: 'DELETE',
            },
        )

        if (!response.ok) {
            toast.add({
                severity: 'error',
                summary: trans('common.ui.error'),
                detail: trans('page-settings.avatar_remove_failed'),
                life: 3500,
            })

            return
        }

        currentUrl.value = null
        pendingFile.value = null
        removeDialogVisible.value = false
        toast.add({
            severity: 'success',
            summary: trans('common.ui.saved'),
            detail: trans('page-settings.avatar_removed_status'),
            life: 2500,
        })
        refreshAvatarState()
    } catch {
        toast.add({
            severity: 'error',
            summary: trans('common.ui.error'),
            detail: trans('page-settings.avatar_remove_failed'),
            life: 3500,
        })
    } finally {
        uploading.value = false
    }
}
</script>

<template>
    <div class="cp-avatar-upload">
        <ConfirmActionDialog
            v-model:visible="removeDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('page-settings.avatar_remove')"
            confirm-severity="danger"
            :description="$t('page-settings.avatar_remove_description')"
            :loading="uploading"
            :message="$t('page-settings.avatar_remove_confirm')"
            icon="trash"
            :title="$t('page-settings.avatar_remove_title')"
            tone="danger"
            @confirm="removeAvatar"
        />

        <div class="cp-avatar-upload__copy">
            <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                {{ $t('page-settings.avatar_title') }}
            </h2>
        </div>

        <AvatarUploadDropzone
            :current-avatar-url="currentUrl"
            :disabled="uploading"
            :initials="initials"
            layout="inline"
            :model-value="pendingFile"
            :overlay-icon="uploading ? 'refresh' : 'camera'"
            :overlay-spinning="uploading"
            :presence-status="presenceStatus ?? 'offline'"
            :show-badges="false"
            :show-hint="false"
            size="xl"
            variant="regular"
            :show-presence="false"
            @invalid-file="notifyInvalidFileType"
            @update:model-value="handleAvatarSelection"
        >
            <template #actions="{ openPicker }">
                <div class="cp-avatar-upload__actions">
                    <Button
                        class="gap-2"
                        outlined
                        size="small"
                        :disabled="uploading"
                        @click="openPicker"
                    >
                        <AppIcon name="upload" />
                        <span>{{ $t('page-settings.avatar_change') }}</span>
                    </Button>

                    <Button
                        v-if="currentUrl"
                        class="gap-2"
                        outlined
                        severity="danger"
                        size="small"
                        :disabled="uploading"
                        @click="removeDialogVisible = true"
                    >
                        <AppIcon name="trash" />
                        <span>{{ $t('page-settings.avatar_remove') }}</span>
                    </Button>
                </div>
            </template>
        </AvatarUploadDropzone>

        <div class="cp-avatar-upload__body">
            <div class="cp-avatar-upload__meta">
                <span
                    class="cp-avatar-upload__state"
                    :class="
                        currentUrl
                            ? 'cp-avatar-upload__state--success'
                            : 'cp-avatar-upload__state--warning'
                    "
                >
                    {{
                        currentUrl
                            ? $t('page-settings.avatar_uploaded')
                            : $t('page-settings.avatar_empty')
                    }}
                </span>
                <span
                    v-for="badge in ['JPG', 'PNG', 'WEBP', '10 MB']"
                    :key="badge"
                    class="cp-avatar-upload__state"
                >
                    {{ badge }}
                </span>
            </div>
        </div>
    </div>
</template>
