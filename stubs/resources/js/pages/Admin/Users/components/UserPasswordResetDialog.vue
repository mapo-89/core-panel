<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { inject } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import TranslatedPassword from '@/components/TranslatedPassword.vue'
import userPasswordRoutes from '@/routes/core-panel/users/password'
import type { UserRecord } from '@/types/core-panel'

type DialogRef = {
    close: () => void
    data?: {
        onSaved?: () => void
        user: UserRecord
    }
}

const dialogRef = inject<{ value: DialogRef }>('dialogRef')
const onSaved = dialogRef?.value.data?.onSaved
const user = dialogRef?.value.data?.user

const form = useForm({
    password: '',
    password_confirmation: '',
})

function close(): void {
    dialogRef?.value.close()
}

function submit(): void {
    if (!user) {
        return
    }

    form.put(userPasswordRoutes.update.url(user.id), {
        onSuccess: () => {
            onSaved?.()
            close()
        },
        preserveScroll: true,
    })
}
</script>

<template>
    <form class="cp-user-form-dialog" @submit.prevent="submit">
        <div class="grid gap-5">
            <div class="cp-user-profile__section-copy">
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ user?.email }}
                </p>
            </div>

            <div class="grid gap-4">
                <div class="auth-form__field">
                    <label class="auth-form__label" for="password">
                        {{ $t('common.auth.new_password') }}
                    </label>
                    <TranslatedPassword
                        id="password"
                        v-model="form.password"
                        fluid
                        :invalid="Boolean(form.errors.password)"
                        :min-length="12"
                        toggle-mask
                    />
                    <small
                        v-if="form.errors.password"
                        class="auth-form__field-error"
                    >
                        {{ form.errors.password }}
                    </small>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="password_confirmation">
                        {{ $t('page-auth.confirm_password') }}
                    </label>
                    <TranslatedPassword
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        fluid
                        :invalid="Boolean(form.errors.password_confirmation)"
                        :match-password="form.password"
                        toggle-mask
                    />
                    <small
                        v-if="form.errors.password_confirmation"
                        class="auth-form__field-error"
                    >
                        {{ form.errors.password_confirmation }}
                    </small>
                </div>
            </div>
        </div>

        <div class="cp-user-form-dialog__actions">
            <Button severity="secondary" text type="button" @click="close">
                <AppIcon name="x" />
                <span>{{ $t('common.ui.cancel') }}</span>
            </Button>
            <Button :loading="form.processing" type="submit">
                <AppIcon name="key-round" />
                <span>{{ $t('page-users.password_reset_directly') }}</span>
            </Button>
        </div>
    </form>
</template>
