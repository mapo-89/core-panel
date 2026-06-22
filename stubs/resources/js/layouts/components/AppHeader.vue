<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import {
    currentLocale as activeLocale,
    loadLanguageAsync,
} from 'laravel-vue-i18n'
import { trans } from 'laravel-vue-i18n'
import type { MenuItem } from 'primevue/menuitem'
import { computed, ref } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import LocaleFlag from '@/components/Locale/LocaleFlag.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { useCan } from '@/composables/useCan'
import locale from '@/routes/locale'
import profile from '@/routes/profile'
import settings from '@/routes/core-panel/settings'
import users from '@/routes/core-panel/users'
import type { CorePanelColorModePreference } from '@core-panel/theme/core-panel'

type LocaleMenuItem = MenuItem & {
    localeCode: string
}

type UserMenuItem = MenuItem & {
    iconName?: string
}

const props = defineProps<{
    collapsed: boolean
    colorMode: CorePanelColorModePreference
    isDarkMode: boolean
    isMobile: boolean
}>()

const emit = defineEmits<{
    setColorMode: [mode: CorePanelColorModePreference]
    toggleSidebar: []
}>()

const page = usePage<{
    appName?: string
    auth?: {
        user?: {
            id?: string | null
            avatarUrl?: string | null
            email?: string | null
            firstName?: string | null
            lastName?: string | null
            presenceLastSeenAt?: number | null
            presenceStatus?: 'online' | 'away' | 'offline' | null
        }
    }
    locale?: {
        current?: string
        supported?: string[]
        labels?: Record<string, string>
    }
}>()

const appName = computed(() => page.props.appName ?? 'CorePanel')
const user = computed(() => page.props.auth?.user ?? null)
const userDisplayName = computed(() => {
    const firstName = user.value?.firstName?.trim() ?? ''
    const lastName = user.value?.lastName?.trim() ?? ''

    return [firstName, lastName].filter(Boolean).join(' ')
})
const currentLocale = computed(
    () => activeLocale.value || page.props.locale?.current || 'en',
)
const supportedLocales = computed(
    () => page.props.locale?.supported ?? ['de', 'en'],
)
const currentLocaleLabel = computed(() =>
    displayLocaleLabel(currentLocale.value),
)
const { can, canAny } = useCan()
const localeMenuOpen = ref(false)
const localeMenuRef = ref()
const userMenuRef = ref()

