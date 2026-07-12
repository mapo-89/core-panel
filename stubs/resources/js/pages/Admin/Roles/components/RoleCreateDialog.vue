<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { inject } from 'vue'

import AppIcon from '@core-panel/components/AppIcon.vue'
import roleRoutes from '@/routes/core-panel/roles'

type DialogRef = {
    close: () => void
}

const dialogRef = inject<{ value: DialogRef }>('dialogRef')

const form = useForm({
    guard_name: 'web',
    name: '',
    redirect_to_matrix: true,
})

function close(): void {
    dialogRef?.value.close()
}

function submit(): void {
    form.post(roleRoutes.store.url(), {
        onSuccess: () => {
            close()
        },
        preserveScroll: true,
    })
}
</script>

<template>
    <form class="grid gap-5" @submit.prevent="submit">
        <div class="grid gap-2">
            <label
                class="text-sm font-medium text-[var(--cp-text-primary)]"
                for="role-name"
            >
                {{ $t('common.ui.name') }}
            </label>
            <InputText
                id="role-name"
                v-model="form.name"
                autocomplete="off"
                autofocus
                fluid
            />
            <small v-if="form.errors.name" class="text-sm text-red-600">
                {{ form.errors.name }}
            </small>
        </div>

        <div
            class="mt-2 flex flex-wrap items-center justify-end gap-2 border-t border-[var(--cp-surface-border)] pt-5"
        >
            <Button severity="secondary" text type="button" @click="close">
                <AppIcon name="x" />
                <span>{{ $t('common.ui.cancel') }}</span>
            </Button>
            <Button :loading="form.processing" type="submit">
                <AppIcon name="plus" />
                <span>{{ $t('page-roles.roles_create') }}</span>
            </Button>
        </div>
    </form>
</template>
