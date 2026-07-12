declare module '@core-panel/theme/core-panel' {
    export type CorePanelColorMode = 'dark' | 'light'
    export type CorePanelColorModePreference = CorePanelColorMode | 'system'
    export type CorePanelThemePalette = 'paper' | 'soft' | 'ocean' | 'contrast'
    export type CorePanelLayoutDensity = 'comfortable' | 'compact' | 'spacious'
    export type CorePanelRadiusToken = 'lg' | 'md' | 'none' | 'sm' | 'xl'

    export const CORE_PANEL_COLOR_MODE_KEY: string
    export const CORE_PANEL_THEME_PALETTE_KEY: string
    export const CORE_PANEL_THEME_ACCENT_KEY: string
    export const CORE_PANEL_LAYOUT_DENSITY_KEY: string
    export const CORE_PANEL_RADIUS_TOKEN_KEY: string

    export function applyCorePanelRuntimeThemeVariables(
        mode: CorePanelColorMode,
    ): void

    export function normalizeCorePanelColorModePreference(
        mode: unknown,
    ): CorePanelColorModePreference
    export function resolveCorePanelColorMode(
        preference: CorePanelColorModePreference,
    ): CorePanelColorMode
    export function resolveSystemCorePanelColorMode(): CorePanelColorMode
    export function toggleCorePanelColorMode(
        mode: CorePanelColorModePreference,
        effectiveMode?: CorePanelColorMode,
    ): CorePanelColorModePreference

    export function applyCorePanelLayoutDensity(
        density: CorePanelLayoutDensity,
    ): void
    export function normalizeCorePanelLayoutDensity(
        density: unknown,
    ): CorePanelLayoutDensity

    export function applyCorePanelRadiusToken(
        radius: CorePanelRadiusToken,
    ): void
    export function normalizeCorePanelRadiusToken(
        radius: unknown,
    ): CorePanelRadiusToken

    export function applyCorePanelThemeAccent(accent: string): void
    export function normalizeCorePanelThemeAccent(accent: unknown): string

    export function applyCorePanelThemePalette(
        palette: CorePanelThemePalette,
    ): void
    export function normalizeCorePanelThemePalette(
        palette: unknown,
    ): CorePanelThemePalette

    export function readStoredCorePanelColorMode():
        | CorePanelColorModePreference
        | null
        | undefined

    export function resolveCorePanelRuntimeTheme(themeName: string): unknown
    export function resolveCorePanelPreviewPalette(
        palette: CorePanelThemePalette,
    ): {
        light: {
            frame: string
            header: string
            shell: string
            sidebar: string
            text: string
            textMuted: string
        }
        dark: {
            frame: string
            header: string
            shell: string
            sidebar: string
            text: string
            textMuted: string
        }
    }
}

declare module '@core-panel/components/TableBuilder/DataTable.vue' {
    const component: unknown
    export default component
}

declare module '@core-panel/components/TableBuilder/ColumnVisibilityDropdown.vue' {
    const component: unknown
    export default component
}

declare module '@core-panel/components/TableBuilder/TablePagination.vue' {
    const component: unknown
    export default component
}

declare module '@core-panel/components/TableBuilder/types' {
    export type DataTableActionTarget = string | string[]

    export type DataTableAction = {
        [key: string]: unknown
        key: string
        permission?: string | string[]
        requiresConfirmation?: boolean
        requiresSoftDeleteContext?: boolean
        type?: string
    }

    export type DataTableActionMethod = string
    export type DataTableColumn = {
        [key: string]: unknown
        key: string
        label: string | null
        searchable: boolean
        sortable: boolean
        toggleable: boolean
        type: string
        visible: boolean
    }
    export type DataTableFilter = {
        [key: string]: unknown
    }
    export type DataTablePagination = {
        currentPage?: number
        perPage?: number
        total?: number
        totalPages?: number
        [key: string]: unknown
    }
    export type DataTableRow = {
        [key: string]: unknown
    }
    export type DataTableSchema = {
        pagination: DataTablePagination
        rows: DataTableRow[]
        state: DataTableState
    }
    export type DataTableState = {
        sort?: string
        sortOrder?: 1 | -1
        visibleColumns: string[]
        [key: string]: unknown
    }
}

declare module '@core-panel/components/TabBuilder/TabsRenderer.vue' {
    const component: unknown
    export default component
}

declare module '@core-panel/components/TabBuilder/types' {
    export type TabBuilderTab = {
        [key: string]: unknown
        component?: string
        componentProps?: Record<string, unknown>
        icon?: string
        key?: string
        label?: string
    }

    export type TabsSchema = {
        tabs: Array<TabBuilderTab>
        [key: string]: unknown
    }
}

declare module '@core-panel/components/FormBuilder/FormRenderer.vue' {
    const component: unknown
    export default component
}

declare module '@core-panel/components/FormBuilder/types' {
    export type FormCondition = {
        [key: string]: unknown
    }

    export type FormConditionClause = {
        [key: string]: unknown
    }

    export type FormErrors = {
        [key: string]: string | string[] | undefined
    }

    export type FormModel = {
        [key: string]: unknown
    }

    export type FormOptionRecord = {
        [key: string]: unknown
    }

    export type FormOptionTranslationMap = {
        [key: string]: string
    }

    export type FormSchema = Array<{
        [key: string]: unknown
    }>

    export type FormSchemaField = {
        [key: string]: unknown
    }

    export type FormTranslatedMap = {
        [key: string]: string
    }

    export type WayfinderAction = {
        [key: string]: unknown
    }
}

declare module '@core-panel/components/*' {
    const component: unknown
    export default component
}
