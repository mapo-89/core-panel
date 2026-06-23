import type {
    FormOptionRecord,
    FormSchema,
} from '@core-panel/components/FormBuilder/types'

export type CorePanelUserModel = string

export type CorePanelUiTheme = {
    darkMode: boolean
    theme: string
}

export type CorePanelUploadConfig = {
    accept: string
    badges: string[]
    mimeTypes: string[]
}

export type LocaleOption = {
    code: string
    label: string
}

export type SocialProviderRecord = {
    provider: string
    label: string
    icon: string | null
    enabled: boolean
    isMaster: boolean
}

export type SocialAccountRecord = {
    id: string
    provider: string
    label: string
    providerEmail: string | null
    avatarUrl: string | null
    expiresAt: string | null
    connectedAt: string | null
}

export type ApiTokenRecord = {
    id: string
    name: string
    abilities: string[]
    lastUsedAt: string | null
    createdAt: string | null
}

export type ApiTokenManagerPayload = {
    abilities: FormOptionRecord[]
    canCreate: boolean
    canDelete: boolean
    tokens: ApiTokenRecord[]
}

export type OAuthClientRecord = {
    id: string
    name: string
    provider: string | null
    redirect: string
    secret: string | null
    confidential: boolean
    personalAccessClient: boolean
    passwordClient: boolean
    revoked: boolean
    scopes: string[]
}

export type RoleRecord = {
    id: string
    name: string
    displayLabel?: string | null
    group: string
    guardName: string
    permissions: string[]
    seededPermissions: string[]
    isSuperAdmin: boolean
    isProtected: boolean
    permissionsCount?: number
    usersCount?: number
    createdAt?: string | null
}

export type UserGroupRecord = {
    id: string
    name: string
    color: string
    usersCount?: number
    createdAt?: string | null
}

export type PermissionRecord = {
    id: string
    name: string
    group: string
    label: string
    guardName: string
}

export type AssignableUser = {
    id: string
    name: string
    email: string
}

export type UserRecord = {
    id: string
    firstName: string
    lastName: string
    name: string
    email: string
    locale: string | null
    status: 'active' | 'inactive' | 'blocked'
    avatarUrl: string | null
    presenceLastSeenAt: number | null
    presenceStatus: 'online' | 'away' | 'offline' | null
    roles: string[]
    userGroups: UserGroupRecord[]
    twoFactorEnabled: boolean
    canDelete: boolean
    canForceDelete: boolean
    canUpdate: boolean
    createdAt: string | null
    emailVerifiedAt: string | null
    deletedAt: string | null
    invitationAcceptedAt: string | null
    invitationExpiresAt: string | null
    invitationSentAt: string | null
    invitationStatus: 'accepted' | 'expired' | 'none' | 'pending'
    requiresPasswordSetup: boolean
}

export type UserCapabilities = {
    supportsLocale: boolean
    supportsMedia: boolean
    supportsRoles: boolean
    supportsStatus: boolean
    supportsSoftDeletes: boolean
}

export type UserSessionRecord = {
    id: string
    ip_address: string | null
    user_agent: string | null
    last_active: number
    is_current: boolean
}

export type SettingFieldRecord = {
    key: string
    label: string
    help: string | null
    type: string
    value: unknown
    isPublic: boolean
    isLocalized: boolean
    options: FormOptionRecord[]
}

export type SettingGroupRecord = {
    key: string
    label: string
    description: string
    fields: SettingFieldRecord[]
}

export type ActivityLogRecord = {
    id: string
    event: string | null
    description: string | null
    logName: string | null
    subjectType: string | null
    subjectId: string | null
    subjectLabel: string | null
    systemCauser: boolean
    causerId: string | null
    causerAvatarUrl: string | null
    causerName: string | null
    properties: Record<string, unknown>
    changes: Record<string, unknown>
    createdAt: string | null
}

export type AuthenticationLogRecord = {
    authMethod: string
    authenticationResult:
        | 'expired'
        | 'failed'
        | 'logout'
        | 'revoked'
        | 'successful'
    browser: string | null
    deviceName: string | null
    deviceType: string | null
    guard: string | null
    id: string
    ipAddress: string | null
    lastActiveAt: string | null
    login: string | null
    loginAt: string | null
    loginSuccessful: boolean
    logoutAt: string | null
    logoutReason: string | null
    platform: string | null
    properties: Record<string, unknown>
    socialProvider: string | null
    userAvatarUrl: string | null
    userEmail: string | null
    userAgent: string | null
    userId: string | null
    userName: string | null
}

export type LogEntryRecord = {
    context: Record<string, unknown> | null
    env: string
    isRaw: boolean
    level: string
    message: string
    stack: string | null
    timestamp: string | null
}

export type LogFileRecord = {
    channelType: 'daily' | 'single' | 'other'
    isActive: boolean
    modifiedAt: string
    name: string
    path: string
    sizeBytes: number
}

export type DeveloperRouteRecord = {
    action: string
    domain: string | null
    id: string
    method: string
    methods: string[]
    middleware: string[]
    name: string | null
    uri: string
}

export type DeveloperRouteTabPayload = {
    filters: {
        method: string | null
        search: string
    }
    options: {
        methods: Array<{ label: string; value: string }>
    }
    routes: {
        currentPage: number
        data: DeveloperRouteRecord[]
        lastPage: number
        perPage: number
        total: number
    }
}

export type FileRecord = {
    id: string
    folderId: string | null
    name: string
    collection: string
    mimeType: string | null
    size: number
    extension: string | null
    disk: string | null
    url: string | null
    previewUrl: string | null
    downloadUrl: string | null
    meta: Record<string, unknown>
    createdAt: string | null
}

export type FormRecord = {
    id: string
    name: string
    slug: string
    status: string
    schema: FormSchema
    settings: Record<string, unknown> | null
    createdBy: string | null
    version: number
    publicUrl: string
}

export type FormSubmissionRecord = {
    id: string
    formId: string
    data: Record<string, unknown>
    submittedBy: string | null
    ipAddress: string | null
    userAgent: string | null
    locale: string | null
    submittedAt: string | null
}

export type {
    DataTableAction,
    DataTableActionMethod,
    DataTableActionTarget,
    DataTableColumn,
    DataTableFilter,
    DataTablePagination,
    DataTableRow,
    DataTableSchema,
    DataTableState,
} from '@core-panel/components/TableBuilder/types'

export type {
    TabBuilderTab,
    TabsSchema,
} from '@core-panel/components/TabBuilder/types'

export type {
    FormCondition,
    FormConditionClause,
    FormErrors,
    FormModel,
    FormOptionRecord,
    FormOptionTranslationMap,
    FormSchema,
    FormSchemaField,
    FormTranslatedMap,
    WayfinderAction,
} from '@core-panel/components/FormBuilder/types'
