<script setup lang="ts">
/* eslint-disable vue/no-v-html */

import { router, useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import Message from 'primevue/message'
import { computed, onMounted, ref } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import passwordConfirm from '@/routes/password/confirm'
import twoFactorRoutes from '@/routes/two-factor'

const props = defineProps<{
    twoFactor: {
        confirmed: boolean
        enabled: boolean
    }
}>()

const confirmForm = useForm({
    code: '',
})

const passwordConfirmForm = useForm({
    password: '',
})

const passwordConfirmError = ref('')
const passwordConfirmProcessing = ref(false)
const pendingAction = ref<'disable' | 'enable' | null>(null)
const qrCodeSvg = ref('')
const recoveryCodes = ref<string[]>([])
const setupKey = ref('')
const showPasswordDialog = ref(false)
const showRecoveryCodes = ref(false)
const twoFactorProcessing = ref(false)

const twoFactorState = computed<'confirmed' | 'disabled' | 'pending'>(() => {
    if (props.twoFactor.confirmed) {
        return 'confirmed'
    }

    if (props.twoFactor.enabled) {
        return 'pending'
    }

    return 'disabled'
})

const recoveryCodesToggleLabel = computed(() =>
    showRecoveryCodes.value
        ? trans('page-settings.two_factor_hide_codes')
        : trans('page-settings.two_factor_show_codes'),
)

function getCsrfToken(): string | undefined {
    const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)

    return match ? decodeURIComponent(match[2]) : undefined
}

function requestHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }
    const xsrfToken = getCsrfToken()

    if (xsrfToken !== undefined) {
        headers['X-XSRF-TOKEN'] = xsrfToken
    }

    return headers
}

async function jsonRequest<T>(
    url: string,
    options: {
        body?: Record<string, unknown>
        method?: 'DELETE' | 'GET' | 'POST'
    } = {},
): Promise<T | null> {
    const response = await fetch(url, {
        body:
            options.body === undefined
                ? undefined
                : JSON.stringify(options.body),
        credentials: 'same-origin',
        headers: requestHeaders(),
        method: options.method ?? 'GET',
    })

    if (!response.ok) {
        throw response
    }

    const contentType = response.headers.get('content-type') ?? ''

    if (!contentType.includes('application/json')) {
        return null
    }

    return (await response.json()) as T
}

function reloadTwoFactorState(): Promise<void> {
    return new Promise((resolve) => {
        router.reload({
            only: ['flash', 'twoFactor'],
            onFinish: () => resolve(),
        })
    })
}

function requirePasswordConfirmation(action: 'disable' | 'enable'): void {
    pendingAction.value = action
    passwordConfirmForm.reset()
    passwordConfirmError.value = ''
    showPasswordDialog.value = true
}

async function loadQrAndSetupKey(): Promise<void> {
    const [qrResponse, secretKeyResponse] = await Promise.all([
        jsonRequest<{ svg?: string }>(twoFactorRoutes.qrCode.url()),
        jsonRequest<{ secretKey?: string }>(twoFactorRoutes.secretKey.url()),
    ])

    qrCodeSvg.value = qrResponse?.svg ?? ''
    setupKey.value = secretKeyResponse?.secretKey ?? ''
}

async function fetchRecoveryCodes(): Promise<void> {
    const response = await jsonRequest<string[]>(
        twoFactorRoutes.recoveryCodes.url(),
    )

    recoveryCodes.value = response ?? []
}

async function enableTwoFactor(): Promise<void> {
    twoFactorProcessing.value = true

    try {
        await jsonRequest(twoFactorRoutes.enable.url(), {
            method: 'POST',
        })
        await reloadTwoFactorState()
        await loadQrAndSetupKey()
    } finally {
        twoFactorProcessing.value = false
    }
}

async function disableTwoFactor(): Promise<void> {
    twoFactorProcessing.value = true

    try {
        await jsonRequest(twoFactorRoutes.disable.url(), {
            method: 'DELETE',
        })
        confirmForm.reset()
        qrCodeSvg.value = ''
        setupKey.value = ''
        showRecoveryCodes.value = false
        recoveryCodes.value = []
        await reloadTwoFactorState()
    } finally {
        twoFactorProcessing.value = false
    }
}

