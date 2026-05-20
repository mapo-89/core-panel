import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

import type {
    FormCondition,
    FormConditionClause,
    FormErrors,
    FormModel,
    FormOptionRecord,
    FormSchemaField,
    FormTranslatedMap,
} from './types'

type PageLocaleProps = {
    locale?: {
        current?: string
        default?: string
        fallback?: string
    }
}

function isClauseArray(value: FormCondition): value is FormConditionClause[] {
    return Array.isArray(value)
}

function isClauseObject(value: FormCondition): value is FormConditionClause {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export function getNestedValue(source: unknown, path: string): unknown {
    return path.split('.').reduce<unknown>((current, segment) => {
        if (current === null || current === undefined) {
            return undefined
        }

        if (Array.isArray(current)) {
            const index = Number(segment)

            return Number.isInteger(index) ? current[index] : undefined
        }

        if (typeof current === 'object') {
            return (current as Record<string, unknown>)[segment]
        }

        return undefined
    }, source)
}

export function setNestedValue(
    source: FormModel,
    path: string,
    value: unknown,
): FormModel {
    const segments = path.split('.')
    const root: Record<string, unknown> = { ...source }

    let currentTarget: Record<string, unknown> = root
    let currentSource: unknown = source

    for (const [index, segment] of segments.entries()) {
        const isLeaf = index === segments.length - 1

        if (isLeaf) {
            currentTarget[segment] = value

            break
        }

        const nextSource =
            currentSource !== null &&
            currentSource !== undefined &&
            typeof currentSource === 'object'
                ? (currentSource as Record<string, unknown>)[segment]
                : undefined

        const nextTarget =
            nextSource !== null &&
            nextSource !== undefined &&
            typeof nextSource === 'object' &&
            !Array.isArray(nextSource)
                ? { ...(nextSource as Record<string, unknown>) }
                : {}

        currentTarget[segment] = nextTarget
        currentTarget = nextTarget
        currentSource = nextSource
    }

    return root
}

function evaluateClause(
    clause: FormConditionClause,
    model: FormModel,
): boolean {
    const currentValue = getNestedValue(model, clause.field)
    const operator = clause.operator ?? 'equals'
    const expected = clause.value

    switch (operator) {
        case 'contains':
            return Array.isArray(currentValue)
                ? currentValue.includes(expected)
                : String(currentValue ?? '').includes(String(expected ?? ''))

        case 'empty':
            return (
                currentValue === null ||
                currentValue === undefined ||
                currentValue === '' ||
                (Array.isArray(currentValue) && currentValue.length === 0)
            )

        case 'endsWith':
            return String(currentValue ?? '').endsWith(String(expected ?? ''))

        case 'equals':
            return currentValue === expected

        case 'filled':
            return !evaluateClause(
                { field: clause.field, operator: 'empty' },
                model,
            )

        case 'gt':
            return Number(currentValue) > Number(expected)

        case 'gte':
            return Number(currentValue) >= Number(expected)

        case 'in':
            return Array.isArray(expected) && expected.includes(currentValue)

        case 'lt':
            return Number(currentValue) < Number(expected)

        case 'lte':
            return Number(currentValue) <= Number(expected)

        case 'notContains':
            return !evaluateClause({ ...clause, operator: 'contains' }, model)

        case 'notEquals':
            return currentValue !== expected

        case 'notIn':
            return Array.isArray(expected) && !expected.includes(currentValue)

        case 'startsWith':
            return String(currentValue ?? '').startsWith(String(expected ?? ''))
    }
}

export function evaluateCondition(
    condition: FormCondition,
    model: FormModel,
): boolean {
    if (condition === null || condition === undefined) {
        return true
    }

    if (typeof condition === 'string') {
        return Boolean(getNestedValue(model, condition))
    }

    if (isClauseArray(condition)) {
        return condition.every((clause) => evaluateClause(clause, model))
    }

    if (isClauseObject(condition)) {
        return evaluateClause(condition, model)
    }

    return true
}

export function resolveLocalePreference(locale?: string): string {
    const page = usePage<PageLocaleProps>()
    const current = page.props.locale?.current
    const fallback = page.props.locale?.fallback
    const defaultLocale = page.props.locale?.default

    return locale ?? current ?? defaultLocale ?? fallback ?? 'en'
}

export function resolveTranslatedText(
    translations: FormTranslatedMap | undefined,
    fallback: string | undefined,
    locale?: string,
): string {
    const activeLocale = resolveLocalePreference(locale)
    const page = usePage<PageLocaleProps>()
    const defaultLocale = page.props.locale?.default ?? 'de'
    const fallbackLocale = page.props.locale?.fallback ?? 'en'

    return (
        translations?.[activeLocale] ??
        translations?.[defaultLocale] ??
        translations?.[fallbackLocale] ??
        fallback ??
        ''
    )
}

export function resolveFieldLabel(
    field: FormSchemaField,
    locale?: string,
): string {
    return resolveTranslatedText(field.labelTranslations, field.label, locale)
}

export function resolveFieldPlaceholder(
    field: FormSchemaField,
    locale?: string,
): string {
    return resolveTranslatedText(
        field.placeholderTranslations,
        field.placeholder,
        locale,
    )
}

export function resolveFieldHelp(
    field: FormSchemaField,
    locale?: string,
): string {
    return resolveTranslatedText(field.helpTranslations, field.help, locale)
}

export function resolveFieldOptions(
    field: FormSchemaField,
    locale?: string,
): FormOptionRecord[] {
    const options = field.options ?? []
    const normalized = Array.isArray(options)
        ? options.map((option) => {
              if (
                  typeof option === 'object' &&
                  option !== null &&
                  'value' in option
              ) {
                  return option as FormOptionRecord
              }

              return {
                  label: String(option),
                  value: option as string,
              }
          })
        : Object.entries(options).map(([value, label]) => ({
              label,
              value,
          }))

    return normalized.map((option) => ({
        ...option,
        label: resolveTranslatedText(
            field.optionTranslations?.[String(option.value)],
            option.label ?? String(option.value),
            locale,
        ),
    }))
}

export function resolveFieldError(
    errors: FormErrors,
    fieldPath: string,
): string | undefined {
    const error = errors[fieldPath]

    if (Array.isArray(error)) {
        return error[0]
    }

    return error
}

export function isFieldVisible(
    field: FormSchemaField,
    model: FormModel,
): boolean {
    return evaluateCondition(field.visibleIf ?? null, model)
}

export function isFieldDisabled(
    field: FormSchemaField,
    model: FormModel,
): boolean {
    if (field.disabledIf === null || field.disabledIf === undefined) {
        return false
    }

    return evaluateCondition(field.disabledIf, model)
}

export function createDefaultValue(field: FormSchemaField): unknown {
    if (field.default !== undefined) {
        return field.default
    }

    switch (field.type) {
        case 'checkbox':
            return false
        case 'file':
            return null
        case 'group':
            return {}
        case 'multi-select':
        case 'repeater':
            return []
        default:
            return ''
    }
}

export function useCurrentLocale(explicitLocale?: string) {
    return computed(() => resolveLocalePreference(explicitLocale))
}
