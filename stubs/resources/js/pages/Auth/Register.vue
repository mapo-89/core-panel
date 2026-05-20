<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import Message from 'primevue/message'

import AppIcon from '@/components/AppIcon.vue'
import { useTranslatedAuthErrors } from '@/composables/useTranslatedAuthErrors'
import AuthLayout from '@/layouts/AuthLayout.vue'
import TranslatedPassword from '@/components/TranslatedPassword.vue'

const form = useForm({
    email: '',
    first_name: '',
    last_name: '',
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
    form.post('/register')
}
</script>

<template>
    <AuthLayout
        :heading="$t('page-auth.register_heading')"
        :subheading="$t('page-auth.register_subheading')"
    >
        <Head :title="$t('common.auth.register')" />

        <form class="auth-form" @submit.prevent="submit">
            <Message v-if="form.hasErrors" severity="error">{{
                $t('page-auth.form_review_errors')
            }}</Message>

            <div class="auth-form__grid auth-form__grid--two">
                <div class="auth-form__field">
                    <label class="auth-form__label" for="first_name">{{
                        $t('common.ui.first_name')
                    }}</label>
                    <IconField>
                        <InputIcon>
                            <AppIcon name="user" />
                        </InputIcon>
                        <InputText
                            id="first_name"
                            v-model="form.first_name"
                            autocomplete="given-name"
                            fluid
                            :placeholder="
                                $t('page-auth.register_name_placeholder')
                            "
                        />
                    </IconField>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="last_name">{{
                        $t('common.ui.last_name')
                    }}</label>
                    <IconField>
                        <InputIcon>
                            <AppIcon name="user" />
                        </InputIcon>
                        <InputText
                            id="last_name"
                            v-model="form.last_name"
                            autocomplete="family-name"
                            fluid
                            :placeholder="
                                $t('page-auth.register_last_name_placeholder')
                            "
                        />
                    </IconField>
                </div>
            </div>

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

            <div class="auth-form__field">
                <label class="auth-form__label" for="password">{{
                    $t('common.auth.password')
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
                <AppIcon name="user-plus" />
                <span>{{ $t('common.auth.register') }}</span>
            </Button>
        </form>

        <template #footer>
            <Link class="auth-link" href="/login">
                {{ $t('page-auth.back_to_login') }}
            </Link>
        </template>
    </AuthLayout>
</template>
