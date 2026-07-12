<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import type { FileUploadUploaderEvent } from 'primevue/fileupload'
import { trans } from 'laravel-vue-i18n'

import AppIcon from '@core-panel/components/AppIcon.vue'
import ConfirmActionDialog from '@core-panel/components/Dialogs/ConfirmActionDialog.vue'
import fileRoutes from '@/routes/core-panel/files'
import AppLayout from '@core-panel/layouts/AppLayout.vue'
import type { FileRecord } from '@core-panel/types/core-panel'

const props = defineProps<{
    collections: string[]
    files: {
        data: FileRecord[]
        current_page: number
        per_page: number
        total: number
    }
    filters: {
        collection: string
        search: string
        view: string
    }
    limits: {
        allowedMimeTypes: string[]
        maxUploadSize: number
    }
    summary: {
        totalSize: number
    }
}>()

const deleteDialogVisible = ref(false)
const loading = ref(false)
const pendingDeleteFile = ref<FileRecord | null>(null)
const previewVisible = ref(false)
const selectedFile = ref<FileRecord | null>(null)
const search = ref(props.filters.search)
const selectedCollection = ref(props.filters.collection)
const currentView = ref<'grid' | 'list'>(
    props.filters.view === 'list' ? 'list' : 'grid',
)
const debounceHandle = ref<number | null>(null)

const collectionOptions = computed(() =>
    props.collections.map((value) => ({ label: value, value })),
)
const resultCountLabel = computed(() =>
    props.files.total === 1
        ? trans('files.states.count_one', { count: String(props.files.total) })
        : trans('files.states.count_many', {
              count: String(props.files.total),
          }),
)
const uploadLimitsLabel = computed(
    () =>
        `${props.limits.maxUploadSize} KB · ${props.limits.allowedMimeTypes.join(', ')}`,
)
const resultMetaLabel = computed(
    () =>
        `${currentView.value === 'grid' ? trans('files.view.grid') : trans('files.view.list')} · ${trans('files.summary.total_size')}: ${readableSize(props.summary.totalSize)}`,
)

function applyFilters(): void {
    loading.value = true

    router.get(
        fileRoutes.index.url(),
        {
            'filter.collection': selectedCollection.value || undefined,
            search: search.value || undefined,
            view: currentView.value,
        },
        {
            only: ['files', 'filters', 'collections', 'limits'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                loading.value = false
            },
        },
    )
}

function onSearchInput(): void {
    if (debounceHandle.value !== null) {
        window.clearTimeout(debounceHandle.value)
    }

    debounceHandle.value = window.setTimeout(() => applyFilters(), 250)
}

function onUpload(event: FileUploadUploaderEvent): void {
    const file = Array.isArray(event.files) ? event.files[0] : event.files

    if (!file) {
        return
    }

    router.post(
        fileRoutes.store.url(),
        {
            collection: selectedCollection.value || 'files',
            file,
        },
        {
            forceFormData: true,
        },
    )
}

function openPreview(file: FileRecord): void {
    selectedFile.value = {
        ...file,
        previewUrl: fileRoutes.preview.url(file.id),
        downloadUrl: fileRoutes.download.url(file.id),
    }
    previewVisible.value = true
}

function destroyFile(file: FileRecord): void {
    pendingDeleteFile.value = file
    deleteDialogVisible.value = true
}

function confirmDestroyFile(): void {
    if (pendingDeleteFile.value === null) {
        return
    }

    router.delete(fileRoutes.destroy.url(pendingDeleteFile.value.id), {
        onFinish: () => {
            deleteDialogVisible.value = false
            pendingDeleteFile.value = null
        },
        preserveScroll: true,
    })
}