const userInitials = computed(() => {
    const name = userDisplayName.value.trim()

    if (!name) {
        return 'CP'
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((segment) => segment[0]?.toUpperCase() ?? '')
        .join('')
})

const localeMenuItems = computed<LocaleMenuItem[]>(() =>
    supportedLocales.value.map((code) => ({
        command: () => switchLocale(code),
        label: displayLocaleLabel(code),
        localeCode: code,
    })),
)
const colorModeIcon = computed(() => {
    if (nextColorMode.value === 'system') {
        return 'desktop'
    }

    return nextColorMode.value === 'dark' ? 'moon' : 'sun'
})
const nextColorMode = computed<CorePanelColorModePreference>(() => {
    if (props.colorMode === 'system') {
        return 'light'
    }

    if (props.colorMode === 'light') {
        return 'dark'
    }

    return 'system'
})
const nextColorModeLabel = computed(() => {
    if (nextColorMode.value === 'system') {
        return trans('page-layout.system')
    }

    return nextColorMode.value === 'dark'
        ? trans('page-layout.dark')
        : trans('page-layout.light')
})
const colorModeTooltip = computed(() => nextColorModeLabel.value)

const userMenuItems = computed<UserMenuItem[]>(() => {
    const items: UserMenuItem[] = [
        {
            command: () => router.visit(profile.show.url()),
            iconName: 'user',
            label: trans('common.auth.profile'),
        },
    ]

    if (canAny(['roles.view', 'user-groups.view', 'users.view'])) {
        items.push({
            command: () => router.visit(users.index.url()),
            iconName: 'users',
            label: trans('page-users.management_title'),
        })
    }

    if (can('settings.view')) {
        items.push({
            command: () => router.visit(settings.index.url()),
            iconName: 'settings',
            label: trans('navigation.settings'),
        })
    }

    items.push(
        { separator: true },
        {
            command: () => {
                router.post('/logout')
            },
            iconName: 'sign-out',
            label: trans('common.auth.logout'),
        },
    )

    return items
})

const localeMenuPt = {
    root: {
        class: 'cp-header-menu cp-header-menu--locale',
    },
}

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

function toggleUserMenu(event: Event): void {
    userMenuRef.value?.toggle(event)
}
</script>

<template>
    <header
        class="app-header"
        :class="{
            'app-header--collapsed': !props.isMobile && props.collapsed,
            'app-header--expanded': !props.isMobile && !props.collapsed,
            'app-header--mobile': props.isMobile,
        }"
    >
        <div
            class="flex h-[4.5rem] w-full items-center justify-between gap-4 px-4 md:px-6 lg:px-8"
        >
            <div class="flex items-center gap-2.5">
                <button
                    class="app-header__icon-button"
                    :aria-label="$t('page-layout.menu')"
                    type="button"
                    @click="emit('toggleSidebar')"
                >
                    <AppIcon :name="props.isMobile ? 'menu' : 'sidebar'" />
                </button>
            </div>

            <div class="flex min-w-0 items-center gap-2.5">
                <div v-if="supportedLocales.length > 1" class="hidden md:block">
                    <button
                        class="app-header__locale-button"
                        :aria-expanded="localeMenuOpen"
                        :title="currentLocaleLabel"
                        :aria-label="$t('common.ui.change_language')"
                        type="button"
                        @click="toggleLocaleMenu"
                    >
                        <LocaleFlag :code="currentLocale" />
                        <AppIcon
                            class="app-header__locale-chevron"
                            name="chevron-down"
                            :class="{ 'is-open': localeMenuOpen }"
                        />
                    </button>

                    <Menu
                        ref="localeMenuRef"
                        :model="localeMenuItems"
                        :popup="true"
                        :pt="localeMenuPt"
                        @hide="localeMenuOpen = false"
                        @show="localeMenuOpen = true"
                    >
                        <template #item="{ item, props: menuItemProps }">
                            <a
                                class="flex min-w-40 items-center gap-[0.65rem] px-3 py-2"
                                v-bind="menuItemProps.action"
                                href="#"
                                @click.prevent="switchLocale(item.localeCode)"
                            >
                                <LocaleFlag :code="item.localeCode" />
                                <span class="flex-1 text-[0.85rem] font-medium">
                                    {{ item.label }}
                                </span>
                                <AppIcon
                                    v-if="item.localeCode === currentLocale"
                                    class="cp-header-menu__locale-check"
                                    name="check"
                                />
                            </a>
                        </template>
                    </Menu>
                </div>

                <button
                    v-tooltip.top="colorModeTooltip"
                    :aria-label="colorModeTooltip"
                    class="app-header__icon-button"
                    type="button"
                    @click="emit('setColorMode', nextColorMode)"
                >
                    <AppIcon :name="colorModeIcon" />
                </button>
                <button
                    class="app-header__icon-button"
                    type="button"
                    aria-label="Notifications"
                >
                    <AppIcon name="bell" />
                </button>

                <button
                    v-if="user"
                    class="app-header__user"
                    type="button"
                    @click="toggleUserMenu"
                >
                    <div class="hidden flex-col items-end gap-0.5 sm:flex">
                        <span
                            class="text-sm font-semibold leading-tight text-[var(--cp-text-primary)]"
                        >
                            {{ userDisplayName || appName }}
                        </span>
                        <span
                            class="text-xs leading-tight text-[var(--cp-text-muted)]"
                        >
                            {{ user.email ?? '' }}
                        </span>
                    </div>
                    <UserAvatar
                        :avatar-url="user.avatarUrl"
                        :initials="userInitials"
                        :presence-last-seen-at="user.presenceLastSeenAt ?? null"
                        :presence-status="user.presenceStatus ?? 'offline'"
                        :user-id="user.id ?? null"
                        size="sm"
                    />
                </button>

                <Menu ref="userMenuRef" :model="userMenuItems" :popup="true">
                    <template #item="{ item, props: menuItemProps }">
                        <a
                            class="flex min-w-40 items-center gap-[0.65rem] px-3 py-2"
                            v-bind="menuItemProps.action"
                        >
                            <AppIcon
                                v-if="typeof item.iconName === 'string'"
                                :name="item.iconName"
                            />
                            <span class="flex-1 text-[0.85rem] font-medium">
                                {{ item.label }}
                            </span>
                        </a>
                    </template>
                </Menu>
            </div>
        </div>
    </header>
</template>
