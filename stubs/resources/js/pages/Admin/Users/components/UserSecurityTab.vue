<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { useDialog } from 'primevue/usedialog'
import { ref } from 'vue'

import ConfirmActionDialog from '@core-panel/components/Dialogs/ConfirmActionDialog.vue'
import UserPasswordResetDialog from '@/pages/Admin/Users/components/UserPasswordResetDialog.vue'
import userPasswordRoutes from '@/routes/core-panel/users/password'
import users from '@/routes/core-panel/users'
import type { UserCapabilities, UserRecord } from '@core-panel/types/core-panel'

const props = defineProps<{
    canHardResetPassword: boolean
    capabilities: UserCapabilities
    user: UserRecord
}>()

const dialog = useDialog()
const deleteDialogVisible = ref(false)
const resetLinkForm = useForm({})

function forceDeleteUser(): void {
    router.delete(users.forceDelete.url(props.user.id), {
        onFinish: () => {
            deleteDialogVisible.value = false
        },
    })
}

function sendResetLink(): void {
    resetLinkForm.post(userPasswordRoutes.resetLink.url(props.user.id), {
        preserveScroll: true,
    })
}

function openDirectResetDialog(): void {
    dialog.open(UserPasswordResetDialog, {
        data: {
            user: props.user,
        },
        props: {
            header: trans('page-users.password_reset_directly'),
            modal: true,
            style: {
                width: 'min(34rem, 92vw)',
            },
        },
    })
}
</script>

<template>
    <div class="cp-user-profile__workspace">
        <ConfirmActionDialog
            v-model:visible="deleteDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('common.ui.force_delete')"
            confirm-severity="danger"
            :description="$t('page-users.force_delete_current_message')"
            icon="trash"
            :message="$t('common.ui.force_delete')"
            :title="$t('common.ui.force_delete')"
            tone="danger"
            @confirm="forceDeleteUser"
        />

        <section class="cp-card grid gap-5 p-6">
            <div class="cp-user-profile__section-copy">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{ $t('page-users.tab_security') }}
                </h2>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ $t('page-users.security_description') }}
                </p>
            </div>

            <div class="cp-user-profile__grid cp-user-profile__grid--double">
                <article class="cp-user-profile__card">
                    <h3>{{ $t('common.auth.reset_password') }}</h3>
                    <p>
                        {{ $t('page-users.password_reset_link_description') }}
                    </p>

                    <div class="cp-user-profile__actions">
                        <Button
                            v-if="user.canUpdate"
                            :label="$t('page-auth.send_reset_link')"
                            outlined
                            :loading="resetLinkForm.processing"
                            @click="sendResetLink"
                        />
                    </div>
                </article>

                <article
                    v-if="canHardResetPassword && user.canUpdate"
                    class="cp-user-profile__card"
                >
                    <h3>{{ $t('page-users.password_reset_directly') }}</h3>
                    <p>
                        {{
                            $t('page-users.password_reset_directly_description')
                        }}
                    </p>

                    <div class="cp-user-profile__actions">
                        <Button
                            :label="$t('page-users.password_reset_directly')"
                            outlined
                            severity="secondary"
                            @click="openDirectResetDialog"
                        />
                    </div>
                </article>

                <article
                    v-if="
                        capabilities.supportsSoftDeletes && user.canForceDelete
                    "
                    class="cp-user-profile__card"
                >
                    <h3>{{ $t('common.ui.force_delete') }}</h3>
                    <p>{{ $t('page-users.force_delete_current_message') }}</p>

                    <div class="cp-user-profile__actions">
                        <Button
                            :label="$t('common.ui.force_delete')"
                            severity="danger"
                            @click="deleteDialogVisible = true"
                        />
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
