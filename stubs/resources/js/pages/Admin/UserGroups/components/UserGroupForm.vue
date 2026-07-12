<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, inject } from 'vue'

import userGroupRoutes from '@/routes/core-panel/user-groups'
import AppIcon from '@core-panel/components/AppIcon.vue'
import type { UserGroupRecord } from '@core-panel/types/core-panel'

type DialogRef = {
    close: () => void
    data?: {
        onSaved?: () => void
        userGroup?: UserGroupRecord | null
    }
}

const dialogRef = inject<{ value: DialogRef }>('dialogRef')
const onSaved = dialogRef?.value.data?.onSaved
const userGroup = dialogRef?.value.data?.userGroup ?? null
const isEdit = computed(() => userGroup !== null)

const form = useForm({
    color: userGroup?.color ?? '#6366F1',
    name: userGroup?.name ?? '',
})

function close(): void {
    dialogRef?.value.close()
}

function submit(): void {
    const options = {
        onSuccess: () => {
            onSaved?.()
            close()
        },
        preserveScroll: true,
    }

    if (userGroup) {
        form.put(userGroupRoutes.update.url(userGroup.id), options)

        return
    }

    form.post(userGroupRoutes.store.url(), options)
}
</script>

<template>
    <form class="cp-user-group-form grid gap-5" @submit.prevent="submit">
        <div class="cp-user-group-form__field grid gap-2">
            <label
                class="cp-user-group-form__label text-sm font-medium text-[var(--cp-text-primary)]"
                for="user-group-name"
            >
                {{ $t('common.ui.name') }}
            </label>
            <InputText id="user-group-name" v-model="form.name" fluid />
            <small
                v-if="form.errors.name"
                class="cp-user-group-form__error text-sm text-red-600"
            >
                {{ form.errors.name }}
            </small>
        </div>

        <div class="cp-user-group-form__field grid gap-2">
            <label
                class="cp-user-group-form__label text-sm font-medium text-[var(--cp-text-primary)]"
                for="user-group-color"
            >
                {{ $t('page-user-groups.color') }}
            </label>
            <div class="cp-user-group-form__color-row flex items-center gap-3">
                <input
                    id="user-group-color"
                    v-model="form.color"
                    class="cp-user-group-form__color-input h-11 w-14 rounded-[var(--cp-radius-md)] border border-[var(--cp-surface-border)] bg-[var(--cp-surface-panel)] p-1"
                    type="color"
                />
                <InputText
                    v-model="form.color"
                    class="cp-user-group-form__color-text"
                    fluid
                />
            </div>
            <small
                v-if="form.errors.color"
                class="cp-user-group-form__error text-sm text-red-600"
            >
                {{ form.errors.color }}
            </small>
        </div>

        <div
            class="cp-user-group-form__actions mt-2 flex flex-wrap items-center justify-end gap-2 border-t border-[var(--cp-surface-border)] pt-5"
        >
            <Button severity="secondary" text type="button" @click="close">
                <AppIcon name="x" />
                <span>{{ $t('common.ui.cancel') }}</span>
            </Button>
            <Button :loading="form.processing" type="submit">
                <AppIcon :name="isEdit ? 'save' : 'plus'" />
                <span>
                    {{
                        isEdit
                            ? $t('common.ui.save_changes')
                            : $t('page-user-groups.create')
                    }}
                </span>
            </Button>
        </div>
    </form>
</template>
