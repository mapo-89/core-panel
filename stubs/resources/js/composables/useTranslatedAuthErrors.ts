import { trans } from 'laravel-vue-i18n'
import { reactive } from 'vue'

type AuthFieldErrorKey =
    | 'auth.failed'
    | 'auth.throttle'
    | 'validation.confirmed'
    | 'validation.email'
    | 'validation.min.string'
    | 'validation.required'

type AuthFieldErrorMeta = {
    key: AuthFieldErrorKey
    params?: Record<string, string>
}

export function useTranslatedAuthErrors<TField extends string>(
    fields: readonly TField[],
) {
    const errorMeta = reactive(
        Object.fromEntries(fields.map((field) => [field, null])) as Record<
            TField,
            AuthFieldErrorMeta | null
        >,
    ) as Record<TField, AuthFieldErrorMeta | null>

    function translatedAuthError(
        field: TField,
        error: string | null | undefined,
    ): string | null {
        if (!error) {
            return null
        }

        const translatedFromMeta = translateAuthErrorMeta(errorMeta[field])

        if (translatedFromMeta !== null) {
            return translatedFromMeta
        }

        const detectedMeta = resolveAuthErrorMeta(field, error)

        if (detectedMeta !== null) {
            errorMeta[field] = detectedMeta

            return translateAuthErrorMeta(detectedMeta)
        }

        return error
    }

    function clearTranslatedAuthErrors(): void {
        for (const field of fields) {
            errorMeta[field] = null
        }
    }

    return {
        clearTranslatedAuthErrors,
        translatedAuthError,
    }
}

function resolveAuthErrorMeta<TField extends string>(
    field: TField,
    error: string,
): AuthFieldErrorMeta | null {
    const normalizedError = error.trim()
    const attribute = translatedValidationAttribute(field)

    if (
        normalizedError ===
        trans('validation.required', {
            attribute,
        })
    ) {
        return {
            key: 'validation.required',
            params: { attribute },
        }
    }

    if (
        normalizedError ===
        trans('validation.email', {
            attribute,
        })
    ) {
        return {
            key: 'validation.email',
            params: { attribute },
        }
    }

    const minLength = extractFirstNumber(normalizedError)

    if (
        minLength !== null &&
        normalizedError ===
            trans('validation.min.string', {
                attribute,
                min: String(minLength),
            })
    ) {
        return {
            key: 'validation.min.string',
            params: {
                attribute,
                min: String(minLength),
            },
        }
    }

    if (
        normalizedError ===
        trans('validation.confirmed', {
            attribute,
        })
    ) {
        return {
            key: 'validation.confirmed',
            params: { attribute },
        }
    }

    if (normalizedError === trans('auth.failed')) {
        return {
            key: 'auth.failed',
        }
    }

    const throttleSeconds = extractFirstNumber(normalizedError)

    if (
        throttleSeconds !== null &&
        normalizedError ===
            trans('auth.throttle', { seconds: String(throttleSeconds) })
    ) {
        return {
            key: 'auth.throttle',
            params: { seconds: String(throttleSeconds) },
        }
    }

    return null
}

function translateAuthErrorMeta(
    meta: AuthFieldErrorMeta | null,
): string | null {
    if (meta === null) {
        return null
    }

    return trans(meta.key, meta.params)
}

function translatedValidationAttribute(field: string): string {
    return trans(`validation.attributes.${field}`)
}

function extractFirstNumber(value: string): number | null {
    const number = value.match(/(\d+)/)?.[1]

    return number ? Number.parseInt(number, 10) : null
}
