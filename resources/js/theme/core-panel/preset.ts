import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

import { corePanelDarkPreset } from './dark'
import { corePanelLightPreset } from './light'
import { corePanelRuntimeTokens } from './tokens'

export const corePanelRuntimePreset = definePreset(Aura, {
    semantic: {
        primary: corePanelRuntimeTokens.colors.primary,
        transitionDuration: '0.2s',
        colorScheme: {
            light: {
                primary: corePanelLightPreset.primary,
                surface: corePanelLightPreset.surface,
            },
            dark: {
                primary: corePanelDarkPreset.primary,
                surface: corePanelDarkPreset.surface,
            },
        },
        focusRing: {
            width: '2px',
            style: 'solid',
            color: corePanelRuntimeTokens.colors.primary[300],
            offset: '2px',
        },
        borderRadius: corePanelRuntimeTokens.radius.md,
        formField: {
            paddingX: corePanelRuntimeTokens.spacing[4],
            paddingY: '0.64rem',
            sm: {
                fontSize: '0.875rem',
                paddingX: '0.625rem',
                paddingY: '0.25rem',
            },
            lg: {
                fontSize: '1.125rem',
                paddingX: '0.875rem',
                paddingY: '0.5rem',
            },
            borderRadius: corePanelRuntimeTokens.radius.md,
            focusRing: {
                width: '0',
                style: 'none',
                color: 'transparent',
                offset: '0',
            },
        },
        content: {
            borderRadius: corePanelRuntimeTokens.radius.sm,
        },
        navigation: {
            list: {
                padding: '0.375rem',
                gap: '2px',
            },
            item: {
                padding: '0.5rem 0.75rem',
                borderRadius: corePanelRuntimeTokens.radius.sm,
                gap: '0.5rem',
            },
            submenuLabel: {
                padding: '0.5rem 0.75rem',
                fontWeight: '600',
            },
            submenuIcon: {
                size: '0.875rem',
            },
        },
        overlay: {
            select: {
                borderRadius: corePanelRuntimeTokens.radius.sm,
                shadow: corePanelRuntimeTokens.shadow.lg,
            },
            popover: {
                borderRadius: corePanelRuntimeTokens.radius.sm,
                padding: corePanelRuntimeTokens.spacing[3],
                shadow: corePanelRuntimeTokens.shadow.lg,
            },
            modal: {
                borderRadius: corePanelRuntimeTokens.radius.lg,
                padding: corePanelRuntimeTokens.spacing[5],
                shadow: corePanelRuntimeTokens.shadow.lg,
            },
            navigation: {
                shadow: corePanelRuntimeTokens.shadow.lg,
            },
        },
        list: {
            padding: '0.375rem',
            gap: '2px',
            header: {
                padding: '0.5rem 0.75rem 0.25rem',
            },
            option: {
                padding: '0.5rem 0.75rem',
                borderRadius: corePanelRuntimeTokens.radius.sm,
            },
            optionGroup: {
                padding: '0.5rem 0.75rem',
                fontWeight: '600',
            },
        },
    },
    components: {
        button: {
            root: {
                paddingX: '1rem',
            },
        },
    },
})

export const corePanelPreset = corePanelRuntimePreset
