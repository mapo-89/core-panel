<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Message from 'primevue/message'

import AppIcon from '@/components/AppIcon.vue'
import { useTranslatedAuthErrors } from '@/composables/useTranslatedAuthErrors'
import AuthLayout from '@/layouts/AuthLayout.vue'
import password from '@/routes/password'

const page = usePage<{
    flash?: {
        status?: string | null
    }
}>()
const prefilledEmail =
    new URL(page.url, 'http://localhost').searchParams.get('email') ?? ''

const form = useForm({
    email: prefilledEmail,
})
const { clearTranslatedAuthErrors, translatedAuthError } =
    useTranslatedAuthErrors(['email'] as const)

function submit(): void {
    clearTranslatedAuthErrors()
    form.post(password.email.url())
}
</script>

<template>
    <AuthLayout
        :heading="$t('page-auth.forgot_heading')"
        :subheading="$t('page-auth.forgot_subheading')"
    >
        <Head :title="$t('common.auth.forgot_password')" />

        <form class="auth-form" @submit.prevent="submit">
            <Message
                v-if="page.props.flash?.status"
                class="auth-status"
                severity="success"
                >{{ page.props.flash?.status }}</Message
            >

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
                        :placeholder="$t('page-auth.login_email_placeholder')"
                    />
                </IconField>
                <small v-if="form.errors.email" class="auth-form__field-error">
                    {{ translatedAuthError('email', form.errors.email) }}
                </small>
            </div>

            <Button
                class="auth-form__submit"
                :disabled="form.processing"
                :loading="form.processing"
                type="submit"
            >
                <AppIcon name="envelope" />
                <span>{{ $t('page-auth.send_reset_link') }}</span>
            </Button>
        </form>

        <template #footer>
            <Link class="auth-link" href="/login">
                {{ $t('page-auth.back_to_login') }}
            </Link>
        </template>
    </AuthLayout>
</template>
