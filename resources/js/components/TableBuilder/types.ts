export type DataTableActionMethod = 'delete' | 'get' | 'patch' | 'post' | 'put'

export type DataTableActionTarget = {
    method?: DataTableActionMethod
    url: string
}

export type DataTableColumn = {
    key: string
    label: string | null
    meta?: Record<string, unknown>
    searchable: boolean
    sortable: boolean
    toggleable: boolean
    type: string
    visible: boolean
}

export type DataTableFilter = {
    key: string
    label: string | null
    meta?: Record<string, unknown>
    options?: Record<string, string>
    type: string
}

export type DataTableAction = {
    bulk: boolean
    label: string | null
    meta?: Record<string, unknown>
    name: string
    type: string
    target?: DataTableActionTarget | null
}

export type DataTablePagination = {
    page: number
    perPage: number
    total: number
    lastPage: number
    from: number | null
    to: number | null
}

export type DataTableState = {
    filters: Record<string, unknown>
    search: string
    sort: string
    visibleColumns: string[]
}

export type DataTableRow = Record<string, unknown> & {
    id?: string | number
}

export type DataTableSchema = {
    actions: DataTableAction[]
    bulkActions: DataTableAction[]
    columns: DataTableColumn[]
    filters: DataTableFilter[]
    mode?: 'local' | 'remote'
    pagination: DataTablePagination
    rows: DataTableRow[]
    state: DataTableState
}
