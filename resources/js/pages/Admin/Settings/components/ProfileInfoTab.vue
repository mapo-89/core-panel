<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { computed } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'
import type { FormSchema } from '@core-panel/components/FormBuilder/types'

import userProfileInformation from '@/routes/user-profile-information'
import ProfileAvatarUpload from './ProfileAvatarUpload.vue'

const page = usePage<{
    auth: {
        user: {
            avatarUrl?: string | null
            email?: string | null
            firstName?: string | null
            id?: string | null
            lastName?: string | null
            locale?: string | null
            presenceLastSeenAt?: number | null
            presenceStatus?: 'online' | 'away' | 'offline' | null
        } | null
    }
    locale: {
        labels?: Record<string, string>
        supported: string[]
    }
}>()

const user = computed(() => page.props.auth.user)

const localeOptions = computed(() =>
    (page.props.locale.supported ?? []).map((locale) => ({
        label: page.props.locale.labels?.[locale] ?? locale.toUpperCase(),
        value: locale,
    })),
)

const initials = computed(() => {
    const firstName = user.value?.firstName?.trim() ?? ''
    const lastName = user.value?.lastName?.trim() ?? ''
    const value = [firstName, lastName]
        .filter(Boolean)
        .map((segment) => segment[0]?.toUpperCase() ?? '')
        .join('')

    return value || 'CP'
})

const form = useForm({
    email: user.value?.email ?? '',
    first_name: user.value?.firstName ?? '',
    last_name: user.value?.lastName ?? '',
    locale: user.value?.locale ?? page.props.locale.supported[0] ?? 'de',
})

const nameSchema = computed<FormSchema>(() => [
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
])

const detailsSchema = computed<FormSchema>(() => [
    {
        label: trans('common.auth.email'),
        name: 'email',
        type: 'email',
    },
    {
        label: trans('common.ui.locale'),
        name: 'locale',
        options: localeOptions.value,
        placeholder: trans('common.ui.locale_select'),
        type: 'select',
    },
])

function submit(): void {
    form.put(userProfileInformation.update.url(), {
        onSuccess: () => {
            form.defaults()
        },
        preserveScroll: true,
    })
}

function updateForm(value: Record<string, unknown>): void {
    form.first_name = String(value.first_name ?? '')
    form.last_name = String(value.last_name ?? '')
    form.email = String(value.email ?? '')
    form.locale = String(value.locale ?? page.props.locale.supported[0] ?? 'de')
}
</script>

<template>
    <section class="cp-profile-panel">
        <div v-if="user?.id" class="cp-card p-6">
            <ProfileAvatarUpload
                :avatar-url="user.avatarUrl ?? null"
                :initials="initials"
                :presence-status="user.presenceStatus ?? 'offline'"
                :user-id="user.id"
            />
        </div>

        <form class="cp-card grid gap-5 p-6" @submit.prevent="submit">
            <div class="grid gap-1">
                <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                    {{ $t('page-settings.profile_info_title') }}
                </h2>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ $t('page-settings.profile_info_description') }}
                </p>
            </div>

            <FormRenderer
                :columns="2"
                :errors="form.errors"
                :model-value="form.data()"
                :schema="nameSchema"
                :wrap-in-form="false"
                @update:model-value="updateForm"
            />

            <FormRenderer
                :columns="2"
                :errors="form.errors"
                :model-value="form.data()"
                :schema="detailsSchema"
                :wrap-in-form="false"
                @update:model-value="updateForm"
            />

            <div class="flex justify-end">
                <Button
                    :disabled="form.processing || !form.isDirty"
                    :loading="form.processing"
                    type="submit"
                >
                    <AppIcon name="save" />
                    <span>{{ $t('page-settings.profile_save') }}</span>
                </Button>
            </div>
        </form>
    </section>
</template>
