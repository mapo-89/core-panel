<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

import { useConfirm } from 'primevue/useconfirm'
import { useDialog } from 'primevue/usedialog'

import AppIcon from '@core-panel/components/AppIcon.vue'
import RoleCreateDialog from '@core-panel/pages/Admin/Roles/components/RoleCreateDialog.vue'
import { useCan } from '@core-panel/composables/useCan'
import permissionRoutes from '@/routes/core-panel/permissions'
import roleRoutes from '@/routes/core-panel/roles'
import userRoleRoutes from '@/routes/core-panel/users/roles'
import type {
    AssignableUser,
    PermissionRecord,
    RoleRecord,
} from '@core-panel/types/core-panel'

type ManagedRoleRecord = {
    name: string
    group: string
    label: string
    permissions: string[]
    protected: boolean
}

type MatrixResource = {
    key: string
    label: string
    permissionsByAbility: Record<string, PermissionRecord | null>
}

type MatrixGroup = {
    key: string
    label: string
    abilities: string[]
    resources: MatrixResource[]
}

const props = withDefaults(
    defineProps<{
        defaultRoles: ManagedRoleRecord[]
        permissions: PermissionRecord[]
        permissionDefaults: string[]
        permissionGroups: Record<string, string>
        roles: RoleRecord[]
        users: AssignableUser[]
        variant?: 'full' | 'tab'
    }>(),
    {
        variant: 'full',
    },
)

const confirm = useConfirm()
const dialog = useDialog()
const { can, hasRole } = useCan()

const roleDialogVisible = ref(false)
const permissionDialogVisible = ref(false)
const editingRole = ref<RoleRecord | null>(null)
const editingPermission = ref<PermissionRecord | null>(null)

const roleForm = useForm({
    name: '',
    guard_name: 'web',
    permissions: [] as string[],
})

const permissionForm = useForm({
    name: '',
    guard_name: 'web',
})

const assignmentForm = useForm({
    user_id: props.users[0]?.id ?? '',
    roles: [] as string[],
})

const resyncForm = useForm({
    fresh: false,
})

const roleStats = computed(() => ({
    managedRoles: props.defaultRoles.length,
    permissions: props.permissions.length,
    roles: props.roles.length,
}))

const groupedPermissions = computed(() => {
    return props.permissions.reduce<Record<string, PermissionRecord[]>>(
        (groups, permission) => {
            groups[permission.group] ??= []
            groups[permission.group].push(permission)

            return groups
        },
        {},
    )
})

const permissionMatrix = computed<MatrixGroup[]>(() => {
    const matrix = new Map<
        string,
        {
            key: string
            label: string
            abilities: Set<string>
            resources: Map<
                string,
                {
                    key: string
                    label: string
                    permissionsByAbility: Record<
                        string,
                        PermissionRecord | null
                    >
                }
            >
        }
    >()

    props.permissions.forEach((permission) => {
        const { ability, resource } = splitPermissionName(permission.name)
        const [resourceLabel, abilityLabel] = splitPermissionLabel(
            permission.label,
            resource,
            ability,
        )

        const group = matrix.get(permission.group) ?? {
            key: permission.group,
            label: props.permissionGroups[permission.group] ?? permission.group,
            abilities: new Set<string>(),
            resources: new Map(),
        }

        group.abilities.add(ability)

        const resourceEntry = group.resources.get(resource) ?? {
            key: resource,
            label: resourceLabel,
            permissionsByAbility: {},
        }

        resourceEntry.permissionsByAbility[ability] = {
            ...permission,
            label: abilityLabel,
        }

        group.resources.set(resource, resourceEntry)
        matrix.set(permission.group, group)
    })

    return [...matrix.values()].map((group) => ({
        key: group.key,
        label: group.label,
        abilities: [...group.abilities].sort(compareAbilities),
        resources: [...group.resources.values()].sort((left, right) =>
            left.label.localeCompare(right.label),
        ),
    }))
})

