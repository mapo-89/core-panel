<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

import { useToast } from 'primevue/usetoast'

import FormRenderer from '@core-panel/components/FormBuilder/FormRenderer.vue'
import type {
    FormModel,
    FormSchema,
} from '@core-panel/components/FormBuilder/types'
import AppIcon from '@/components/AppIcon.vue'
import ConfirmActionDialog from '@/components/Dialogs/ConfirmActionDialog.vue'
import oauthClients from '@/routes/core-panel/oauth-clients'
import AppLayout from '@/layouts/AppLayout.vue'
import type { OAuthClientRecord } from '@/types/core-panel'

const props = defineProps<{
    clients: OAuthClientRecord[]
    personalAccessClientsEnabled: boolean
    scopes: string[]
}>()

const page = usePage<{
    flash: {
        oauthClientSecret?: string | null
        status?: string | null
    }
}>()
const toast = useToast()
const deleteDialogVisible = ref(false)
const dialogVisible = ref(false)
const editingClient = ref<OAuthClientRecord | null>(null)
const pendingDeleteClient = ref<OAuthClientRecord | null>(null)
const search = ref('')

const form = useForm({
    confidential: true,
    name: '',
    personal_access_client: false,
    provider: '',
    redirect: '',
    scopes: [] as string[],
})

const filteredClients = computed(() => {
    const term = search.value.trim().toLowerCase()

    if (term === '') {
        return props.clients
    }

    return props.clients.filter((client) =>
        [client.name, client.redirect, client.provider ?? '', ...client.scopes]
            .join(' ')
            .toLowerCase()
            .includes(term),
    )
})

const clientFormSchema = computed<FormSchema>(() => {
    const schema: FormSchema = [
        {
            label: trans('common.ui.name'),
            name: 'name',
            placeholder: trans('page-oauth-clients.name_placeholder'),
            type: 'text',
        },
        {
            label: trans('common.ui.redirect_uri'),
            name: 'redirect',
            placeholder: trans('page-oauth-clients.redirect_placeholder'),
            type: 'text',
        },
        {
            label: trans('common.ui.provider'),
            name: 'provider',
            placeholder: trans('page-oauth-clients.provider_placeholder'),
            type: 'text',
        },
        {
            label: trans('page-oauth-clients.confidential_client'),
            name: 'confidential',
            type: 'checkbox',
        },
    ]

    if (props.personalAccessClientsEnabled) {
        schema.push({
            label: trans('page-oauth-clients.personal_access_client'),
            name: 'personal_access_client',
            type: 'checkbox',
        })
    }

    schema.push({
        help: trans('page-oauth-clients.scopes_help'),
        label: trans('common.ui.scopes'),
        name: 'scopes',
        options: props.scopes,
        placeholder: trans('page-oauth-clients.scopes_placeholder'),
        type: 'multi-select',
    })

    return schema
})

function openCreateDialog(): void {
    editingClient.value = null
    form.reset()
    form.confidential = true
    form.personal_access_client = false
    form.scopes = []
    dialogVisible.value = true
}

function openEditDialog(client: OAuthClientRecord): void {
    editingClient.value = client
    form.name = client.name
    form.provider = client.provider ?? ''
    form.redirect = client.redirect
    form.scopes = [...client.scopes]
    form.confidential = client.confidential
    form.personal_access_client = client.personalAccessClient
    dialogVisible.value = true
}

function saveClient(): void {
    const action =
        editingClient.value === null
            ? oauthClients.store.url()
            : oauthClients.update.url(editingClient.value.id)

    const method = editingClient.value === null ? form.post : form.put

    method(action, {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false

            if (editingClient.value === null) {
                toast.add({
                    severity: 'success',
                    summary: trans('common.ui.saved'),
                    detail: trans('page-oauth-clients.oauth_clients.created'),
                    life: 2200,
                })
            }
        },
    })
}

function updateClientForm(value: Record<string, unknown>): void {
    form.name = String(value.name ?? '')
    form.redirect = String(value.redirect ?? '')
    form.provider = String(value.provider ?? '')
    form.confidential = Boolean(value.confidential)
    form.personal_access_client = Boolean(value.personal_access_client)
    form.scopes = Array.isArray(value.scopes)
        ? value.scopes.map((entry) => String(entry))
        : []
}

function revokeClient(client: OAuthClientRecord): void {
    pendingDeleteClient.value = client
    deleteDialogVisible.value = true
}

