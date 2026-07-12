<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, ref, watch } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import LocaleFlag from '@core-panel/components/Locale/LocaleFlag.vue'
import ConfirmActionDialog from '@core-panel/components/Dialogs/ConfirmActionDialog.vue'

import settings from '@/routes/core-panel/settings'
import type { SettingFieldRecord, SettingGroupRecord } from '@core-panel/types/core-panel'

type SettingFormValue = boolean | number | string | string[] | null

const props = defineProps<{
    generalGroup: SettingGroupRecord
    languageGroup?: SettingGroupRecord | null
}>()

const page = usePage<{
    appLogo?: string | null
    corePanel?: {
        uploads?: {
            logo?: {
                accept?: string
                badges?: string[]
                mimeTypes?: string[]
            }
        }
    }
}>()
const toast = useToast()
const generalFields = computed<SettingFieldRecord[]>(
    () => props.generalGroup?.fields ?? [],
)
const generalForm = useForm({
    values: buildInitialValues(generalFields.value),
})
const languageFields = computed<SettingFieldRecord[]>(
    () => props.languageGroup?.fields ?? [],
)
const languageForm = useForm({
    values: buildInitialValues(languageFields.value),
})
const fileInput = ref<HTMLInputElement | null>(null)
const logoUrl = ref<string | null>(page.props.appLogo ?? null)
const logoDragActive = ref(false)
const uploadingLogo = ref(false)
const removeDialogVisible = ref(false)

const logoUploadConfig = computed(() => page.props.corePanel?.uploads?.logo)
const logoBadges = computed(() => logoUploadConfig.value?.badges ?? [])
const logoAccept = computed(
    () =>
        logoUploadConfig.value?.accept ??
        'image/jpeg,image/png,image/svg+xml,image/webp',
)
const generalFieldsByKey = computed<Record<string, SettingFieldRecord>>(() =>
    Object.fromEntries(generalFields.value.map((field) => [field.key, field])),
)
const languageFieldsByKey = computed<Record<string, SettingFieldRecord>>(() =>
    Object.fromEntries(languageFields.value.map((field) => [field.key, field])),
)
const generalAppNameField = computed(
    () => generalFieldsByKey.value.app_name ?? null,
)
const generalAppSubtitleField = computed(
    () => generalFieldsByKey.value.app_subtitle ?? null,
)
const generalTimezoneField = computed(
    () => generalFieldsByKey.value.timezone ?? null,
)
const languageLanguagesField = computed(
    () => languageFieldsByKey.value.languages ?? null,
)
const languageOptions = computed(
    () => languageLanguagesField.value?.options ?? [],
)
const activeLanguageOptions = computed(() => {
    const selected = new Set(selectedLanguages.value)

    return (languageOptions.value ?? []).filter((option) =>
        selected.has(String(option.value)),
    )
})
const defaultLocaleField = computed(
    () => languageFieldsByKey.value.default_locale ?? null,
)
const fallbackLocaleField = computed(
    () => languageFieldsByKey.value.fallback_locale ?? null,
)
const generalTimezoneOptions = computed(
    () => generalTimezoneField.value?.options ?? [],
)
const selectedLanguages = computed<string[]>(() => {
    const value = languageForm.values.languages?.value

    if (Array.isArray(value) && value.length > 0) {
        return value.map((entry) => String(entry))
    }

    const fallbackValue = languageLanguagesField.value?.value

    return Array.isArray(fallbackValue)
        ? fallbackValue.map((entry) => String(entry))
        : []
})

const generalAppNameValue = computed<string>({
    get: () => stringFieldValue(generalForm.values.app_name?.value),
    set: (value) => {
        if (generalForm.values.app_name) {
            generalForm.values.app_name.value = value
        }
    },
})
const generalAppSubtitleValue = computed<string>({
    get: () => stringFieldValue(generalForm.values.app_subtitle?.value),
    set: (value) => {
        if (generalForm.values.app_subtitle) {
            generalForm.values.app_subtitle.value = value
        }
    },
})

watch(
    () => page.props.appLogo,
    (value) => {
        logoUrl.value = value ?? null
    },
)
watch(
    selectedLanguages,
    (locales) => {
        const nextDefault = locales[0] ?? null

        for (const key of ['default_locale', 'fallback_locale'] as const) {
            const currentValue = languageForm.values[key]?.value
            const normalizedValue =
                typeof currentValue === 'string' ? currentValue : null

            if (normalizedValue !== null && locales.includes(normalizedValue)) {
                continue
            }

            if (languageForm.values[key]) {
                languageForm.values[key].value = nextDefault
            }
        }
    },
    { immediate: true },
)

