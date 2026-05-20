<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import forms from '@/routes/core-panel/forms'
import AppLayout from '@/layouts/AppLayout.vue'
import type { FormRecord, FormSubmissionRecord } from '@/types/core-panel'

defineProps<{
    form: FormRecord
    submissions: FormSubmissionRecord[]
    table: {
        columns: Array<{
            key: string
            label: string
            sortable: boolean
        }>
    }
}>()
</script>

<template>
    <AppLayout>
        <Head
            :title="
                trans('forms.submissions_title', {
                    name: form.name,
                })
            "
        />

        <div class="grid gap-6 px-4 py-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid gap-2">
                    <h1
                        class="text-2xl font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ form.name }}
                    </h1>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ trans('forms.submissions_description') }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <a :href="forms.submissions.export.url(form.id)">
                        <Button
                            :label="trans('forms.export')"
                            severity="secondary"
                            outlined
                        />
                    </a>
                    <Link :href="forms.edit.url(form.id)">
                        <Button :label="trans('forms.back_to_form')" />
                    </Link>
                </div>
            </div>

            <div class="cp-card">
                <DataTable
                    :value="submissions"
                    data-key="id"
                    paginator
                    :rows="10"
                >
                    <Column field="id" :header="trans('forms.columns.id')" />
                    <Column
                        field="locale"
                        :header="trans('forms.columns.locale')"
                    />
                    <Column
                        field="submittedAt"
                        :header="trans('forms.columns.submitted_at')"
                    />
                    <Column :header="trans('forms.columns.data')">
                        <template #body="{ data }">
                            <pre
                                class="overflow-x-auto whitespace-pre-wrap text-xs text-[var(--cp-text-muted)]"
                                >{{ JSON.stringify(data.data, null, 2) }}</pre
                            >
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