function readableSize(size: number): string {
    if (size < 1024) {
        return `${size} B`
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

function filePreviewUrl(file: FileRecord): string {
    return file.previewUrl ?? fileRoutes.preview.url(file.id)
}

function switchView(view: 'grid' | 'list'): void {
    currentView.value = view
    applyFilters()
}
</script>

<template>
    <AppLayout
        :title="trans('files.title')"
        :subtitle="trans('files.description')"
    >
        <Head :title="trans('files.title')" />

        <template #page-actions>
            <FileUpload
                mode="basic"
                name="file"
                custom-upload
                auto
                :choose-label="trans('files.upload')"
                @uploader="onUpload"
            />
        </template>

        <ConfirmActionDialog
            v-model:visible="deleteDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('common.ui.delete')"
            confirm-severity="danger"
            :description="$t('files.messages.delete_confirm')"
            icon="trash"
            :message="pendingDeleteFile?.name ?? $t('common.ui.delete')"
            :title="$t('common.ui.delete')"
            tone="danger"
            @confirm="confirmDestroyFile"
        />

        <div class="grid gap-6">
            <section class="cp-card cp-section">
                <div class="cp-section__header">
                    <div class="grid min-w-0 flex-1 gap-1">
                        <h2
                            class="text-lg font-semibold text-[var(--cp-text-primary)]"
                        >
                            {{ trans('files.filters.title') }}
                        </h2>
                        <p class="text-sm text-[var(--cp-text-muted)]">
                            {{ uploadLimitsLabel }}
                        </p>
                    </div>
                </div>

                <div class="cp-section__body">
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="grid min-w-72 flex-1 gap-2">
                            <span
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ trans('files.filters.search') }}
                            </span>
                            <InputText
                                v-model="search"
                                @input="onSearchInput"
                            />
                        </label>

                        <label class="grid min-w-56 gap-2">
                            <span
                                class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >
                                {{ trans('files.filters.collection') }}
                            </span>
                            <Select
                                v-model="selectedCollection"
                                :options="collectionOptions"
                                option-label="label"
                                option-value="value"
                                show-clear
                                @change="applyFilters()"
                            />
                        </label>
                    </div>
                </div>
            </section>

            <section class="cp-card cp-section">
                <div class="cp-section__header cp-section__header--split">
                    <div class="grid min-w-0 flex-1 gap-1">
                        <h2
                            class="text-lg font-semibold text-[var(--cp-text-primary)]"
                        >
                            {{ resultCountLabel }}
                        </h2>
                        <p class="text-sm text-[var(--cp-text-muted)]">
                            {{ resultMetaLabel }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            class="gap-2"
                            :severity="
                                currentView === 'grid' ? 'primary' : 'secondary'
                            "
                            @click="switchView('grid')"
                        >
                            <AppIcon name="grid" />
                            <span>{{ trans('files.view.grid') }}</span>
                        </Button>
                        <Button
                            class="gap-2"
                            :severity="
                                currentView === 'list' ? 'primary' : 'secondary'
                            "
                            @click="switchView('list')"
                        >
                            <AppIcon name="list" />
                            <span>{{ trans('files.view.list') }}</span>
                        </Button>
                    </div>
                </div>

                <div class="cp-section__body">
                    <div v-if="loading" class="grid gap-3 md:grid-cols-3">
                        <Skeleton
                            v-for="index in 6"
                            :key="index"
                            height="11rem"
                        />
                    </div>

                    <template v-else-if="props.files.data.length === 0">
                        <div
                            class="rounded-[var(--cp-radius-md)] border border-dashed border-[var(--cp-surface-border)] px-6 py-12 text-center text-sm text-[var(--cp-text-muted)]"
                        >
                            {{ trans('files.empty') }}
                        </div>
                    </template>

                    <div
                        v-else-if="currentView === 'grid'"
                        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="file in props.files.data"
                            :key="file.id"
                            class="rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-4 shadow-[var(--cp-shadow-sm)]"
                        >
                            <div class="grid gap-3">
                                <div
                                    class="aspect-video overflow-hidden rounded-[var(--cp-radius-sm)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-muted)]"
                                >
                                    <iframe
                                        v-if="
                                            file.mimeType === 'application/pdf'
                                        "
                                        :src="filePreviewUrl(file)"
                                        class="h-full w-full scale-[0.92]"
                                    />
                                    <img
                                        v-else-if="
                                            file.mimeType?.startsWith('image/')
                                        "
                                        :src="filePreviewUrl(file)"
                                        :alt="file.name"
                                        class="h-full w-full object-contain p-2"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full items-center justify-center text-sm text-[var(--cp-text-muted)]"
                                    >
                                        {{
                                            file.extension ??
                                            file.mimeType ??
                                            trans('files.file')
                                        }}
                                    </div>
                                </div>

                                <div class="grid gap-1">
                                    <strong
                                        class="truncate text-sm text-[var(--cp-text-primary)]"
                                        >{{ file.name }}</strong
                                    >
                                    <span
                                        class="text-xs text-[var(--cp-text-muted)]"
                                        >{{ readableSize(file.size) }} ·
                                        {{ file.collection }}</span
                                    >
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        class="gap-2"
                                        :label="trans('files.actions.preview')"
                                        icon="pi pi-eye"
                                        severity="secondary"
                                        outlined
                                        @click="openPreview(file)"
                                    />
                                    <a
                                        :href="fileRoutes.download.url(file.id)"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Button
                                            class="gap-2"
                                            :label="
                                                trans('files.actions.download')
                                            "
                                            icon="pi pi-download"
                                            severity="secondary"
                                            outlined
                                        />
                                    </a>
                                    <Button
                                        class="gap-2"
                                        :label="trans('common.ui.delete')"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        @click="destroyFile(file)"
                                    />
                                </div>
                            </div>
                        </article>
                    </div>

                    <DataTable
                        v-else
                        :value="props.files.data"
                        data-key="id"
                        table-style="min-width: 100%"
                    >
                        <Column
                            field="name"
                            :header="trans('files.fields.name')"
                        >
                            <template #body="{ data }">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-[var(--cp-radius-sm)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-muted)]"
                                    >
                                        <iframe
                                            v-if="
                                                data.mimeType ===
                                                'application/pdf'
                                            "
                                            :src="filePreviewUrl(data)"
                                            class="h-full w-full scale-[0.82]"
                                        />
                                        <img
                                            v-else-if="
                                                data.mimeType?.startsWith(
                                                    'image/',
                                                )
                                            "
                                            :src="filePreviewUrl(data)"
                                            :alt="data.name"
                                            class="h-full w-full object-contain p-1.5"
                                        />
                                        <span
                                            v-else
                                            class="px-1 text-center text-[10px] font-medium uppercase tracking-wide text-[var(--cp-text-muted)]"
                                        >
                                            {{
                                                data.extension ??
                                                data.mimeType ??
                                                trans('files.file')
                                            }}
                                        </span>
                                    </div>

                                    <span class="truncate">{{
                                        data.name
                                    }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column
                            field="collection"
                            :header="trans('files.fields.collection')"
                        />
                        <Column
                            field="mimeType"
                            :header="trans('files.fields.mime_type')"
                        />
                        <Column
                            field="size"
                            :header="trans('files.fields.size')"
                        >
                            <template #body="{ data }">
                                {{ readableSize(data.size) }}
                            </template>
                        </Column>
                        <Column
                            field="createdAt"
                            :header="trans('files.fields.created_at')"
                        />
                        <Column :header="trans('common.ui.actions')">
                            <template #body="{ data }">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        class="gap-2"
                                        :label="trans('files.actions.preview')"
                                        icon="pi pi-eye"
                                        severity="secondary"
                                        outlined
                                        @click="openPreview(data)"
                                    />
                                    <a
                                        :href="fileRoutes.download.url(data.id)"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Button
                                            class="gap-2"
                                            :label="
                                                trans('files.actions.download')
                                            "
                                            icon="pi pi-download"
                                            severity="secondary"
                                            outlined
                                        />
                                    </a>
                                    <Button
                                        class="gap-2"
                                        :label="trans('common.ui.delete')"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        @click="destroyFile(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog
            v-model:visible="previewVisible"
            modal
            :header="selectedFile?.name ?? trans('files.title')"
            class="w-full max-w-5xl"
        >
            <div v-if="selectedFile" class="grid gap-4">
                <div
                    class="overflow-hidden rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)]"
                >
                    <iframe
                        v-if="selectedFile.mimeType === 'application/pdf'"
                        :src="
                            selectedFile.previewUrl ??
                            fileRoutes.preview.url(selectedFile.id)
                        "
                        class="h-[70vh] w-full"
                    />
                    <img
                        v-else-if="selectedFile.mimeType?.startsWith('image/')"
                        :src="
                            selectedFile.previewUrl ??
                            fileRoutes.preview.url(selectedFile.id)
                        "
                        :alt="selectedFile.name"
                        class="max-h-[70vh] w-full object-contain"
                    />
                    <div
                        v-else
                        class="px-6 py-16 text-center text-sm text-[var(--cp-text-muted)]"
                    >
                        {{ trans('files.messages.preview_unavailable') }}
                    </div>
                </div>

                <div class="grid gap-1 text-sm text-[var(--cp-text-muted)]">
                    <span>{{ selectedFile.mimeType ?? '—' }}</span>
                    <span>{{ readableSize(selectedFile.size) }}</span>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