function buildInitialValues(
    fields: SettingFieldRecord[],
): Record<string, { value: SettingFormValue }> {
    return Object.fromEntries(
        fields.map((field) => [
            field.key,
            { value: cloneValue(field.value) as SettingFormValue },
        ]),
    )
}

function cloneValue(value: unknown): SettingFormValue {
    if (Array.isArray(value)) {
        return value.map((entry) => String(entry))
    }

    if (typeof value === 'boolean' || typeof value === 'number') {
        return value
    }

    if (value === null || typeof value === 'string') {
        return value
    }

    return JSON.stringify(value)
}

function languageOptionByCode(code: string | null | undefined) {
    if (!code) {
        return null
    }

    return activeLanguageOptions.value.find(
        (option) => String(option.value) === String(code),
    )
}

function stringFieldValue(value: SettingFormValue | undefined): string {
    if (typeof value === 'string') {
        return value
    }

    if (value === null || value === undefined) {
        return ''
    }

    return String(value)
}

function fieldError(
    form: typeof generalForm | typeof languageForm,
    key: string,
): string | undefined {
    return form.errors[`values.${key}.value`]
}

function saveGeneral(): void {
    generalForm.put(settings.update.url(props.generalGroup.key), {
        onSuccess: () => {
            generalForm.defaults()
        },
    })
}

function normalizeLanguageSettings(): string[] {
    const currentValue = languageForm.values.languages?.value
    const locales = Array.isArray(currentValue)
        ? currentValue
              .map((entry) => String(entry))
              .filter((entry) => entry.length > 0)
        : []

    if (languageForm.values.languages) {
        languageForm.values.languages.value = locales
    }

    const primaryLocale = locales[0] ?? null

    for (const key of ['default_locale', 'fallback_locale'] as const) {
        const currentValue = languageForm.values[key]?.value
        const normalizedValue =
            typeof currentValue === 'string' ? currentValue : null

        if (normalizedValue !== null && locales.includes(normalizedValue)) {
            continue
        }

        if (languageForm.values[key]) {
            languageForm.values[key].value = primaryLocale
        }
    }

    return locales
}

async function saveLanguage(): Promise<void> {
    if (!props.languageGroup) {
        return
    }

    const locales = normalizeLanguageSettings()

    if (locales.length === 0) {
        languageForm.clearErrors(
            'values.languages.value',
            'values.default_locale.value',
            'values.fallback_locale.value',
        )
        toast.add({
            detail: trans('page-settings.general_languages_required'),
            life: 3500,
            severity: 'error',
            summary: trans('common.ui.error'),
        })

        return
    }

    languageForm.clearErrors(
        'values.languages.value',
        'values.default_locale.value',
        'values.fallback_locale.value',
    )

    await nextTick()

    languageForm.put(settings.update.url(props.languageGroup.key), {
        onSuccess: () => {
            languageForm.defaults()
        },
    })
}

function getCsrfToken(): string | undefined {
    const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)

    return match ? decodeURIComponent(match[2]) : undefined
}

function refreshLogoState(): void {
    router.reload({
        only: ['appLogo', 'auth', 'corePanel'],
    })
}

function openLogoPicker(): void {
    fileInput.value?.click()
}

function isAllowedLogoFile(file: File): boolean {
    return (
        !logoUploadConfig.value?.mimeTypes?.length ||
        logoUploadConfig.value.mimeTypes.includes(file.type)
    )
}

function notifyLogoUploadError(detail: string): void {
    toast.add({
        detail,
        life: 3500,
        severity: 'error',
        summary: trans('common.ui.error'),
    })
}

