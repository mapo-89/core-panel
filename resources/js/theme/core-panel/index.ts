import type { Preset } from '@primeuix/themes/types'

import { corePanelRuntimePreset } from './preset'
import {
    corePanelRuntimeDarkVariables,
    corePanelRuntimeLightVariables,
} from './tokens'

export type CorePanelColorMode = 'dark' | 'light'
export type CorePanelColorModePreference = CorePanelColorMode | 'system'
export type CorePanelLayoutDensity = 'comfortable' | 'compact' | 'spacious'
export type CorePanelRadiusToken = 'lg' | 'md' | 'none' | 'sm' | 'xl'
export type CorePanelPreviewPalette = {
    dark: {
        frame: string
        header: string
        shell: string
        sidebar: string
        text: string
        textMuted: string
    }
    light: {
        frame: string
        header: string
        shell: string
        sidebar: string
        text: string
        textMuted: string
    }
}
export const CORE_PANEL_THEME_PALETTES = [
    'paper',
    'soft',
    'ocean',
    'contrast',
] as const
export type CorePanelThemePalette = (typeof CORE_PANEL_THEME_PALETTES)[number]

export const CORE_PANEL_COLOR_MODE_KEY = 'core-panel.color-mode'
export const CORE_PANEL_THEME_PALETTE_KEY = 'core-panel.theme-palette'
export const CORE_PANEL_THEME_ACCENT_KEY = 'core-panel.theme-accent'
export const CORE_PANEL_LAYOUT_DENSITY_KEY = 'core-panel.layout-density'
export const CORE_PANEL_RADIUS_TOKEN_KEY = 'core-panel.radius-token'