const wrapperClass = computed(() =>
    props.variant === 'tab'
        ? 'cp-access-page cp-access-page--tab'
        : 'cp-access-page',
)

const isTabVariant = computed(() => props.variant === 'tab')
const canResyncManagedAccess = computed(
    () => !isTabVariant.value && hasRole('super-admin') && can('roles.update'),
)

function openCreateRoleDialog(): void {
    dialog.open(RoleCreateDialog, {
        props: {
            header: trans('page-roles.roles_create'),
            modal: true,
            style: {
                width: 'min(28rem, 92vw)',
            },
        },
    })
}

function openEditRoleDialog(role: RoleRecord): void {
    const matrixUrl = roleRoutes.matrix.url()
    const glue = matrixUrl.includes('?') ? '&' : '?'

    router.visit(`${matrixUrl}${glue}role=${encodeURIComponent(role.id)}`)
}

function saveRole(): void {
    if (editingRole.value === null) {
        roleForm.post(roleRoutes.store.url(), {
            onSuccess: () => {
                roleDialogVisible.value = false
            },
        })

        return
    }

    roleForm.put(roleRoutes.update.url(editingRole.value.id), {
        onSuccess: () => {
            roleForm.post(
                roleRoutes.permissions.sync.url(editingRole.value!.id),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        roleDialogVisible.value = false
                    },
                },
            )
        },
    })
}

function confirmDeleteRole(role: RoleRecord): void {
    confirm.require({
        message: trans('page-roles.delete_role_message', {
            name: role.name,
        }),
        header: trans('page-roles.delete_role_header'),
        acceptLabel: trans('common.ui.delete'),
        rejectLabel: trans('common.ui.cancel'),
        accept: () => {
            roleForm.delete(roleRoutes.destroy.url(role.id), {
                preserveScroll: true,
            })
        },
    })
}

function openCreatePermissionDialog(): void {
    editingPermission.value = null
    permissionForm.reset()
    permissionDialogVisible.value = true
}

function openEditPermissionDialog(permission: PermissionRecord): void {
    editingPermission.value = permission
    permissionForm.name = permission.name
    permissionForm.guard_name = permission.guardName
    permissionDialogVisible.value = true
}

function savePermission(): void {
    if (editingPermission.value === null) {
        permissionForm.post(permissionRoutes.store.url(), {
            onSuccess: () => {
                permissionDialogVisible.value = false
            },
        })

        return
    }

    permissionForm.put(
        permissionRoutes.update.url(editingPermission.value.id),
        {
            onSuccess: () => {
                permissionDialogVisible.value = false
            },
        },
    )
}

function confirmDeletePermission(permission: PermissionRecord): void {
    confirm.require({
        message: trans('page-roles.delete_permission_message', {
            name: permission.name,
        }),
        header: trans('page-roles.delete_permission_header'),
        acceptLabel: trans('common.ui.delete'),
        rejectLabel: trans('common.ui.cancel'),
        accept: () => {
            permissionForm.delete(permissionRoutes.destroy.url(permission.id), {
                preserveScroll: true,
            })
        },
    })
}

function assignRoles(): void {
    if (assignmentForm.user_id === '') {
        return
    }

    assignmentForm.post(userRoleRoutes.assign.url(assignmentForm.user_id), {})
}

function resyncManagedAccess(): void {
    resyncForm.post(roleRoutes.resync.url(), {
        preserveScroll: true,
    })
}

function isRolePermissionSelected(permissionName: string): boolean {
    return roleForm.permissions.includes(permissionName)
}

function toggleRolePermission(permissionName: string): void {
    if (isRolePermissionSelected(permissionName)) {
        roleForm.permissions = roleForm.permissions.filter(
            (permission) => permission !== permissionName,
        )

        return
    }

    roleForm.permissions = [...roleForm.permissions, permissionName]
}

