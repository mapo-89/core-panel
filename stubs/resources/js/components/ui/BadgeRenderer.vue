<script setup lang="ts">
import { computed } from 'vue'
import type { StyleValue } from 'vue'

type BadgeVariant = 'soft' | 'outlined' | 'filled'
type TextColorMode = 'readable' | 'shade'

type RgbColor = {
    red: number
    green: number
    blue: number
}

interface Props {
    label: string
    tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info'
    size?: 'sm' | 'md'
    icon?: string | null
    variant?: BadgeVariant
    outlined?: boolean
    rounded?: boolean
    color?: string | null
    textColor?: string | null
    textColorMode?: TextColorMode
    badgeStyle?: StyleValue
}

const props = withDefaults(defineProps<Props>(), {
    tone: 'neutral',
    size: 'sm',
    icon: null,
    variant: 'soft',
    outlined: false,
    rounded: true,
    color: null,
    textColor: null,
    textColorMode: 'readable',
    badgeStyle: undefined,
})

const effectiveVariant = computed<BadgeVariant>(() =>
    props.outlined ? 'outlined' : props.variant,
)

const toneClasses = computed(() => {
    const styles = {
        soft: {
            neutral:
                'bg-surface-200 text-surface-700 ring-surface-300 dark:bg-surface-700/70 dark:text-surface-200 dark:ring-surface-600',
            success:
                'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/20',
            warning:
                'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/20',
            danger: 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/20',
            info: 'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/20',
        },
        outlined: {
            neutral:
                'bg-transparent text-surface-700 ring-surface-300 dark:text-surface-200 dark:ring-surface-600',
            success:
                'bg-transparent text-emerald-700 ring-emerald-300 dark:text-emerald-300 dark:ring-emerald-500/40',
            warning:
                'bg-transparent text-amber-700 ring-amber-300 dark:text-amber-300 dark:ring-amber-500/40',
            danger: 'bg-transparent text-rose-700 ring-rose-300 dark:text-rose-300 dark:ring-rose-500/40',
            info: 'bg-transparent text-sky-700 ring-sky-300 dark:text-sky-300 dark:ring-sky-500/40',
        },
    }

    return styles[effectiveVariant.value === 'outlined' ? 'outlined' : 'soft'][
        props.tone
    ]
})

const sizeClasses = computed(() => {
    return {
        sm: 'px-2.5 py-1 text-xs',
        md: 'px-3 py-1.5 text-sm',
    }[props.size]
})

const shapeClasses = computed(() =>
    props.rounded ? 'rounded-full' : 'rounded-md',
)

function parseHexColor(color: string): RgbColor | null {
    const hex = color.trim().replace(/^#/, '')
    const normalizedHex =
        hex.length === 3
            ? hex
                  .split('')
                  .map((character) => character + character)
                  .join('')
            : hex

    if (!/^[0-9a-fA-F]{6}$/.test(normalizedHex)) {
        return null
    }

    return {
        red: Number.parseInt(normalizedHex.slice(0, 2), 16),
        green: Number.parseInt(normalizedHex.slice(2, 4), 16),
        blue: Number.parseInt(normalizedHex.slice(4, 6), 16),
    }
}

function parseRgbColor(color: string): RgbColor | null {
    const match = color
        .trim()
        .match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i)

    if (!match) {
        return null
    }

    const [, red, green, blue] = match
    const values = [red, green, blue].map((value) => Number.parseInt(value, 10))

    if (values.some((value) => value < 0 || value > 255)) {
        return null
    }

    return {
        red: values[0],
        green: values[1],
        blue: values[2],
    }
}

