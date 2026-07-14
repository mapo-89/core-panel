<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import type { MenuItem } from 'primevue/menuitem'
import { computed, provide, ref } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import CorePanelLogo from '@core-panel/components/CorePanelLogo.vue'
import { useAdminMenu } from '@core-panel/composables/useAdminMenu'
import SidebarMenuItem from '@core-panel/layouts/components/SidebarMenuItem.vue'
import { APP_RELEASE_VERSION } from '@core-panel/support/version'

const props = defineProps<{
    collapsed: boolean
    isMobile: boolean
    mobileOpen: boolean
}>()

const emit = defineEmits<{
    closeMobile: []
}>()

type TenantSwitcherOption = {
    action: 'central' | 'tenant'
    label: string
    logo_url?: string | null
    meta?: string | null
    method: 'get' | 'post'
    url: string
    value: string
}

type TenantSwitcherMenuItem = MenuItem & TenantSwitcherOption

const page = usePage<{
    appLogo?: string | null
    appName?: string
    appSubtitle?: string | null
    corePanel?: {
        debug?: boolean
        environment?: string | null
        isLocal?: boolean
        version?: string | null
    }
    navigation?: {
        tenant_switcher?: {
            current_value?: string | null
            options: TenantSwitcherOption[]
        } | null
    }
    url?: string
}>()

const appName = computed(() => page.props.appName ?? 'CorePanel')
const appSubtitle = computed(() => {
    const value = page.props.appSubtitle

    return typeof value === 'string' && value.trim() !== '' ? value : null
})
const appLogo = computed(() => page.props.appLogo ?? null)
const appDebug = computed(() => page.props.corePanel?.debug === true)
const appEnvironment = computed(() => page.props.corePanel?.environment ?? null)
const appIsLocal = computed(() => page.props.corePanel?.isLocal === true)
const appVersion = computed(() => {
    return page.props.corePanel?.version ?? APP_RELEASE_VERSION ?? null
})
const tenantSwitcher = computed(() => page.props.navigation?.tenant_switcher ?? null)
const tenantSwitcherOptions = computed(
    () => tenantSwitcher.value?.options ?? [],
)
const selectedTenantValue = computed(
    () => tenantSwitcher.value?.current_value ?? null,
)
const selectedTenantOption = computed(
    () =>
        tenantSwitcherOptions.value.find(
            (option) => String(option.value) === String(selectedTenantValue.value),
        ) ?? null,
)
const { isGroupOpen, isItemActive, items: menuItems } = useAdminMenu()
provide('adminMenu', { isGroupOpen, isItemActive })

const isCollapsed = computed(() => props.collapsed && !props.isMobile)
const isHovered = ref(false)
const effectiveCollapsed = computed(() => isCollapsed.value && !isHovered.value)
const tenantSwitcherMenuOpen = ref(false)
const tenantSwitcherMenuRef = ref()
const tenantSwitcherQuery = ref('')
const tenantSwitcherMenuItems = computed<TenantSwitcherMenuItem[]>(() =>
    tenantSwitcherOptions.value
        .filter((option) => {
            const query = tenantSwitcherQuery.value.trim().toLocaleLowerCase()

            if (query === '') {
                return true
            }

            return [option.label, option.meta ?? '']
                .join(' ')
                .toLocaleLowerCase()
                .includes(query)
        })
        .map((option) => ({
            ...option,
            command: () => switchTenant(option),
        })),
)

function tenantSwitcherItemLabel(item: MenuItem | TenantSwitcherMenuItem): string {
    return typeof item.label === 'string' ? item.label : ''
}

function handleNavigation(): void {
    if (props.isMobile) {
        emit('closeMobile')
    }
}

function toggleTenantSwitcherMenu(event: Event): void {
    tenantSwitcherMenuRef.value?.toggle(event)
}

function handleTenantSwitcherMenuShow(): void {
    tenantSwitcherMenuOpen.value = true
    tenantSwitcherQuery.value = ''
}

function switchTenant(option: TenantSwitcherOption): void {
    if (option.method === 'post') {
        router.post(option.url, {}, {
            preserveScroll: true,
        })

        return
    }

    router.visit(option.url)
}
</script>