function toggleResourcePermissions(resource: MatrixResource): void {
    const resourcePermissions = Object.values(resource.permissionsByAbility)
        .filter(
            (permission): permission is PermissionRecord => permission !== null,
        )
        .map((permission) => permission.name)

    const allSelected = resourcePermissions.every(isRolePermissionSelected)

    if (allSelected) {
        roleForm.permissions = roleForm.permissions.filter(
            (permission) => !resourcePermissions.includes(permission),
        )

        return
    }

    roleForm.permissions = [
        ...new Set([...roleForm.permissions, ...resourcePermissions]),
    ]
}

const allAbilities = computed(() => {
    const abilities = new Set<string>()

    permissionMatrix.value.forEach((group) => {
        group.abilities.forEach((ability) => abilities.add(ability))
    })

    return [...abilities].sort(compareAbilities)
})

const allPermissionsFlat = computed(() =>
    permissionMatrix.value.flatMap((group) =>
        group.resources.flatMap((resource) =>
            Object.values(resource.permissionsByAbility)
                .filter(
                    (permission): permission is PermissionRecord =>
                        permission !== null,
                )
                .map((permission) => permission.name),
        ),
    ),
)

function permissionNameFor(
    resource: MatrixResource,
    ability: string,
): string | null {
    return resource.permissionsByAbility[ability]?.name ?? null
}

function toggleAbilityColumn(ability: string): void {
    const relevantPermissions = permissionMatrix.value.flatMap((group) =>
        group.resources
            .map((resource) => permissionNameFor(resource, ability))
            .filter((permission): permission is string => permission !== null),
    )

    const allSelected = relevantPermissions.every(isRolePermissionSelected)

    if (allSelected) {
        roleForm.permissions = roleForm.permissions.filter(
            (permission) => !relevantPermissions.includes(permission),
        )

        return
    }

    roleForm.permissions = [
        ...new Set([...roleForm.permissions, ...relevantPermissions]),
    ]
}

function isAbilityColumnAllChecked(ability: string): boolean {
    const relevantPermissions = permissionMatrix.value.flatMap((group) =>
        group.resources
            .map((resource) => permissionNameFor(resource, ability))
            .filter((permission): permission is string => permission !== null),
    )

    return (
        relevantPermissions.length > 0 &&
        relevantPermissions.every(isRolePermissionSelected)
    )
}

function isAbilityColumnPartiallyChecked(ability: string): boolean {
    const relevantPermissions = permissionMatrix.value.flatMap((group) =>
        group.resources
            .map((resource) => permissionNameFor(resource, ability))
            .filter((permission): permission is string => permission !== null),
    )
    const selectedCount = relevantPermissions.filter(
        isRolePermissionSelected,
    ).length

    return selectedCount > 0 && selectedCount < relevantPermissions.length
}

function toggleAllPermissions(): void {
    const allSelected = allPermissionsFlat.value.every(isRolePermissionSelected)

    if (allSelected) {
        roleForm.permissions = roleForm.permissions.filter(
            (permission) => !allPermissionsFlat.value.includes(permission),
        )

        return
    }

    roleForm.permissions = [
        ...new Set([...roleForm.permissions, ...allPermissionsFlat.value]),
    ]
}

function toggleGroupPermissions(group: MatrixGroup): void {
    const groupPermissions = group.resources.flatMap((resource) =>
        Object.values(resource.permissionsByAbility)
            .filter(
                (permission): permission is PermissionRecord =>
                    permission !== null,
            )
            .map((permission) => permission.name),
    )
    const allSelected = groupPermissions.every(isRolePermissionSelected)

    if (allSelected) {
        roleForm.permissions = roleForm.permissions.filter(
            (permission) => !groupPermissions.includes(permission),
        )

        return
    }

    roleForm.permissions = [
        ...new Set([...roleForm.permissions, ...groupPermissions]),
    ]
}

function isGroupAllChecked(group: MatrixGroup): boolean {
    const groupPermissions = group.resources.flatMap((resource) =>
        Object.values(resource.permissionsByAbility)
            .filter(
                (permission): permission is PermissionRecord =>
                    permission !== null,
            )
            .map((permission) => permission.name),
    )

    return (
        groupPermissions.length > 0 &&
        groupPermissions.every(isRolePermissionSelected)
    )
}

