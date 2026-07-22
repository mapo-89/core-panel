<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppIcon from '@core-panel/components/AppIcon.vue'
import githubIcon from '@core-panel/assets/icons/github.svg'
import githubWhiteIcon from '@core-panel/assets/icons/github-white.svg'
import googleIcon from '@core-panel/assets/icons/google.png'
import AuthLayout from '@core-panel/layouts/AuthLayout.vue'
import microsoftIcon from '@core-panel/assets/icons/microsoft.svg'
import type { SocialProviderRecord } from '@core-panel/types/core-panel'
import { useTranslatedAuthErrors } from '@core-panel/composables/useTranslatedAuthErrors'
import login from '@/routes/login'
import socialite from '@/routes/socialite'
import { computed } from 'vue'

defineProps<{
    canRegister: boolean
    socialProviders: SocialProviderRecord[]
}>()

const page = usePage<{
    appName?: string
    errors?: Record<string, string>
    flash?: {
        status?: string | null
    }
}>()

const appName = computed(() => page.props.appName ?? 'CorePanel')
const socialiteError = computed(() => page.props.errors?.socialite ?? null)
const statusMessage = computed(() => page.props.flash?.status ?? null)
const { clearTranslatedAuthErrors, translatedAuthError } =
    useTranslatedAuthErrors(['email', 'password'] as const)
const emailError = computed(() =>
    translateLoginError('email', form.errors.email),
)
const passwordError = computed(() =>
    translateLoginError('password', form.errors.password),
)

const form = useForm({
    email: '',
    password: '',
    remember: true,
})

function providerIcon(provider: string): string | null {
    return (
        {
            google: googleIcon,
            microsoft: microsoftIcon,
        }[provider] ?? null
    )
}

function translateLoginError(
    field: 'email' | 'password',
    error: string | null | undefined,
): string | null {
    return translatedAuthError(field, error)
}

function submit(): void {
    clearTranslatedAuthErrors()
    form.clearErrors()

    form.transform((data) => ({
        ...data,
        remember: data.remember ? 'on' : '',
    })).post(login.store.url(), {
        onError: (errors) => {
            const fallbackError = errors.error ?? errors.message

            if (
                fallbackError &&
                !errors.email &&
                !errors.password &&
                !errors.socialite
            ) {
                form.setError('email', fallbackError)
            }
        },
    })
}
</script>

<template>
    <AuthLayout
        :heading="$t('page-auth.login_heading', { app_name: appName })"
        :subheading="$t('page-auth.login_subheading', { app_name: appName })"
        :show-subheading="true"
    >
        <Head :title="$t('common.auth.login')" />

        <template #header>
            <h2 class="auth-title">
                {{ $t('page-auth.login_heading', { app_name: appName }) }}
            </h2>
            <p class="auth-subtitle">
                {{ $t('page-auth.login_subheading', { app_name: appName }) }}
            </p>
        </template>

        <form class="auth-form" @submit.prevent="submit">
            <Message v-if="statusMessage" severity="success">
                {{ statusMessage }}
            </Message>

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
                        autocomplete="username"
                        :invalid="Boolean(form.errors.email)"
                        fluid
                        :placeholder="$t('page-auth.login_email_placeholder')"
                    />
                </IconField>
                <small
                    v-if="emailError"
                    id="login-email-error"
                    class="auth-form__field-error"
                >
                    {{ emailError }}
                </small>
            </div>

            <div class="auth-form__field auth-form__field--password">
                <label class="auth-form__label" for="password">{{
                    $t('common.auth.password')
                }}</label>
                <IconField>
                    <InputIcon>
                        <AppIcon name="lock" />
                    </InputIcon>
                    <Password
                        id="password"
                        v-model="form.password"
                        autocomplete="current-password"
                        :invalid="Boolean(form.errors.password)"
                        fluid
                        :feedback="false"
                        toggle-mask
                    />
                </IconField>
                <small
                    v-if="passwordError"
                    id="login-password-error"
                    class="auth-form__field-error"
                >
                    {{ passwordError }}
                </small>
            </div>

            <div class="auth-form__options">
                <label class="auth-remember">
                    <Checkbox v-model="form.remember" binary />
                    <span class="auth-remember__label">{{
                        $t('common.auth.remember_me')
                    }}</span>
                </label>
            </div>

            <Button
                class="auth-form__submit"
                :disabled="form.processing"
                :loading="form.processing"
                type="submit"
            >
                <AppIcon name="lock" />
                <span>{{ $t('common.auth.login') }}</span>
            </Button>

            <div class="auth-form__forgot">
                <Link class="auth-link" href="/forgot-password">{{
                    $t('common.auth.forgot_password')
                }}</Link>
            </div>

            <div v-if="socialProviders.length > 0" class="auth-social">
                <div v-if="socialiteError" class="auth-error">
                    {{ socialiteError }}
                </div>

                <div class="auth-social__divider" role="separator">
                    <span>{{ $t('page-auth.continue_with') }}</span>
                </div>

                <a
                    v-for="provider in socialProviders"
                    :key="provider.provider"
                    class="auth-social__button"
                    :href="socialite.redirect.url(provider.provider)"
                >
                    <template v-if="provider.provider === 'github'">
                        <img
                            :src="githubIcon"
                            :alt="provider.label"
                            loading="lazy"
                            class="auth-social__button-lockup auth-social__button-lockup--github auth-social__button-lockup--light"
                        />
                        <img
                            :src="githubWhiteIcon"
                            :alt="provider.label"
                            loading="lazy"
                            class="auth-social__button-lockup auth-social__button-lockup--github auth-social__button-lockup--dark"
                        />
                        <span class="sr-only">{{ provider.label }}</span>
                    </template>
                    <template v-else>
                        <span class="auth-social__icon" aria-hidden="true">
                            <img
                                v-if="provider.logoUrl || providerIcon(provider.provider)"
                                :src="
                                    provider.logoUrl ?? providerIcon(provider.provider) ?? undefined
                                "
                                alt=""
                                loading="lazy"
                                class="h-5 w-auto"
                            />
                            <i
                                v-else-if="provider.icon"
                                :class="provider.icon"
                            />
                        </span>
                        <span>{{
                            provider.provider === 'microsoft'
                                ? 'Microsoft'
                                : provider.label
                        }}</span>
                    </template>
                </a>
            </div>
        </form>

        <template v-if="canRegister" #footer>
            <span>{{ $t('page-auth.no_account') }}</span>
            {{ ' ' }}
            <Link class="auth-link" href="/register">
                {{ $t('page-auth.create_account') }}
            </Link>
        </template>
    </AuthLayout>
</template>
