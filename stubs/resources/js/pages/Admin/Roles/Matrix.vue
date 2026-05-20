<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import roleRoutes from '@/routes/core-panel/roles'
import users from '@/routes/core-panel/users'
import type { PermissionRecord, RoleRecord } from '@/types/core-panel'

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

const props = defineProps<{
    permissions: PermissionRecord[]
    permissionGroups: Record<string, string>
    roles: RoleRecord[]
}>()

const page = usePage()

function requestedRoleId(): string {
    const query = page.url.split('?', 2)[1] ?? ''
    const params = new URLSearchParams(query)

    return params.get('role') ?? ''
}

const selectedRoleId = ref(
    requestedRoleId() !== '' ? requestedRoleId() : (props.roles[0]?.id ?? ''),
)

const form = useForm({
    guard_name: props.roles[0]?.guardName ?? 'web',
    name: props.roles[0]?.name ?? '',
    permissions: (props.roles[0]?.permissions ?? []) as string[],
})

const permissionOptions = computed(() =>
    props.roles.map((role) => ({
        label: role.displayLabel ?? role.name,
        value: role.id,
    })),
)

const selectedRole = computed(
    () => props.roles.find((role) => role.id === selectedRoleId.value) ?? null,
)

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

watch(
    () => props.roles,
    (roles) => {
        if (
            selectedRoleId.value !== '' &&
            roles.some((role) => role.id === selectedRoleId.value)
        ) {
            return
        }

        selectedRoleId.value = roles[0]?.id ?? ''
    },
    { immediate: true },
)

watch(
    selectedRole,
    (role) => {
        if (role === null) {
            return
        }

        form.name = role.name
        form.guard_name = role.guardName
        form.permissions = [...role.permissions]
    },
    { immediate: true },
)

function goBackToRolesOverview(): void {
    router.visit(`${users.index.url()}?tab=roles`)
}

function isRolePermissionSelected(permissionName: string): boolean {
    return form.permissions.includes(permissionName)
}

function toggleRolePermission(permissionName: string): void {
    if (isRolePermissionSelected(permissionName)) {
        form.permissions = form.permissions.filter(
            (permission) => permission !== permissionName,
        )

        return
    }

    form.permissions = [...form.permissions, permissionName]
}

function permissionNameFor(
    resource: MatrixResource,
    ability: string,
): string | null {
    return resource.permissionsByAbility[ability]?.name ?? null
}

function toggleResourcePermissions(resource: MatrixResource): void {
    const resourcePermissions = Object.values(resource.permissionsByAbility)
        .filter(
            (permission): permission is PermissionRecord => permission !== null,
        )
        .map((permission) => permission.name)

    const allSelected = resourcePermissions.every(isRolePermissionSelected)

    if (allSelected) {
        form.permissions = form.permissions.filter(
            (permission) => !resourcePermissions.includes(permission),
        )

        return
    }

    form.permissions = [
        ...new Set([...form.permissions, ...resourcePermissions]),
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
        form.permissions = form.permissions.filter(
            (permission) => !groupPermissions.includes(permission),
        )

        return
    }

    form.permissions = [...new Set([...form.permissions, ...groupPermissions])]
}

function toggleAbilityColumn(ability: string): void {
    const relevantPermissions = permissionMatrix.value.flatMap((group) =>
        group.resources
            .map((resource) => permissionNameFor(resource, ability))
            .filter((permission): permission is string => permission !== null),
    )
    const allSelected = relevantPermissions.every(isRolePermissionSelected)

    if (allSelected) {
        form.permissions = form.permissions.filter(
            (permission) => !relevantPermissions.includes(permission),
        )

        return
    }

    form.permissions = [
        ...new Set([...form.permissions, ...relevantPermissions]),
    ]
}

function toggleAllPermissions(): void {
    const allSelected = allPermissionsFlat.value.every(isRolePermissionSelected)

    if (allSelected) {
        form.permissions = form.permissions.filter(
            (permission) => !allPermissionsFlat.value.includes(permission),
        )

        return
    }

    form.permissions = [
        ...new Set([...form.permissions, ...allPermissionsFlat.value]),
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

function canSave(): boolean {
    return selectedRole.value !== null && form.name.trim() !== ''
}

function saveRoleMatrix(): void {
    if (selectedRole.value === null) {
        return
    }

    form.put(roleRoutes.update.url(selectedRole.value.id), {
        onSuccess: () => router.reload({ only: ['roles', 'permissions'] }),
    })
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
        switch: trans('common.ui.status'),
        update: trans('common.ui.edit'),
        upload: trans('common.ui.upload'),
        view: trans('common.ui.view'),
    }

    return labels[ability] ?? headline(ability)
}
</script>

<template>
    <AppLayout
        :title="$t('page-roles.matrix')"
        :subtitle="$t('page-roles.matrix_description')"
    >
        <template #page-actions>
            <div class="cp-role-matrix-page__header-actions">
                <Button
                    class="gap-2"
                    outlined
                    severity="secondary"
                    @click="goBackToRolesOverview"
                >
                    <AppIcon name="arrow-left" />
                    <span>{{ $t('navigation.roles') }}</span>
                </Button>

                <Select
                    v-model="selectedRoleId"
                    :options="permissionOptions"
                    class="cp-role-matrix-page__role-select"
                    option-label="label"
                    option-value="value"
                    placeholder=""
                    fluid
                />
            </div>
        </template>

        <div class="cp-role-matrix-page">
            <div class="cp-role-matrix-table-wrap">
                <table class="cp-role-matrix-table">
                    <thead>
                        <tr>
                            <th>{{ $t('common.ui.name') }}</th>
                            <th class="cp-role-matrix-table__toggle">
                                <Checkbox
                                    :binary="true"
                                    :indeterminate="
                                        form.permissions.length > 0 &&
                                        form.permissions.length <
                                            allPermissionsFlat.length
                                    "
                                    :model-value="
                                        allPermissionsFlat.length > 0 &&
                                        form.permissions.length ===
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

            <div class="cp-role-matrix-page__actions">
                <Button
                    :disabled="!canSave() || form.processing"
                    :loading="form.processing"
                    class="gap-2"
                    @click="saveRoleMatrix"
                >
                    <AppIcon name="save" />
                    <span>{{ $t('page-roles.roles_save') }}</span>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