function isGroupPartiallyChecked(group: MatrixGroup): boolean {
    const groupPermissions = group.resources.flatMap((resource) =>
        Object.values(resource.permissionsByAbility)
            .filter(
                (permission): permission is PermissionRecord =>
                    permission !== null,
            )
            .map((permission) => permission.name),
    )
    const selectedCount = groupPermissions.filter(
        isRolePermissionSelected,
    ).length

    return selectedCount > 0 && selectedCount < groupPermissions.length
}

function isResourceAllChecked(resource: MatrixResource): boolean {
    const resourcePermissions = Object.values(resource.permissionsByAbility)
        .filter(
            (permission): permission is PermissionRecord => permission !== null,
        )
        .map((permission) => permission.name)

    return (
        resourcePermissions.length > 0 &&
        resourcePermissions.every(isRolePermissionSelected)
    )
}

function isResourcePartiallyChecked(resource: MatrixResource): boolean {
    const resourcePermissions = Object.values(resource.permissionsByAbility)
        .filter(
            (permission): permission is PermissionRecord => permission !== null,
        )
        .map((permission) => permission.name)
    const selectedCount = resourcePermissions.filter(
        isRolePermissionSelected,
    ).length

    return selectedCount > 0 && selectedCount < resourcePermissions.length
}

function resourcePermissionCount(role: RoleRecord): number {
    return role.permissions.length
}

function splitPermissionName(name: string): {
    ability: string
    resource: string
} {
    const segments = name.split('.')
    const ability = segments.pop() ?? name

    return {
        ability,
        resource: segments.join('.'),
    }
}

function splitPermissionLabel(
    label: string,
    resource: string,
    ability: string,
): [string, string] {
    const [resourceLabel, abilityLabel] = label.split(' - ')

    return [
        resourceLabel ?? headline(resource),
        abilityLabel ?? headline(ability),
    ]
}

function compareAbilities(left: string, right: string): number {
    const priority = ['create', 'view', 'update', 'delete', 'switch', 'upload']
    const leftIndex = priority.indexOf(left)
    const rightIndex = priority.indexOf(right)

    if (leftIndex === -1 && rightIndex === -1) {
        return left.localeCompare(right)
    }

    if (leftIndex === -1) {
        return 1
    }

    if (rightIndex === -1) {
        return -1
    }

    return leftIndex - rightIndex
}

