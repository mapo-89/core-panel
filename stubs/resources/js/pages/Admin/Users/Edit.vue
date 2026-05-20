<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import UserFormFields from '@/pages/Admin/Users/components/UserFormFields.vue'
import users from '@/routes/core-panel/users'
import type {
    RoleRecord,
    UserCapabilities,
    UserRecord,
} from '@/types/core-panel'

const props = defineProps<{
    canAssignRoles: boolean
    capabilities: UserCapabilities
    roleLabels: Record<string, string>
    roles: RoleRecord[]
    userGroupOptions: Array<{
        color: string
        label: string
        value: string
    }>
    user: UserRecord
}>()

const form = useForm({
    avatar: null as File | null,
    first_name: props.user.firstName,
    last_name: props.user.lastName,
    email: props.user.email,
    role_name: props.user.roles[0] ?? null,
    status: props.user.status,
    user_group_ids: props.user.userGroups.map((group) => group.id),
    password: '',
    password_confirmation: '',
    remove_avatar: false,
})

function submit(): void {
    form.transform((data) => ({
        ...data,
        ...(props.canAssignRoles
            ? {
                  role_names: data.role_name ? [data.role_name] : [],
              }
            : {}),
    }))
    form.put(users.update.url(props.user.id))
}
</script>

<template>
    <AppLayout
        :title="$t('page-users.edit_title')"
        :subtitle="$t('page-users.edit_description')"
        :back-url="users.show.url(user.id)"
    >
        <Head :title="$t('page-users.edit_title')" />

        <template #page-actions>
            <Link :href="users.show.url(user.id)">
                <Button
                    :label="$t('common.ui.details')"
                    severity="secondary"
                    outlined
                />
            </Link>
        </template>

        <div class="grid w-full gap-6">
            <form class="cp-card grid gap-6" @submit.prevent="submit">
                <UserFormFields
                    :can-assign-roles="canAssignRoles"
                    :capabilities="capabilities"
                    :current-avatar-url="user.avatarUrl"
                    :form="form"
                    :role-labels="roleLabels"
                    :roles="roles"
                    :user-group-options="userGroupOptions"
                />

                <div class="flex justify-end gap-2">
                    <Link :href="users.show.url(user.id)">
                        <Button severity="secondary" text>
                            <AppIcon name="x" />
                            <span>{{ $t('common.ui.cancel') }}</span>
                        </Button>
                    </Link>
                    <Button type="submit" :loading="form.processing">
                        <AppIcon name="save" />
                        <span>{{ $t('common.ui.save_changes') }}</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