function confirmRevokeClient(): void {
    if (pendingDeleteClient.value === null) {
        return
    }

    form.delete(oauthClients.destroy.url(pendingDeleteClient.value.id), {
        onFinish: () => {
            deleteDialogVisible.value = false
            pendingDeleteClient.value = null
        },
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout>
        <Head :title="$t('page-oauth-clients.title')" />

        <ConfirmActionDialog
            v-model:visible="deleteDialogVisible"
            :cancel-label="$t('common.ui.cancel')"
            :confirm-label="$t('common.ui.revoke')"
            confirm-severity="danger"
            :description="
                pendingDeleteClient
                    ? $t('page-oauth-clients.revoke_message', {
                          name: pendingDeleteClient.name,
                      })
                    : null
            "
            icon="trash"
            :message="pendingDeleteClient?.name ?? $t('common.ui.revoke')"
            :title="$t('page-oauth-clients.revoke_header')"
            tone="danger"
            @confirm="confirmRevokeClient"
        />

        <div class="grid gap-6 px-4 py-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid gap-2">
                    <h1
                        class="text-2xl font-semibold text-[var(--cp-text-primary)]"
                    >
                        {{ $t('page-oauth-clients.title') }}
                    </h1>
                    <p class="text-sm text-[var(--cp-text-muted)]">
                        {{ $t('page-oauth-clients.description') }}
                    </p>
                </div>

                <Button
                    :label="$t('page-oauth-clients.new')"
                    @click="openCreateDialog"
                />
            </div>

            <div
                v-if="page.props.flash.oauthClientSecret"
                class="grid gap-3 rounded-[var(--cp-radius-lg)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-5 shadow-[var(--cp-shadow-sm)]"
            >
                <div class="grid gap-1">
                    <strong
                        class="text-sm font-semibold text-[var(--cp-text-primary)]"
                        >{{
                            $t('page-oauth-clients.secret_visible_once')
                        }}</strong
                    >
                    <span class="text-sm text-[var(--cp-text-muted)]">
                        {{ $t('common.ui.secret_visible_once_help') }}
                    </span>
                </div>
                <code
                    class="overflow-x-auto rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] px-3 py-2 text-sm text-[var(--cp-text-primary)]"
                >
                    {{ page.props.flash.oauthClientSecret }}
                </code>
            </div>

            <div class="cp-card grid gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex min-w-72 flex-1 items-center gap-2">
                        <span
                            class="text-sm font-medium text-[var(--cp-text-primary)]"
                            >{{ $t('common.ui.search') }}</span
                        >
                        <InputText
                            v-model="search"
                            class="w-full"
                            :placeholder="
                                $t('page-oauth-clients.search_placeholder')
                            "
                        />
                    </label>
                </div>

                <DataTable
                    :value="filteredClients"
                    paginator
                    :rows="10"
                    table-style="min-width: 100%"
                >
                    <Column field="name" :header="$t('common.ui.client')" />
                    <Column :header="$t('common.ui.scopes')">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-2">
                                <Tag
                                    v-for="scope in data.scopes.slice(0, 3)"
                                    :key="scope"
                                    :value="scope"
                                />
                                <Badge
                                    v-if="data.scopes.length > 3"
                                    :value="`+${data.scopes.length - 3}`"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="redirect"
                        :header="$t('common.ui.redirect')"
                    />
                    <Column :header="$t('common.ui.type')">
                        <template #body="{ data }">
                            <Tag
                                :severity="
                                    data.personalAccessClient
                                        ? 'contrast'
                                        : data.confidential
                                          ? 'info'
                                          : 'secondary'
                                "
                                :value="
                                    data.personalAccessClient
                                        ? $t('page-oauth-clients.personal')
                                        : data.confidential
                                          ? $t(
                                                'page-oauth-clients.confidential',
                                            )
                                          : $t('page-oauth-clients.public')
                                "
                            />
                        </template>
                    </Column>
                    <Column :header="$t('common.ui.status')">
                        <template #body="{ data }">
                            <Tag
                                :severity="data.revoked ? 'danger' : 'success'"
                                :value="
                                    data.revoked
                                        ? $t('common.ui.revoked')
                                        : $t('common.ui.active')
                                "
                            />
                        </template>
                    </Column>
                    <Column :header="$t('common.ui.actions')">
                        <template #body="{ data }">
                            <div class="flex justify-end gap-2">
                                <Button
                                    :aria-label="$t('common.ui.edit')"
                                    text
                                    @click="openEditDialog(data)"
                                >
                                    <AppIcon name="pencil" />
                                </Button>
                                <Button
                                    :aria-label="$t('common.ui.revoke')"
                                    severity="danger"
                                    text
                                    @click="revokeClient(data)"
                                >
                                    <AppIcon name="ban" />
                                </Button>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="
                editingClient
                    ? $t('page-oauth-clients.edit')
                    : $t('page-oauth-clients.create')
            "
            class="w-full max-w-3xl"
        >
            <form class="grid gap-5" @submit.prevent="saveClient">
                <FormRenderer
                    :columns="2"
                    :errors="form.errors"
                    :model-value="form as unknown as FormModel"
                    :schema="clientFormSchema"
                    :wrap-in-form="false"
                    @update:model-value="updateClientForm"
                />

                <div class="flex justify-end gap-2">
                    <Button
                        :label="$t('common.ui.cancel')"
                        severity="secondary"
                        text
                        type="button"
                        @click="dialogVisible = false"
                    />
                    <Button
                        :disabled="form.processing"
                        :loading="form.processing"
                        type="submit"
                    >
                        <AppIcon :name="editingClient ? 'save' : 'plus'" />
                        <span>{{
                            editingClient
                                ? $t('common.ui.save')
                                : $t('common.ui.create')
                        }}</span>
                    </Button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