async function uploadLogoFile(file: File | null): Promise<void> {
    if (file === null) {
        return
    }

    if (!isAllowedLogoFile(file)) {
        notifyLogoUploadError(trans('page-settings.general_logo_invalid_type'))

        return
    }

    const previousUrl = logoUrl.value
    const previewReader = new FileReader()
    previewReader.onload = (loadEvent) => {
        logoUrl.value =
            (loadEvent.target?.result as string | null) ?? previousUrl
    }
    previewReader.readAsDataURL(file)

    uploadingLogo.value = true

    try {
        const formData = new FormData()
        formData.append('logo', file)

        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }

        const xsrfToken = getCsrfToken()

        if (xsrfToken !== undefined) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        const response = await fetch(settings.logo.store.url(), {
            body: formData,
            credentials: 'same-origin',
            headers,
            method: 'POST',
        })

        if (!response.ok) {
            logoUrl.value = previousUrl
            notifyLogoUploadError(
                trans('page-settings.general_logo_upload_failed'),
            )

            return
        }

        const payload = (await response.json()) as {
            data?: {
                logo_url?: string | null
            }
        }

        logoUrl.value = payload.data?.logo_url ?? logoUrl.value
        toast.add({
            detail: trans('page-settings.general_logo_uploaded_status'),
            life: 2500,
            severity: 'success',
            summary: trans('common.ui.saved'),
        })
        refreshLogoState()
    } catch {
        logoUrl.value = previousUrl
        notifyLogoUploadError(trans('page-settings.general_logo_upload_failed'))
    } finally {
        uploadingLogo.value = false
    }
}

async function onLogoSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement

    if (!input.files?.length) {
        return
    }

    const [file] = input.files
    input.value = ''

    await uploadLogoFile(file)
}

function handleLogoDragOver(): void {
    if (!uploadingLogo.value) {
        logoDragActive.value = true
    }
}

function handleLogoDragLeave(): void {
    logoDragActive.value = false
}

async function handleLogoDrop(event: DragEvent): Promise<void> {
    logoDragActive.value = false

    if (uploadingLogo.value) {
        return
    }

    await uploadLogoFile(event.dataTransfer?.files?.[0] ?? null)
}

async function removeLogo(): Promise<void> {
    uploadingLogo.value = true

    try {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }

        const xsrfToken = getCsrfToken()

        if (xsrfToken !== undefined) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        const response = await fetch(settings.logo.destroy.url(), {
            credentials: 'same-origin',
            headers,
            method: 'DELETE',
        })

        if (!response.ok) {
            notifyLogoUploadError(
                trans('page-settings.general_logo_remove_failed'),
            )

            return
        }

        logoUrl.value = null
        removeDialogVisible.value = false
        toast.add({
            detail: trans('page-settings.general_logo_removed_status'),
            life: 2500,
            severity: 'success',
            summary: trans('common.ui.saved'),
        })
        refreshLogoState()
    } catch {
        notifyLogoUploadError(trans('page-settings.general_logo_remove_failed'))
    } finally {
        uploadingLogo.value = false
    }
}

function updateLanguages(locale: string, enabled: boolean): void {
    const currentValue = languageForm.values.languages?.value
    const selectedLocales = Array.isArray(currentValue)
        ? currentValue.map((entry) => String(entry))
        : []

    languageForm.values.languages.value = enabled
        ? Array.from(new Set([...selectedLocales, locale]))
        : selectedLocales.filter((entry) => entry !== locale)
}
</script>

