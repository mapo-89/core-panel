<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { inject, ref } from 'vue'

import { trans } from 'laravel-vue-i18n'

import userGroupRoutes from '@/routes/core-panel/user-groups'
import AppIcon from '@/components/AppIcon.vue'

type DialogRef = {
    close: () => void
    data?: {
        onSaved?: () => void
    }
}

type PreviewRow = {
    action: 'create' | 'update'
    color: string
    name: string
}

type PreviewResult = {
    create_count: number
    has_more: boolean
    rows: PreviewRow[]
    total_count: number
    update_count: number
}

const dialogRef = inject<{ value: DialogRef }>('dialogRef')
const onSaved = dialogRef?.value.data?.onSaved
const preview = ref<PreviewResult | null>(null)
const previewLoading = ref(false)
const previewError = ref('')

const form = useForm({
    file: null as File | null,
})

function previewActionLabel(action: PreviewRow['action']): string {
    return action === 'create'
        ? trans('page-user-groups.preview_create')
        : trans('page-user-groups.preview_update')
}

function csrfToken(): string | null {
    const metaTag = document.querySelector('meta[name="csrf-token"]')

    return metaTag instanceof HTMLMetaElement ? metaTag.content : null
}

function xsrfToken(): string | null {
    const matches = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)

    return matches?.[2] ? decodeURIComponent(matches[2]) : null
}

function toAppRelativeUrl(url: string): string {
    const parsedUrl = new URL(url, window.location.origin)

    return `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`
}

function close(): void {
    dialogRef?.value.close()
}

function handleFileChange(event: Event): void {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null

    form.file = file
    form.clearErrors('file')
    preview.value = null
    previewError.value = ''

    if (file !== null) {
        void loadPreview()
    }
}

async function loadPreview(): Promise<void> {
    if (form.file === null) {
        form.setError(
            'file',
            trans('validation.required', {
                attribute: trans('page-user-groups.import_file'),
            }),
        )

        return
    }

    previewLoading.value = true
    previewError.value = ''
    form.clearErrors('file')

    const payload = new FormData()
    payload.append('file', form.file)

    try {
        const response = await fetch(
            toAppRelativeUrl(userGroupRoutes.preview.url()),
            {
                body: payload,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...(csrfToken() ? { 'X-CSRF-TOKEN': csrfToken()! } : {}),
                    ...(xsrfToken() ? { 'X-XSRF-TOKEN': xsrfToken()! } : {}),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                method: 'POST',
            },
        )

        const body = (await response.json().catch(() => null)) as {
            data?: PreviewResult
            errors?: Record<string, string[]>
            message?: string
        } | null

        if (!response.ok) {
            previewError.value =
                body?.errors?.file?.[0] ??
                body?.message ??
                trans('common.ui.error')
            preview.value = null

            return
        }

        if (!body?.data) {
            previewError.value = trans('common.ui.error')
            preview.value = null

            return
        }

        preview.value = body.data
    } catch {
        previewError.value = trans('common.ui.error')
        preview.value = null
    } finally {
        previewLoading.value = false
    }
}

function submit(): void {
    if (form.file === null) {
        form.setError(
            'file',
            trans('validation.required', {
                attribute: trans('page-user-groups.import_file'),
            }),
        )

        return
    }

    form.post(userGroupRoutes.import.url(), {
        forceFormData: true,
        onSuccess: () => {
            onSaved?.()
            close()
        },
        preserveScroll: true,
    })
}
</script>

