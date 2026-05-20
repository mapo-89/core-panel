import { computed, watch } from 'vue'
import { useMediaQuery, useStorage } from '@vueuse/core'

import {
    applyCorePanelRuntimeThemeVariables,
    resolveCorePanelColorMode,
    toggleCorePanelColorMode,
    CORE_PANEL_COLOR_MODE_KEY,
    type CorePanelColorMode,
    type CorePanelColorModePreference,
} from '@core-panel/theme/core-panel'

export function useColorMode(
    initialMode: CorePanelColorModePreference = 'system',
) {
    const systemPrefersDark = useMediaQuery('(prefers-color-scheme: dark)')
    const colorMode = useStorage<CorePanelColorModePreference>(
        CORE_PANEL_COLOR_MODE_KEY,
        initialMode,
    )
    const effectiveColorMode = computed<CorePanelColorMode>(() =>
        colorMode.value === 'system'
            ? systemPrefersDark.value
                ? 'dark'
                : 'light'
            : colorMode.value,
    )

    watch(
        [colorMode, systemPrefersDark],
        () => {
            applyCorePanelRuntimeThemeVariables(
                resolveCorePanelColorMode(colorMode.value),
            )
        },
        { immediate: true },
    )

    function setColorMode(mode: CorePanelColorModePreference): void {
        colorMode.value = mode
    }

    function toggleColorMode(): void {
        setColorMode(
            toggleCorePanelColorMode(colorMode.value, effectiveColorMode.value),
        )
    }

    return {
        colorMode,
        effectiveColorMode,
        isDarkMode: computed(() => effectiveColorMode.value === 'dark'),
        isSystemMode: computed(() => colorMode.value === 'system'),
        setColorMode,
        toggleColorMode,
    }
}
