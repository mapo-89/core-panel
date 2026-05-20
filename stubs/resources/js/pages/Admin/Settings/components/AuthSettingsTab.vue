<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { useConfirm } from 'primevue/useconfirm'

import AppIcon from '@/components/AppIcon.vue'
import settings from '@/routes/core-panel/settings'
import type { SettingFieldRecord, SettingGroupRecord } from '@/types/core-panel'

type SettingFormValue = boolean | string | null
type ProviderCard = {
    credentialFields: string[]
    isMaster: boolean
    key: 'github' | 'google' | 'microsoft'
    toggleField: SettingFieldRecord
    title: string
}

const props = defineProps<{
    authGroup?: SettingGroupRecord | null
}>()

const confirm = useConfirm()

const authFields = computed<SettingFieldRecord[]>(
    () => props.authGroup?.fields ?? [],
)

const form = useForm({
    values: Object.fromEntries(
        authFields.value.map((field) => [
            field.key,
            { value: cloneValue(field.value, field.type) as SettingFormValue },
        ]),
    ),
})

const fieldMap = computed<Record<string, SettingFieldRecord>>(() =>
    Object.fromEntries(authFields.value.map((field) => [field.key, field])),
)

const accessFields = computed(() =>
    authFields.value.filter((field) =>
        [
            'registration_enabled',
            'email_verification_enabled',
            'password_reset_enabled',
            'two_factor_enabled',
        ].includes(field.key),
    ),
)

const socialMasterProviderField = computed<SettingFieldRecord | undefined>(
    () => fieldMap.value.social_master_provider,
)

const masterProviderKey = computed<string>(
    () => stringFieldValue('social_master_provider') ?? '',
)

const providerCards = computed<ProviderCard[]>(() =>
    [
        {
            credentialFields: ['github_client_id', 'github_client_secret'],
            isMaster: masterProviderKey.value === 'github',
            key: 'github',
            toggleField: fieldMap.value.social_github_enabled,
            title: 'GitHub',
        },
        {
            credentialFields: ['google_client_id', 'google_client_secret'],
            isMaster: masterProviderKey.value === 'google',
            key: 'google',
            toggleField: fieldMap.value.social_google_enabled,
            title: 'Google',
        },
        {
            credentialFields: [
                'microsoft_client_id',
                'microsoft_client_secret',
                'microsoft_tenant',
            ],
            isMaster: masterProviderKey.value === 'microsoft',
            key: 'microsoft',
            toggleField: fieldMap.value.social_microsoft_enabled,
            title: 'Microsoft',
        },
    ].filter(
        (provider): provider is ProviderCard =>
            provider.toggleField !== undefined,
    ),
)

const enabledProviderCards = computed(() =>
    providerCards.value.filter((provider) => providerEnabled(provider)),
)

const socialMasterNoneLabel = computed(() => {
    const noneOption = socialMasterProviderField.value?.options?.find(
        (option) => option.value === '',
    )

    return (
        noneOption?.label ??
        trans('core-panel::settings.options.social_master_provider.none')
    )
})

function providerEnabled(provider: ProviderCard): boolean {
    return form.values[provider.toggleField.key]?.value === true
}

function cloneValue(value: unknown, fieldType: string): SettingFormValue {
    if (fieldType === 'boolean') {
        return value === true
    }

    if (value === null || value === undefined) {
        return ''
    }

    if (typeof value === 'string') {
        return value
    }

    if (typeof value === 'boolean') {
        return value
    }

    return String(value)
}

function booleanFieldValue(key: string): boolean | undefined {
    const value = form.values[key]?.value

    if (typeof value === 'boolean') {
        return value
    }

    if (value === '1') {
        return true
    }

    if (value === '0') {
        return false
    }

    return undefined
}

function setBooleanFieldValue(
    key: string,
    value: boolean | string | undefined,
): void {
    if (!form.values[key]) {
        return
    }

    form.values[key].value = value === true || value === 'true' || value === '1'
}

function stringFieldValue(key: string): string | null {
    const value = form.values[key]?.value

    if (typeof value === 'string') {
        return value
    }

    if (value === null || value === undefined) {
        return null
    }

    return String(value)
}

function setStringFieldValue(
    key: string,
    value: string | null | undefined,
): void {
    if (!form.values[key]) {
        return
    }

    form.values[key].value = value ?? ''
}

function fieldError(key: string): string | undefined {
    return form.errors[`values.${key}.value`]
}

function selectMasterProvider(value: string): void {
    setStringFieldValue(
        socialMasterProviderField.value?.key ?? 'social_master_provider',
        value,
    )
}