<template>
    <Transition name="fade">
        <button
            v-if="isMobile && mobileOpen"
            class="fixed inset-0 z-[39] bg-slate-950/45 backdrop-blur-[5px]"
            type="button"
            @click="emit('closeMobile')"
        />
    </Transition>

    <aside
        class="app-sidebar"
        :class="{
            'app-sidebar--collapsed': isCollapsed,
            'app-sidebar--hidden': isMobile && !mobileOpen,
        }"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <div
            class="flex min-h-[4.5rem] items-center gap-3.5 border-b border-[color:color-mix(in_srgb,var(--cp-sidebar-surface-border,var(--cp-surface-border))_78%,transparent)] bg-[color:color-mix(in_srgb,var(--cp-sidebar-surface,var(--cp-surface-panel))_97%,transparent)] px-4"
        >
            <div
                v-if="!effectiveCollapsed && tenantSwitcherOptions.length > 0"
                class="w-full px-1"
            >
                <button
                    class="app-header__user h-[3.5rem] w-full px-2 py-0 text-left"
                    type="button"
                    @click="toggleTenantSwitcherMenu"
                >
                    <div
                        v-if="selectedTenantOption"
                        class="flex min-w-0 flex-1 items-center gap-3"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden bg-white shadow-sm"
                        >
                            <img
                                v-if="selectedTenantOption.logo_url"
                                :src="selectedTenantOption.logo_url"
                                :alt="selectedTenantOption.label"
                                class="h-full w-full object-contain"
                            />
                            <CorePanelLogo
                                v-else-if="
                                    selectedTenantOption.action === 'central'
                                "
                                :title="selectedTenantOption.label"
                                class="h-5 w-5 text-[var(--cp-text-muted)]"
                            />
                            <AppIcon
                                v-else
                                class="h-4 w-4 text-[var(--cp-text-muted)]"
                                name="building"
                            />
                        </div>
                        <div class="min-w-0 flex flex-col gap-0.5">
                            <div
                                class="truncate text-sm font-semibold leading-tight text-[var(--cp-text-primary)]"
                            >
                                {{ selectedTenantOption.label }}
                            </div>
                            <div
                                v-if="selectedTenantOption.meta"
                                class="truncate text-xs leading-tight text-[var(--cp-text-muted)]"
                            >
                                {{ selectedTenantOption.meta }}
                            </div>
                        </div>
                    </div>
                    <AppIcon
                        class="ml-auto h-4 w-4 shrink-0 text-[var(--cp-text-muted)]"
                        :class="{ 'rotate-180': tenantSwitcherMenuOpen }"
                        name="chevron-down"
                    />
                </button>

                <Menu
                    ref="tenantSwitcherMenuRef"
                    :model="tenantSwitcherMenuItems"
                    :popup="true"
                    @hide="tenantSwitcherMenuOpen = false"
                    @show="handleTenantSwitcherMenuShow"
                >
                    <template #start>
                        <div
                            class="border-b border-[var(--cp-surface-border)] px-3 py-2"
                        >
                            <InputText
                                v-model="tenantSwitcherQuery"
                                class="w-full !min-h-0 !py-1.5"
                                :placeholder="$t('common.ui.search')"
                                size="small"
                            />
                        </div>
                    </template>
                    <template #item="{ item, props: menuItemProps }">
                        <a
                            class="flex min-w-56 items-center gap-3 px-3 py-2"
                            v-bind="menuItemProps.action"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden border border-[var(--cp-surface-border)] bg-white"
                            >
                                <img
                                    v-if="item.logo_url"
                                    :src="item.logo_url"
                                    :alt="tenantSwitcherItemLabel(item)"
                                    class="h-full w-full object-contain"
                                />
                                <CorePanelLogo
                                    v-else-if="item.action === 'central'"
                                    :title="tenantSwitcherItemLabel(item)"
                                    class="h-4 w-4 text-[var(--cp-text-muted)]"
                                />
                                <AppIcon
                                    v-else
                                    class="h-4 w-4 text-[var(--cp-text-muted)]"
                                    name="building"
                                />
                            </div>
                            <div class="min-w-0 flex flex-col gap-0.5">
                                <div
                                    class="truncate text-sm font-medium text-[var(--cp-text-primary)]"
                                >
                                    {{ item.label }}
                                </div>
                                <div
                                    v-if="item.meta"
                                    class="truncate text-xs text-[var(--cp-text-muted)]"
                                >
                                    {{ item.meta }}
                                </div>
                            </div>
                        </a>
                    </template>
                </Menu>
            </div>
            <template v-else>
                <template v-if="appLogo">
                    <div
                        class="app-sidebar__logo-brand"
                        :class="{
                            'app-sidebar__logo-brand--collapsed':
                                effectiveCollapsed,
                        }"
                    >
                        <img
                            :src="appLogo"
                            :alt="appName"
                            class="app-sidebar__logo-img"
                        />
                    </div>
                </template>
                <div v-else class="app-sidebar__logo-mark">
                    <CorePanelLogo :title="appName" />
                </div>
                <div
                    class="app-sidebar__logo-copy flex min-w-0 flex-col gap-[0.15rem]"
                >
                    <span
                        class="truncate text-[0.95rem] font-bold text-[var(--cp-text-primary)]"
                    >
                        {{ appName }}
                    </span>
                    <span
                        v-if="appSubtitle"
                        class="truncate text-xs text-[var(--cp-text-muted)]"
                    >
                        {{ appSubtitle }}
                    </span>
                </div>
            </template>
        </div>

        <nav
            class="app-sidebar__nav flex min-h-0 flex-1 flex-col gap-[0.35rem] overflow-x-hidden overflow-y-auto px-3 py-[0.9rem]"
        >
            <SidebarMenuItem
                v-for="item in menuItems"
                :key="item.key"
                :collapsed="effectiveCollapsed"
                :item="item"
                @nav-click="handleNavigation"
            />
        </nav>

        <footer class="app-sidebar__footer">
            <div
                v-if="appVersion || appIsLocal || appDebug"
                class="app-sidebar__footer-info"
            >
                <div class="app-sidebar__footer-meta">
                    <span
                        v-if="appIsLocal"
                        class="app-sidebar__footer-badge app-sidebar__footer-badge--info"
                        :title="appEnvironment ?? undefined"
                    >
                        {{ $t('common.ui.dev_mode') }}
                    </span>
                    <span
                        v-if="appDebug"
                        class="app-sidebar__footer-badge app-sidebar__footer-badge--danger"
                    >
                        {{ $t('common.ui.debug_mode') }}
                    </span>
                    <Badge
                        v-if="appVersion"
                        :value="appVersion"
                        class="app-sidebar__version-badge"
                        severity="secondary"
                    />
                </div>
            </div>
        </footer>
    </aside>
</template>
