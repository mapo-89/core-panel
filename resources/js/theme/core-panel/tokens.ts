/**
 * Package runtime design tokens used by the PrimeVue preset and runtime
 * color-mode synchronization.
 */
export const corePanelRuntimeTokens = {
    colors: {
        primary: {
            50: '#eefdf9',
            100: '#d5fbf0',
            200: '#aef4df',
            300: '#79e9c7',
            400: '#3fd3ab',
            500: '#1ab88f',
            600: '#0f926f',
            700: '#11755c',
            800: '#145d4b',
            900: '#144d3f',
            950: '#052d25',
        },
        success: '#1a8f5a',
        warning: '#c08112',
        danger: '#cf3c4f',
        info: '#2463eb',
    },
    radius: {
        sm: '8px',
        md: '12px',
        lg: '18px',
        xl: '24px',
    },
    shadow: {
        sm: '0 1px 2px rgb(15 23 42 / 0.06)',
        md: '0 14px 40px rgb(15 23 42 / 0.12)',
        lg: '0 24px 56px rgb(15 23 42 / 0.18)',
    },
    spacing: {
        1: '0.25rem',
        2: '0.5rem',
        3: '0.75rem',
        4: '1rem',
        5: '1.25rem',
        6: '1.5rem',
        8: '2rem',
        10: '2.5rem',
    },
} as const

export const corePanelRuntimeLightVariables = {
    '--cp-primary': corePanelRuntimeTokens.colors.primary[500],
    '--cp-surface-canvas': '#f4f5f7',
    '--cp-surface-panel': '#ffffff',
    '--cp-surface-panel-alt': '#eef1f4',
    '--cp-surface-border': '#d8dde3',
    '--cp-text-primary': '#171717',
    '--cp-text-muted': '#5f6773',
    '--cp-success': corePanelRuntimeTokens.colors.success,
    '--cp-warning': corePanelRuntimeTokens.colors.warning,
    '--cp-danger': corePanelRuntimeTokens.colors.danger,
    '--cp-info': corePanelRuntimeTokens.colors.info,
    '--cp-radius-sm': corePanelRuntimeTokens.radius.sm,
    '--cp-radius-md': corePanelRuntimeTokens.radius.md,
    '--cp-radius-lg': corePanelRuntimeTokens.radius.lg,
    '--cp-radius-xl': corePanelRuntimeTokens.radius.xl,
    '--cp-shadow-sm': corePanelRuntimeTokens.shadow.sm,
    '--cp-shadow-md': corePanelRuntimeTokens.shadow.md,
    '--cp-shadow-lg': corePanelRuntimeTokens.shadow.lg,
    '--cp-spacing-1': corePanelRuntimeTokens.spacing[1],
    '--cp-spacing-2': corePanelRuntimeTokens.spacing[2],
    '--cp-spacing-3': corePanelRuntimeTokens.spacing[3],
    '--cp-spacing-4': corePanelRuntimeTokens.spacing[4],
    '--cp-spacing-5': corePanelRuntimeTokens.spacing[5],
    '--cp-spacing-6': corePanelRuntimeTokens.spacing[6],
    '--cp-spacing-8': corePanelRuntimeTokens.spacing[8],
    '--cp-spacing-10': corePanelRuntimeTokens.spacing[10],
} as const

export const corePanelRuntimeDarkVariables = {
    '--cp-primary': corePanelRuntimeTokens.colors.primary[300],
    '--cp-surface-canvas': '#101419',
    '--cp-surface-panel': '#171c23',
    '--cp-surface-panel-alt': '#1e252e',
    '--cp-surface-border': '#303845',
    '--cp-text-primary': '#edf1ed',
    '--cp-text-muted': '#aab3bf',
    '--cp-success': '#35bf78',
    '--cp-warning': '#f3b44c',
    '--cp-danger': '#ff6677',
    '--cp-info': '#72a1ff',
    '--cp-radius-sm': corePanelRuntimeTokens.radius.sm,
    '--cp-radius-md': corePanelRuntimeTokens.radius.md,
    '--cp-radius-lg': corePanelRuntimeTokens.radius.lg,
    '--cp-radius-xl': corePanelRuntimeTokens.radius.xl,
    '--cp-shadow-sm': '0 1px 2px rgb(0 0 0 / 0.28)',
    '--cp-shadow-md': '0 18px 44px rgb(0 0 0 / 0.32)',
    '--cp-shadow-lg': '0 24px 60px rgb(0 0 0 / 0.46)',
    '--cp-spacing-1': corePanelRuntimeTokens.spacing[1],
    '--cp-spacing-2': corePanelRuntimeTokens.spacing[2],
    '--cp-spacing-3': corePanelRuntimeTokens.spacing[3],
    '--cp-spacing-4': corePanelRuntimeTokens.spacing[4],
    '--cp-spacing-5': corePanelRuntimeTokens.spacing[5],
    '--cp-spacing-6': corePanelRuntimeTokens.spacing[6],
    '--cp-spacing-8': corePanelRuntimeTokens.spacing[8],
    '--cp-spacing-10': corePanelRuntimeTokens.spacing[10],
} as const

/**
 * Backwards-compatible aliases for existing imports.
 */
export const corePanelTokens = corePanelRuntimeTokens
export const corePanelLightVariables = corePanelRuntimeLightVariables
export const corePanelDarkVariables = corePanelRuntimeDarkVariables
