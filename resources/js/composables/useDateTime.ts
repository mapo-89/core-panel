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

const defaultDateFormat: DateTimeFormatOptions = {
    dateStyle: 'medium',
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
            timeZone: resolveFormatterTimeZone(value, timeZone.value),
        }).format(parsedDate)
    }

    function formatDate(
        value: Date | number | string | null | undefined,
        options: DateTimeFormatOptions = defaultDateFormat,
    ): string {
        if (value === null || value === undefined || value === '') {
            return '—'
        }

        const parsedDate = parseDateTime(value)

        if (parsedDate === null) {
            return String(value)
        }

        return new Intl.DateTimeFormat(locale.value, {
            ...defaultDateFormat,
            ...options,
            timeZone: resolveFormatterTimeZone(value, timeZone.value),
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

    function toDateTimeLocalInput(
        value: Date | number | string | null | undefined,
    ): string {
        if (value === null || value === undefined || value === '') {
            return ''
        }

        if (typeof value === 'string') {
            const trimmedValue = value.trim()

            if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/.test(trimmedValue)) {
                return trimmedValue.replace(' ', 'T')
            }
        }

        const parsedDate = parseDateTime(value)

        if (parsedDate === null) {
            return typeof value === 'string' ? value.trim().slice(0, 16) : ''
        }

        const formatter = new Intl.DateTimeFormat('en-CA', {
            hour: '2-digit',
            hourCycle: 'h23',
            minute: '2-digit',
            month: '2-digit',
            timeZone: timeZone.value,
            year: 'numeric',
            day: '2-digit',
        })

        const parts = formatter.formatToParts(parsedDate)
        const year = parts.find((part) => part.type === 'year')?.value
        const month = parts.find((part) => part.type === 'month')?.value
        const day = parts.find((part) => part.type === 'day')?.value
        const hour = parts.find((part) => part.type === 'hour')?.value
        const minute = parts.find((part) => part.type === 'minute')?.value

        if (!year || !month || !day || !hour || !minute) {
            return ''
        }

        return `${year}-${month}-${day}T${hour}:${minute}`
    }

    return {
        formatDate,
        formatDateTime,
        formatUnixTimestamp,
        locale,
        timeZone,
        toDateTimeLocalInput,
    }
}

function resolveFormatterTimeZone(
    value: Date | number | string,
    fallbackTimeZone: string,
): string {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value.trim())
        ? 'UTC'
        : fallbackTimeZone
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

    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmedValue)) {
        return `${trimmedValue}T00:00:00Z`
    }

    if (
        /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(trimmedValue)
    ) {
        return `${trimmedValue.replace(' ', 'T')}Z`
    }

    return trimmedValue
}
