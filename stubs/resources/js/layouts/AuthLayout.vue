<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import {
    currentLocale as activeLocale,
    loadLanguageAsync,
} from 'laravel-vue-i18n'
import type { MenuItem } from 'primevue/menuitem'
import { computed, ref, useSlots } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import AppToast from '@/components/AppToast.vue'
import CorePanelLogo from '@/components/CorePanelLogo.vue'
import LocaleFlag from '@/components/Locale/LocaleFlag.vue'
import { useColorMode } from '@/composables/useColorMode'
import { useRuntimeUiSettings } from '@/composables/useRuntimeUiSettings'
import locale from '@/routes/locale'

withDefaults(
    defineProps<{
        heading?: string
        subheading?: string
        showSubheading?: boolean
    }>(),
    {
        heading: '',
        subheading: '',
        showSubheading: false,
    },
)

type LocaleMenuItem = MenuItem & {
    localeCode: string
}

const page = usePage<{
    appName?: string
    appSubtitle?: string | null
    appLogo?: string | null
    locale?: {
        current?: string
        supported?: string[]
        labels?: Record<string, string>
    }
    url?: string
}>()

const { isDarkMode, toggleColorMode } = useColorMode('system')
useRuntimeUiSettings()

const appName = computed(() => page.props.appName ?? 'CorePanel')
const appSubtitle = computed(() => {
    const value = page.props.appSubtitle

    return typeof value === 'string' && value.trim() !== '' ? value : null
})
const appLogo = computed(() => page.props.appLogo ?? null)
const slots = useSlots()
const currentLocale = computed(
    () => activeLocale.value || page.props.locale?.current || 'en',
)
const supportedLocales = computed(
    () => page.props.locale?.supported ?? ['de', 'en'],
)
const currentLocaleLabel = computed(() =>
    displayLocaleLabel(currentLocale.value),
)
const localeMenuOpen = ref(false)
const localeMenuRef = ref()

const localeMenuItems = computed<LocaleMenuItem[]>(() =>
    supportedLocales.value.map((code) => ({
        command: () => switchLocale(code),
        label: displayLocaleLabel(code),
        localeCode: code,
    })),
)

const hasHeaderSlot = computed(() => Boolean(slots.header))

function displayLocaleLabel(code: string): string {
    return page.props.locale?.labels?.[code] ?? code.toUpperCase()
}

function toggleLocaleMenu(event: Event): void {
    localeMenuOpen.value = !localeMenuOpen.value
    localeMenuRef.value?.toggle(event)
}

async function switchLocale(code: string): Promise<void> {
    if (code === currentLocale.value) {
        return
    }

    router.post(
        locale.set.url(),
        {
            locale: code,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: async () => {
                await loadLanguageAsync(code)
                document.documentElement.lang = code

                router.reload()
            },
        },
    )
}
</script>

<template>
    <div class="auth-layout">
        <AppToast />

        <div class="auth-layout__controls">
            <button
                v-if="supportedLocales.length > 1"
                class="auth-control-button auth-control-button--locale"
                :aria-expanded="localeMenuOpen"
                :title="currentLocaleLabel"
                aria-label="Change language"
                type="button"
                @click="toggleLocaleMenu"
            >
                <LocaleFlag :code="currentLocale" />
                <AppIcon
                    class="auth-locale-switch__chevron"
                    name="chevron-down"
                    :class="{ 'is-open': localeMenuOpen }"
                />
            </button>

            <Menu
                ref="localeMenuRef"
                :model="localeMenuItems"
                :popup="true"
                @hide="localeMenuOpen = false"
                @show="localeMenuOpen = true"
            >
                <template #item="{ item, props: menuItemProps }">
                    <a
                        class="auth-locale-switch__item"
                        v-bind="menuItemProps.action"
                        href="#"
                        @click.prevent="switchLocale(item.localeCode)"
                    >
                        <LocaleFlag :code="item.localeCode" />
                        <span class="auth-locale-switch__item-label">
                            {{ item.label }}
                        </span>
                        <AppIcon
                            v-if="item.localeCode === currentLocale"
                            class="auth-locale-switch__check"
                            name="check"
                        />
                    </a>
                </template>
            </Menu>

            <button
                class="auth-control-button"
                type="button"
                @click="toggleColorMode"
            >
                <AppIcon :name="isDarkMode ? 'sun' : 'moon'" />
            </button>
        </div>

        <div class="auth-left">
            <div class="auth-left-brand">
                <img
                    v-if="appLogo"
                    :src="appLogo"
                    :alt="appName"
                    class="auth-left-logo"
                />
                <div v-else class="auth-left-icon">
                    <CorePanelLogo :title="appName" />
                </div>
                <h1>{{ appName }}</h1>
            </div>

            <h2 v-if="appSubtitle">{{ appSubtitle }}</h2>

            <div class="auth-left-status" aria-hidden="true">
                <span
                    class="auth-left-status__tile auth-left-status__tile--primary"
                />
                <span
                    class="auth-left-status__tile auth-left-status__tile--muted"
                />
                <span
                    class="auth-left-status__tile auth-left-status__tile--muted"
                />
                <span
                    class="auth-left-status__tile auth-left-status__tile--accent"
                />
            </div>
        </div>

        <div class="auth-right">
            <div class="auth-mobile-brand">
                <img
                    v-if="appLogo"
                    :src="appLogo"
                    :alt="appName"
                    class="auth-mobile-brand__logo"
                />
                <div v-else class="auth-mobile-brand__icon">
                    <CorePanelLogo :title="appName" />
                </div>
            </div>

            <div class="auth-card">
                <template v-if="hasHeaderSlot">
                    <slot name="header" />
                </template>
                <template v-else>
                    <div class="auth-header">
                        <h2 class="auth-title">{{ heading }}</h2>
                        <p v-if="showSubheading" class="auth-subtitle">
                            {{ subheading }}
                        </p>
                    </div>
                </template>
                <slot />
            </div>

            <div class="auth-footer">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
