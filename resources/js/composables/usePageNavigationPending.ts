import { router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref } from 'vue'

type NavigationVisit = {
    method?: string
    prefetch?: boolean
    url?: string | URL
}

export type PageNavigationLoadingState =
    | 'dashboard'
    | 'datatable'
    | 'files'
    | 'page'
    | 'side-tabs'
    | 'side-tabs-cards'
    | 'tabs-datatable'

export type PageNavigationPresentation = {
    actionCount: number
    loadingState: PageNavigationLoadingState
    subtitleKey?: string
    titleKey?: string
}

export function resolvePageNavigationPresentation(
    targetUrl: string | URL,
    currentUrl: string,
): PageNavigationPresentation {
    const pathname = new URL(String(targetUrl), currentUrl).pathname.replace(
        /\/$/,
        '',
    )
    const presentations: Record<string, PageNavigationPresentation> = {
        '/admin/developer': {
            actionCount: 1,
            loadingState: 'side-tabs',
            subtitleKey: 'page-developer.description',
            titleKey: 'navigation.routes',
        },
        '/admin/files': {
            actionCount: 1,
            loadingState: 'files',
            subtitleKey: 'files.description',
            titleKey: 'files.title',
        },
        '/admin/logs': {
            actionCount: 0,
            loadingState: 'side-tabs',
            subtitleKey: 'page-logs.description',
            titleKey: 'page-logs.title',
        },
        '/admin/settings': {
            actionCount: 0,
            loadingState: 'side-tabs-cards',
            subtitleKey: 'page-settings.settings_description',
            titleKey: 'navigation.settings',
        },
        '/admin/system/administration': {
            actionCount: 0,
            loadingState: 'side-tabs',
            subtitleKey: 'administration.subtitle',
            titleKey: 'administration.title',
        },
        '/admin/users': {
            actionCount: 2,
            loadingState: 'side-tabs',
            subtitleKey: 'page-users.index_description',
            titleKey: 'page-users.management_title',
        },
        '/dashboard': {
            actionCount: 0,
            loadingState: 'dashboard',
            titleKey: 'navigation.dashboard',
        },
        '/profile': {
            actionCount: 0,
            loadingState: 'side-tabs-cards',
            subtitleKey: 'page-settings.profile_workspace_description',
            titleKey: 'settings.profile',
        },
    }

    const presentation = presentations[pathname]

    if (presentation !== undefined) {
        return presentation
    }

    if (pathname.startsWith('/admin/files/')) {
        return { actionCount: 0, loadingState: 'files' }
    }

    return { actionCount: 0, loadingState: 'page' }
}

export function resolvePageNavigationLoadingState(
    targetUrl: string | URL,
    currentUrl: string,
): PageNavigationLoadingState {
    return resolvePageNavigationPresentation(targetUrl, currentUrl).loadingState
}

export function isFullPageGetNavigation(
    visit: NavigationVisit,
    currentUrl: string,
): boolean {
    if (
        visit.prefetch ||
        String(visit.method ?? 'get').toLowerCase() !== 'get'
    ) {
        return false
    }

    if (visit.url === undefined) {
        return false
    }

    const current = new URL(currentUrl)
    const target = new URL(String(visit.url), current)

    return target.pathname !== current.pathname
}

export function usePageNavigationPending() {
    const pending = ref(false)
    const loadingState = ref<PageNavigationLoadingState>('page')
    const presentation = ref<PageNavigationPresentation>({
        actionCount: 0,
        loadingState: 'page',
    })
    let removeBeforeListener: (() => void) | null = null
    let removeFinishListener: (() => void) | null = null

    onMounted(() => {
        removeBeforeListener = router.on('before', (event) => {
            const visit = (event as { detail?: { visit?: NavigationVisit } })
                .detail?.visit

            if (
                visit !== undefined &&
                visit.url !== undefined &&
                isFullPageGetNavigation(visit, window.location.href)
            ) {
                presentation.value = resolvePageNavigationPresentation(
                    visit.url,
                    window.location.href,
                )
                loadingState.value = presentation.value.loadingState
                pending.value = true
            }
        })
        removeFinishListener = router.on('finish', () => {
            pending.value = false
        })
    })

    onBeforeUnmount(() => {
        removeBeforeListener?.()
        removeBeforeListener = null
        removeFinishListener?.()
        removeFinishListener = null
    })

    return { loadingState, pending, presentation }
}
