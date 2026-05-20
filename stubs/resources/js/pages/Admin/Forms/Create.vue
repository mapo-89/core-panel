<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'
import forms from '@/routes/core-panel/forms'
import type { FormSchema } from '@/types/core-panel'

type FormEditorPayload = {
    name: string
    schema_json: string
    settings_json: string
    slug: string
    status: string
}

const props = defineProps<{
    statuses: string[]
}>()

const schemaText = ref('[]')
const form = useForm<FormEditorPayload>({
    name: '',
    slug: '',
    status: props.statuses[0] ?? 'draft',
    schema_json: '[]',
    settings_json: '{}',
})

function parseSchema(): FormSchema | null {
    try {
        return JSON.parse(schemaText.value) as FormSchema
    } catch {
        return null
    }
}

const previewSchema = computed<FormSchema>(() => parseSchema() ?? [])
const resolvedSchemaError = computed<string | null>(() =>
    parseSchema() === null ? trans('forms.invalid_schema') : null,
)

function submit(): void {
    if (resolvedSchemaError.value !== null) {
        return
    }

    form.schema_json = schemaText.value
    form.post(forms.store.url())
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('forms.create_title')" />

        <div
            class="grid gap-6 px-4 py-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
        >
            <div class="grid gap-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="grid gap-2">
                        <h1
                            class="text-2xl font-semibold text-[var(--cp-text-primary)]"
                        >
                            {{ $t('forms.create_title') }}
                        </h1>
                        <p class="text-sm text-[var(--cp-text-muted)]">
                            {{ $t('forms.create_description') }}
                        </p>
                    </div>

                    <Link :href="forms.index.url()">
                        <Button
                            :label="$t('common.ui.back')"
                            severity="secondary"
                            outlined
                        />
                    </Link>
                </div>

                <form class="cp-card grid gap-5" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">{{
                                $t('forms.labels.name')
                            }}</label>
                            <InputText v-model="form.name" />
                            <small
                                v-if="form.errors.name"
                                class="text-[var(--cp-danger)]"
                                >{{ form.errors.name }}</small
                            >
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">{{
                                $t('forms.labels.slug')
                            }}</label>
                            <InputText v-model="form.slug" />
                            <small
                                v-if="form.errors.slug"
                                class="text-[var(--cp-danger)]"
                                >{{ form.errors.slug }}</small
                            >
                        </div>
                    </div>

                    <div class="grid gap-2 md:max-w-xs">
                        <label class="text-sm font-medium">{{
                            $t('forms.labels.status')
                        }}</label>
                        <Select v-model="form.status" :options="statuses" />
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium">{{
                            $t('forms.labels.schema_json')
                        }}</label>
                        <Textarea
                            v-model="schemaText"
                            rows="18"
                            auto-resize
                            fluid
                        />
                        <Message
                            v-if="resolvedSchemaError"
                            severity="error"
                            size="small"
                            >{{ resolvedSchemaError }}</Message
                        >
                        <small
                            v-if="form.errors.schema_json"
                            class="text-[var(--cp-danger)]"
                            >{{ form.errors.schema_json }}</small
                        >
                    </div>

                    <div class="flex justify-end gap-2">
                        <Link :href="forms.index.url()">
                            <Button
                                :label="$t('common.ui.cancel')"
                                severity="secondary"
                                text
                            />
                        </Link>
                        <Button type="submit" :loading="form.processing">
                            <AppIcon name="save" />
                            <span>{{ $t('common.ui.save') }}</span>
                        </Button>
                    </div>
                </form>
            </div>

            <section class="grid gap-4">
                <div class="grid gap-1">
                    <h2
                        class="text-lg font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ $t('forms.live_preview') }}
                    </h2>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ $t('forms.live_preview_description') }}
                    </p>
                </div>

                <div class="cp-card">
                    <FormRenderer
                        :errors="{}"
                        :model-value="{}"
                        :schema="previewSchema"
                        :wrap-in-form="false"
                        @update:model-value="() => {}"
                    />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
