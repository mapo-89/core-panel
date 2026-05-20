<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '@/components/AppIcon.vue'
import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'
import {
    passwordMatchMeta,
    passwordMinLengthMeta,
} from '@core-panel/components/FormBuilder/passwordRequirements'
import type { FormSchema } from '@core-panel/components/FormBuilder/types'
import userPassword from '@/routes/user-password'

const props = defineProps<{
    requiresPasswordSetup: boolean
}>()

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const schema = computed<FormSchema>(() => [
    ...(props.requiresPasswordSetup
        ? []
        : [
              {
                  columnSpan: 2,
                  label: trans('page-settings.current_password'),
                  name: 'current_password',
                  type: 'password',
              },
          ]),
    {
        label: trans('common.auth.new_password'),
        meta: passwordMinLengthMeta(12),
        name: 'password',
        type: 'password',
    },
    {
        label: trans('page-auth.confirm_password'),
        meta: passwordMatchMeta(),
        name: 'password_confirmation',
        type: 'password',
    },
])

function submit(): void {
    form.put(userPassword.update.url(), {
        onSuccess: () => {
            form.defaults()
        },
        preserveScroll: true,
        preserveState: false,
    })
}

function updateForm(value: Record<string, unknown>): void {
    form.current_password = String(value.current_password ?? '')
    form.password = String(value.password ?? '')
    form.password_confirmation = String(value.password_confirmation ?? '')
}
</script>

<template>
    <section class="cp-card grid gap-5 p-6">
        <div class="grid gap-1">
            <h2 class="text-lg font-semibold text-[var(--cp-text-primary)]">
                {{
                    $t(
                        requiresPasswordSetup
                            ? 'page-settings.password_setup_title'
                            : 'page-settings.password_title',
                    )
                }}
            </h2>
            <p class="text-sm text-[var(--cp-text-muted)]">
                {{
                    $t(
                        requiresPasswordSetup
                            ? 'page-settings.password_setup_subtitle'
                            : 'page-settings.password_description',
                    )
                }}
            </p>
        </div>

        <Message v-if="requiresPasswordSetup" severity="warn" variant="simple">
            {{ $t('page-settings.password_setup_required_notice') }}
        </Message>

        <form class="grid gap-5" @submit.prevent="submit">
            <FormRenderer
                :columns="2"
                :errors="form.errors"
                :model-value="form.data()"
                :schema="schema"
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
                    <span>{{
                        $t(
                            requiresPasswordSetup
                                ? 'page-settings.password_setup_submit'
                                : 'page-settings.password_update',
                        )
                    }}</span>
                </Button>
            </div>
        </form>
    </section>
</template>
