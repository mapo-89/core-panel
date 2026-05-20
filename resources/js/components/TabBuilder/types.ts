import type { FormSchema } from '../FormBuilder/types'

export type TabBuilderTab = {
    badge?: string
    component?: string
    componentProps?: Record<string, unknown>
    icon?: string
    key: string
    label?: string
    labelTranslations?: Record<string, string>
    lazy?: boolean
    meta?: Record<string, unknown>
    permission?: string
    schema?: FormSchema
    visible?: boolean
}

export type TabsSchema = {
    panelSurface?: boolean
    panelSurfaceClass?: string
    panelSurfaceVariant?: 'card' | 'flush'
    tabs: TabBuilderTab[]
}