async function submitPasswordConfirmation(): Promise<void> {
    passwordConfirmError.value = ''
    passwordConfirmProcessing.value = true

    try {
        await jsonRequest(passwordConfirm.store.url(), {
            body: {
                password: passwordConfirmForm.password,
            },
            method: 'POST',
        })

        showPasswordDialog.value = false

        if (pendingAction.value === 'enable') {
            await enableTwoFactor()
        }

        if (pendingAction.value === 'disable') {
            await disableTwoFactor()
        }
    } catch (error) {
        const response = error as Response

        if (response.status === 422) {
            const payload = (await response.json()) as {
                errors?: {
                    password?: string[]
                }
            }

            passwordConfirmError.value =
                payload.errors?.password?.[0] ??
                trans('page-settings.confirm_password')
        }
    } finally {
        passwordConfirmProcessing.value = false
        pendingAction.value = null
    }
}

function confirmTwoFactor(): void {
    confirmForm.post(twoFactorRoutes.confirm.url(), {
        onSuccess: async () => {
            confirmForm.reset()
            qrCodeSvg.value = ''
            setupKey.value = ''
            await fetchRecoveryCodes()
            showRecoveryCodes.value = true
            await reloadTwoFactorState()
        },
    })
}

async function regenerateRecoveryCodes(): Promise<void> {
    await jsonRequest(twoFactorRoutes.regenerateRecoveryCodes.url(), {
        method: 'POST',
    })
    await fetchRecoveryCodes()
}

async function toggleRecoveryCodes(): Promise<void> {
    if (!showRecoveryCodes.value) {
        await fetchRecoveryCodes()
    }

    showRecoveryCodes.value = !showRecoveryCodes.value
}

onMounted(async () => {
    if (props.twoFactor.enabled && !props.twoFactor.confirmed) {
        try {
            await loadQrAndSetupKey()
        } catch {
            qrCodeSvg.value = ''
            setupKey.value = ''
        }
    }
})
</script>

