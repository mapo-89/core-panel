import { corePanelRuntimeTokens } from './tokens'

export const corePanelLightPreset = {
    primary: corePanelRuntimeTokens.colors.primary,
    surface: {
        0: '#ffffff',
        50: '#f4f5f7',
        100: '#eef1f4',
        200: '#dde3e8',
        300: '#c7ced7',
        400: '#a8b2bf',
        500: '#8791a0',
        600: '#697385',
        700: '#525b6b',
        800: '#3a414d',
        900: '#242931',
        950: '#171a20',
    },
    text: {
        color: '#171717',
        mutedColor: '#5f6773',
    },
    semantic: {
        success: {
            color: corePanelRuntimeTokens.colors.success,
        },
        warning: {
            color: corePanelRuntimeTokens.colors.warning,
        },
        danger: {
            color: corePanelRuntimeTokens.colors.danger,
        },
        info: {
            color: corePanelRuntimeTokens.colors.info,
        },
    },
} as const