function saveAuth(): void {
    form.put(settings.update.url(props.authGroup?.key ?? 'auth'), {
        onSuccess: () => {
            form.defaults()
        },
        preserveScroll: true,
    })
}

watch(
    enabledProviderCards,
    (providers) => {
        if (
            masterProviderKey.value !== '' &&
            !providers.some(
                (provider) => provider.key === masterProviderKey.value,
            )
        ) {
            selectMasterProvider('')
        }
    },
    { deep: true },
)

watch(
    () => form.values.two_factor_enabled?.value,
    (value, previousValue) => {
        if (previousValue === true && value === false) {
            confirm.require({
                accept: () => undefined,
                acceptClass: 'p-button-danger',
                acceptLabel: trans('common.ui.confirm'),
                header: trans('page-settings.tab_auth'),
                icon: 'pi pi-exclamation-triangle',
                message: trans('page-settings.auth_two_factor_disable_warning'),
                reject: () => {
                    form.values.two_factor_enabled.value = true
                },
                rejectLabel: trans('common.ui.cancel'),
            })
        }
    },
)
</script>

<template>
    <form
        class="cp-section cp-section--sticky-actions"
        @submit.prevent="saveAuth"
    >
        <div class="cp-section__header">
            <div class="grid min-w-0 flex-1 gap-1">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{ authGroup?.label ?? '' }}
                </h2>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ authGroup?.description ?? '' }}
                </p>
            </div>
        </div>

        <div class="cp-section__body">
            <div class="grid gap-6 lg:gap-7">
                <div class="grid gap-3 xl:grid-cols-2">
                    <label
                        v-for="field in accessFields"
                        :key="field.key"
                        class="flex flex-col gap-4 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] px-4 py-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="grid gap-1">
                            <span
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ field.label }}
                            </span>
                            <span
                                v-if="field.help"
                                class="text-sm text-[var(--cp-text-muted)]"
                            >
                                {{ field.help }}
                            </span>
                            <span
                                v-if="fieldError(field.key)"
                                class="text-sm text-[var(--cp-danger)]"
                            >
                                {{ fieldError(field.key) }}
                            </span>
                        </div>

                        <div
                            class="flex min-w-[4.5rem] shrink-0 justify-end self-end sm:self-start"
                        >
                            <ToggleSwitch
                                :model-value="booleanFieldValue(field.key)"
                                :invalid="Boolean(fieldError(field.key))"
                                @update:model-value="
                                    setBooleanFieldValue(field.key, $event)
                                "
                            />
                        </div>
                    </label>
                </div>

                <section
                    class="grid gap-4 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel)_96%,transparent)] p-4"
                >
                    <div class="grid gap-1">
                        <h3
                            class="text-sm font-semibold text-[var(--cp-text-primary)]"
                        >
                            {{ $t('page-settings.auth_social_title') }}
                        </h3>
                        <p class="text-sm text-[var(--cp-text-muted)]">
                            {{ $t('page-settings.auth_social_subtitle') }}
                        </p>
                    </div>

                    <section
                        v-if="socialMasterProviderField"
                        class="grid gap-3 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4"
                    >
                        <div class="grid gap-1">
                            <label
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                                for="settings_auth_social_master_provider"
                            >
                                {{ socialMasterProviderField.label }}
                            </label>
                            <p
                                v-if="socialMasterProviderField.help"
                                class="text-sm text-[var(--cp-text-muted)]"
                            >
                                {{ socialMasterProviderField.help }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                :outlined="masterProviderKey !== ''"
                                :severity="
                                    masterProviderKey === ''
                                        ? 'contrast'
                                        : 'secondary'
                                "
                                size="small"
                                type="button"
                                @click="selectMasterProvider('')"
                            >
                                {{ socialMasterNoneLabel }}
                            </Button>
                            <Button
                                v-for="provider in enabledProviderCards"
                                :key="provider.key"
                                :outlined="masterProviderKey !== provider.key"
                                :severity="
                                    masterProviderKey === provider.key
                                        ? 'contrast'
                                        : 'secondary'
                                "
                                size="small"
                                type="button"
                                @click="selectMasterProvider(provider.key)"
                            >
                                {{ provider.title }}
                            </Button>
                        </div>

                        <p
                            v-if="enabledProviderCards.length === 0"
                            class="text-sm text-[var(--cp-text-muted)]"
                        >
                            {{ $t('page-settings.social_providers_empty') }}
                        </p>
                        <p
                            v-if="fieldError(socialMasterProviderField.key)"
                            class="text-sm text-[var(--cp-danger)]"
                        >
                            {{ fieldError(socialMasterProviderField.key) }}
                        </p>
                    </section>

                    <div class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
                        <section
                            v-for="provider in providerCards"
                            :key="provider.key"
                            class="grid content-start gap-4 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="grid min-w-0 flex-1 gap-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h4
                                            class="text-sm font-semibold text-[var(--cp-text-primary)]"
                                        >
                                            {{ provider.title }}
                                        </h4>
                                        <Tag
                                            v-if="provider.isMaster"
                                            severity="contrast"
                                            :value="
                                                $t(
                                                    'page-settings.social_provider_master_badge',
                                                )
                                            "
                                        />
                                    </div>
                                    <p
                                        v-if="provider.toggleField.help"
                                        class="text-sm text-[var(--cp-text-muted)]"
                                    >
                                        {{ provider.toggleField.help }}
                                    </p>
                                    <p
                                        v-if="
                                            fieldError(provider.toggleField.key)
                                        "
                                        class="text-sm text-[var(--cp-danger)]"
                                    >
                                        {{
                                            fieldError(provider.toggleField.key)
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="flex min-w-[4.5rem] shrink-0 justify-end self-end sm:self-start"
                                >
                                    <ToggleSwitch
                                        :model-value="
                                            booleanFieldValue(
                                                provider.toggleField.key,
                                            )
                                        "
                                        :invalid="
                                            Boolean(
                                                fieldError(
                                                    provider.toggleField.key,
                                                ),
                                            )
                                        "
                                        @update:model-value="
                                            setBooleanFieldValue(
                                                provider.toggleField.key,
                                                $event,
                                            )
                                        "
                                    />
                                </div>
                            </div>

                            <div
                                v-if="providerEnabled(provider)"
                                class="grid gap-4 md:grid-cols-2"
                            >
                                <section
                                    v-for="fieldKey in provider.credentialFields"
                                    :key="fieldKey"
                                    class="grid gap-2"
                                    :class="{
                                        'md:col-span-2':
                                            fieldKey === 'microsoft_tenant',
                                    }"
                                >
                                    <div class="flex items-center gap-2">
                                        <label
                                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                                            :for="`settings_auth_${fieldKey}`"
                                        >
                                            {{ fieldMap[fieldKey]?.label }}
                                        </label>

                                        <button
                                            v-if="fieldMap[fieldKey]?.help"
                                            v-tooltip.top="
                                                fieldMap[fieldKey]?.help
                                            "
                                            class="inline-flex h-4 w-4 items-center justify-center text-[var(--cp-text-muted)] transition hover:text-[var(--cp-text-primary)]"
                                            type="button"
                                        >
                                            <AppIcon
                                                class="h-3.5 w-3.5"
                                                name="info"
                                            />
                                        </button>
                                    </div>

                                    <Password
                                        v-if="fieldKey.endsWith('_secret')"
                                        :id="`settings_auth_${fieldKey}`"
                                        :model-value="
                                            stringFieldValue(fieldKey)
                                        "
                                        fluid
                                        input-class="w-full"
                                        :feedback="false"
                                        :disabled="!providerEnabled(provider)"
                                        :invalid="Boolean(fieldError(fieldKey))"
                                        toggle-mask
                                        @update:model-value="
                                            setStringFieldValue(
                                                fieldKey,
                                                $event,
                                            )
                                        "
                                    />
                                    <InputText
                                        v-else
                                        :id="`settings_auth_${fieldKey}`"
                                        :model-value="
                                            stringFieldValue(fieldKey)
                                        "
                                        fluid
                                        :disabled="!providerEnabled(provider)"
                                        :invalid="Boolean(fieldError(fieldKey))"
                                        @update:model-value="
                                            setStringFieldValue(
                                                fieldKey,
                                                $event,
                                            )
                                        "
                                    />

                                    <p
                                        v-if="fieldError(fieldKey)"
                                        class="text-sm text-[var(--cp-danger)]"
                                    >
                                        {{ fieldError(fieldKey) }}
                                    </p>
                                </section>
                            </div>
                        </section>
                    </div>
                </section>
            </div>

            <div
                class="cp-settings-group-panel__actions cp-settings-group-panel__actions--sticky"
            >
                <Button
                    :disabled="form.processing || !form.isDirty"
                    :loading="form.processing"
                    type="submit"
                >
                    <AppIcon name="save" />
                    <span>{{ $t('common.ui.save') }}</span>
                </Button>
            </div>
        </div>
    </form>
</template>