<template>
    <section class="cp-profile-panel">
        <div class="cp-card grid gap-5 p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="grid gap-1">
                    <h2
                        class="text-lg font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ $t('common.auth.two_factor') }}
                    </h2>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ $t('page-settings.two_factor_description') }}
                    </p>
                </div>

                <Tag
                    v-if="twoFactorState === 'confirmed'"
                    severity="success"
                    :value="$t('page-settings.two_factor_enabled')"
                />
                <Tag
                    v-else-if="twoFactorState === 'pending'"
                    severity="warn"
                    :value="$t('page-settings.two_factor_finish')"
                />
                <Tag
                    v-else
                    severity="secondary"
                    :value="$t('page-settings.two_factor_disabled')"
                />
            </div>

            <p
                v-if="twoFactorState === 'disabled'"
                class="text-sm text-[var(--cp-text-muted)]"
            >
                {{ $t('page-settings.two_factor_disabled_description') }}
            </p>

            <template
                v-if="props.twoFactor.enabled && !props.twoFactor.confirmed"
            >
                <Message severity="warn">
                    {{ $t('page-settings.two_factor_finish_description') }}
                </Message>

                <div v-if="qrCodeSvg" class="cp-two-factor-setup">
                    <div class="cp-two-factor-setup__qr">
                        <p class="text-sm text-[var(--cp-text-muted)]">
                            {{ $t('page-settings.two_factor_scan') }}
                        </p>
                        <div
                            class="cp-two-factor-setup__qr-card"
                            v-html="qrCodeSvg"
                        />
                    </div>

                    <div class="cp-two-factor-setup__body">
                        <div v-if="setupKey" class="grid gap-2">
                            <p
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ $t('page-settings.two_factor_manual') }}
                            </p>
                            <code class="cp-two-factor-setup__secret">
                                {{ setupKey }}
                            </code>
                        </div>

                        <form
                            class="grid gap-3"
                            @submit.prevent="confirmTwoFactor"
                        >
                            <div class="grid gap-1">
                                <label
                                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                                    for="confirm_two_factor_code"
                                >
                                    {{
                                        $t(
                                            'page-settings.two_factor_code_label',
                                        )
                                    }}
                                </label>
                                <InputText
                                    id="confirm_two_factor_code"
                                    v-model="confirmForm.code"
                                    autocomplete="one-time-code"
                                    :invalid="Boolean(confirmForm.errors.code)"
                                    inputmode="numeric"
                                    placeholder="000000"
                                    fluid
                                />
                                <small
                                    v-if="confirmForm.errors.code"
                                    class="auth-form__field-error"
                                >
                                    {{ confirmForm.errors.code }}
                                </small>
                            </div>

                            <div
                                class="flex flex-wrap items-center justify-end gap-2"
                            >
                                <Button
                                    :disabled="confirmForm.processing"
                                    :loading="confirmForm.processing"
                                    type="submit"
                                >
                                    <AppIcon name="shield" />
                                    <span>{{
                                        $t('page-settings.two_factor_verify')
                                    }}</span>
                                </Button>
                                <Button
                                    :disabled="twoFactorProcessing"
                                    :loading="twoFactorProcessing"
                                    :label="
                                        $t(
                                            'page-settings.two_factor_cancel_setup',
                                        )
                                    "
                                    outlined
                                    severity="secondary"
                                    type="button"
                                    @click="
                                        requirePasswordConfirmation('disable')
                                    "
                                />
                            </div>
                        </form>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-wrap items-center justify-end gap-2"
                >
                    <Button
                        :disabled="twoFactorProcessing"
                        :loading="twoFactorProcessing"
                        :label="$t('page-settings.two_factor_continue')"
                        type="button"
                        @click="requirePasswordConfirmation('enable')"
                    />
                    <Button
                        :disabled="twoFactorProcessing"
                        :loading="twoFactorProcessing"
                        :label="$t('page-settings.two_factor_cancel_setup')"
                        outlined
                        severity="secondary"
                        type="button"
                        @click="requirePasswordConfirmation('disable')"
                    />
                </div>
            </template>

            <div
                v-if="showRecoveryCodes && recoveryCodes.length > 0"
                class="cp-two-factor-recovery"
            >
                <p class="text-sm font-medium text-[var(--cp-text-primary)]">
                    {{ $t('page-settings.two_factor_recovery_info') }}
                </p>
                <div class="cp-two-factor-recovery__codes">
                    <code
                        v-for="code in recoveryCodes"
                        :key="code"
                        class="cp-two-factor-recovery__code"
                    >
                        {{ code }}
                    </code>
                </div>
                <Button
                    :label="$t('page-settings.two_factor_regenerate')"
                    outlined
                    severity="secondary"
                    size="small"
                    type="button"
                    @click="regenerateRecoveryCodes"
                />
            </div>

            <div
                v-if="twoFactorState === 'disabled'"
                class="flex flex-wrap items-center justify-end gap-2"
            >
                <Button
                    :disabled="twoFactorProcessing"
                    :loading="twoFactorProcessing"
                    :label="$t('page-settings.two_factor_enable')"
                    type="button"
                    @click="requirePasswordConfirmation('enable')"
                />
            </div>

            <div
                v-if="props.twoFactor.confirmed"
                class="flex flex-wrap items-center justify-end gap-2"
            >
                <Button
                    :label="recoveryCodesToggleLabel"
                    outlined
                    severity="secondary"
                    type="button"
                    @click="toggleRecoveryCodes"
                />
                <Button
                    :disabled="twoFactorProcessing"
                    :loading="twoFactorProcessing"
                    :label="$t('page-settings.two_factor_disable')"
                    severity="danger"
                    type="button"
                    @click="requirePasswordConfirmation('disable')"
                />
            </div>
        </div>

        <Dialog
            v-model:visible="showPasswordDialog"
            :header="$t('page-settings.confirm_password_title')"
            modal
            :style="{ width: '26rem' }"
        >
            <p class="mb-4 text-sm text-[var(--cp-text-muted)]">
                {{ $t('page-settings.confirm_password_message') }}
            </p>

            <form
                class="grid gap-4"
                @submit.prevent="submitPasswordConfirmation"
            >
                <div class="grid gap-1">
                    <label
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                        for="confirm_password_dialog"
                    >
                        {{ $t('page-settings.confirm_password') }}
                    </label>
                    <Password
                        id="confirm_password_dialog"
                        v-model="passwordConfirmForm.password"
                        autocomplete="current-password"
                        autofocus
                        :feedback="false"
                        :invalid="Boolean(passwordConfirmError)"
                        toggle-mask
                        fluid
                    />
                    <small
                        v-if="passwordConfirmError"
                        class="auth-form__field-error"
                    >
                        {{ passwordConfirmError }}
                    </small>
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        :label="$t('common.ui.cancel')"
                        outlined
                        severity="secondary"
                        type="button"
                        @click="showPasswordDialog = false"
                    />
                    <Button
                        :disabled="passwordConfirmProcessing"
                        :loading="passwordConfirmProcessing"
                        type="submit"
                    >
                        <AppIcon name="check" />
                        <span>{{ $t('common.ui.confirm') }}</span>
                    </Button>
                </div>
            </form>
        </Dialog>
    </section>
</template>