function parseNamedColor(color: string): RgbColor | null {
    const namedColors: Record<string, RgbColor> = {
        amber: { red: 245, green: 158, blue: 11 },
        black: { red: 0, green: 0, blue: 0 },
        blue: { red: 0, green: 0, blue: 255 },
        cyan: { red: 0, green: 255, blue: 255 },
        emerald: { red: 16, green: 185, blue: 129 },
        fuchsia: { red: 255, green: 0, blue: 255 },
        gray: { red: 128, green: 128, blue: 128 },
        green: { red: 0, green: 128, blue: 0 },
        grey: { red: 128, green: 128, blue: 128 },
        indigo: { red: 75, green: 0, blue: 130 },
        lime: { red: 0, green: 255, blue: 0 },
        neutral: { red: 115, green: 115, blue: 115 },
        orange: { red: 255, green: 165, blue: 0 },
        pink: { red: 255, green: 192, blue: 203 },
        purple: { red: 128, green: 0, blue: 128 },
        red: { red: 255, green: 0, blue: 0 },
        rose: { red: 225, green: 29, blue: 72 },
        sky: { red: 14, green: 165, blue: 233 },
        slate: { red: 100, green: 116, blue: 139 },
        stone: { red: 120, green: 113, blue: 108 },
        teal: { red: 0, green: 128, blue: 128 },
        violet: { red: 139, green: 92, blue: 246 },
        white: { red: 255, green: 255, blue: 255 },
        yellow: { red: 255, green: 255, blue: 0 },
        zinc: { red: 113, green: 113, blue: 122 },
    }

    return namedColors[color.trim().toLowerCase()] ?? null
}

function parseColor(color: string): RgbColor | null {
    return (
        parseHexColor(color) ?? parseRgbColor(color) ?? parseNamedColor(color)
    )
}

function readableTextColor(backgroundColor: string): string {
    const color = parseColor(backgroundColor)

    if (!color) {
        return '#ffffff'
    }

    const luminance =
        (0.299 * color.red + 0.587 * color.green + 0.114 * color.blue) / 255

    return luminance > 0.62 ? '#111827' : '#ffffff'
}

function mixRgbColor(
    color: RgbColor,
    target: RgbColor,
    amount: number,
): RgbColor {
    return {
        red: Math.round(color.red + (target.red - color.red) * amount),
        green: Math.round(color.green + (target.green - color.green) * amount),
        blue: Math.round(color.blue + (target.blue - color.blue) * amount),
    }
}

function rgbToHex(color: RgbColor): string {
    const toHex = (value: number): string => value.toString(16).padStart(2, '0')

    return `#${toHex(color.red)}${toHex(color.green)}${toHex(color.blue)}`
}

function shadedTextColor(backgroundColor: string): string {
    const color = parseColor(backgroundColor)

    if (!color) {
        return readableTextColor(backgroundColor)
    }

    const luminance =
        (0.299 * color.red + 0.587 * color.green + 0.114 * color.blue) / 255
    const target =
        luminance > 0.62
            ? { red: 0, green: 0, blue: 0 }
            : { red: 255, green: 255, blue: 255 }
    const amount = luminance > 0.62 ? 0.65 : 0.8

    return rgbToHex(mixRgbColor(color, target, amount))
}

function filledTextColor(backgroundColor: string): string {
    return (
        props.textColor ??
        (props.textColorMode === 'shade'
            ? shadedTextColor(backgroundColor)
            : readableTextColor(backgroundColor))
    )
}

const colorStyle = computed<StyleValue>(() => {
    if (!props.color) {
        return undefined
    }

    if (effectiveVariant.value === 'filled') {
        return {
            backgroundColor: props.color,
            boxShadow: `inset 0 0 0 1px ${props.color}`,
            color: filledTextColor(props.color),
        }
    }

    if (effectiveVariant.value === 'outlined') {
        return {
            backgroundColor: 'transparent',
            boxShadow: `inset 0 0 0 1px ${props.color}`,
            color: props.textColor ?? props.color,
        }
    }

    return {
        backgroundColor: `color-mix(in srgb, ${props.color} 12%, transparent)`,
        boxShadow: `inset 0 0 0 1px color-mix(in srgb, ${props.color} 35%, transparent)`,
        color: props.textColor ?? props.color,
    }
})

const customStyle = computed(() => [colorStyle.value, props.badgeStyle])
</script>

<template>
    <span
        class="inline-flex items-center font-medium ring-1"
        :class="[toneClasses, sizeClasses, shapeClasses]"
        :style="customStyle"
    >
        <i v-if="icon" :class="[icon, 'mr-1.5 text-[0.9em]']" />
        {{ label }}
    </span>
</template>
