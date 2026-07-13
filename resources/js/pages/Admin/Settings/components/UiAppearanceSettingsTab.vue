<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import {
    applyCorePanelLayoutDensity,
    applyCorePanelRadiusToken,
    applyCorePanelThemeAccent,
    applyCorePanelThemePalette,
    normalizeCorePanelLayoutDensity,
    normalizeCorePanelRadiusToken,
    normalizeCorePanelThemeAccent,
    normalizeCorePanelThemePalette,
    resolveCorePanelPreviewPalette,
    type CorePanelThemePalette,
} from '@core-panel/theme/core-panel'

import settings from '@/routes/core-panel/settings'
import type {
    SettingFieldRecord,
    SettingGroupRecord,
} from '@core-panel/types/core-panel'

type SettingFormValue = boolean | number | string | string[] | null

const props = defineProps<{
    appearanceGroup?: SettingGroupRecord | null
    uiGroup?: SettingGroupRecord | null
}>()

const appearanceFields = computed<SettingFieldRecord[]>(
    () => props.appearanceGroup?.fields ?? [],
)

const uiFieldsSource = computed<SettingFieldRecord[]>(
    () => props.uiGroup?.fields ?? [],
)

const styleForm = useForm({
    values: buildInitialValues([
        ...appearanceFields.value,
        ...uiFieldsSource.value,
    ]),
})

const appearancePaletteField = computed(
    () =>
        appearanceFields.value.find((field) => field.key === 'theme_palette') ??
        null,
)
const uiAccentField = computed(
    () =>
        uiFieldsSource.value.find(
            (field) => field.key === 'primary_color_token',
        ) ?? null,
)
const uiFields = computed(() =>
    uiFieldsSource.value.filter((field) => field.key !== 'primary_color_token'),
)
const uiFieldMap = computed(() =>
    Object.fromEntries(uiFields.value.map((field) => [field.key, field])),
)
const savedPalette = computed<CorePanelThemePalette>(() =>
    normalizeCorePanelThemePalette(appearancePaletteField.value?.value),
)
const selectedPalette = computed<CorePanelThemePalette>(() =>
    normalizeCorePanelThemePalette(styleForm.values.theme_palette?.value),
)
const savedAccentColor = computed(() =>
    normalizeCorePanelThemeAccent(uiAccentField.value?.value),
)
const savedDensity = computed(() =>
    normalizeCorePanelLayoutDensity(uiFieldMap.value.layout_density?.value),
)
const savedRadius = computed(() =>
    normalizeCorePanelRadiusToken(uiFieldMap.value.radius_token?.value),
)
const selectedAccentColor = computed(() =>
    normalizeCorePanelThemeAccent(styleForm.values.primary_color_token?.value),
)
const selectedDensity = computed(() =>
    String(styleForm.values.layout_density?.value ?? 'comfortable'),
)
const selectedRadius = computed(() =>
    String(styleForm.values.radius_token?.value ?? 'md'),
)
const selectedDefaultMode = computed<'dark' | 'light'>(() => {
    if (typeof document === 'undefined') {
        return 'dark'
    }

    return document.documentElement.classList.contains('core-panel-dark')
        ? 'dark'
        : 'light'
})
const selectedFooterVisibility = computed(() =>
    styleForm.values.show_app_footer?.value === false ? 'hidden' : 'visible',
)
const previewDensityVariables = computed(() => {
    const densityMap = {
        comfortable: {
            cardPadding: '1rem',
            contentGap: '0.85rem',
            controlPaddingX: '0.9rem',
            controlPaddingY: '0.55rem',
            controlRadius: '0.75rem',
            inputHeight: '2.75rem',
        },
        compact: {
            cardPadding: '0.8rem',
            contentGap: '0.65rem',
            controlPaddingX: '0.8rem',
            controlPaddingY: '0.45rem',
            controlRadius: '0.65rem',
            inputHeight: '2.45rem',
        },
        spacious: {
            cardPadding: '1.15rem',
            contentGap: '1rem',
            controlPaddingX: '1rem',
            controlPaddingY: '0.65rem',
            controlRadius: '0.85rem',
            inputHeight: '3rem',
        },
    } as const

    return (
        densityMap[selectedDensity.value as keyof typeof densityMap] ??
        densityMap.comfortable
    )
})
const previewRadiusValue = computed(() => {
    const radiusMap = {
        none: '0px',
        lg: '1rem',
        md: '0.75rem',
        sm: '0.5rem',
        xl: '1.25rem',
    } as const

    return (
        radiusMap[selectedRadius.value as keyof typeof radiusMap] ?? '0.75rem'
    )
})
const selectedPreviewPalette = computed(() =>
    resolveCorePanelPreviewPalette(selectedPalette.value),
)
const componentPreviewStyle = computed(() => ({
    '--cp-settings-preview-accent': selectedAccentColor.value,
    '--cp-settings-preview-card-padding':
        previewDensityVariables.value.cardPadding,
    '--cp-settings-preview-content-gap':
        previewDensityVariables.value.contentGap,
    '--cp-settings-preview-control-padding-x':
        previewDensityVariables.value.controlPaddingX,
    '--cp-settings-preview-control-padding-y':
        previewDensityVariables.value.controlPaddingY,
    '--cp-settings-preview-control-radius':
        previewDensityVariables.value.controlRadius,
    '--cp-settings-preview-input-height':
        previewDensityVariables.value.inputHeight,
    '--cp-settings-preview-radius': previewRadiusValue.value,
    '--cp-settings-preview-light-frame':
        selectedPreviewPalette.value.light.frame,
    '--cp-settings-preview-light-header':
        selectedPreviewPalette.value.light.header,
    '--cp-settings-preview-light-shell':
        selectedPreviewPalette.value.light.shell,
    '--cp-settings-preview-light-sidebar':
        selectedPreviewPalette.value.light.sidebar,
    '--cp-settings-preview-light-text': selectedPreviewPalette.value.light.text,
    '--cp-settings-preview-light-muted':
        selectedPreviewPalette.value.light.textMuted,
    '--cp-settings-preview-dark-frame': selectedPreviewPalette.value.dark.frame,
    '--cp-settings-preview-dark-header':
        selectedPreviewPalette.value.dark.header,
    '--cp-settings-preview-dark-shell': selectedPreviewPalette.value.dark.shell,
    '--cp-settings-preview-dark-sidebar':
        selectedPreviewPalette.value.dark.sidebar,
    '--cp-settings-preview-dark-text': selectedPreviewPalette.value.dark.text,
    '--cp-settings-preview-dark-muted':
        selectedPreviewPalette.value.dark.textMuted,
}))
const showThemePreviewDialog = ref(false)

