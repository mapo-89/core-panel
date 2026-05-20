<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { computed, ref, watch } from 'vue'

import { useToast } from 'primevue/usetoast'

import ColumnVisibilityDropdown from '@core-panel/components/TableBuilder/ColumnVisibilityDropdown.vue'
import TableBuilderDataTable from '@core-panel/components/TableBuilder/DataTable.vue'
import type {
    DataTablePagination,
    DataTableSchema,
    FormOptionRecord,
} from '@/types/core-panel'
import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'
import type {
    FormModel,
    FormSchema,
} from '@core-panel/components/FormBuilder/types'
import AppIcon from '@/components/AppIcon.vue'
import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'
import apiTokens from '@/routes/core-panel/api-tokens'
import type { ApiTokenRecord } from '@/types/core-panel'

const props = withDefaults(
    defineProps<{
        abilities: FormOptionRecord[]
        canCreate?: boolean
        canDelete?: boolean
        createRequestKey?: number
        embedded?: boolean
        tokens: ApiTokenRecord[]
    }>(),
    {
        canCreate: false,
        canDelete: false,
        createRequestKey: 0,
        embedded: false,
    },
)

const page = usePage<{
    flash: {
        apiToken?: string | null
        status?: string | null
    }
}>()
const toast = useToast()
const createDialogVisible = ref(false)
const deleteDialogVisible = ref(false)
const replaceDialogVisible = ref(false)
const pendingDeleteToken = ref<ApiTokenRecord | null>(null)
const pendingReplaceToken = ref<ApiTokenRecord | null>(null)

const form = useForm({
    name: '',
    abilities: [] as string[],
})

const tokenFormSchema = computed<FormSchema>(() => [
    {
        label: trans('common.ui.name'),
        name: 'name',
        placeholder: trans('page-api-tokens.name_placeholder'),
        type: 'text',
    },
    {
        help: trans('page-api-tokens.abilities_help'),
        label: trans('common.ui.abilities'),
        name: 'abilities',
        options: props.abilities,
        type: 'multi-select',
    },
])

const tableSchema = computed<DataTableSchema>(() => ({
    actions: [],
    bulkActions: [],
    columns: [
        {
            key: 'name',
            label: null,
            meta: { labelKey: 'common.ui.token' },
            searchable: true,
            sortable: true,
            toggleable: false,
            type: 'text',
            visible: true,
        },
        {
            key: 'abilities',
            label: null,
            meta: { labelKey: 'common.ui.abilities' },
            searchable: true,
            sortable: false,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'lastUsedAt',
            label: null,
            meta: { labelKey: 'common.ui.last_used' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
        {
            key: 'createdAt',
            label: null,
            meta: { labelKey: 'table-builder.columns.created_at' },
            searchable: false,
            sortable: true,
            toggleable: true,
            type: 'text',
            visible: true,
        },
    ],
    filters: [],
    pagination: buildPagination(props.tokens.length),
    rows: props.tokens,
    state: {
        filters: {},
        search: '',
        sort: '-createdAt',
        visibleColumns: currentColumns([
            'name',
            'abilities',
            'lastUsedAt',
            'createdAt',
        ]),
    },
}))

function buildPagination(total: number): DataTablePagination {
    return {
        from: total === 0 ? null : 1,
        lastPage: 1,
        page: 1,
        perPage: total === 0 ? 10 : total,
        to: total === 0 ? null : total,
        total,
    }
}

function currentColumns(fallback: string[]): string[] {
    if (typeof window === 'undefined') {
        return fallback
    }

    const columns = new URLSearchParams(window.location.search).get('columns')

    if (!columns) {
        return fallback
    }

    const visibleColumns = columns
        .split(',')
        .filter((column) => fallback.includes(column))

    return visibleColumns.length > 0 ? visibleColumns : fallback
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString()
}

function openCreateDialog(): void {
    form.reset()
    form.abilities = []
    createDialogVisible.value = true
}

watch(
    () => props.createRequestKey,
    (value, previousValue) => {
        if (value > previousValue) {
            openCreateDialog()
        }
    },
)

function createToken(): void {
    form.post(apiTokens.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createDialogVisible.value = false
            toast.add({
                detail: trans('page-api-tokens.api_tokens.created'),
                life: 2200,
                severity: 'success',
                summary: trans('common.ui.saved'),
            })
        },
    })
}

