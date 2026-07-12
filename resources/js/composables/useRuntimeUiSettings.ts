import { computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

import {
    applyCorePanelLayoutDensity,
    applyCorePanelRadiusToken,
    applyCorePanelThemeAccent,
    applyCorePanelThemePalette,
    normalizeCorePanelLayoutDensity,
    normalizeCorePanelRadiusToken,
    normalizeCorePanelThemeAccent,
    normalizeCorePanelThemePalette,
} from '@core-panel/theme/core-panel'

type CorePanelRuntimeSettings = {
    corePanel?: {
        settings?: {
            appearance?: {
                theme_palette?: string
            }
            ui?: {
                layout_density?: string
                primary_color_token?: string
                radius_token?: string
            }
        }
    }
}

export function useRuntimeUiSettings(): void {
    const page = usePage<CorePanelRuntimeSettings>()
    const runtimeUiSettings = computed(() => ({
        layoutDensity:
            page.props.corePanel?.settings?.ui?.layout_density ?? 'comfortable',
        radiusToken: page.props.corePanel?.settings?.ui?.radius_token ?? 'md',
        themeAccent:
            page.props.corePanel?.settings?.ui?.primary_color_token ??
            '#1ab88f',
        themePalette:
            page.props.corePanel?.settings?.appearance?.theme_palette ??
            'paper',
    }))

    watch(
        runtimeUiSettings,
        (settings) => {
            applyCorePanelLayoutDensity(
                normalizeCorePanelLayoutDensity(settings.layoutDensity),
            )
            applyCorePanelRadiusToken(
                normalizeCorePanelRadiusToken(settings.radiusToken),
            )
            applyCorePanelThemePalette(
                normalizeCorePanelThemePalette(settings.themePalette),
            )
            applyCorePanelThemeAccent(
                normalizeCorePanelThemeAccent(settings.themeAccent),
            )
        },
        { immediate: true },
    )
}
