<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { ref } from 'vue'

import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import formRoutes from '@/routes/core-panel/forms'
import type { FormRecord } from '@/types/core-panel'

defineProps<{
    filters: {
        search: string
    }
    forms: FormRecord[]
}>()

const deleteDialogVisible = ref(false)
const pendingDeleteForm = ref<FormRecord | null>(null)

function openForm(form: FormRecord): void {
    router.visit(formRoutes.edit.url(form.id))
}

function previewForm(form: FormRecord): void {
    router.visit(formRoutes.preview.url(form.id))
}

function openSubmissions(form: FormRecord): void {
    router.visit(formRoutes.submissions.index.url(form.id))
}

function destroyForm(form: FormRecord): void {
    pendingDeleteForm.value = form
    deleteDialogVisible.value = true
}

function confirmDestroyForm(): void {
    if (pendingDeleteForm.value === null) {
        return
    }

    router.delete(formRoutes.destroy.url(pendingDeleteForm.value.id), {
        onFinish: () => {
            deleteDialogVisible.value = false
            pendingDeleteForm.value = null
        },
    })
}
</script>

<template>
    <AppLayout>
        <Head :title="trans('forms.title')" />

        <ConfirmActionDialog
            v-model:visible="deleteDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('common.ui.delete')"
            confirm-severity="danger"
            :description="
                pendingDeleteForm
                    ? trans('forms.delete_message', {
                          name: pendingDeleteForm.name,
                      })
                    : null
            "
            icon="trash"
            :message="pendingDeleteForm?.name ?? $t('common.ui.delete')"
            :title="$t('common.ui.delete')"
            tone="danger"
            @confirm="confirmDestroyForm"
        />

        <div class="grid gap-6 px-4 py-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid gap-2">
                    <h1
                        class="text-2xl font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ trans('forms.title') }}
                    </h1>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ trans('forms.description') }}
                    </p>
                </div>

                <Link :href="formRoutes.create.url()">
                    <Button :label="trans('forms.create_title')" />
                </Link>
            </div>

            <div class="cp-card">
                <DataTable :value="forms" data-key="id" paginator :rows="10">
                    <Column field="name" :header="trans('forms.labels.name')" />
                    <Column field="slug" :header="trans('forms.labels.slug')" />
                    <Column
                        field="status"
                        :header="trans('forms.labels.status')"
                    >
                        <template #body="{ data }">
                            <Tag
                                :value="data.status"
                                :severity="
                                    data.status === 'published'
                                        ? 'success'
                                        : 'secondary'
                                "
                            />
                        </template>
                    </Column>
                    <Column
                        field="version"
                        :header="trans('forms.labels.version')"
                    />
                    <Column
                        field="publicUrl"
                        :header="trans('forms.labels.public_url')"
                    >
                        <template #body="{ data }">
                            <a
                                :href="data.publicUrl"
                                class="text-sm text-[var(--cp-primary)] underline-offset-2 hover:underline"
                                target="_blank"
                                rel="noreferrer"
                            >
                                {{ data.publicUrl }}
                            </a>
                        </template>
                    </Column>
                    <Column :header="trans('common.ui.actions')">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    :label="trans('common.ui.edit')"
                                    size="small"
                                    @click="openForm(data)"
                                />
                                <Button
                                    :label="trans('forms.labels.preview')"
                                    severity="secondary"
                                    outlined
                                    size="small"
                                    @click="previewForm(data)"
                                />
                                <Button
                                    :label="trans('forms.labels.submissions')"
                                    severity="secondary"
                                    text
                                    size="small"
                                    @click="openSubmissions(data)"
                                />
                                <Button
                                    :label="trans('common.ui.delete')"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="destroyForm(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
