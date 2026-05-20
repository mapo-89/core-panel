<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import UserFormFields from '@/pages/Admin/Users/components/UserFormFields.vue'
import users from '@/routes/core-panel/users'
import type { RoleRecord, UserCapabilities } from '@/types/core-panel'

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
}>()

const form = useForm({
    avatar: null as File | null,
    first_name: '',
    last_name: '',
    email: '',
    role_name: null as string | null,
    status: 'active' as 'active' | 'inactive' | 'blocked',
    user_group_ids: [] as string[],
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
    form.post(users.store.url())
}
</script>

<template>
    <AppLayout
        :title="$t('page-users.create_title')"
        :subtitle="$t('page-users.create_description')"
        :back-url="users.index.url()"
    >
        <Head :title="$t('page-users.create_title')" />

        <div class="grid w-full gap-6">
            <form class="cp-card grid gap-6" @submit.prevent="submit">
                <UserFormFields
                    :can-assign-roles="canAssignRoles"
                    :capabilities="capabilities"
                    :form="form"
                    :role-labels="roleLabels"
                    :roles="roles"
                    :user-group-options="userGroupOptions"
                />

                <div class="flex justify-end gap-2">
                    <Link :href="users.index.url()">
                        <Button severity="secondary" text>
                            <AppIcon name="x" />
                            <span>{{ $t('common.ui.cancel') }}</span>
                        </Button>
                    </Link>
                    <Button type="submit" :loading="form.processing">
                        <AppIcon name="save" />
                        <span>{{ $t('page-users.save') }}</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
