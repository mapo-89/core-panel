<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed, inject, ref, watch } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import type {
    MenuBuilderContext,
    MenuBuilderItem,
} from '@core-panel/composables/useMenuBuilder'

defineOptions({ name: 'SidebarMenuItem' })

const props = withDefaults(
    defineProps<{
        collapsed: boolean
        depth?: number
        item: MenuBuilderItem
    }>(),
    {
        depth: 0,
    },
)

const emit = defineEmits<{
    navClick: []
}>()

const menu = inject<MenuBuilderContext>('adminMenu')

if (!menu) {
    throw new Error('SidebarMenuItem requires adminMenu context.')
}

const isActive = computed(() => menu.isItemActive(props.item))
const isOpen = ref(menu.isGroupOpen(props.item))
const badgeLabel = computed(() => {
    if (
        props.item.badge === undefined ||
        props.item.badge === null ||
        props.item.badge === ''
    ) {
        return null
    }

    return String(props.item.badge)
})

watch(
    () => props.collapsed,
    (collapsed) => {
        isOpen.value = collapsed ? false : menu.isGroupOpen(props.item)
    },
)

function toggle(): void {
    if (!props.collapsed) {
        isOpen.value = !isOpen.value
    }
}

function handleNavClick(): void {
    emit('navClick')
}
</script>

<template>
    <div v-if="item.section" class="app-nav-section">
        <span v-if="!collapsed" class="app-nav-section__title">
            {{ $t(item.label) }}
        </span>
        <span v-else class="app-nav-section__divider" />
    </div>

    <div v-else-if="item.children?.length" class="app-nav-group-wrapper">
        <button
            class="app-nav-group"
            :class="{
                'app-nav-group--active': isOpen || menu.isGroupOpen(item),
            }"
            type="button"
            @click="toggle"
        >
            <span class="app-nav-icon">
                <AppIcon v-if="item.icon" :name="item.icon" />
            </span>
            <span class="app-nav-label">{{ $t(item.label) }}</span>
            <span v-if="badgeLabel && !collapsed" class="app-nav-badge">
                {{ badgeLabel }}
            </span>
            <span
                v-if="!collapsed"
                class="app-nav-chevron"
                :class="{ 'app-nav-chevron--open': isOpen }"
            >
                <AppIcon name="chevron-down" />
            </span>
        </button>

        <Transition name="submenu">
            <div v-if="isOpen && !collapsed" class="app-nav-submenu">
                <SidebarMenuItem
                    v-for="child in item.children"
                    :key="child.key"
                    :collapsed="false"
                    :depth="depth + 1"
                    :item="child"
                    @nav-click="emit('navClick')"
                />
            </div>
        </Transition>
    </div>

    <a
        v-else-if="item.external && item.href"
        class="app-nav-link"
        :class="{ 'app-nav-link--child': depth > 0 }"
        :href="item.href"
        rel="noopener noreferrer"
        target="_blank"
        @click="handleNavClick"
    >
        <span v-if="depth === 0" class="app-nav-icon">
            <AppIcon v-if="item.icon" :name="item.icon" />
        </span>
        <span v-else class="app-nav-dot" />
        <span class="app-nav-label">{{ $t(item.label) }}</span>
        <span v-if="badgeLabel && !collapsed" class="app-nav-badge">
            {{ badgeLabel }}
        </span>
    </a>

    <Link
        v-else-if="item.href"
        class="app-nav-link"
        :class="{
            'app-nav-link--active': isActive && depth === 0,
            'app-nav-link--child': depth > 0,
            'app-nav-link--child-active': isActive && depth > 0,
        }"
        :href="item.href"
        @click="handleNavClick"
    >
        <span v-if="depth === 0" class="app-nav-icon">
            <AppIcon v-if="item.icon" :name="item.icon" />
        </span>
        <span
            v-else
            class="app-nav-dot"
            :class="{ 'app-nav-dot--active': isActive }"
        />
        <span class="app-nav-label">{{ $t(item.label) }}</span>
        <span v-if="badgeLabel && !collapsed" class="app-nav-badge">
            {{ badgeLabel }}
        </span>
    </Link>
</template>
