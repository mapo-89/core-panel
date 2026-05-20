<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed, provide, ref } from 'vue'

import CorePanelLogo from '@/components/CorePanelLogo.vue'
import { useAdminMenu } from '@/composables/useAdminMenu'
import SidebarMenuItem from '@/layouts/components/SidebarMenuItem.vue'

const props = defineProps<{
    collapsed: boolean
    isMobile: boolean
    mobileOpen: boolean
}>()

const emit = defineEmits<{
    closeMobile: []
}>()

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
const appVersion = computed(() => page.props.corePanel?.version ?? null)
const { isGroupOpen, isItemActive, items: menuItems } = useAdminMenu()
provide('adminMenu', { isGroupOpen, isItemActive })

const isCollapsed = computed(() => props.collapsed && !props.isMobile)
const isHovered = ref(false)
const effectiveCollapsed = computed(() => isCollapsed.value && !isHovered.value)

function handleNavigation(): void {
    if (props.isMobile) {
        emit('closeMobile')
    }
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