export function resolveSystemCorePanelColorMode(): CorePanelColorMode {
    if (
        typeof window === 'undefined' ||
        typeof window.matchMedia !== 'function'
    ) {
        return 'dark'
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light'
}

export function normalizeCorePanelColorModePreference(
    mode: unknown,
): CorePanelColorModePreference {
    if (mode === 'dark' || mode === 'light' || mode === 'system') {
        return mode
    }

    return 'dark'
}

export function resolveCorePanelColorMode(
    preference: CorePanelColorModePreference,
): CorePanelColorMode {
    if (preference === 'system') {
        return resolveSystemCorePanelColorMode()
    }

    return preference
}

const corePanelRuntimeDensityVariables: Record<
    CorePanelLayoutDensity,
    Record<string, string>
> = {
    comfortable: {
        '--cp-control-padding-y': '0.52rem',
        '--cp-density-card-gap': '1rem',
        '--cp-density-section-gap': '1.25rem',
        '--cp-density-section-padding-x': '1.125rem',
        '--cp-density-section-padding-y': '1rem',
        '--cp-input-height': '2.75rem',
        '--p-button-padding-y': '0.46rem',
    },
    compact: {
        '--cp-control-padding-y': '0.46rem',
        '--cp-density-card-gap': '0.8rem',
        '--cp-density-section-gap': '1rem',
        '--cp-density-section-padding-x': '0.95rem',
        '--cp-density-section-padding-y': '0.85rem',
        '--cp-input-height': '2.45rem',
        '--p-button-padding-y': '0.44rem',
    },
    spacious: {
        '--cp-control-padding-y': '0.72rem',
        '--cp-density-card-gap': '1.15rem',
        '--cp-density-section-gap': '1.4rem',
        '--cp-density-section-padding-x': '1.3rem',
        '--cp-density-section-padding-y': '1.2rem',
        '--cp-input-height': '3rem',
        '--p-button-padding-y': '0.64rem',
    },
}

const corePanelRuntimeRadiusVariables: Record<
    CorePanelRadiusToken,
    Record<string, string>
> = {
    none: {
        '--cp-control-radius': '0rem',
        '--cp-radius-lg': '0px',
        '--cp-radius-md': '0px',
        '--cp-radius-sm': '0px',
        '--cp-radius-xl': '0px',
    },
    lg: {
        '--cp-control-radius': '0.75rem',
        '--cp-radius-lg': '18px',
        '--cp-radius-md': '12px',
        '--cp-radius-sm': '8px',
        '--cp-radius-xl': '24px',
    },
    md: {
        '--cp-control-radius': '0.625rem',
        '--cp-radius-lg': '16px',
        '--cp-radius-md': '10px',
        '--cp-radius-sm': '7px',
        '--cp-radius-xl': '22px',
    },
    sm: {
        '--cp-control-radius': '0.5rem',
        '--cp-radius-lg': '14px',
        '--cp-radius-md': '8px',
        '--cp-radius-sm': '6px',
        '--cp-radius-xl': '20px',
    },
    xl: {
        '--cp-control-radius': '0.9rem',
        '--cp-radius-lg': '22px',
        '--cp-radius-md': '14px',
        '--cp-radius-sm': '10px',
        '--cp-radius-xl': '28px',
    },
}

const corePanelRuntimePaletteVariables: Record<
    CorePanelColorMode,
    Record<
        CorePanelThemePalette,
        Partial<
            Record<
                | '--cp-surface-canvas'
                | '--cp-surface-panel'
                | '--cp-surface-panel-alt'
                | '--cp-surface-border'
                | '--cp-text-primary'
                | '--cp-text-muted',
                string
            >
        >
    >
> = {
    light: {
        contrast: {
            '--cp-surface-border': '#c4d0dc',
            '--cp-surface-canvas': '#eef2f7',
            '--cp-surface-panel': '#ffffff',
            '--cp-surface-panel-alt': '#e4eaf2',
            '--cp-text-muted': '#53657a',
            '--cp-text-primary': '#101828',
        },
        ocean: {
            '--cp-surface-border': '#bedceb',
            '--cp-surface-canvas': '#edf7fc',
            '--cp-surface-panel': '#f8fcff',
            '--cp-surface-panel-alt': '#e0eff7',
            '--cp-text-muted': '#5a7f94',
            '--cp-text-primary': '#17374a',
        },
        paper: {
            '--cp-surface-border': '#d8dde3',
            '--cp-surface-canvas': '#f4f5f7',
            '--cp-surface-panel': '#ffffff',
            '--cp-surface-panel-alt': '#eef1f4',
            '--cp-text-muted': '#5f6773',
            '--cp-text-primary': '#171717',
        },
        soft: {
            '--cp-surface-border': '#ddcdb7',
            '--cp-surface-canvas': '#f8f3ec',
            '--cp-surface-panel': '#fffaf2',
            '--cp-surface-panel-alt': '#f2e8d9',
            '--cp-text-muted': '#8d6e55',
            '--cp-text-primary': '#3d2d20',
        },
    },
    dark: {
        contrast: {
            '--cp-surface-border': '#334155',
            '--cp-surface-canvas': '#020617',
            '--cp-surface-panel': '#0f172a',
            '--cp-surface-panel-alt': '#111c31',
            '--cp-text-muted': '#a9b6c6',
            '--cp-text-primary': '#f8fafc',
        },
        ocean: {
            '--cp-surface-border': '#154961',
            '--cp-surface-canvas': '#07141d',
            '--cp-surface-panel': '#0c1d29',
            '--cp-surface-panel-alt': '#0f2734',
            '--cp-text-muted': '#91b9c9',
            '--cp-text-primary': '#e4f7ff',
        },
        paper: {
            '--cp-surface-border': '#303845',
            '--cp-surface-canvas': '#101419',
            '--cp-surface-panel': '#171c23',
            '--cp-surface-panel-alt': '#1e252e',
            '--cp-text-muted': '#aab3bf',
            '--cp-text-primary': '#edf1ed',
        },
        soft: {
            '--cp-surface-border': '#4c3d31',
            '--cp-surface-canvas': '#1b1612',
            '--cp-surface-panel': '#241d18',
            '--cp-surface-panel-alt': '#2d241d',
            '--cp-text-muted': '#ccb79e',
            '--cp-text-primary': '#f4e6d1',
        },
    },
}

/**
 * Package runtime theme registry.
 *
 * This layer belongs to the package runtime, not to the host application's
 * scaffolded page/theme CSS. It provides:
 * - the PrimeVue preset
 * - runtime color tokens for light/dark mode
 * - DOM helpers for applying the active color mode
 */
export const corePanelRuntimeThemes: Record<string, Preset> = {
    'core-panel': corePanelRuntimePreset,
}

export function resolveCorePanelRuntimeTheme(themeName = 'core-panel'): Preset {
    return (
        corePanelRuntimeThemes[themeName] ??
        corePanelRuntimeThemes['core-panel']
    )
}

export function applyCorePanelRuntimeThemeVariables(
    mode: CorePanelColorMode,
): void {
    if (typeof document === 'undefined') {
        return
    }

    const root = document.documentElement
    const activeRadius = normalizeCorePanelRadiusToken(root.dataset.radiusToken)
    const activeAccent = normalizeCorePanelThemeAccent(root.dataset.themeAccent)
    const variables =
        mode === 'dark'
            ? corePanelRuntimeDarkVariables
            : corePanelRuntimeLightVariables

    Object.entries(variables).forEach(([name, value]) => {
        root.style.setProperty(name, value)
    })

    const activePalette = normalizeCorePanelThemePalette(
        root.dataset.themePalette,
    )
    const paletteVariables =
        corePanelRuntimePaletteVariables[mode][activePalette]

    Object.entries(paletteVariables).forEach(([name, value]) => {
        root.style.setProperty(name, value)
    })

    applyCorePanelSidebarSurfaceVariables(root, mode)
    applyCorePanelRadiusToken(activeRadius)
    applyCorePanelThemeAccent(activeAccent)

    root.dataset.corePanelColorScheme = mode
    root.classList.toggle('core-panel-dark', mode === 'dark')
}

export function resolveCorePanelPreviewPalette(
    palette: CorePanelThemePalette,
): CorePanelPreviewPalette {
    const normalizedPalette = normalizeCorePanelThemePalette(palette)
    const lightPalette =
        corePanelRuntimePaletteVariables.light[normalizedPalette]
    const darkPalette = corePanelRuntimePaletteVariables.dark[normalizedPalette]

    return {
        light: {
            frame: lightPalette['--cp-surface-border'] ?? '#d8dde3',
            header: lightPalette['--cp-surface-panel-alt'] ?? '#eef1f4',
            shell: lightPalette['--cp-surface-panel'] ?? '#ffffff',
            sidebar: lightPalette['--cp-surface-panel-alt'] ?? '#eef1f4',
            text: lightPalette['--cp-text-primary'] ?? '#171717',
            textMuted: lightPalette['--cp-text-muted'] ?? '#5f6773',
        },
        dark: {
            frame: darkPalette['--cp-surface-border'] ?? '#303845',
            header: darkPalette['--cp-surface-panel-alt'] ?? '#1e252e',
            shell: darkPalette['--cp-surface-panel'] ?? '#171c23',
            sidebar: darkPalette['--cp-surface-panel-alt'] ?? '#1e252e',
            text: darkPalette['--cp-text-primary'] ?? '#edf1ed',
            textMuted: darkPalette['--cp-text-muted'] ?? '#aab3bf',
        },
    }
}

export function normalizeCorePanelLayoutDensity(
    density: unknown,
): CorePanelLayoutDensity {
    if (
        density === 'comfortable' ||
        density === 'compact' ||
        density === 'spacious'
    ) {
        return density
    }

    return 'comfortable'
}

export function applyCorePanelLayoutDensity(
    density: CorePanelLayoutDensity,
): void {
    if (typeof document === 'undefined') {
        return
    }

    const root = document.documentElement
    const normalizedDensity = normalizeCorePanelLayoutDensity(density)
    const densityVariables = corePanelRuntimeDensityVariables[normalizedDensity]

    Object.entries(densityVariables).forEach(([name, value]) => {
        root.style.setProperty(name, value)
    })

    root.dataset.layoutDensity = normalizedDensity
}

export function normalizeCorePanelRadiusToken(
    radius: unknown,
): CorePanelRadiusToken {
    if (radius === 'none') {
        return 'none'
    }

    if (radius === 'small') {
        return 'sm'
    }

    if (radius === 'medium') {
        return 'md'
    }

    if (radius === 'large') {
        return 'lg'
    }

    if (radius === 'very-large') {
        return 'xl'
    }

    if (
        radius === 'lg' ||
        radius === 'md' ||
        radius === 'sm' ||
        radius === 'xl'
    ) {
        return radius
    }

    return 'md'
}

export function applyCorePanelRadiusToken(radius: CorePanelRadiusToken): void {
    if (typeof document === 'undefined') {
        return
    }

    const root = document.documentElement
    const normalizedRadius = normalizeCorePanelRadiusToken(radius)
    const radiusVariables = corePanelRuntimeRadiusVariables[normalizedRadius]

    Object.entries(radiusVariables).forEach(([name, value]) => {
        root.style.setProperty(name, value)
    })

    root.dataset.radiusToken = normalizedRadius
}

export function normalizeCorePanelThemePalette(
    palette: unknown,
): CorePanelThemePalette {
    if (
        typeof palette === 'string' &&
        CORE_PANEL_THEME_PALETTES.includes(palette as CorePanelThemePalette)
    ) {
        return palette as CorePanelThemePalette
    }

    return 'paper'
}

export function applyCorePanelThemePalette(
    palette: CorePanelThemePalette,
): void {
    if (typeof document === 'undefined') {
        return
    }

    const root = document.documentElement
    const normalizedPalette = normalizeCorePanelThemePalette(palette)

    root.dataset.themePalette = normalizedPalette

    applyCorePanelRuntimeThemeVariables(
        root.classList.contains('core-panel-dark') ? 'dark' : 'light',
    )
}

export function normalizeCorePanelThemeAccent(color: unknown): string {
    if (typeof color !== 'string') {
        return '#1ab88f'
    }

    const normalized = color.trim().replace(/^#/, '')

    if (!/^[0-9A-Fa-f]{6}$/.test(normalized)) {
        return '#1ab88f'
    }

    return `#${normalized.toLowerCase()}`
}

function mixChannel(channel: number, target: number, amount: number): number {
    return Math.round(channel + (target - channel) * amount)
}

function hexToRgb(hex: string): [number, number, number] {
    const normalized = normalizeCorePanelThemeAccent(hex).slice(1)

    return [
        Number.parseInt(normalized.slice(0, 2), 16),
        Number.parseInt(normalized.slice(2, 4), 16),
        Number.parseInt(normalized.slice(4, 6), 16),
    ]
}

function rgbToHex(red: number, green: number, blue: number): string {
    return `#${[red, green, blue]
        .map((channel) => channel.toString(16).padStart(2, '0'))
        .join('')}`
}

function tint(hex: string, amount: number): string {
    const [red, green, blue] = hexToRgb(hex)

    return rgbToHex(
        mixChannel(red, 255, amount),
        mixChannel(green, 255, amount),
        mixChannel(blue, 255, amount),
    )
}

function shade(hex: string, amount: number): string {
    const [red, green, blue] = hexToRgb(hex)

    return rgbToHex(
        mixChannel(red, 0, amount),
        mixChannel(green, 0, amount),
        mixChannel(blue, 0, amount),
    )
}

function applyCorePanelSidebarSurfaceVariables(
    root: HTMLElement,
    mode: CorePanelColorMode,
): void {
    const sidebarVariables =
        mode === 'dark'
            ? {
                  border: '#313a46',
                  surface: '#161b22',
                  surfaceAlt: '#1d242d',
              }
            : {
                  border: '#d9dde3',
                  surface: '#fbfbfc',
                  surfaceAlt: '#f3f5f7',
              }

    root.style.setProperty('--cp-sidebar-surface', sidebarVariables.surface)
    root.style.setProperty(
        '--cp-sidebar-surface-alt',
        sidebarVariables.surfaceAlt,
    )
    root.style.setProperty(
        '--cp-sidebar-surface-border',
        sidebarVariables.border,
    )
}

export function applyCorePanelThemeAccent(color: string): void {
    if (typeof document === 'undefined') {
        return
    }

    const root = document.documentElement
    const normalized = normalizeCorePanelThemeAccent(color)
    const shades = {
        50: tint(normalized, 0.9),
        100: tint(normalized, 0.76),
        200: tint(normalized, 0.58),
        300: tint(normalized, 0.38),
        400: tint(normalized, 0.18),
        500: normalized,
        600: shade(normalized, 0.14),
        700: shade(normalized, 0.28),
        800: shade(normalized, 0.42),
        900: shade(normalized, 0.56),
        950: shade(normalized, 0.72),
    } as const

    root.style.setProperty('--cp-primary', shades[500])
    root.style.setProperty('--p-primary-50', shades[50])
    root.style.setProperty('--p-primary-100', shades[100])
    root.style.setProperty('--p-primary-200', shades[200])
    root.style.setProperty('--p-primary-300', shades[300])
    root.style.setProperty('--p-primary-400', shades[400])
    root.style.setProperty('--p-primary-500', shades[500])
    root.style.setProperty('--p-primary-600', shades[600])
    root.style.setProperty('--p-primary-700', shades[700])
    root.style.setProperty('--p-primary-800', shades[800])
    root.style.setProperty('--p-primary-900', shades[900])
    root.style.setProperty('--p-primary-950', shades[950])
    root.style.setProperty('--p-focus-ring-color', shades[300])
    root.dataset.themeAccent = normalized
}

export function readStoredCorePanelColorMode(): CorePanelColorModePreference | null {
    if (typeof window === 'undefined') {
        return null
    }

    const storedMode = window.localStorage.getItem(CORE_PANEL_COLOR_MODE_KEY)

    return storedMode === 'dark' ||
        storedMode === 'light' ||
        storedMode === 'system'
        ? storedMode
        : null
}

export function persistCorePanelColorMode(
    mode: CorePanelColorModePreference,
): void {
    if (typeof window === 'undefined') {
        return
    }

    window.localStorage.setItem(CORE_PANEL_COLOR_MODE_KEY, mode)
}

export function toggleCorePanelColorMode(
    current: CorePanelColorModePreference,
    effectiveMode: CorePanelColorMode = resolveCorePanelColorMode(current),
): CorePanelColorModePreference {
    if (current === 'system') {
        return effectiveMode === 'dark' ? 'light' : 'dark'
    }

    return current === 'dark' ? 'light' : 'dark'
}

/**
 * Backwards-compatible aliases for existing scaffold imports.
 */
export const corePanelThemes = corePanelRuntimeThemes
export const resolveCorePanelTheme = resolveCorePanelRuntimeTheme
export const applyCorePanelThemeVariables = applyCorePanelRuntimeThemeVariables
