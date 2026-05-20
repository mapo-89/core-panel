import { corePanelRuntimeTokens } from './tokens'

export const corePanelDarkPreset = {
    primary: {
        50: '#0d1d18',
        100: '#12342b',
        200: '#165245',
        300: corePanelRuntimeTokens.colors.primary[300],
        400: corePanelRuntimeTokens.colors.primary[200],
        500: '#98f0d5',
        600: '#bbf7e6',
        700: '#d5fbf0',
        800: '#eefdf9',
        900: '#f8fffd',
        950: '#ffffff',
    },
    surface: {
        0: '#ffffff',
        50: '#f8fafc',
        100: '#e2e8f0',
        200: '#cbd5e1',
        300: '#94a3b8',
        400: '#64748b',
        500: '#475569',
        600: '#334155',
        700: '#1e293b',
        800: '#0f172a',
        900: '#0b1120',
        950: '#030712',
    },
    text: {
        color: '#edf1ed',
        mutedColor: '#aab3bf',
    },
    semantic: {
        success: {
            color: '#35bf78',
        },
        warning: {
            color: '#f3b44c',
        },
        danger: {
            color: '#ff6677',
        },
        info: {
            color: '#72a1ff',
        },
    },
} as const
