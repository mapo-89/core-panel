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

        const parsedDate = parseDateTime(value, timeZone.value)

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

        const parsedDate = parseDateTime(value, timeZone.value)

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

        const parsedDate = parseDateTime(value, timeZone.value)

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

function parseDateTime(
    value: Date | number | string,
    configuredTimeZone = 'UTC',
): Date | null {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value
    }

    if (typeof value === 'number') {
        const parsedDate = new Date(value)

        return Number.isNaN(parsedDate.getTime()) ? null : parsedDate
    }

    const parsedDate = parseDateTimeString(value, configuredTimeZone)

    return Number.isNaN(parsedDate.getTime()) ? null : parsedDate
}

function parseDateTimeString(value: string, configuredTimeZone: string): Date {
    const trimmedValue = value.trim()

    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmedValue)) {
        return new Date(`${trimmedValue}T00:00:00Z`)
    }

    if (
        /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(trimmedValue)
    ) {
        return parseConfiguredDateTime(trimmedValue, configuredTimeZone)
    }

    return new Date(trimmedValue)
}

function parseConfiguredDateTime(value: string, timeZone: string): Date {
    const match = value
        .trim()
        .match(
            /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,3}))?$/,
        )

    if (!match) {
        return new Date(value)
    }

    const [
        ,
        year,
        month,
        day,
        hour,
        minute,
        second,
        millisecond = '0',
    ] = match
    const utcGuess = Date.UTC(
        Number(year),
        Number(month) - 1,
        Number(day),
        Number(hour),
        Number(minute),
        Number(second),
        Number(millisecond.padEnd(3, '0')),
    )

    let offsetMinutes = resolveTimeZoneOffsetMinutes(new Date(utcGuess), timeZone)
    let timestamp = utcGuess - offsetMinutes * 60_000
    const correctedOffsetMinutes = resolveTimeZoneOffsetMinutes(
        new Date(timestamp),
        timeZone,
    )

    if (correctedOffsetMinutes !== offsetMinutes) {
        offsetMinutes = correctedOffsetMinutes
        timestamp = utcGuess - offsetMinutes * 60_000
    }

    return new Date(timestamp)
}

function resolveTimeZoneOffsetMinutes(date: Date, timeZone: string): number {
    const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone,
        timeZoneName: 'longOffset',
    })
    const timeZoneName =
        formatter
            .formatToParts(date)
            .find((part) => part.type === 'timeZoneName')?.value ?? 'GMT'

    if (timeZoneName === 'GMT') {
        return 0
    }

    const match = timeZoneName.match(/^GMT([+-])(\d{2}):(\d{2})$/)

    if (!match) {
        return 0
    }

    const [, sign, hours, minutes] = match
    const totalMinutes = Number(hours) * 60 + Number(minutes)

    return sign === '-' ? -totalMinutes : totalMinutes
}