<template>
    <form class="cp-user-group-import space-y-5" @submit.prevent="submit">
        <div class="cp-user-group-import__field space-y-2">
            <p
                class="cp-user-group-import__hint text-sm leading-6 text-slate-500 dark:text-slate-400"
            >
                {{ $t('page-user-groups.import_hint') }}
            </p>

            <label
                class="cp-user-group-import__label text-sm font-medium text-slate-700 dark:text-slate-200"
                for="user-group-file"
            >
                {{ $t('page-user-groups.import_file') }}
            </label>
            <input
                id="user-group-file"
                accept=".csv,.txt,.sql"
                class="cp-user-group-import__file block w-full rounded-[var(--cp-radius-lg)] border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-[var(--cp-radius-md)] file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200"
                type="file"
                @change="handleFileChange"
            />
            <small
                v-if="form.errors.file"
                class="cp-user-group-import__error text-sm text-red-600"
            >
                {{ form.errors.file }}
            </small>
        </div>

        <div
            class="cp-user-group-import__formats rounded-[var(--cp-radius-lg)] border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-300"
        >
            <div>{{ $t('page-user-groups.import_formats') }}</div>
            <div class="cp-user-group-import__badges mt-3 flex flex-wrap gap-2">
                <Tag value=".csv" severity="contrast" />
                <Tag value=".txt" severity="contrast" />
                <Tag value=".sql" severity="contrast" />
            </div>
        </div>

        <div
            v-if="previewError"
            class="cp-user-group-import__feedback cp-user-group-import__feedback--danger rounded-[var(--cp-radius-lg)] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/70 dark:bg-red-950/40 dark:text-red-200"
        >
            {{ previewError }}
        </div>

        <div
            v-if="preview"
            class="cp-user-group-import__preview space-y-3 rounded-[var(--cp-radius-lg)] border border-slate-200 bg-white px-4 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
        >
            <div
                class="cp-user-group-import__preview-stats flex flex-wrap items-center gap-2"
            >
                <Tag
                    :value="
                        $t('page-user-groups.preview_total', {
                            count: String(preview.total_count),
                        })
                    "
                    severity="contrast"
                />
                <Tag
                    :value="
                        $t('page-user-groups.preview_create_count', {
                            count: String(preview.create_count),
                        })
                    "
                    severity="success"
                />
                <Tag
                    :value="
                        $t('page-user-groups.preview_update_count', {
                            count: String(preview.update_count),
                        })
                    "
                    severity="warn"
                />
            </div>

            <ul
                v-if="preview.rows.length > 0"
                class="cp-user-group-import__preview-list space-y-2"
            >
                <li
                    v-for="row in preview.rows"
                    :key="`${row.action}-${row.name}`"
                    class="cp-user-group-import__preview-row flex items-center justify-between gap-3 rounded-[var(--cp-radius-md)] border border-slate-200 px-3 py-2.5 dark:border-slate-800"
                >
                    <div
                        class="cp-user-group-import__preview-row-main flex min-w-0 items-center gap-3"
                    >
                        <span
                            class="cp-user-group-import__preview-color h-3 w-3 shrink-0 rounded-full border border-white/70 shadow-sm"
                            :style="{ backgroundColor: row.color }"
                        />
                        <strong
                            class="cp-user-group-import__preview-name truncate text-sm font-medium text-slate-800 dark:text-slate-100"
                            >{{ row.name }}</strong
                        >
                    </div>
                    <Tag
                        :severity="row.action === 'create' ? 'success' : 'warn'"
                        :value="previewActionLabel(row.action)"
                    />
                </li>
            </ul>
            <p
                v-else
                class="cp-user-group-import__empty text-sm text-slate-500 dark:text-slate-400"
            >
                {{ $t('page-user-groups.preview_empty') }}
            </p>

            <p
                v-if="preview.has_more"
                class="cp-user-group-import__more text-sm text-slate-500 dark:text-slate-400"
            >
                {{ $t('page-user-groups.preview_more') }}
            </p>
        </div>

        <div
            class="cp-user-group-import__actions mt-2 flex flex-wrap items-center justify-end gap-2 border-t border-[var(--cp-surface-border)] pt-5"
        >
            <Button severity="secondary" text type="button" @click="close">
                <AppIcon name="x" />
                <span>{{ $t('common.ui.cancel') }}</span>
            </Button>
            <Button
                :disabled="preview === null || previewLoading"
                :loading="form.processing"
                type="submit"
            >
                <AppIcon name="upload" />
                <span>{{ $t('page-user-groups.import_action') }}</span>
            </Button>
        </div>
    </form>
</template>
