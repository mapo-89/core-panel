<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { useTranslatedAuthErrors } from '@/composables/useTranslatedAuthErrors'
import AuthLayout from '@/layouts/AuthLayout.vue'
import twoFactorLoginRoutes from '@/routes/two-factor/login'

const useRecoveryCode = ref(false)

const form = useForm({
    code: '',
    recovery_code: '',
})
const { clearTranslatedAuthErrors, translatedAuthError } =
    useTranslatedAuthErrors(['code', 'recovery_code'] as const)

function submit(): void {
    clearTranslatedAuthErrors()
    form.post(twoFactorLoginRoutes.store.url(), {
        onFinish: () => {
            form.reset()
        },
    })
}

function toggleRecoveryMode(): void {
    useRecoveryCode.value = !useRecoveryCode.value
    form.reset()
    form.clearErrors()
    clearTranslatedAuthErrors()
}
</script>

<template>
    <AuthLayout
        :heading="$t('page-auth.two_factor_heading')"
        :subheading="
            useRecoveryCode
                ? $t('page-auth.two_factor_recovery_subheading')
                : $t('page-auth.two_factor_subheading')
        "
    >
        <Head :title="$t('common.auth.two_factor')" />

        <template #header>
            <h2 class="auth-title">{{ $t('page-auth.two_factor_heading') }}</h2>
            <p class="auth-subtitle">
                {{
                    useRecoveryCode
                        ? $t('page-auth.two_factor_recovery_subheading')
                        : $t('page-auth.two_factor_subheading')
                }}
            </p>
        </template>

        <form class="auth-form" @submit.prevent="submit">
            <div v-if="!useRecoveryCode" class="auth-form__field">
                <label class="auth-form__label" for="code">{{
                    $t('page-auth.authenticator_code')
                }}</label>
                <InputOtp
                    id="code"
                    v-model="form.code"
                    autocomplete="one-time-code"
                    :invalid="Boolean(form.errors.code)"
                    integer-only
                    :length="6"
                    fluid
                />
                <small v-if="form.errors.code" class="auth-form__field-error">
                    {{ translatedAuthError('code', form.errors.code) }}
                </small>
            </div>

            <div v-else class="auth-form__field">
                <label class="auth-form__label" for="recovery_code">{{
                    $t('page-auth.recovery_code')
                }}</label>
                <InputText
                    id="recovery_code"
                    v-model="form.recovery_code"
                    autocomplete="one-time-code"
                    :invalid="Boolean(form.errors.recovery_code)"
                    :placeholder="
                        $t('page-auth.two_factor_recovery_placeholder')
                    "
                    fluid
                />
                <small
                    v-if="form.errors.recovery_code"
                    class="auth-form__field-error"
                >
                    {{
                        translatedAuthError(
                            'recovery_code',
                            form.errors.recovery_code,
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
                <AppIcon name="shield" />
                <span>{{ $t('page-auth.two_factor_submit') }}</span>
            </Button>
        </form>

        <template #footer>
            <a class="auth-link" href="#" @click.prevent="toggleRecoveryMode">
                {{
                    useRecoveryCode
                        ? $t('page-auth.two_factor_use_authenticator_code')
                        : $t('page-auth.two_factor_use_recovery_code')
                }}
            </a>
        </template>
    </AuthLayout>
</template>
