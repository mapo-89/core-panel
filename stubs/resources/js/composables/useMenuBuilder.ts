import { computed, unref, type Ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

import { useCan } from '@/composables/useCan'

export type MenuBuilderItem = {
    anyPermissions?: string[]
    badge?: number | string
    children?: MenuBuilderItem[]
    external?: boolean
    href?: string
    icon?: string
    key: string
    label: string
    match?: string[]
    permission?: string
    query?: Record<string, string>
    role?: string
    roles?: string[]
    section?: boolean
}

export type MenuBuilderContext = {
    isGroupOpen: (item: MenuBuilderItem) => boolean
    isItemActive: (item: MenuBuilderItem) => boolean
}

function normalizeUrl(url: string): URL {
    return new URL(url, 'http://localhost')
}

function matchesPath(current: string, target: string): boolean {
    if (current === target) {
        return true
    }

    if (target === '/') {
        return false
    }

    return current.startsWith(`${target}/`)
}

export function useMenuBuilder(
    allItems: MenuBuilderItem[] | Readonly<Ref<MenuBuilderItem[]>>,
) {
    const page = usePage<{ url?: string }>()
    const { can, canAny, hasRole } = useCan()

    function isVisible(item: MenuBuilderItem): boolean {
        if (item.permission && !can(item.permission)) {
            return false
        }

        if (item.anyPermissions && !canAny(item.anyPermissions)) {
            return false
        }

        if (item.role && !hasRole(item.role)) {
            return false
        }

        if (item.roles && !hasRole(item.roles)) {
            return false
        }

        return true
    }

    const items = computed(() => {
        const filtered = unref(allItems)
            .map((item) => {
                if (!isVisible(item)) {
                    return null
                }

                if (item.children) {
                    const visibleChildren = item.children.filter(isVisible)

                    if (visibleChildren.length === 0) {
                        return null
                    }

                    return { ...item, children: visibleChildren }
                }

                return item
            })
            .filter((item): item is MenuBuilderItem => item !== null)

        return filtered.filter((item, index) => {
            if (!item.section) {
                return true
            }

            const nextItems = filtered.slice(index + 1)

            return nextItems.length > 0 && !nextItems[0].section
        })
    })

    function isItemActive(item: MenuBuilderItem): boolean {
        if (!item.href || item.external) {
            return false
        }

        const currentUrl = normalizeUrl(page.url ?? '/')
        const currentPath = currentUrl.pathname
        const matches = item.match ?? [item.href]

        const pathMatches = matches.some((prefix) =>
            matchesPath(currentPath, normalizeUrl(prefix).pathname),
        )

        if (!pathMatches) {
            return false
        }

        if (!item.query) {
            return true
        }

        return Object.entries(item.query).every(
            ([key, value]) => currentUrl.searchParams.get(key) === value,
        )
    }

    function isGroupOpen(item: MenuBuilderItem): boolean {
        return (
            item.children?.some(
                (child) => isItemActive(child) || isGroupOpen(child),
            ) ?? false
        )
    }

    return {
        currentUrl: computed(() => page.url ?? '/'),
        isActive: isItemActive,
        isGroupOpen,
        isItemActive,
        items,
        visibleItems: items,
    }
}
