import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

type SharedPageProps = {
    corePanel?: {
        settings?: {
            general?: {
                timezone?: string | null
            }
        }
    }
    locale?: {
        current?: string | null
    }
}

type DateTimeFormatOptions = Intl.DateTimeFormatOptions

const defaultDateTimeFormat: DateTimeFormatOptions = {
    dateStyle: 'medium',
    timeStyle: 'short',
}

export function useDateTime() {
    const page = usePage<SharedPageProps>()

    const locale = computed(() => {
        const currentLocale = page.props.locale?.current

        return typeof currentLocale === 'string' && currentLocale !== ''
            ? currentLocale
            : undefined
    })

    const timeZone = computed(() => {
        const configuredTimeZone =
            page.props.corePanel?.settings?.general?.timezone

        return typeof configuredTimeZone === 'string' &&
            configuredTimeZone !== ''
            ? configuredTimeZone
            : 'UTC'
    })

    function formatDateTime(
        value: Date | number | string | null | undefined,
        options: DateTimeFormatOptions = defaultDateTimeFormat,
    ): string {
        if (value === null || value === undefined || value === '') {
            return '—'
        }

        const parsedDate = parseDateTime(value)

        if (parsedDate === null) {
            return String(value)
        }

        return new Intl.DateTimeFormat(locale.value, {
            ...defaultDateTimeFormat,
            ...options,
            timeZone: timeZone.value,
        }).format(parsedDate)
    }

    function formatUnixTimestamp(
        value: number | null | undefined,
        options: DateTimeFormatOptions = defaultDateTimeFormat,
    ): string {
        if (value === null || value === undefined || Number.isNaN(value)) {
            return '—'
        }

        return formatDateTime(value * 1000, options)
    }

    return {
        formatDateTime,
        formatUnixTimestamp,
        locale,
        timeZone,
    }
}

function parseDateTime(value: Date | number | string): Date | null {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value
    }

    if (typeof value === 'number') {
        const parsedDate = new Date(value)

        return Number.isNaN(parsedDate.getTime()) ? null : parsedDate
    }

    const normalizedValue = normalizeDateTimeString(value)
    const parsedDate = new Date(normalizedValue)

    return Number.isNaN(parsedDate.getTime()) ? null : parsedDate
}

function normalizeDateTimeString(value: string): string {
    const trimmedValue = value.trim()

    if (
        /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(trimmedValue)
    ) {
        return `${trimmedValue.replace(' ', 'T')}Z`
    }

    return trimmedValue
}