const paletteOptions: Array<{
    dark: {
        frame: string
        header: string
        shell: string
        sidebar: string
        text: string
    }
    descriptionKey: string
    key: CorePanelThemePalette
    light: {
        frame: string
        header: string
        shell: string
        sidebar: string
        text: string
    }
    titleKey: string
}> = [
    {
        dark: {
            frame: '#1e293b',
            header: '#172033',
            shell: '#0f172a',
            sidebar: '#111827',
            text: 'rgba(255, 255, 255, 0.12)',
        },
        descriptionKey: 'page-settings.appearance_palette_description_paper',
        key: 'paper',
        light: {
            frame: '#dbe2ea',
            header: '#f6f8fb',
            shell: '#ffffff',
            sidebar: '#ffffff',
            text: 'rgba(15, 23, 42, 0.12)',
        },
        titleKey: 'settings.options.theme_palette.paper',
    },
    {
        dark: {
            frame: '#4a3c2f',
            header: '#382d25',
            shell: '#211b16',
            sidebar: '#2e241d',
            text: 'rgba(255, 236, 214, 0.16)',
        },
        descriptionKey: 'page-settings.appearance_palette_description_soft',
        key: 'soft',
        light: {
            frame: '#ddd1bf',
            header: '#fffaf2',
            shell: '#f7f3eb',
            sidebar: '#efe3d1',
            text: 'rgba(120, 90, 58, 0.18)',
        },
        titleKey: 'settings.options.theme_palette.soft',
    },
    {
        dark: {
            frame: '#12425a',
            header: '#113247',
            shell: '#071b27',
            sidebar: '#0a2735',
            text: 'rgba(178, 230, 255, 0.18)',
        },
        descriptionKey: 'page-settings.appearance_palette_description_ocean',
        key: 'ocean',
        light: {
            frame: '#bfdcf1',
            header: '#f8fcff',
            shell: '#eff8ff',
            sidebar: '#d8eef8',
            text: 'rgba(10, 91, 126, 0.16)',
        },
        titleKey: 'settings.options.theme_palette.ocean',
    },
    {
        dark: {
            frame: '#334155',
            header: '#111c31',
            shell: '#020617',
            sidebar: '#0f172a',
            text: 'rgba(226, 232, 240, 0.18)',
        },
        descriptionKey: 'page-settings.appearance_palette_description_contrast',
        key: 'contrast',
        light: {
            frame: '#b8c3d1',
            header: '#ffffff',
            shell: '#eef1f5',
            sidebar: '#dce3ec',
            text: 'rgba(15, 23, 42, 0.18)',
        },
        titleKey: 'settings.options.theme_palette.contrast',
    },
]

