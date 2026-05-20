import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

type SharedAuthPayload = {
    permissions?: string[]
    role?: string | null
    roles?: string[]
}

function normalizeRoleName(role: string): string {
    return role.trim().toLowerCase().replace(/_/g, '-')
}

function wrapValues(values: string | string[]): string[] {
    return Array.isArray(values) ? values : [values]
}

export function hasRoleInAuth(
    auth: SharedAuthPayload | null | undefined,
    roles: string | string[],
): boolean {
    const availableRoles = [
        ...(auth?.roles ?? []),
        ...(auth?.role ? [auth.role] : []),
    ].map(normalizeRoleName)
    const expectedRoles = wrapValues(roles).map(normalizeRoleName)

    return expectedRoles.some((role) => availableRoles.includes(role))
}

export function canInAuth(
    auth: SharedAuthPayload | null | undefined,
    permission: string,
): boolean {
    if (permission.trim() === '') {
        return true
    }

    if (hasRoleInAuth(auth, 'super-admin')) {
        return true
    }

    return (auth?.permissions ?? []).includes(permission)
}

export function canAnyInAuth(
    auth: SharedAuthPayload | null | undefined,
    permissions: string[],
): boolean {
    if (permissions.length === 0) {
        return true
    }

    return permissions.some((permission) => canInAuth(auth, permission))
}

export function useCan() {
    const page = usePage<{
        auth?: SharedAuthPayload
    }>()

    const auth = computed(() => page.props.auth ?? {})

    return {
        can: (permission: string): boolean => canInAuth(auth.value, permission),
        canAny: (permissions: string[]): boolean =>
            canAnyInAuth(auth.value, permissions),
        hasRole: (roles: string | string[]): boolean =>
            hasRoleInAuth(auth.value, roles),
    }
}
