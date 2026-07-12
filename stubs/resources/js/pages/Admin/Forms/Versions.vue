<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import AppLayout from '@core-panel/layouts/AppLayout.vue'

defineProps<{
    formId: string
    versions: Array<{
        id: string
        version: number
        schema: unknown[]
        createdBy: string | null
        createdAt: string | null
    }>
}>()
</script>

<template>
    <AppLayout>
        <Head :title="trans('forms.versions_title')" />

        <div class="grid gap-6 px-4 py-8">
            <div class="grid gap-2">
                <h1
                    class="text-2xl font-semibold text-[var(--cp-text-primary)]"
                >
                    {{ trans('forms.labels.versions') }}
                </h1>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ trans('forms.form_id', { id: formId }) }}
                </p>
            </div>

            <div class="cp-card grid gap-4">
                <div
                    v-for="version in versions"
                    :key="version.id"
                    class="rounded border border-[var(--cp-surface-border)] p-4"
                >
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <strong>{{
                            trans('forms.version_number', {
                                version: String(version.version),
                            })
                        }}</strong>
                        <span class="text-xs text-[var(--cp-text-muted)]">{{
                            version.createdAt
                        }}</span>
                    </div>
                    <pre
                        class="overflow-x-auto whitespace-pre-wrap text-xs text-[var(--cp-text-muted)]"
                        >{{ JSON.stringify(version.schema, null, 2) }}</pre
                    >
                </div>
            </div>
        </div>
    </AppLayout>
</template>