watch(
    selectedPalette,
    (palette) => {
        applyCorePanelThemePalette(palette)
    },
    { immediate: true },
)

watch(
    selectedAccentColor,
    (color) => {
        applyCorePanelThemeAccent(color)
    },
    { immediate: true },
)

watch(
    selectedDensity,
    (density) => {
        applyCorePanelLayoutDensity(normalizeCorePanelLayoutDensity(density))
    },
    { immediate: true },
)

watch(
    selectedRadius,
    (radius) => {
        applyCorePanelRadiusToken(normalizeCorePanelRadiusToken(radius))
    },
    { immediate: true },
)

onBeforeUnmount(() => {
    applyCorePanelThemePalette(savedPalette.value)
    applyCorePanelThemeAccent(savedAccentColor.value)
    applyCorePanelLayoutDensity(savedDensity.value)
    applyCorePanelRadiusToken(savedRadius.value)
})

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

function saveStyles(): void {
    styleForm.put(settings.styles.url(), {
        onSuccess: () => {
            styleForm.defaults()
        },
        preserveScroll: true,
    })
}

function setThemePalette(palette: CorePanelThemePalette): void {
    applyCorePanelThemePalette(palette)
    styleForm.values.theme_palette.value = palette
}

function setThemeColor(color: string): void {
    const normalized = normalizeCorePanelThemeAccent(color)

    applyCorePanelThemeAccent(normalized)
    styleForm.values.primary_color_token.value = normalized
}

function setUiFieldValue(key: string, value: SettingFormValue): void {
    if (!styleForm.values[key]) {
        return
    }

    styleForm.values[key].value = value
}
</script>

