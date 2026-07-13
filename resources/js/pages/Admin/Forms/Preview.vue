<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'
import forms from '@/routes/core-panel/forms'
import publicForms from '@/routes/core-panel/forms/public'
import type { FormModel, FormRecord } from '@core-panel/types/core-panel'

const props = withDefaults(
    defineProps<{
        form: FormRecord
        public?: boolean
    }>(),
    {
        public: false,
    },
)

const formState = ref<FormModel>({})

function submit(payload: FormModel): void {
    formState.value = payload

    if (!props.public) {
        return
    }

    router.post(
        publicForms.store.url(props.form.slug),
        payload as RequestPayload,
        {
            preserveScroll: true,
        },
    )
}
</script>

<template>
    <div
        class="min-h-screen bg-[var(--cp-surface-canvas)] px-4 py-8 text-[var(--cp-text-primary)] md:px-6"
    >
        <Head :title="props.form.name" />

        <div class="grid gap-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid gap-2">
                    <h1 class="text-2xl font-semibold">
                        {{ props.form.name }}
                    </h1>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ props.form.slug }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Link
                        v-if="!props.public"
                        :href="forms.edit.url(props.form.id)"
                    >
                        <Button
                            :label="trans('common.ui.edit')"
                            severity="secondary"
                            outlined
                        />
                    </Link>
                </div>
            </div>

            <div class="cp-card">
                <FormRenderer
                    :errors="{}"
                    :model-value="formState"
                    :schema="props.form.schema"
                    :submit-label="trans('common.ui.submit')"
                    @submit="submit"
                    @update:model-value="formState = $event"
                />
            </div>
        </div>
    </div>
</template>