function updateTokenForm(value: Record<string, unknown>): void {
    form.name = String(value.name ?? '')
    form.abilities = Array.isArray(value.abilities)
        ? value.abilities.map((entry) => String(entry))
        : []
}

function deleteToken(token: ApiTokenRecord): void {
    pendingDeleteToken.value = token
    deleteDialogVisible.value = true
}

function replaceToken(token: ApiTokenRecord): void {
    pendingReplaceToken.value = token
    replaceDialogVisible.value = true
}

async function copySecret(secret: string): Promise<void> {
    await navigator.clipboard.writeText(secret)

    toast.add({
        detail: trans('common.ui.copied'),
        life: 2200,
        severity: 'success',
        summary: trans('common.ui.copy'),
    })
}

function confirmReplaceToken(): void {
    if (pendingReplaceToken.value === null) {
        return
    }

    form.post(apiTokens.replace.url(pendingReplaceToken.value.id), {
        onFinish: () => {
            replaceDialogVisible.value = false
            pendingReplaceToken.value = null
        },
        preserveScroll: true,
    })
}

function confirmDeleteToken(): void {
    if (pendingDeleteToken.value === null) {
        return
    }

    form.delete(apiTokens.destroy.url(pendingDeleteToken.value.id), {
        onFinish: () => {
            deleteDialogVisible.value = false
            pendingDeleteToken.value = null
        },
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="grid gap-4">
        <ConfirmActionDialog
            v-model:visible="deleteDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('common.ui.delete')"
            confirm-severity="danger"
            :description="
                pendingDeleteToken
                    ? $t('page-api-tokens.delete_message', {
                          name: pendingDeleteToken.name,
                      })
                    : null
            "
            icon="trash"
            :message="pendingDeleteToken?.name ?? $t('common.ui.delete')"
            :title="$t('page-api-tokens.delete_header')"
            tone="danger"
            @confirm="confirmDeleteToken"
        />
        <ConfirmActionDialog
            v-model:visible="replaceDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('page-api-tokens.replace')"
            confirm-severity="warn"
            :description="
                pendingReplaceToken
                    ? $t('page-api-tokens.replace_message', {
                          name: pendingReplaceToken.name,
                      })
                    : null
            "
            icon="refresh"
            :message="
                pendingReplaceToken?.name ?? $t('page-api-tokens.replace')
            "
            :title="$t('page-api-tokens.replace_header')"
            tone="warning"
            @confirm="confirmReplaceToken"
        />

        <div
            v-if="!props.embedded"
            class="flex flex-wrap items-start justify-between gap-4"
        >
            <div class="grid gap-2">
                <h1
                    class="text-2xl font-semibold text-[var(--cp-text-primary)]"
                >
                    {{ $t('page-api-tokens.title') }}
                </h1>
                <p class="text-sm text-[var(--cp-text-muted)]">
                    {{ $t('page-api-tokens.description') }}
                </p>
            </div>
        </div>

        <div
            v-if="page.props.flash.apiToken"
            class="grid gap-4 rounded-[var(--cp-radius-lg)] border p-5 shadow-[var(--cp-shadow-sm)]"
            style="
                border-color: color-mix(
                    in srgb,
                    var(--cp-primary) 30%,
                    var(--cp-surface-border)
                );
                background: color-mix(
                    in srgb,
                    var(--cp-primary) 6%,
                    var(--cp-surface-panel)
                );
            "
        >
            <div class="grid gap-1">
                <strong class="text-sm font-semibold text-[var(--cp-primary)]">
                    {{ $t('common.ui.secret_visible_once') }}
                </strong>
                <span class="text-sm text-[var(--cp-text-muted)]">
                    {{ $t('common.ui.secret_visible_once_help') }}
                </span>
            </div>
            <div
                class="rounded-[var(--cp-radius-md)] border bg-[var(--cp-surface-0)] p-3"
                style="
                    border-color: color-mix(
                        in srgb,
                        var(--cp-primary) 18%,
                        var(--cp-surface-border)
                    );
                "
            >
                <textarea
                    :value="page.props.flash.apiToken"
                    class="min-h-28 w-full resize-y overflow-auto bg-transparent font-mono text-sm leading-6 text-[var(--cp-text-primary)] outline-none"
                    readonly
                    spellcheck="false"
                />
            </div>
            <div class="flex justify-end">
                <Button
                    class="cp-datatable__toolbar-button"
                    severity="secondary"
                    type="button"
                    @click="copySecret(page.props.flash.apiToken)"
                >
                    <AppIcon name="copy" />
                    <span>{{ $t('common.ui.copy') }}</span>
                </Button>
            </div>
        </div>

        <TableBuilderDataTable
            :empty-message="$t('table-builder.states.empty_title')"
            :schema="tableSchema"
        >
            <template
                #toolbar-actions="{
                    columns,
                    visibleColumns,
                    setVisibleColumns,
                }"
            >
                <Button
                    v-if="props.canCreate && !props.embedded"
                    class="cp-datatable__toolbar-button"
                    @click="openCreateDialog"
                >
                    <AppIcon name="plus" />
                    <span>{{ $t('page-api-tokens.new') }}</span>
                </Button>
                <ColumnVisibilityDropdown
                    :columns="columns"
                    :model-value="visibleColumns"
                    @update:model-value="setVisibleColumns"
                />
            </template>

            <template #cell-name="{ row }">
                <span class="text-sm font-medium text-[var(--cp-text-primary)]">
                    {{ row.name }}
                </span>
            </template>

            <template #cell-abilities="{ row }">
                <div class="flex flex-wrap gap-2">
                    <Tag
                        v-for="ability in row.abilities"
                        :key="ability"
                        :value="ability"
                    />
                </div>
            </template>

            <template #cell-lastUsedAt="{ row }">
                <span class="text-sm text-[var(--cp-text-muted)]">
                    {{
                        row.lastUsedAt
                            ? formatDateTime(row.lastUsedAt)
                            : $t('common.ui.never')
                    }}
                </span>
            </template>

            <template #cell-createdAt="{ row }">
                <span class="text-sm text-[var(--cp-text-muted)]">
                    {{ formatDateTime(row.createdAt) }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Button
                        v-if="props.canCreate && props.canDelete"
                        v-tooltip.top="$t('page-api-tokens.replace')"
                        :aria-label="$t('page-api-tokens.replace')"
                        class="cp-datatable__action-button"
                        outlined
                        severity="secondary"
                        size="small"
                        @click="replaceToken(row)"
                    >
                        <AppIcon name="refresh" />
                    </Button>
                    <Button
                        v-if="props.canDelete"
                        :aria-label="$t('common.ui.delete')"
                        severity="danger"
                        @click="deleteToken(row)"
                    >
                        <AppIcon name="trash" />
                    </Button>
                </div>
            </template>
        </TableBuilderDataTable>

        <Dialog
            v-model:visible="createDialogVisible"
            modal
            :header="$t('page-api-tokens.create')"
            class="w-full max-w-2xl"
        >
            <form class="grid gap-5" @submit.prevent="createToken">
                <FormRenderer
                    :errors="form.errors"
                    :model-value="form as unknown as FormModel"
                    :schema="tokenFormSchema"
                    :wrap-in-form="false"
                    @update:model-value="updateTokenForm"
                />

                <div
                    class="flex justify-end gap-3 border-t border-[var(--cp-surface-border)] pt-5"
                >
                    <Button
                        :label="$t('common.ui.cancel')"
                        severity="secondary"
                        text
                        type="button"
                        @click="createDialogVisible = false"
                    />
                    <Button
                        :disabled="form.processing"
                        :loading="form.processing"
                        type="submit"
                    >
                        <AppIcon name="plus" />
                        <span>{{ $t('common.ui.create') }}</span>
                    </Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
