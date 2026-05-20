export type FormConditionOperator =
    | 'contains'
    | 'empty'
    | 'endsWith'
    | 'equals'
    | 'filled'
    | 'gt'
    | 'gte'
    | 'in'
    | 'lt'
    | 'lte'
    | 'notContains'
    | 'notEquals'
    | 'notIn'
    | 'startsWith'

export type FormConditionClause = {
    field: string
    operator?: FormConditionOperator
    value?: unknown
}

export type FormCondition =
    | string
    | FormConditionClause
    | FormConditionClause[]
    | null

export type FormTranslatedMap = Record<string, string>

export type FormOptionTranslationMap = Record<string, FormTranslatedMap>

export type FormOptionValue = string | number | boolean | null

export type FormOptionRecord = {
    disabled?: boolean
    label?: string
    meta?: Record<string, unknown>
    value: FormOptionValue
}

export type BaseField = {
    columnSpan?: number | string | Record<string, number | string>
    default?: unknown
    disabledIf?: FormCondition
    help?: string
    helpTranslations?: FormTranslatedMap
    label?: string
    labelTranslations?: FormTranslatedMap
    meta?: Record<string, unknown>
    name: string
    optionTranslations?: FormOptionTranslationMap
    options?: Record<string, string> | FormOptionRecord[] | string[]
    placeholder?: string
    placeholderTranslations?: FormTranslatedMap
    required?: boolean
    rules?: string[]
    type: string
    validationMessageTranslations?: Record<string, string | FormTranslatedMap>
    visibleIf?: FormCondition
}

export type GroupFieldSchema = BaseField & {
    schema: FormSchemaField[]
    type: 'group'
}

export type RepeaterFieldSchema = BaseField & {
    schema: FormSchemaField[]
    type: 'repeater'
}

export type FormSchemaField = BaseField | GroupFieldSchema | RepeaterFieldSchema

export type FormSchema = FormSchemaField[]

export type FormErrors = Record<string, string | string[] | undefined>

export type FormModel = Record<string, unknown>

export type WayfinderAction = {
    method?: 'delete' | 'get' | 'patch' | 'post' | 'put'
    url: string
}

export type SerializedForm = {
    name: string
    permission?: string | null
    schema: FormSchema
    validation?: {
        messages?: Record<string, string>
        rules?: Record<string, string[]>
    }
}
