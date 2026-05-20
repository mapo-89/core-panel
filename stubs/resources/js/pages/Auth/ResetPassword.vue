<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import AppIcon from '@/components/AppIcon.vue'
import { useTranslatedAuthErrors } from '@/composables/useTranslatedAuthErrors'
import AuthLayout from '@/layouts/AuthLayout.vue'
import TranslatedPassword from '@/components/TranslatedPassword.vue'
import password from '@/routes/password'

const props = defineProps<{
    email: string | null
    token: string
}>()

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
})
const { clearTranslatedAuthErrors, translatedAuthError } =
    useTranslatedAuthErrors([
        'email',
        'password',
        'password_confirmation',
    ] as const)

function submit(): void {
    clearTranslatedAuthErrors()
    form.post(password.update.url())
}
</script>

<template>
    <AuthLayout
        :heading="$t('page-auth.reset_heading')"
        :subheading="$t('page-auth.reset_subheading')"
    >
        <Head :title="$t('common.auth.reset_password')" />

        <form class="auth-form" @submit.prevent="submit">
            <div class="auth-form__field">
                <label class="auth-form__label" for="email">{{
                    $t('common.auth.email')
                }}</label>
                <IconField>
                    <InputIcon>
                        <AppIcon name="envelope" />
                    </InputIcon>
                    <InputText
                        id="email"
                        v-model="form.email"
                        autocomplete="email"
                        :invalid="Boolean(form.errors.email)"
                        fluid
                    />
                </IconField>
                <small v-if="form.errors.email" class="auth-form__field-error">
                    {{ translatedAuthError('email', form.errors.email) }}
                </small>
            </div>

            <div class="auth-form__field">
                <label class="auth-form__label" for="password">{{
                    $t('common.auth.new_password')
                }}</label>
                <IconField>
                    <InputIcon>
                        <AppIcon name="lock" />
                    </InputIcon>
                    <TranslatedPassword
                        id="password"
                        v-model="form.password"
                        autocomplete="new-password"
                        :invalid="Boolean(form.errors.password)"
                        fluid
                        :min-length="12"
                        toggle-mask
                    />
                </IconField>
                <small
                    v-if="form.errors.password"
                    class="auth-form__field-error"
                >
                    {{ translatedAuthError('password', form.errors.password) }}
                </small>
            </div>

            <div class="auth-form__field">
                <label class="auth-form__label" for="password_confirmation">{{
                    $t('page-auth.confirm_password')
                }}</label>
                <IconField>
                    <InputIcon>
                        <AppIcon name="lock" />
                    </InputIcon>
                    <TranslatedPassword
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                        :invalid="Boolean(form.errors.password_confirmation)"
                        fluid
                        :match-password="form.password"
                        toggle-mask
                    />
                </IconField>
                <small
                    v-if="form.errors.password_confirmation"
                    class="auth-form__field-error"
                >
                    {{
                        translatedAuthError(
                            'password_confirmation',
                            form.errors.password_confirmation,
                        )
                    }}
                </small>
            </div>

            <Button
                class="auth-form__submit"
                :disabled="form.processing"
                :loading="form.processing"
                type="submit"
            >
                <AppIcon name="key" />
                <span>{{ $t('common.auth.reset_password') }}</span>
            </Button>
        </form>
    </AuthLayout>
</template>
