<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { inject } from 'vue'

import { trans } from 'laravel-vue-i18n'

import AppIcon from '@core-panel/components/AppIcon.vue'
import UserFormFields from '@core-panel/pages/Admin/Users/components/UserFormFields.vue'
import users from '@/routes/core-panel/users'
import type {
    RoleRecord,
    UserCapabilities,
    UserRecord,
} from '@core-panel/types/core-panel'

type DialogRef = {
    close: () => void
    data?: {
        canAssignRoles?: boolean
        capabilities: UserCapabilities
        onSaved?: () => void
        roleLabels: Record<string, string>
        roles: RoleRecord[]
        userGroupOptions: Array<{
            color: string
            label: string
            value: string
        }>
        user?: UserRecord
    }
}

const dialogRef = inject<{ value: DialogRef }>('dialogRef')

const capabilities: UserCapabilities = dialogRef?.value.data?.capabilities ?? {
    supportsLocale: false,
    supportsMedia: false,
    supportsRoles: false,
    supportsStatus: false,
    supportsSoftDeletes: false,
}
const canAssignRoles = dialogRef?.value.data?.canAssignRoles ?? false
const onSaved = dialogRef?.value.data?.onSaved
const roleLabels = dialogRef?.value.data?.roleLabels ?? {}
const roles = dialogRef?.value.data?.roles ?? []
const userGroupOptions = dialogRef?.value.data?.userGroupOptions ?? []
const user = dialogRef?.value.data?.user

const form = useForm({
    avatar: null as File | null,
    email: user?.email ?? '',
    first_name: user?.firstName ?? '',
    last_name: user?.lastName ?? '',
    password: '',
    password_confirmation: '',
    remove_avatar: false,
    role_name: user?.roles[0] ?? null,
    status: user?.status ?? 'active',
    user_group_ids: user?.userGroups.map((group) => group.id) ?? [],
})

function close(): void {
    dialogRef?.value.close()
}

function submit(): void {
    const options = {
        onSuccess: () => {
            onSaved?.()
            close()
        },
    }

    form.transform((data) => ({
        ...data,
        ...(canAssignRoles
            ? {
                  role_names: data.role_name ? [data.role_name] : [],
              }
            : {}),
    }))

    if (user) {
        form.put(users.update.url(user.id), options)

        return
    }

    form.post(users.store.url(), options)
}
</script>

<template>
    <form class="cp-user-form-dialog" @submit.prevent="submit">
        <UserFormFields
            :can-assign-roles="canAssignRoles"
            :capabilities="capabilities"
            :current-avatar-url="user?.avatarUrl ?? null"
            :form="form"
            :role-labels="roleLabels"
            :roles="roles"
            :show-password-fields="!!user"
            :user-group-options="userGroupOptions"
        />

        <div class="cp-user-form-dialog__actions">
            <Button severity="secondary" text type="button" @click="close">
                <AppIcon name="x" />
                <span>{{ $t('common.ui.cancel') }}</span>
            </Button>
            <Button :loading="form.processing" type="submit">
                <AppIcon name="save" />
                <span>
                    {{
                        user
                            ? $t('common.ui.save_changes')
                            : $t('page-users.invite')
                    }}
                </span>
            </Button>
        </div>

        <Message
            v-if="form.hasErrors"
            severity="error"
            size="small"
            variant="simple"
        >
            {{
                user
                    ? trans('page-users.edit_description')
                    : trans('page-users.create_description')
            }}
        </Message>
    </form>
</template>