<template>
    <div class="cp-settings-appearance-tab">
        <form
            class="cp-section cp-section--sticky-actions"
            @submit.prevent="saveStyles"
        >
            <div class="cp-section__header">
                <div class="grid gap-1">
                    <h2
                        class="text-lg font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ appearanceGroup?.label ?? '' }}
                    </h2>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ appearanceGroup?.description ?? '' }}
                    </p>
                </div>
            </div>

            <div class="cp-section__body grid gap-6 lg:gap-7">
                <section
                    class="grid gap-4 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel)_96%,transparent)] p-4"
                >
                    <div>
                        <div class="grid gap-1">
                            <h3
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{
                                    $t('page-settings.appearance_accent_title')
                                }}
                            </h3>
                            <p class="text-sm text-[var(--cp-text-muted)]">
                                {{ $t('page-settings.appearance_accent_hint') }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-center"
                    >
                        <label
                            class="flex items-center gap-4 rounded-[var(--cp-radius-lg)] border border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_55%,transparent)] px-4 py-3"
                        >
                            <input
                                :value="selectedAccentColor"
                                type="color"
                                class="theme-color-input h-14 w-14 cursor-pointer rounded-2xl border-0 bg-transparent p-0 shadow-sm ring-1 ring-black/10"
                                @input="
                                    setThemeColor(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                            <span
                                class="text-sm font-medium text-[color:var(--cp-text-primary)]"
                            >
                                {{
                                    $t('page-settings.appearance_accent_picker')
                                }}
                            </span>
                        </label>

                        <div class="flex min-w-0 flex-col gap-2">
                            <label
                                class="text-xs font-semibold uppercase tracking-wide text-[color:var(--cp-text-muted)]"
                            >
                                {{ $t('page-settings.appearance_accent_hex') }}
                            </label>
                            <InputText
                                :model-value="selectedAccentColor"
                                class="w-full"
                                @update:model-value="
                                    setThemeColor(String($event ?? ''))
                                "
                            />
                            <p
                                class="text-xs text-[color:var(--cp-text-muted)]"
                            >
                                {{ $t('page-settings.appearance_accent_hint') }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="grid gap-4 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel)_96%,transparent)] p-4"
                >
                    <div class="grid gap-1">
                        <h3
                            class="text-sm font-semibold text-[color:var(--cp-text-primary)]"
                        >
                            {{
                                $t(
                                    'page-settings.appearance_palette_preview_title',
                                )
                            }}
                        </h3>
                        <p class="text-sm text-[color:var(--cp-text-muted)]">
                            {{ $t('page-settings.appearance_palette_hint') }}
                        </p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <button
                            v-for="palette in paletteOptions"
                            :key="palette.key"
                            type="button"
                            class="group rounded-[var(--cp-radius-lg)] border p-3 text-left transition-all duration-150"
                            :class="
                                selectedPalette === palette.key
                                    ? 'border-primary/45 bg-primary/8 shadow-sm'
                                    : 'border-[color:var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel-alt)_58%,transparent)] hover:border-primary/35 hover:bg-primary/4'
                            "
                            @click="setThemePalette(palette.key)"
                        >
                            <div class="mb-3 grid gap-2 md:grid-cols-2">
                                <div
                                    class="overflow-hidden rounded-[var(--cp-settings-preview-radius)] border"
                                    :style="{
                                        borderColor: palette.light.frame,
                                        backgroundColor: palette.light.shell,
                                    }"
                                >
                                    <div
                                        class="border-b px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500"
                                        :style="{
                                            borderColor: palette.light.frame,
                                        }"
                                    >
                                        {{ $t('page-layout.light') }}
                                    </div>
                                    <div class="flex h-20">
                                        <div
                                            class="flex w-12 shrink-0 flex-col gap-1 p-2"
                                            :style="{
                                                backgroundColor:
                                                    palette.light.sidebar,
                                            }"
                                        >
                                            <span
                                                class="h-2 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        palette.light.text,
                                                }"
                                            />
                                            <span
                                                class="h-2 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        palette.light.text,
                                                }"
                                            />
                                            <span
                                                class="mt-auto h-5 rounded-[var(--cp-settings-preview-control-radius)]"
                                                :style="{
                                                    backgroundColor:
                                                        'var(--p-primary-300)',
                                                }"
                                            />
                                        </div>
                                        <div class="flex-1 p-2">
                                            <div
                                                class="mb-2 h-3 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        palette.light.header,
                                                }"
                                            />
                                            <div class="space-y-1.5">
                                                <div
                                                    class="h-2 rounded-full"
                                                    :style="{
                                                        backgroundColor:
                                                            palette.light.text,
                                                    }"
                                                />
                                                <div
                                                    class="h-2 w-4/5 rounded-full"
                                                    :style="{
                                                        backgroundColor:
                                                            palette.light.text,
                                                    }"
                                                />
                                                <div
                                                    class="h-7 w-2/5 rounded-[var(--cp-settings-preview-control-radius)]"
                                                    :style="{
                                                        backgroundColor:
                                                            'var(--p-primary-400)',
                                                    }"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="overflow-hidden rounded-[var(--cp-settings-preview-radius)] border"
                                    :style="{
                                        borderColor: palette.dark.frame,
                                        backgroundColor: palette.dark.shell,
                                    }"
                                >
                                    <div
                                        class="border-b px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300"
                                        :style="{
                                            borderColor: palette.dark.frame,
                                        }"
                                    >
                                        {{ $t('page-layout.dark') }}
                                    </div>
                                    <div class="flex h-20">
                                        <div
                                            class="flex w-12 shrink-0 flex-col gap-1 p-2"
                                            :style="{
                                                backgroundColor:
                                                    palette.dark.sidebar,
                                            }"
                                        >
                                            <span
                                                class="h-2 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        palette.dark.text,
                                                }"
                                            />
                                            <span
                                                class="h-2 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        palette.dark.text,
                                                }"
                                            />
                                            <span
                                                class="mt-auto h-5 rounded-[var(--cp-settings-preview-control-radius)]"
                                                :style="{
                                                    backgroundColor:
                                                        'var(--p-primary-400)',
                                                }"
                                            />
                                        </div>
                                        <div class="flex-1 p-2">
                                            <div
                                                class="mb-2 h-3 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        palette.dark.header,
                                                }"
                                            />
                                            <div class="space-y-1.5">
                                                <div
                                                    class="h-2 rounded-full"
                                                    :style="{
                                                        backgroundColor:
                                                            palette.dark.text,
                                                    }"
                                                />
                                                <div
                                                    class="h-2 w-4/5 rounded-full"
                                                    :style="{
                                                        backgroundColor:
                                                            palette.dark.text,
                                                    }"
                                                />
                                                <div
                                                    class="h-7 w-2/5 rounded-[var(--cp-settings-preview-control-radius)]"
                                                    :style="{
                                                        backgroundColor:
                                                            'var(--p-primary-500)',
                                                    }"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div
                                        class="text-sm font-semibold text-[color:var(--cp-text-primary)]"
                                    >
                                        {{ $t(palette.titleKey) }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs text-[color:var(--cp-text-muted)]"
                                    >
                                        {{ $t(palette.descriptionKey) }}
                                    </div>
                                </div>
                                <AppIcon
                                    v-if="selectedPalette === palette.key"
                                    class="text-primary"
                                    name="check"
                                />
                            </div>
                        </button>
                    </div>
                </section>

                <section
                    class="grid gap-4 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel)_96%,transparent)] p-4"
                    :style="componentPreviewStyle"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h4
                                class="text-sm font-semibold text-[color:var(--cp-text-primary)]"
                            >
                                {{
                                    $t(
                                        'page-settings.appearance_component_preview_title',
                                    )
                                }}
                            </h4>
                            <p
                                class="mt-1 text-xs text-[color:var(--cp-text-muted)]"
                            >
                                {{
                                    $t(
                                        'page-settings.appearance_component_preview_hint',
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="h-10 w-10 rounded-full border-4 border-white shadow-sm dark:border-surface-900"
                            :style="{ backgroundColor: 'var(--p-primary-500)' }"
                        />
                    </div>

                    <div
                        class="cp-settings-appearance-tab__component-preview"
                        :data-preview-mode="selectedDefaultMode"
                    >
                        <div
                            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    class="rounded-[var(--cp-settings-preview-control-radius)] px-3 py-2 text-sm font-medium text-white transition"
                                    :style="{
                                        backgroundColor:
                                            selectedDefaultMode === 'dark'
                                                ? 'var(--p-primary-700)'
                                                : 'var(--p-primary-600)',
                                    }"
                                >
                                    {{
                                        $t(
                                            'page-settings.appearance_component_preview_cta',
                                        )
                                    }}
                                </button>
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :style="{
                                        backgroundColor:
                                            selectedDefaultMode === 'dark'
                                                ? 'color-mix(in srgb, var(--p-primary-500) 20%, transparent)'
                                                : 'var(--p-primary-50)',
                                        color:
                                            selectedDefaultMode === 'dark'
                                                ? 'var(--p-primary-200)'
                                                : 'var(--p-primary-700)',
                                    }"
                                >
                                    {{
                                        $t(
                                            'page-settings.appearance_component_preview_badge',
                                        )
                                    }}
                                </span>
                            </div>

                            <div class="flex items-center gap-3">
                                <div
                                    class="hidden h-12 w-20 overflow-hidden rounded-[var(--cp-settings-preview-radius)] border shadow-sm md:block"
                                    :class="
                                        selectedDefaultMode === 'dark'
                                            ? 'border-slate-700 bg-slate-950'
                                            : 'border-surface-200 bg-white'
                                    "
                                >
                                    <div class="flex h-full">
                                        <div
                                            class="w-4"
                                            :style="{
                                                backgroundColor:
                                                    selectedDefaultMode ===
                                                    'dark'
                                                        ? 'color-mix(in srgb, var(--p-primary-950) 18%, #0f172a 82%)'
                                                        : 'var(--p-primary-100)',
                                            }"
                                        />
                                        <div
                                            class="flex flex-1 flex-col justify-center gap-1 px-2"
                                        >
                                            <span
                                                class="h-1.5 rounded-full"
                                                :class="
                                                    selectedDefaultMode ===
                                                    'dark'
                                                        ? 'bg-slate-700'
                                                        : 'bg-surface-300'
                                                "
                                            />
                                            <span
                                                class="h-3 rounded-[var(--cp-settings-preview-control-radius)]"
                                                :style="{
                                                    backgroundColor:
                                                        selectedDefaultMode ===
                                                        'dark'
                                                            ? 'var(--p-primary-500)'
                                                            : 'var(--p-primary-400)',
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <Button
                                    :icon="undefined"
                                    outlined
                                    type="button"
                                    @click="showThemePreviewDialog = true"
                                >
                                    <AppIcon name="eye" />
                                    <span>{{ $t('common.ui.view') }}</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-2 2xl:grid-cols-4">
                    <section
                        v-if="uiFieldMap.show_app_footer"
                        class="grid gap-3 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel)_96%,transparent)] p-4"
                    >
                        <div class="grid gap-1">
                            <label
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ uiFieldMap.show_app_footer.label }}
                            </label>
                            <p
                                v-if="uiFieldMap.show_app_footer.help"
                                class="text-sm text-[var(--cp-text-muted)]"
                            >
                                {{ uiFieldMap.show_app_footer.help }}
                            </p>
                        </div>

                        <div
                            class="cp-settings-appearance-tab__preview-toggles"
                        >
                            <button
                                type="button"
                                class="cp-settings-appearance-tab__preview-toggle"
                                :class="{
                                    'is-active':
                                        selectedFooterVisibility === 'visible',
                                }"
                                @click="
                                    setUiFieldValue('show_app_footer', true)
                                "
                            >
                                {{ $t('common.ui.yes') }}
                            </button>
                            <button
                                type="button"
                                class="cp-settings-appearance-tab__preview-toggle"
                                :class="{
                                    'is-active':
                                        selectedFooterVisibility === 'hidden',
                                }"
                                @click="
                                    setUiFieldValue('show_app_footer', false)
                                "
                            >
                                {{ $t('common.ui.no') }}
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="uiFieldMap.layout_density"
                        class="grid gap-3 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel)_96%,transparent)] p-4"
                    >
                        <div class="grid gap-1">
                            <label
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ uiFieldMap.layout_density.label }}
                            </label>
                            <p
                                v-if="uiFieldMap.layout_density.help"
                                class="text-sm text-[var(--cp-text-muted)]"
                            >
                                {{ uiFieldMap.layout_density.help }}
                            </p>
                        </div>

                        <div
                            class="cp-settings-appearance-tab__preview-toggles"
                        >
                            <button
                                v-for="option in uiFieldMap.layout_density
                                    .options"
                                :key="String(option.value)"
                                type="button"
                                class="cp-settings-appearance-tab__preview-toggle"
                                :class="{
                                    'is-active':
                                        selectedDensity ===
                                        String(option.value),
                                }"
                                @click="
                                    setUiFieldValue(
                                        'layout_density',
                                        option.value as SettingFormValue,
                                    )
                                "
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="uiFieldMap.radius_token"
                        class="grid gap-3 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[color:color-mix(in_srgb,var(--cp-surface-panel)_96%,transparent)] p-4"
                    >
                        <div class="grid gap-1">
                            <label
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ uiFieldMap.radius_token.label }}
                            </label>
                            <p
                                v-if="uiFieldMap.radius_token.help"
                                class="text-sm text-[var(--cp-text-muted)]"
                            >
                                {{ uiFieldMap.radius_token.help }}
                            </p>
                        </div>

                        <div
                            class="cp-settings-appearance-tab__preview-toggles"
                        >
                            <button
                                v-for="option in uiFieldMap.radius_token
                                    .options"
                                :key="String(option.value)"
                                type="button"
                                class="cp-settings-appearance-tab__preview-toggle"
                                :class="{
                                    'is-active':
                                        selectedRadius === String(option.value),
                                }"
                                @click="
                                    setUiFieldValue(
                                        'radius_token',
                                        option.value as SettingFormValue,
                                    )
                                "
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </section>
                </div>

                <div
                    class="cp-settings-appearance-tab__actions cp-settings-appearance-tab__actions--sticky"
                >
                    <Button
                        :disabled="styleForm.processing || !styleForm.isDirty"
                        :loading="styleForm.processing"
                        type="submit"
                    >
                        <AppIcon name="save" />
                        <span>{{ $t('common.ui.save') }}</span>
                    </Button>
                </div>
            </div>
        </form>

        <Dialog
            v-model:visible="showThemePreviewDialog"
            modal
            maximizable
            :header="$t('page-settings.appearance_component_preview_title')"
            :style="{ width: '72rem', maxWidth: '96vw' }"
        >
            <div
                class="cp-settings-appearance-tab__dialog-preview"
                :style="componentPreviewStyle"
            >
                <p class="cp-settings-appearance-tab__preview-summary-hint">
                    {{ $t('page-settings.appearance_component_preview_hint') }}
                </p>

                <div class="cp-settings-appearance-tab__dialog-grid">
                    <div class="cp-settings-appearance-tab__dialog-surface">
                        <div
                            class="cp-settings-appearance-tab__dialog-surface-header"
                        >
                            <span
                                class="cp-settings-appearance-tab__dialog-surface-mode"
                            >
                                {{ $t('page-layout.light') }}
                            </span>
                            <span
                                class="cp-settings-appearance-tab__preview-badge"
                            >
                                {{
                                    $t(
                                        'page-settings.appearance_component_preview_badge',
                                    )
                                }}
                            </span>
                        </div>

                        <div class="cp-settings-appearance-tab__dialog-body">
                            <div
                                class="cp-settings-appearance-tab__preview-actions"
                            >
                                <button
                                    class="cp-settings-appearance-tab__preview-button"
                                    type="button"
                                >
                                    {{ $t('common.ui.save') }}
                                </button>
                                <button
                                    class="cp-settings-appearance-tab__preview-button cp-settings-appearance-tab__preview-button--ghost"
                                    type="button"
                                >
                                    {{ $t('common.ui.cancel') }}
                                </button>
                                <button
                                    class="cp-settings-appearance-tab__preview-button"
                                    type="button"
                                >
                                    {{
                                        $t(
                                            'page-settings.appearance_component_preview_cta',
                                        )
                                    }}
                                </button>
                            </div>

                            <div
                                class="cp-settings-appearance-tab__dialog-input-card"
                            >
                                <div
                                    class="cp-settings-appearance-tab__dialog-input-title"
                                >
                                    {{
                                        $t(
                                            'page-settings.appearance_accent_title',
                                        )
                                    }}
                                </div>
                                <div
                                    class="cp-settings-appearance-tab__preview-input"
                                >
                                    <span
                                        class="cp-settings-appearance-tab__preview-input-dot"
                                    />
                                    <span
                                        class="cp-settings-appearance-tab__preview-input-text"
                                    >
                                        brand@company.test
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="cp-settings-appearance-tab__dialog-surface cp-settings-appearance-tab__dialog-surface--dark"
                    >
                        <div
                            class="cp-settings-appearance-tab__dialog-surface-header"
                        >
                            <span
                                class="cp-settings-appearance-tab__dialog-surface-mode"
                            >
                                {{ $t('page-layout.dark') }}
                            </span>
                            <span
                                class="cp-settings-appearance-tab__preview-badge"
                            >
                                {{
                                    $t(
                                        'page-settings.appearance_component_preview_badge',
                                    )
                                }}
                            </span>
                        </div>

                        <div class="cp-settings-appearance-tab__dialog-body">
                            <div
                                class="cp-settings-appearance-tab__preview-actions"
                            >
                                <button
                                    class="cp-settings-appearance-tab__preview-button"
                                    type="button"
                                >
                                    {{ $t('common.ui.save') }}
                                </button>
                                <button
                                    class="cp-settings-appearance-tab__preview-button cp-settings-appearance-tab__preview-button--ghost"
                                    type="button"
                                >
                                    {{ $t('common.ui.cancel') }}
                                </button>
                                <button
                                    class="cp-settings-appearance-tab__preview-button"
                                    type="button"
                                >
                                    {{
                                        $t(
                                            'page-settings.appearance_component_preview_cta',
                                        )
                                    }}
                                </button>
                            </div>

                            <div
                                class="cp-settings-appearance-tab__dialog-input-card"
                            >
                                <div
                                    class="cp-settings-appearance-tab__dialog-input-title"
                                >
                                    {{
                                        $t(
                                            'page-settings.appearance_accent_title',
                                        )
                                    }}
                                </div>
                                <div
                                    class="cp-settings-appearance-tab__preview-input"
                                >
                                    <span
                                        class="cp-settings-appearance-tab__preview-input-dot"
                                    />
                                    <span
                                        class="cp-settings-appearance-tab__preview-input-text"
                                    >
                                        brand@company.test
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>