<template>
    <div class="cp-settings-general-tab">
        <ConfirmActionDialog
            v-model:visible="removeDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('page-settings.general_logo_remove')"
            confirm-severity="danger"
            :description="$t('page-settings.general_logo_remove_description')"
            :loading="uploadingLogo"
            :message="$t('page-settings.general_logo_remove_confirm')"
            icon="trash"
            :title="$t('page-settings.general_logo_remove_title')"
            tone="danger"
            @confirm="removeLogo"
        />

        <section class="cp-section">
            <div class="cp-section__body">
                <div class="cp-settings-general-tab__logo-copy">
                    <h2
                        class="text-lg font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ $t('page-settings.general_logo_title') }}
                    </h2>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ $t('page-settings.general_logo_subtitle') }}
                    </p>
                </div>

                <div class="cp-settings-general-tab__logo-shell">
                    <button
                        class="cp-settings-general-tab__logo-preview"
                        :disabled="uploadingLogo"
                        :class="{ 'is-drag-active': logoDragActive }"
                        type="button"
                        @click="openLogoPicker"
                        @dragenter.prevent="handleLogoDragOver"
                        @dragleave.prevent="handleLogoDragLeave"
                        @dragover.prevent="handleLogoDragOver"
                        @drop.prevent="handleLogoDrop"
                    >
                        <img
                            v-if="logoUrl"
                            :src="logoUrl"
                            alt=""
                            class="cp-settings-general-tab__logo-image"
                        />
                        <div v-else class="cp-settings-general-tab__logo-empty">
                            <AppIcon name="image" />
                            <span>{{
                                $t('page-settings.general_logo_empty')
                            }}</span>
                        </div>

                        <div class="cp-settings-general-tab__logo-overlay">
                            <AppIcon
                                :class="{ 'animate-spin': uploadingLogo }"
                                :name="uploadingLogo ? 'refresh' : 'upload'"
                            />
                        </div>
                    </button>

                    <div class="cp-settings-general-tab__logo-body">
                        <div class="cp-settings-general-tab__logo-meta">
                            <span
                                class="cp-settings-general-tab__logo-state"
                                :class="
                                    logoUrl
                                        ? 'cp-settings-general-tab__logo-state--success'
                                        : 'cp-settings-general-tab__logo-state--warning'
                                "
                            >
                                {{
                                    logoUrl
                                        ? $t(
                                              'page-settings.general_logo_uploaded',
                                          )
                                        : $t('page-settings.general_logo_empty')
                                }}
                            </span>

                            <span
                                v-for="badge in logoBadges"
                                :key="badge"
                                class="cp-settings-general-tab__logo-state"
                            >
                                {{ badge }}
                            </span>
                        </div>

                        <p class="text-sm text-[var(--cp-text-muted)]">
                            {{
                                uploadingLogo
                                    ? $t('page-settings.general_logo_pending')
                                    : $t('page-settings.general_logo_drop_hint')
                            }}
                        </p>

                        <div
                            class="cp-settings-general-tab__actions cp-settings-general-tab__actions--inline"
                        >
                            <Button
                                outlined
                                :disabled="uploadingLogo"
                                size="small"
                                type="button"
                                @click="openLogoPicker"
                            >
                                <AppIcon name="upload" />
                                <span>{{
                                    $t('page-settings.general_logo_upload')
                                }}</span>
                            </Button>

                            <Button
                                v-if="logoUrl"
                                outlined
                                severity="danger"
                                :disabled="uploadingLogo"
                                size="small"
                                type="button"
                                @click="removeDialogVisible = true"
                            >
                                <AppIcon name="trash" />
                                <span>{{
                                    $t('page-settings.general_logo_remove')
                                }}</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <input
                    ref="fileInput"
                    class="hidden"
                    :accept="logoAccept"
                    type="file"
                    @change="onLogoSelected"
                />
            </div>
        </section>

        <form
            class="cp-section cp-section--sticky-actions"
            @submit.prevent="saveGeneral"
        >
            <div class="cp-section__header">
                <div class="grid gap-1">
                    <h2
                        class="text-lg font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ generalGroup.label }}
                    </h2>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ generalGroup.description }}
                    </p>
                </div>
            </div>

            <div class="cp-section__body">
                <div class="grid gap-6">
                    <section v-if="generalAppNameField" class="grid gap-3">
                        <div class="grid gap-1">
                            <div class="grid gap-1">
                                <label
                                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                                    for="settings-general-app-name"
                                >
                                    {{ generalAppNameField.label }}
                                </label>
                                <p
                                    v-if="generalAppNameField.help"
                                    class="text-sm text-[var(--cp-text-muted)]"
                                >
                                    {{ generalAppNameField.help }}
                                </p>
                            </div>
                        </div>

                        <InputText
                            id="settings-general-app-name"
                            :model-value="generalAppNameValue"
                            fluid
                            :invalid="
                                Boolean(fieldError(generalForm, 'app_name'))
                            "
                            @update:model-value="
                                generalAppNameValue = $event ?? ''
                            "
                        />

                        <p
                            v-if="fieldError(generalForm, 'app_name')"
                            class="cp-settings-general-tab__field-error"
                        >
                            {{ fieldError(generalForm, 'app_name') }}
                        </p>
                    </section>

                    <section v-if="generalAppSubtitleField" class="grid gap-3">
                        <div class="grid gap-1">
                            <div class="grid gap-1">
                                <label
                                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                                    for="settings-general-app-subtitle"
                                >
                                    {{ generalAppSubtitleField.label }}
                                </label>
                                <p
                                    v-if="generalAppSubtitleField.help"
                                    class="text-sm text-[var(--cp-text-muted)]"
                                >
                                    {{ generalAppSubtitleField.help }}
                                </p>
                            </div>
                        </div>

                        <InputText
                            id="settings-general-app-subtitle"
                            :model-value="generalAppSubtitleValue"
                            fluid
                            :invalid="
                                Boolean(fieldError(generalForm, 'app_subtitle'))
                            "
                            @update:model-value="
                                generalAppSubtitleValue = $event ?? ''
                            "
                        />

                        <p
                            v-if="fieldError(generalForm, 'app_subtitle')"
                            class="cp-settings-general-tab__field-error"
                        >
                            {{ fieldError(generalForm, 'app_subtitle') }}
                        </p>
                    </section>

                    <section v-if="generalTimezoneField" class="grid gap-3">
                        <div class="grid gap-1">
                            <div class="grid gap-1">
                                <label
                                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                                    for="settings-general-timezone"
                                >
                                    {{ generalTimezoneField.label }}
                                </label>
                                <p
                                    v-if="generalTimezoneField.help"
                                    class="text-sm text-[var(--cp-text-muted)]"
                                >
                                    {{ generalTimezoneField.help }}
                                </p>
                            </div>
                        </div>

                        <Select
                            id="settings-general-timezone"
                            v-model="generalForm.values.timezone.value"
                            filter
                            fluid
                            :invalid="
                                Boolean(fieldError(generalForm, 'timezone'))
                            "
                            option-label="label"
                            option-value="value"
                            :options="generalTimezoneOptions"
                        />

                        <p
                            v-if="fieldError(generalForm, 'timezone')"
                            class="cp-settings-general-tab__field-error"
                        >
                            {{ fieldError(generalForm, 'timezone') }}
                        </p>
                    </section>
                </div>

                <div
                    class="cp-settings-general-tab__actions cp-settings-general-tab__actions--sticky"
                >
                    <Button
                        :disabled="
                            generalForm.processing || !generalForm.isDirty
                        "
                        :loading="generalForm.processing"
                        type="submit"
                    >
                        <AppIcon name="save" />
                        <span>{{ $t('common.ui.save') }}</span>
                    </Button>
                </div>
            </div>
        </form>

        <form
            v-if="languageGroup && languageFields.length > 0"
            class="cp-section cp-section--sticky-actions"
            @submit.prevent="saveLanguage"
        >
            <div class="cp-section__header">
                <div class="grid gap-1">
                    <h2
                        class="text-lg font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ languageGroup.label }}
                    </h2>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ languageGroup.description }}
                    </p>
                </div>
            </div>

            <div class="cp-section__body">
                <div
                    class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]"
                >
                    <section v-if="languageLanguagesField" class="grid gap-4">
                        <div class="grid gap-1">
                            <div class="grid gap-1">
                                <label
                                    class="text-sm font-medium text-[var(--cp-text-primary)]"
                                >
                                    {{ languageLanguagesField.label }}
                                </label>
                                <p
                                    v-if="languageLanguagesField.help"
                                    class="text-sm text-[var(--cp-text-muted)]"
                                >
                                    {{ languageLanguagesField.help }}
                                </p>
                            </div>
                        </div>

                        <div class="cp-settings-general-tab__language-summary">
                            <div
                                class="cp-settings-general-tab__language-summary-copy"
                            >
                                <span
                                    class="cp-settings-general-tab__language-summary-label"
                                >
                                    {{
                                        $t(
                                            'page-settings.general_overview_languages',
                                        )
                                    }}
                                </span>
                                <strong
                                    class="cp-settings-general-tab__language-summary-count"
                                >
                                    {{ selectedLanguages.length }} /
                                    {{ languageOptions.length }}
                                </strong>
                            </div>

                            <div
                                class="cp-settings-general-tab__language-badges"
                            >
                                <span
                                    v-for="option in languageOptions.filter(
                                        (entry) =>
                                            selectedLanguages.includes(
                                                String(entry.value),
                                            ),
                                    )"
                                    :key="`active-${option.value}`"
                                    class="cp-settings-general-tab__language-badge"
                                >
                                    <LocaleFlag :code="String(option.value)" />
                                    <span>{{ option.label }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="cp-settings-general-tab__checkbox-grid">
                            <label
                                v-for="option in languageOptions"
                                :key="String(option.value)"
                                class="cp-settings-general-tab__checkbox-option"
                            >
                                <Checkbox
                                    binary
                                    :input-id="`settings-language-${option.value}`"
                                    :model-value="
                                        selectedLanguages.includes(
                                            String(option.value),
                                        )
                                    "
                                    @update:model-value="
                                        updateLanguages(
                                            String(option.value),
                                            Boolean($event),
                                        )
                                    "
                                />
                                <div
                                    class="cp-settings-general-tab__checkbox-copy"
                                >
                                    <div
                                        class="cp-settings-general-tab__checkbox-identity"
                                    >
                                        <LocaleFlag
                                            :code="String(option.value)"
                                        />
                                        <span>{{ option.label }}</span>
                                    </div>
                                    <span
                                        class="cp-settings-general-tab__checkbox-code"
                                    >
                                        {{ String(option.value).toUpperCase() }}
                                    </span>
                                </div>
                            </label>
                        </div>

                        <p
                            v-if="fieldError(languageForm, 'languages')"
                            class="cp-settings-general-tab__field-error"
                        >
                            {{ fieldError(languageForm, 'languages') }}
                        </p>
                    </section>

                    <div class="cp-settings-general-tab__language-sidecards">
                        <section
                            v-if="defaultLocaleField"
                            class="cp-settings-general-tab__language-sidecard"
                        >
                            <div
                                class="cp-settings-general-tab__language-sidecard-head"
                            >
                                <div class="grid gap-1">
                                    <label
                                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                                    >
                                        {{ defaultLocaleField.label }}
                                    </label>
                                    <p
                                        v-if="defaultLocaleField.help"
                                        class="text-sm text-[var(--cp-text-muted)]"
                                    >
                                        {{ defaultLocaleField.help }}
                                    </p>
                                </div>

                                <span
                                    v-if="
                                        languageOptionByCode(
                                            stringFieldValue(
                                                languageForm.values
                                                    .default_locale?.value,
                                            ),
                                        )
                                    "
                                    class="cp-settings-general-tab__locale-current-badge"
                                >
                                    <LocaleFlag
                                        :code="
                                            stringFieldValue(
                                                languageForm.values
                                                    .default_locale?.value,
                                            )
                                        "
                                    />
                                    <span>{{
                                        stringFieldValue(
                                            languageForm.values.default_locale
                                                ?.value,
                                        ).toUpperCase()
                                    }}</span>
                                </span>
                            </div>

                            <Select
                                v-model="
                                    languageForm.values.default_locale.value
                                "
                                class="cp-settings-general-tab__locale-select"
                                fluid
                                :invalid="
                                    Boolean(
                                        fieldError(
                                            languageForm,
                                            'default_locale',
                                        ),
                                    )
                                "
                                option-label="label"
                                option-value="value"
                                :options="activeLanguageOptions"
                            >
                                <template #value="slotProps">
                                    <div
                                        v-if="slotProps.value"
                                        class="cp-settings-general-tab__locale-option"
                                    >
                                        <div
                                            class="cp-settings-general-tab__locale-option-copy"
                                        >
                                            <LocaleFlag
                                                :code="String(slotProps.value)"
                                            />
                                            <span>
                                                {{
                                                    languageOptionByCode(
                                                        String(slotProps.value),
                                                    )?.label ??
                                                    String(
                                                        slotProps.value,
                                                    ).toUpperCase()
                                                }}
                                            </span>
                                        </div>
                                        <span
                                            class="cp-settings-general-tab__locale-option-code"
                                        >
                                            {{
                                                String(
                                                    slotProps.value,
                                                ).toUpperCase()
                                            }}
                                        </span>
                                    </div>
                                </template>
                                <template #option="slotProps">
                                    <div
                                        class="cp-settings-general-tab__locale-option"
                                    >
                                        <div
                                            class="cp-settings-general-tab__locale-option-copy"
                                        >
                                            <LocaleFlag
                                                :code="
                                                    String(
                                                        slotProps.option.value,
                                                    )
                                                "
                                            />
                                            <span>{{
                                                slotProps.option.label
                                            }}</span>
                                        </div>
                                        <span
                                            class="cp-settings-general-tab__locale-option-code"
                                        >
                                            {{
                                                String(
                                                    slotProps.option.value,
                                                ).toUpperCase()
                                            }}
                                        </span>
                                    </div>
                                </template>
                            </Select>

                            <p
                                v-if="
                                    fieldError(languageForm, 'default_locale')
                                "
                                class="cp-settings-general-tab__field-error"
                            >
                                {{ fieldError(languageForm, 'default_locale') }}
                            </p>
                        </section>

                        <section
                            v-if="fallbackLocaleField"
                            class="cp-settings-general-tab__language-sidecard"
                        >
                            <div
                                class="cp-settings-general-tab__language-sidecard-head"
                            >
                                <div class="grid gap-1">
                                    <label
                                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                                    >
                                        {{ fallbackLocaleField.label }}
                                    </label>
                                    <p
                                        v-if="fallbackLocaleField.help"
                                        class="text-sm text-[var(--cp-text-muted)]"
                                    >
                                        {{ fallbackLocaleField.help }}
                                    </p>
                                </div>

                                <span
                                    v-if="
                                        languageOptionByCode(
                                            stringFieldValue(
                                                languageForm.values
                                                    .fallback_locale?.value,
                                            ),
                                        )
                                    "
                                    class="cp-settings-general-tab__locale-current-badge"
                                >
                                    <LocaleFlag
                                        :code="
                                            stringFieldValue(
                                                languageForm.values
                                                    .fallback_locale?.value,
                                            )
                                        "
                                    />
                                    <span>{{
                                        stringFieldValue(
                                            languageForm.values.fallback_locale
                                                ?.value,
                                        ).toUpperCase()
                                    }}</span>
                                </span>
                            </div>

                            <Select
                                v-model="
                                    languageForm.values.fallback_locale.value
                                "
                                class="cp-settings-general-tab__locale-select"
                                fluid
                                :invalid="
                                    Boolean(
                                        fieldError(
                                            languageForm,
                                            'fallback_locale',
                                        ),
                                    )
                                "
                                option-label="label"
                                option-value="value"
                                :options="activeLanguageOptions"
                            >
                                <template #value="slotProps">
                                    <div
                                        v-if="slotProps.value"
                                        class="cp-settings-general-tab__locale-option"
                                    >
                                        <div
                                            class="cp-settings-general-tab__locale-option-copy"
                                        >
                                            <LocaleFlag
                                                :code="String(slotProps.value)"
                                            />
                                            <span>
                                                {{
                                                    languageOptionByCode(
                                                        String(slotProps.value),
                                                    )?.label ??
                                                    String(
                                                        slotProps.value,
                                                    ).toUpperCase()
                                                }}
                                            </span>
                                        </div>
                                        <span
                                            class="cp-settings-general-tab__locale-option-code"
                                        >
                                            {{
                                                String(
                                                    slotProps.value,
                                                ).toUpperCase()
                                            }}
                                        </span>
                                    </div>
                                </template>
                                <template #option="slotProps">
                                    <div
                                        class="cp-settings-general-tab__locale-option"
                                    >
                                        <div
                                            class="cp-settings-general-tab__locale-option-copy"
                                        >
                                            <LocaleFlag
                                                :code="
                                                    String(
                                                        slotProps.option.value,
                                                    )
                                                "
                                            />
                                            <span>{{
                                                slotProps.option.label
                                            }}</span>
                                        </div>
                                        <span
                                            class="cp-settings-general-tab__locale-option-code"
                                        >
                                            {{
                                                String(
                                                    slotProps.option.value,
                                                ).toUpperCase()
                                            }}
                                        </span>
                                    </div>
                                </template>
                            </Select>

                            <p
                                v-if="
                                    fieldError(languageForm, 'fallback_locale')
                                "
                                class="cp-settings-general-tab__field-error"
                            >
                                {{
                                    fieldError(languageForm, 'fallback_locale')
                                }}
                            </p>
                        </section>
                    </div>
                </div>

                <div
                    class="cp-settings-general-tab__actions cp-settings-general-tab__actions--sticky"
                >
                    <Button
                        :disabled="
                            languageForm.processing || !languageForm.isDirty
                        "
                        :loading="languageForm.processing"
                        type="submit"
                    >
                        <AppIcon name="save" />
                        <span>{{ $t('common.ui.save') }}</span>
                    </Button>
                </div>
            </div>
        </form>
    </div>
</template>
