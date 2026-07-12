import { ref, watch } from 'vue'
import { useMediaQuery, useStorage } from '@vueuse/core'

const STORAGE_KEY = 'core-panel.sidebar-collapsed'
const MOBILE_BREAKPOINT = 1024

export function useSidebar() {
    const isCollapsed = useStorage(STORAGE_KEY, false)
    const isMobileOpen = ref(false)
    const isMobile = useMediaQuery(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`)

    watch(
        isMobile,
        (mobile) => {
            if (mobile) {
                isMobileOpen.value = false
            }
        },
        { immediate: true },
    )

    function toggleSidebar(): void {
        if (isMobile.value) {
            isMobileOpen.value = !isMobileOpen.value
            return
        }

        isCollapsed.value = !isCollapsed.value
    }

    function closeMobileSidebar(): void {
        isMobileOpen.value = false
    }

    return {
        closeMobileSidebar,
        isCollapsed,
        isMobile,
        isMobileOpen,
        toggleSidebar,
    }
}
