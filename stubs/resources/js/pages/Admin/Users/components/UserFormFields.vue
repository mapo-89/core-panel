<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { trans } from 'laravel-vue-i18n'
import { computed } from 'vue'

import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'
import {
    passwordMatchMeta,
    passwordMinLengthMeta,
} from '@core-panel/components/FormBuilder/passwordRequirements'
import type { FormSchema } from '@core-panel/components/FormBuilder/types'

import UserAvatarInput from '@/pages/Admin/Users/components/UserAvatarInput.vue'
import type { RoleRecord, UserCapabilities } from '@core-panel/types/core-panel'

type UserFormShape = {
    avatar: File | null
    email: string
    errors: Partial<
        Record<
            | 'avatar'
            | 'email'
            | 'first_name'
            | 'last_name'
            | 'password'
            | 'password_confirmation'
            | 'role_names'
            | 'status'
            | 'user_group_ids',
            string
        >
    >
    first_name: string
    last_name: string
    password: string
    password_confirmation: string
    remove_avatar: boolean
    role_name: string | null
    status: 'active' | 'inactive' | 'blocked'
    user_group_ids: string[]
}

const props = defineProps<{
    canAssignRoles?: boolean
    capabilities: UserCapabilities
    currentAvatarUrl?: string | null
    form: UserFormShape
    showPasswordFields?: boolean
    userGroupOptions: Array<{
        color: string
        label: string
        value: string
    }>
    roleLabels: Record<string, string>
    roles: RoleRecord[]
}>()

const roleOptions = computed(() => {
    return props.roles
        .map((role) => ({
            label: props.roleLabels[role.name] ?? role.name,
            value: role.name,
        }))
        .sort((left, right) => left.label.localeCompare(right.label))
})

const statusOptions = computed(() => [
    {
        label: trans('common.ui.active'),
        value: 'active',
    },
    {
        label: trans('common.ui.inactive'),
        value: 'inactive',
    },
    {
        label: trans('common.ui.blocked'),
        value: 'blocked',
    },
])

const formSchema = computed<FormSchema>(() => {
    const schema: FormSchema = [
        {
            label: trans('common.ui.first_name'),
            name: 'first_name',
            type: 'text',
        },
        {
            label: trans('common.ui.last_name'),
            name: 'last_name',
            type: 'text',
        },
        {
            columnSpan: 2,
            label: trans('common.auth.email'),
            name: 'email',
            type: 'email',
        },
    ]

    if (
        props.capabilities.supportsRoles &&
        props.canAssignRoles === true &&
        roleOptions.value.length > 0
    ) {
        schema.push({
            label: trans('common.ui.role'),
            name: 'role_name',
            options: roleOptions.value,
            placeholder: trans('common.ui.role_select'),
            type: 'select',
        })
    }

    if (props.capabilities.supportsStatus) {
        schema.push({
            label: trans('common.ui.status'),
            name: 'status',
            options: statusOptions.value,
            type: 'select',
        })
    }

    if (props.showPasswordFields === true) {
        schema.push(
            {
                label: trans('common.auth.password'),
                meta: passwordMinLengthMeta(8),
                name: 'password',
                type: 'password',
            },
            {
                label: trans('page-auth.confirm_password'),
                meta: passwordMatchMeta(),
                name: 'password_confirmation',
                type: 'password',
            },
        )
    }

    return schema
})

const initials = computed(() => {
    return [props.form.first_name, props.form.last_name]
        .filter((value) => value.trim() !== '')
        .map((value) => value.trim().charAt(0).toUpperCase())
        .join('')
        .slice(0, 2)
})

const selectedUserGroups = computed(() => {
    const selectedIds = new Set(props.form.user_group_ids)

    return props.userGroupOptions.filter((option) =>
        selectedIds.has(option.value),
    )
})

function updateFormFields(value: Record<string, unknown>): void {
    props.form.first_name = String(value.first_name ?? '')
    props.form.last_name = String(value.last_name ?? '')
    props.form.email = String(value.email ?? '')
    props.form.password = String(value.password ?? '')
    props.form.password_confirmation = String(value.password_confirmation ?? '')
    props.form.role_name =
        value.role_name === null || value.role_name === undefined
            ? null
            : String(value.role_name)
    props.form.status = (value.status ?? 'active') as
        | 'active'
        | 'inactive'
        | 'blocked'
    props.form.user_group_ids = Array.isArray(value.user_group_ids)
        ? value.user_group_ids.map((entry) => String(entry))
        : []
}
</script>

<template>
    <div
        class="grid gap-5 xl:grid-cols-[minmax(17rem,20rem)_minmax(0,1fr)] xl:items-start"
    >
        <div v-if="capabilities.supportsMedia" class="grid gap-4">
            <UserAvatarInput
                v-model="form.avatar"
                v-model:remove-avatar="form.remove_avatar"
                :current-avatar-url="currentAvatarUrl"
                :error="form.errors.avatar"
                :initials="initials"
            />
        </div>

        <div class="grid gap-4">
            <div class="grid gap-[0.35rem]">
                <h2
                    class="m-0 text-[1.05rem] font-semibold text-[var(--cp-text-primary)]"
                >
                    {{ $t('page-users.account') }}
                </h2>
                <p class="m-0 text-[0.92rem] text-[var(--cp-text-muted)]">
                    {{ $t('page-users.account_description') }}
                </p>
            </div>

            <FormRenderer
                :columns="2"
                :errors="form.errors"
                :model-value="form"
                :schema="formSchema"
                :wrap-in-form="false"
                @update:model-value="updateFormFields"
            />

            <div
                v-if="userGroupOptions.length > 0"
                class="grid gap-4 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-muted)] p-4"
            >
                <div class="grid gap-[0.35rem]">
                    <h3
                        class="m-0 text-sm font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ $t('page-users.access_description') }}
                    </h3>
                    <p class="m-0 text-sm text-[var(--cp-text-muted)]">
                        {{ $t('navigation.user_groups') }}
                    </p>
                </div>

                <div class="grid content-start gap-2 self-start">
                    <label
                        class="text-sm font-medium text-[var(--cp-text-primary)]"
                        for="user-group-ids"
                    >
                        {{ $t('navigation.user_groups') }}
                    </label>
                    <MultiSelect
                        id="user-group-ids"
                        v-model="form.user_group_ids"
                        :filter="true"
                        :max-selected-labels="1"
                        :options="userGroupOptions"
                        :placeholder="$t('page-user-groups.select_placeholder')"
                        class="w-full"
                        display="chip"
                        option-label="label"
                        option-value="value"
                        :selected-items-label="
                            $t('page-user-groups.selected_count')
                        "
                    >
                        <template #option="{ option }">
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-2.5 w-2.5 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            option.color || '#6366F1',
                                    }"
                                />
                                <span>{{ option.label }}</span>
                            </div>
                        </template>
                    </MultiSelect>
                    <div
                        v-if="selectedUserGroups.length > 0"
                        class="flex max-h-24 flex-wrap gap-2 overflow-y-auto pt-1"
                    >
                        <span
                            v-for="userGroup in selectedUserGroups"
                            :key="userGroup.value"
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium text-white"
                            :style="{
                                backgroundColor: userGroup.color || '#6366F1',
                            }"
                        >
                            {{ userGroup.label }}
                        </span>
                    </div>
                    <small
                        v-if="form.errors.user_group_ids"
                        class="text-sm text-red-600"
                    >
                        {{ form.errors.user_group_ids }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</template>