function headline(value: string): string {
    return value
        .replace(/[._-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase())
}

function abilityHeading(ability: string): string {
    const labels: Record<string, string> = {
        create: trans('common.ui.create'),
        delete: trans('common.ui.delete'),
        update: trans('common.ui.edit'),
        upload: trans('common.ui.upload'),
        view: trans('common.ui.view'),
    }

    return labels[ability] ?? headline(ability)
}
</script>

<template>
    <div :class="wrapperClass">
        <section v-if="props.variant === 'full'" class="cp-access-stats">
            <article class="cp-access-stat">
                <div class="cp-access-stat__icon">
                    <AppIcon name="shield" />
                </div>
                <div class="cp-access-stat__body">
                    <span class="cp-access-stat__label">{{
                        $t('common.ui.roles')
                    }}</span>
                    <strong class="cp-access-stat__value">{{
                        roleStats.roles
                    }}</strong>
                </div>
            </article>
            <article class="cp-access-stat">
                <div class="cp-access-stat__icon cp-access-stat__icon--primary">
                    <AppIcon name="refresh" />
                </div>
                <div class="cp-access-stat__body">
                    <span class="cp-access-stat__label">{{
                        $t('page-roles.managed_access')
                    }}</span>
                    <strong class="cp-access-stat__value">{{
                        roleStats.managedRoles
                    }}</strong>
                </div>
            </article>
            <article class="cp-access-stat">
                <div class="cp-access-stat__icon cp-access-stat__icon--success">
                    <AppIcon name="key" />
                </div>
                <div class="cp-access-stat__body">
                    <span class="cp-access-stat__label">{{
                        $t('common.ui.permissions')
                    }}</span>
                    <strong class="cp-access-stat__value">{{
                        roleStats.permissions
                    }}</strong>
                </div>
            </article>
        </section>

        <section class="cp-access-grid">
            <div class="cp-access-main">
                <section
                    class="cp-card cp-access-panel cp-access-panel--table cp-section"
                >
                    <div class="cp-access-panel__header cp-section__header">
                        <div class="cp-access-panel__copy">
                            <h2 class="cp-access-panel__title">
                                {{ $t('common.ui.roles') }}
                            </h2>
                            <p class="cp-access-panel__description">
                                {{
                                    $t('page-roles.managed_access_description')
                                }}
                            </p>
                        </div>
                        <div class="cp-access-panel__actions">
                            <Button
                                v-if="canResyncManagedAccess"
                                :disabled="resyncForm.processing"
                                :loading="resyncForm.processing"
                                class="gap-2"
                                severity="secondary"
                                outlined
                                @click="resyncManagedAccess"
                            >
                                <AppIcon name="refresh" />
                                <span>{{ $t('page-roles.resync') }}</span>
                            </Button>
                            <Button
                                v-if="!isTabVariant"
                                class="gap-2"
                                severity="secondary"
                                outlined
                                @click="openCreatePermissionDialog"
                            >
                                <AppIcon name="key" />
                                <span>{{
                                    $t('page-roles.new_permission')
                                }}</span>
                            </Button>
                            <Button class="gap-2" @click="openCreateRoleDialog">
                                <AppIcon name="plus" />
                                <span>{{ $t('page-roles.new_role') }}</span>
                            </Button>
                        </div>
                    </div>

                    <div class="cp-section__body">
                        <DataTable
                            :value="props.roles"
                            paginator
                            :rows="props.variant === 'full' ? 10 : 8"
                            responsive-layout="scroll"
                            table-style="min-width: 100%"
                        >
                            <Column field="name" :header="$t('common.ui.role')">
                                <template #body="{ data }">
                                    <div class="cp-role-cell">
                                        <div class="cp-role-cell__copy">
                                            <strong class="cp-role-cell__name">
                                                {{ data.name }}
                                            </strong>
                                            <span class="cp-role-cell__meta">
                                                {{
                                                    props.permissionGroups[
                                                        data.group
                                                    ] ?? data.group
                                                }}
                                            </span>
                                        </div>
                                        <Tag
                                            v-if="data.isProtected"
                                            severity="secondary"
                                            :value="
                                                $t('page-roles.managed_role')
                                            "
                                        />
                                    </div>
                                </template>
                            </Column>
                            <Column
                                field="guardName"
                                :header="$t('common.ui.guard')"
                            />
                            <Column :header="$t('common.ui.permissions')">
                                <template #body="{ data }">
                                    <div class="cp-role-permissions">
                                        <span
                                            class="cp-role-permissions__count"
                                        >
                                            {{ resourcePermissionCount(data) }}
                                        </span>
                                        <div class="cp-role-permissions__list">
                                            <Tag
                                                v-for="permission in data.permissions.slice(
                                                    0,
                                                    3,
                                                )"
                                                :key="permission"
                                                :value="permission"
                                                severity="secondary"
                                            />
                                            <Badge
                                                v-if="
                                                    data.permissions.length > 3
                                                "
                                                :value="`+${data.permissions.length - 3}`"
                                            />
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column :header="$t('common.ui.actions')">
                                <template #body="{ data }">
                                    <div class="cp-role-actions">
                                        <Button
                                            :aria-label="$t('common.ui.edit')"
                                            severity="secondary"
                                            text
                                            @click="openEditRoleDialog(data)"
                                        >
                                            <AppIcon name="pencil" />
                                        </Button>
                                        <Button
                                            :aria-label="$t('common.ui.delete')"
                                            severity="danger"
                                            text
                                            :disabled="data.isProtected"
                                            @click="confirmDeleteRole(data)"
                                        >
                                            <AppIcon name="trash" />
                                        </Button>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </section>

                <section
                    v-if="!isTabVariant"
                    class="cp-card cp-access-panel cp-access-panel--list cp-section"
                >
                    <div class="cp-access-panel__header cp-section__header">
                        <div class="cp-access-panel__copy">
                            <h2 class="cp-access-panel__title">
                                {{ $t('common.ui.permissions') }}
                            </h2>
                            <p class="cp-access-panel__description">
                                {{ $t('page-roles.permissions_description') }}
                            </p>
                        </div>
                    </div>

                    <div class="cp-section__body">
                        <div class="cp-permission-groups">
                            <article
                                v-for="group in permissionMatrix"
                                :key="group.key"
                                class="cp-permission-group"
                            >
                                <header class="cp-permission-group__header">
                                    <div>
                                        <h3 class="cp-permission-group__title">
                                            {{ group.label }}
                                        </h3>
                                        <p
                                            class="cp-permission-group__subtitle"
                                        >
                                            {{
                                                (
                                                    groupedPermissions[
                                                        group.key
                                                    ] ?? []
                                                ).length
                                            }}
                                            {{ $t('common.ui.permissions') }}
                                        </p>
                                    </div>
                                </header>

                                <div class="cp-permission-group__resources">
                                    <button
                                        v-for="permission in groupedPermissions[
                                            group.key
                                        ] ?? []"
                                        :key="permission.id"
                                        type="button"
                                        class="cp-permission-row"
                                        @click="
                                            openEditPermissionDialog(permission)
                                        "
                                    >
                                        <span class="cp-permission-row__label">
                                            {{ permission.label }}
                                        </span>
                                        <span class="cp-permission-row__name">
                                            {{ permission.name }}
                                        </span>
                                    </button>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </div>

            <aside v-if="!isTabVariant" class="cp-access-sidebar">
                <section
                    class="cp-card cp-access-panel cp-access-panel--sidebar cp-section"
                >
                    <h2 class="cp-access-panel__title">
                        {{ $t('page-roles.assign') }}
                    </h2>
                    <p class="cp-access-panel__description">
                        {{ $t('page-roles.assign_description') }}
                    </p>

                    <div class="cp-access-form cp-section__body">
                        <div class="cp-access-field">
                            <label
                                class="cp-access-field__label"
                                for="assign-user"
                            >
                                {{ $t('common.ui.user') }}
                            </label>
                            <Select
                                id="assign-user"
                                v-model="assignmentForm.user_id"
                                :options="props.users"
                                fluid
                                option-label="name"
                                option-value="id"
                            >
                                <template #option="{ option }">
                                    <div class="cp-option-user">
                                        <span>{{ option.name }}</span>
                                        <span>{{ option.email }}</span>
                                    </div>
                                </template>
                            </Select>
                        </div>

                        <div class="cp-access-field">
                            <label
                                class="cp-access-field__label"
                                for="assign-roles"
                            >
                                {{ $t('common.ui.roles') }}
                            </label>
                            <MultiSelect
                                id="assign-roles"
                                v-model="assignmentForm.roles"
                                :options="props.roles"
                                display="chip"
                                fluid
                                option-label="name"
                                option-value="name"
                            />
                        </div>

                        <div class="cp-access-actions">
                            <Button
                                :disabled="assignmentForm.processing"
                                :loading="assignmentForm.processing"
                                :label="$t('page-roles.assign')"
                                @click="assignRoles"
                            />
                        </div>
                    </div>
                </section>

                <section
                    class="cp-card cp-access-panel cp-access-panel--sidebar cp-section"
                >
                    <h2 class="cp-access-panel__title">
                        {{ $t('page-roles.managed_access') }}
                    </h2>
                    <p class="cp-access-panel__description">
                        {{ $t('page-roles.managed_access_description') }}
                    </p>

                    <div class="cp-managed-roles cp-section__body">
                        <article
                            v-for="role in props.defaultRoles"
                            :key="role.name"
                            class="cp-managed-role"
                        >
                            <div class="cp-managed-role__header">
                                <strong>{{ role.label }}</strong>
                                <Tag
                                    v-if="role.protected"
                                    severity="secondary"
                                    :value="$t('page-roles.managed_role')"
                                />
                            </div>
                            <p class="cp-managed-role__group">
                                {{
                                    props.permissionGroups[role.group] ??
                                    role.group
                                }}
                            </p>
                            <p class="cp-managed-role__count">
                                {{
                                    $t('page-roles.role_permission_count', {
                                        count: String(role.permissions.length),
                                    })
                                }}
                            </p>
                        </article>
                    </div>

                    <div class="cp-managed-defaults cp-section__body">
                        <div class="cp-access-panel__copy">
                            <h3
                                class="cp-access-panel__title cp-access-panel__title--small"
                            >
                                {{ $t('page-roles.default_permissions') }}
                            </h3>
                            <p class="cp-access-panel__description">
                                {{
                                    $t(
                                        'page-roles.default_permissions_description',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="cp-managed-defaults__tags">
                            <Tag
                                v-for="permission in props.permissionDefaults"
                                :key="permission"
                                :value="permission"
                                severity="secondary"
                            />
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <Dialog
            v-model:visible="roleDialogVisible"
            modal
            :header="
                editingRole
                    ? $t('page-roles.edit_role')
                    : $t('page-roles.roles_create')
            "
            :style="{ width: 'min(92vw, 78rem)' }"
        >
            <div class="cp-dialog-grid">
                <div class="cp-dialog-fields">
                    <div class="cp-access-field">
                        <label class="cp-access-field__label" for="role-name">{{
                            $t('common.ui.name')
                        }}</label>
                        <InputText
                            id="role-name"
                            v-model="roleForm.name"
                            fluid
                        />
                    </div>

                    <div class="cp-access-field">
                        <label
                            class="cp-access-field__label"
                            for="role-guard"
                            >{{ $t('common.ui.guard') }}</label
                        >
                        <InputText
                            id="role-guard"
                            v-model="roleForm.guard_name"
                            fluid
                        />
                    </div>
                </div>

                <div class="cp-access-panel__copy">
                    <h3
                        class="cp-access-panel__title cp-access-panel__title--small"
                    >
                        {{ $t('page-roles.matrix') }}
                    </h3>
                    <p class="cp-access-panel__description">
                        {{ $t('page-roles.matrix_description') }}
                    </p>
                </div>

                <div class="cp-role-matrix-table-wrap">
                    <table class="cp-role-matrix-table">
                        <thead>
                            <tr>
                                <th>{{ $t('common.ui.name') }}</th>
                                <th class="cp-role-matrix-table__toggle">
                                    <Checkbox
                                        :binary="true"
                                        :indeterminate="
                                            roleForm.permissions.length > 0 &&
                                            roleForm.permissions.length <
                                                allPermissionsFlat.length
                                        "
                                        :model-value="
                                            allPermissionsFlat.length > 0 &&
                                            roleForm.permissions.length ===
                                                allPermissionsFlat.length
                                        "
                                        @update:model-value="
                                            toggleAllPermissions()
                                        "
                                    />
                                </th>
                                <th
                                    v-for="ability in allAbilities"
                                    :key="ability"
                                >
                                    <div class="cp-role-matrix-table__ability">
                                        <span>{{
                                            abilityHeading(ability)
                                        }}</span>
                                        <Checkbox
                                            :binary="true"
                                            :indeterminate="
                                                isAbilityColumnPartiallyChecked(
                                                    ability,
                                                )
                                            "
                                            :model-value="
                                                isAbilityColumnAllChecked(
                                                    ability,
                                                )
                                            "
                                            @update:model-value="
                                                toggleAbilityColumn(ability)
                                            "
                                        />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="group in permissionMatrix"
                                :key="group.key"
                            >
                                <tr class="cp-role-matrix-table__group-row">
                                    <th>{{ group.label }}</th>
                                    <td class="cp-role-matrix-table__toggle">
                                        <Checkbox
                                            :binary="true"
                                            :indeterminate="
                                                isGroupPartiallyChecked(group)
                                            "
                                            :model-value="
                                                isGroupAllChecked(group)
                                            "
                                            @update:model-value="
                                                toggleGroupPermissions(group)
                                            "
                                        />
                                    </td>
                                    <td
                                        v-for="ability in allAbilities"
                                        :key="`${group.key}-${ability}`"
                                    />
                                </tr>

                                <tr
                                    v-for="resource in group.resources"
                                    :key="resource.key"
                                >
                                    <th
                                        class="cp-role-matrix-table__resource-cell"
                                    >
                                        {{ resource.label }}
                                    </th>
                                    <td class="cp-role-matrix-table__toggle">
                                        <Checkbox
                                            :binary="true"
                                            :indeterminate="
                                                isResourcePartiallyChecked(
                                                    resource,
                                                )
                                            "
                                            :model-value="
                                                isResourceAllChecked(resource)
                                            "
                                            @update:model-value="
                                                toggleResourcePermissions(
                                                    resource,
                                                )
                                            "
                                        />
                                    </td>
                                    <td
                                        v-for="ability in allAbilities"
                                        :key="`${resource.key}-${ability}`"
                                    >
                                        <Checkbox
                                            v-if="
                                                permissionNameFor(
                                                    resource,
                                                    ability,
                                                )
                                            "
                                            :binary="true"
                                            :input-id="`${resource.key}-${ability}`"
                                            :model-value="
                                                isRolePermissionSelected(
                                                    permissionNameFor(
                                                        resource,
                                                        ability,
                                                    )!,
                                                )
                                            "
                                            @update:model-value="
                                                toggleRolePermission(
                                                    permissionNameFor(
                                                        resource,
                                                        ability,
                                                    )!,
                                                )
                                            "
                                        />
                                        <span
                                            v-else
                                            class="cp-role-matrix-table__empty"
                                        >
                                            —
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="cp-dialog-actions">
                    <Button
                        :label="$t('common.ui.cancel')"
                        severity="secondary"
                        text
                        @click="roleDialogVisible = false"
                    />
                    <Button
                        :disabled="roleForm.processing"
                        :loading="roleForm.processing"
                        :label="$t('page-roles.roles_save')"
                        @click="saveRole"
                    />
                </div>
            </div>
        </Dialog>

        <Dialog
            v-model:visible="permissionDialogVisible"
            modal
            :header="
                editingPermission
                    ? $t('page-roles.edit_permission')
                    : $t('page-roles.permissions_create')
            "
            :style="{ width: 'min(92vw, 36rem)' }"
        >
            <div class="cp-dialog-grid cp-dialog-grid--narrow">
                <div class="cp-access-field">
                    <label
                        class="cp-access-field__label"
                        for="permission-name"
                        >{{ $t('common.ui.name') }}</label
                    >
                    <InputText
                        id="permission-name"
                        v-model="permissionForm.name"
                        fluid
                    />
                </div>

                <div class="cp-access-field">
                    <label
                        class="cp-access-field__label"
                        for="permission-guard"
                        >{{ $t('common.ui.guard') }}</label
                    >
                    <InputText
                        id="permission-guard"
                        v-model="permissionForm.guard_name"
                        fluid
                    />
                </div>

                <div class="cp-dialog-actions cp-dialog-actions--spread">
                    <Button
                        v-if="editingPermission"
                        :label="$t('common.ui.delete')"
                        severity="danger"
                        text
                        @click="confirmDeletePermission(editingPermission)"
                    />
                    <div class="cp-dialog-actions__group">
                        <Button
                            :label="$t('common.ui.cancel')"
                            severity="secondary"
                            text
                            @click="permissionDialogVisible = false"
                        />
                        <Button
                            :disabled="permissionForm.processing"
                            :loading="permissionForm.processing"
                            :label="$t('page-roles.permissions_save')"
                            @click="savePermission"
                        />
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>
